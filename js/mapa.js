// ============================================================
// SIGRA-ETSR · Componente B
// js/mapa.js — Plano SVG de la sala + cajón lateral de reportes
// ============================================================
// Lee el JSON #datos-sala que imprime mapa-sala.php y dibuja todo.
// Los catálogos de fallas tienen que coincidir EXACTO con
// $tipos_validos en public/docente/reportar-falla.php (el servidor
// es quien valida de verdad; esto es solo para que el docente vea
// las opciones correctas según lo que clickeó).

// ── Catálogos de fallas por tipo de equipo ──
const FALLAS_PC = [
    "No enciende", "Falta mouse", "Falta teclado", "Sin internet",
    "Pantalla rota", "Falta de software", "Otro"
];

const FALLAS_SWITCH_TV = [
    "Sin alimentación / No prende", "Roto / Daño físico",
    "No funcional / No da señal", "Equipo faltante", "Otro (especificar)"
];

// Tipos que, al elegirse, exigen que el docente escriba el detalle.
const TIPOS_QUE_REQUIEREN_DETALLE = ["Falta de software", "Otro", "Otro (especificar)"];

// ── Placeholders del campo Detalle según el tipo de falla (CP-03-RF04) ──
const PLACEHOLDERS_DETALLE = {
    "Falta de software": "Ej: falta instalar Python",
    "Otro": "Ej: no está el monitor",
    "Otro (especificar)": "Ej: no está el monitor"
};

// ── RNF01: mapeo estado → clase CSS. Los colores viven en mapa.css
//    y están FIJADOS (verde/rojo/gris); acá solo se elige la clase. ──
const INDICADOR_ESTADO = { funcional: "OK", falla: "FALLA", sin_eval: "?" };
const ETIQUETA_ESTADO  = { funcional: "Funcional", falla: "Con falla", sin_eval: "No evaluada" };
const ORDEN_SEGMENTOS  = ["funcional", "sin_eval", "falla"];

// ── Estado global ──
let DATOS = null;      // JSON de #datos-sala
let seleccion = null;  // equipo seleccionado (objeto de DATOS.equipos) o null
let segsActualizacion = 0;

const $ = (s) => document.querySelector(s);
const NS_SVG = "http://www.w3.org/2000/svg";

// Escapa texto para insertarlo en HTML/SVG generado por string.
function esc(texto) {
    const div = document.createElement("div");
    div.textContent = texto === null || texto === undefined ? "" : String(texto);
    return div.innerHTML;
}

// ============================================================
// Toast: mensaje flotante (reemplaza alert())
// ============================================================
let toastTimer = null;
function toast(mensaje) {
    const t = $("#toast");
    if (!t) return;
    t.textContent = mensaje;
    t.classList.add("toast-ver");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove("toast-ver"), 2800);
}

// ============================================================
// Antigüedad legible de una fecha YYYY-MM-DD HH:MM:SS
// ============================================================
function antiguedad(fecha) {
    const d = new Date(String(fecha).replace(" ", "T"));
    if (isNaN(d)) return "";
    const dias = Math.floor((Date.now() - d.getTime()) / 86400000);
    if (dias <= 0) return "hoy";
    if (dias === 1) return "hace 1 día";
    return "hace " + dias + " días";
}

// ============================================================
// PLANO SVG
// ============================================================

// Asigna coordenadas del plano según fila/posicion reales de la DB:
//   fila 2 = fila del fondo (3 PCs centradas, monitor hacia atrás)
//   fila 3 = pata izquierda   fila 1 = pata derecha
//   Switch y TV/Proyector van al frente: TV a la derecha del pizarrón,
//   Switch junto a la puerta (abajo a la derecha).
function asignarCoordenadas(equipos) {
    const Y_TOP = 36, X_IZQ = 36, X_DER = 740;
    const ys = [130, 208, 286, 364, 442];
    let iTop = 0, iIzq = 0, iDer = 0;
    let tvAsignada = false, switchAsignado = false;

    equipos.forEach(e => {
        if (e.tipo_equipo === "PC") {
            const idx = Number(e.posicion) - 1;
            if (Number(e.fila) === 2) {
                // centradas: 3 posiciones con la misma separación que el mockup
                e.x = 274 + idx * 122; e.y = Y_TOP; e.o = "top";
            } else if (Number(e.fila) === 3) {
                e.x = X_IZQ; e.y = ys[Math.min(idx, ys.length - 1)]; e.o = "izq";
            } else {
                e.x = X_DER; e.y = ys[Math.min(idx, ys.length - 1)]; e.o = "der";
            }
        } else if (e.tipo_equipo === "Switch") {
            e.x = 742; e.y = 540; e.w = 118; e.h = 46; e.o = "obj";
            switchAsignado = true;
        } else { // TV / Proyector
            e.x = 430; e.y = 552; e.w = 128; e.h = 34; e.o = "obj";
            tvAsignada = true;
        }
    });
    return { tvAsignada, switchAsignado };
}

// Dibuja una mesa con monitor orientado a la pared y textos.
// o: 'top' (fila del fondo, monitor arriba), 'izq', 'der', 'obj' (switch/tv)
function svgMesa(e) {
    const w = e.w || (e.o === "top" ? 108 : 124);
    const h = e.h || (e.o === "top" ? 70 : 66);
    const claseEstado = e.estado_actual;
    const cod = e.etiqueta.startsWith("PC-") ? e.etiqueta.slice(3) : e.etiqueta;

    let g = `<g class="eq ${claseEstado}" tabindex="0" role="button"
        data-id-pc="${e.id_pc}" data-etiqueta="${esc(e.etiqueta)}"
        data-estado="${e.estado_actual}" data-tipo="${esc(e.tipo_equipo)}"
        aria-label="${esc(e.etiqueta)}, ${esc(e.tipo_equipo)}, estado ${INDICADOR_ESTADO[claseEstado] || '?'}">`;
    g += `<rect class="halo" x="${e.x - 5}" y="${e.y - 5}" width="${w + 10}" height="${h + 10}" rx="9"/>`;
    // RNF01: el fill del cuerpo de la mesa lo pinta mapa.css según la clase
    // de estado (funcional/falla/sin_eval). Objetos fijos van con borde punteado.
    g += `<rect class="mesa" x="${e.x}" y="${e.y}" width="${w}" height="${h}" rx="6"
        ${e.o === "obj" ? 'stroke-dasharray="5 4"' : ""}/>`;
    if (e.tipo_equipo === "PC") {
        // monitor del lado de la pared, teclado hacia el centro
        if (e.o === "top") g += `<rect class="monitor" x="${e.x + w / 2 - 22}" y="${e.y + 6}" width="44" height="9" rx="2"/>`;
        if (e.o === "izq") g += `<rect class="monitor" x="${e.x + 6}" y="${e.y + h / 2 - 22}" width="9" height="44" rx="2"/>`;
        if (e.o === "der") g += `<rect class="monitor" x="${e.x + w - 15}" y="${e.y + h / 2 - 22}" width="9" height="44" rx="2"/>`;
    }
    const cx = e.x + w / 2, cy = e.y + h / 2;
    if (e.o === "top") {
        g += `<text class="cod" x="${cx}" y="${cy + 6}" text-anchor="middle">${esc(cod)}</text>
              <text class="est" x="${cx}" y="${cy + 24}" text-anchor="middle">${INDICADOR_ESTADO[claseEstado] || '?'}</text>`;
    } else if (e.o === "izq") {
        g += `<text class="cod" x="${cx + 8}" y="${cy - 2}" text-anchor="middle">${esc(cod)}</text>
              <text class="est" x="${cx + 8}" y="${cy + 16}" text-anchor="middle">${INDICADOR_ESTADO[claseEstado] || '?'}</text>`;
    } else if (e.o === "der") {
        g += `<text class="cod" x="${cx - 8}" y="${cy - 2}" text-anchor="middle">${esc(cod)}</text>
              <text class="est" x="${cx - 8}" y="${cy + 16}" text-anchor="middle">${INDICADOR_ESTADO[claseEstado] || '?'}</text>`;
    } else {
        g += `<text class="cod cod-obj" x="${cx}" y="${cy - 1}" text-anchor="middle">${esc(e.etiqueta)}</text>
              <text class="est" x="${cx}" y="${cy + 13}" text-anchor="middle">${INDICADOR_ESTADO[claseEstado] || '?'}</text>`;
    }
    return g + "</g>";
}

// Dibuja el plano completo: sala, puerta (abajo derecha), pizarrón (izquierda
// del frente), resumen en el centro de la U y todas las mesas.
function dibujarPlano() {
    const svg = $("#plano");
    if (!svg) return;
    const equipos = DATOS.equipos;
    asignarCoordenadas(equipos);
    const pcs = equipos.filter(e => e.tipo_equipo === "PC");
    const ok = pcs.filter(e => e.estado_actual === "funcional").length;
    const fa = pcs.filter(e => e.estado_actual === "falla").length;
    const na = pcs.filter(e => e.estado_actual === "sin_eval").length;

    let s = `<rect x="8" y="8" width="884" height="604" rx="4" class="sala-fondo"/>`;

    // Frente de sala: pizarrón a la IZQUIERDA
    s += `<rect class="frente-obj" x="170" y="590" width="170" height="10" rx="2"/>
          <text class="etiq-zona" x="255" y="584" text-anchor="middle">Pizarrón</text>`;

    // Frente de sala: TV a la DERECHA del frente (si hay TV registrada).
    // El rectángulo del equipo TV se dibuja igual en su lugar (abajo);
    // esta marca solo indica la zona del frente.
    const tv = equipos.find(e => e.tipo_equipo === "TV" || e.tipo_equipo === "Proyector");
    if (tv && !tv.x) {
        s += `<text class="etiq-zona" x="770" y="584" text-anchor="middle">Zona TV / Proyector</text>`;
    }

    // Puerta: abajo a la DERECHA, junto al switch
    s += `<rect class="puerta" x="620" y="606" width="76" height="12"/>
          <path class="puerta-arco" d="M620 610 A76 76 0 0 1 696 534"/>
          <line class="puerta-linea" x1="696" y1="610" x2="696" y2="534"/>
          <text class="etiq-zona etiq-puerta" x="612" y="602" text-anchor="end">Puerta</text>`;

    // Resumen en el centro de la U
    s += `<text class="resumen-cifra" x="450" y="268" text-anchor="middle">${ok}<tspan class="resumen-total">/${pcs.length}</tspan></text>`;
    s += `<text class="resumen-texto" x="450" y="302" text-anchor="middle">computadoras operativas</text>`;
    const seg = pcs.slice().sort((a, b) =>
        ORDEN_SEGMENTOS.indexOf(a.estado_actual) - ORDEN_SEGMENTOS.indexOf(b.estado_actual));
    seg.forEach((p, i) => {
        s += `<rect class="seg-${p.estado_actual}" x="${450 - pcs.length * 9 + i * 18}" y="320" width="14" height="14" rx="2"/>`;
    });
    s += `<text class="resumen-detalle" x="450" y="362" text-anchor="middle">${fa} con falla · ${na} sin evaluar</text>`;

    s += equipos.map(svgMesa).join("");
    svg.innerHTML = s;

    svg.querySelectorAll(".eq").forEach(g => {
        const activar = () => {
            seleccion = equipos.find(e => String(e.id_pc) === g.dataset.idPc) || null;
            dibujarPlano();
            renderCajon();
        };
        g.addEventListener("click", activar);
        g.addEventListener("keydown", ev => {
            if (ev.key === "Enter" || ev.key === " ") { ev.preventDefault(); activar(); }
        });
    });
}

// ============================================================
// CAJÓN LATERAL
// ============================================================

// Pasos del ticket (pendiente → en_reparacion → solucionado)
function htmlPasos(estado) {
    const orden = ["pendiente", "en_reparacion", "solucionado"];
    const i = orden.indexOf(estado);
    return `<div class="pasos">` + ["Pendiente", "En reparación", "Solucionado"].map((n, k) =>
        `<span class="${k <= i ? "hecho" : ""}">${n}</span>`).join("") + `</div>`;
}

// Vista previa de la foto elegida + botón Quitar.
// Devuelve HTML del bloque y deja listeners listos (se llaman después).
function htmlSelectorFoto(idInput, idPreview) {
    return `
    <div class="bloque-foto">
        <label for="${idInput}" class="etiq-foto">Foto del problema (opcional)</label>
        <input type="file" id="${idInput}" accept="image/*">
        <div class="previa-foto" id="${idPreview}" hidden>
            <img alt="Vista previa de la foto elegida">
            <button type="button" class="btn-quitar-foto">Quitar</button>
        </div>
    </div>`;
}

// Conecta input file + preview + quitar.
function conectarSelectorFoto(idInput, idPreview, alCambiar) {
    const input = document.getElementById(idInput);
    const previa = document.getElementById(idPreview);
    if (!input || !previa) return null;
    input.addEventListener("change", () => {
        const f = input.files && input.files[0];
        if (!f) { previa.hidden = true; if (alCambiar) alCambiar(null); return; }
        const url = URL.createObjectURL(f);
        previa.querySelector("img").src = url;
        previa.hidden = false;
        if (alCambiar) alCambiar(f);
    });
    previa.querySelector(".btn-quitar-foto").addEventListener("click", () => {
        input.value = "";
        previa.hidden = true;
        if (alCambiar) alCambiar(null);
    });
    return input;
}

// Cajón: resumen de reportes activos (sin selección), detalle de equipo,
// formulario de reporte de falla o sección de foto de control.
function renderCajon() {
    const c = $("#cajon");
    if (!c) return;

    // ── Sin equipo seleccionado: resumen de la sala ──
    if (!seleccion) {
        const activos = Object.values(DATOS.reportes);
        let h = `<h2>Reportes activos</h2>`;
        h += activos.length
            ? `<ul class="lista-act">` + activos.map(r =>
                `<li><button type="button" data-ir="${r.id_pc}">
                    <span class="c">${esc(r.etiqueta)}</span><span>${esc(r.tipo_falla)}</span>
                 </button></li>`).join("") + `</ul>`
            : `<p class="nota">No hay fallas abiertas en esta sala.</p>`;
        // Foto de control de la sala completa
        h += `<div class="seccion-control">
                <h3>Foto de control de la sala</h3>
                <p class="nota">Guardá cómo quedó la sala al terminar la clase. No reporta ninguna falla.</p>
                ${htmlSelectorFoto("foto-control-sala", "previa-control-sala")}
                <button type="button" class="btn-guardar-foto" id="btn-control-sala" disabled>Guardar foto</button>
              </div>`;
        // Últimas fotos de control guardadas
        const fotosControl = DATOS.fotos.filter(f => f.tipo === "control");
        if (fotosControl.length) {
            h += `<div class="historial-fotos"><h3>Fotos de control guardadas</h3><ul>` +
                fotosControl.map(f =>
                    `<li>${esc(formatoFecha(f.fecha_subida))}${f.etiqueta ? " · " + esc(f.etiqueta) : ""}
                     <a href="/uploads/fotos/${esc(f.archivo)}" target="_blank" rel="noopener">Ver</a></li>`).join("") +
                `</ul></div>`;
        }
        h += `<p class="nota">Tocá un equipo del plano para ver su estado o reportar una falla.</p>`;
        c.innerHTML = h;

        c.querySelectorAll("button[data-ir]").forEach(b => b.addEventListener("click", () => {
            seleccion = DATOS.equipos.find(e => String(e.id_pc) === b.dataset.ir) || null;
            dibujarPlano(); renderCajon();
        }));

        const inputSala = conectarSelectorFoto("foto-control-sala", "previa-control-sala", () => {
            $("#btn-control-sala").disabled = !(inputSala.files && inputSala.files[0]);
        });
        $("#btn-control-sala").addEventListener("click", () => enviarFotoControl(null, "foto-control-sala"));
        return;
    }

    // ── Equipo seleccionado ──
    const e = seleccion;
    const reporte = DATOS.reportes[String(e.id_pc)];
    let h = `<p class="codigo">${esc(e.etiqueta)}</p>
             <p class="tipo">${e.tipo_equipo === "PC" ? "Computadora" : e.tipo_equipo === "TV" ? "TV / proyector" : "Switch"} · ${esc(DATOS.sala)}</p>
             <span class="chip ${e.estado_actual}">${ETIQUETA_ESTADO[e.estado_actual] || e.estado_actual}</span>`;

    if (reporte) {
        // Reporte abierto: tipo, antigüedad, pasos y foto si hay
        const fotosRep = DATOS.fotos.filter(f => f.id_ticket !== null && String(f.id_pc) === String(e.id_pc) && f.tipo === "falla");
        h += `<h2 class="tit-reporte">Reporte abierto</h2>
              <p><b>${esc(reporte.tipo_falla)}</b><br>
              <span class="nota">Reportado ${esc(antiguedad(reporte.fecha_reporte))}</span></p>
              ${htmlPasos(reporte.estado)}`;
        if (fotosRep.length) {
            h += `<div class="miniatura-foto">
                    <img src="/uploads/fotos/${esc(fotosRep[0].archivo)}" alt="Foto adjunta del reporte">
                    <a href="/uploads/fotos/${esc(fotosRep[0].archivo)}" target="_blank" rel="noopener">Ver foto adjunta</a>
                  </div>`;
        }
        h += `<p class="nota">El técnico actualiza el avance; el equipo vuelve a verde cuando queda solucionado.</p>`;
    } else {
        // Formulario de reporte con foto opcional
        const ops = e.tipo_equipo === "PC" ? FALLAS_PC : FALLAS_SWITCH_TV;
        h += `<h2 class="tit-reporte">Reportar falla</h2>
            <label class="etiq-foto" for="campo-tipo-falla">Tipo de falla</label>
            <select id="campo-tipo-falla">
                <option value="">-- Elegí un tipo --</option>
                ${ops.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join("")}
            </select>
            <div id="contenedor-detalle" hidden>
                <label class="etiq-foto" for="campo-detalle">Detalle</label>
                <textarea id="campo-detalle" rows="3" placeholder="Ej: falta instalar Python"></textarea>
            </div>
            ${htmlSelectorFoto("campo-foto", "previa-foto-reporte")}
            <div id="cajon-error" class="nota error-nota" hidden></div>
            <button type="button" class="btn-turq" id="btn-confirmar-reporte">Reportar falla</button>`;
    }

    // Foto de control del equipo + botón volver
    h += `<div class="seccion-control">
            <h3>Foto de control del equipo</h3>
            <p class="nota">Registra el estado actual del equipo. No reporta una falla.</p>
            ${htmlSelectorFoto("foto-control-eq", "previa-control-eq")}
            <button type="button" class="btn-guardar-foto" id="btn-control-eq" disabled>Guardar foto</button>
          </div>
          <p class="pie-cajon"><button type="button" class="btn-volver" id="btn-cerrar-cajon">Volver al resumen</button></p>`;
    c.innerHTML = h;

    // ── Conectar controles ──
    const selectTipo = $("#campo-tipo-falla");
    if (selectTipo) {
        selectTipo.addEventListener("change", () => {
            $("#contenedor-detalle").hidden = !TIPOS_QUE_REQUIEREN_DETALLE.includes(selectTipo.value);
            $("#campo-detalle").placeholder = PLACEHOLDERS_DETALLE[selectTipo.value] || "";
        });
        $("#btn-confirmar-reporte").addEventListener("click", enviarReporte);
    }

    const inputEq = conectarSelectorFoto("foto-control-eq", "previa-control-eq", () => {
        $("#btn-control-eq").disabled = !(inputEq.files && inputEq.files[0]);
    });
    $("#btn-control-eq").addEventListener("click", () => enviarFotoControl(e.id_pc, "foto-control-eq"));

    $("#btn-cerrar-cajon").addEventListener("click", () => {
        seleccion = null;
        dibujarPlano(); renderCajon();
    });
}

// Formato de fecha local legible
function formatoFecha(f) {
    const d = new Date(String(f).replace(" ", "T"));
    if (isNaN(d)) return String(f);
    return d.toLocaleDateString("es-UY") + " " + d.toLocaleTimeString("es-UY", { hour: "2-digit", minute: "2-digit" });
}

// ============================================================
// ENVÍOS (fetch) — mismos endpoints y campos que antes
// ============================================================

// Reporte de falla: POST a reportar-falla.php con id_pc, tipo_falla,
// detalle_texto y (opcional) foto. En éxito actualiza el plano en el
// momento (sin recargar); si algo falla, recarga como fallback.
function enviarReporte() {
    const idPC = seleccion.id_pc;
    const tipoFalla = $("#campo-tipo-falla").value;
    const detalleTexto = $("#campo-detalle").value;
    const inputFoto = $("#campo-foto");
    const error = $("#cajon-error");

    error.hidden = true;

    if (!tipoFalla) {
        error.textContent = "Seleccioná el tipo de falla";
        error.hidden = false;
        return;
    }
    if (TIPOS_QUE_REQUIEREN_DETALLE.includes(tipoFalla) && detalleTexto.trim() === "") {
        error.textContent = "Especificá el detalle de la falla";
        error.hidden = false;
        return;
    }

    const datos = new FormData();
    datos.append("id_pc", idPC);
    datos.append("tipo_falla", tipoFalla);
    datos.append("detalle_texto", detalleTexto);
    if (inputFoto && inputFoto.files && inputFoto.files[0]) {
        datos.append("foto", inputFoto.files[0]);
    }

    fetch("reportar-falla.php", { method: "POST", body: datos })
        .then(r => r.json())
        .then(resp => {
            if (resp.exito) {
                toast(resp.mensaje || "Falla reportada.");
                // Actualización local SIN recargar: el equipo pasa a falla
                // con ticket pendiente y el cajón muestra el reporte abierto.
                seleccion.estado_actual = "falla";
                DATOS.reportes[String(seleccion.id_pc)] = {
                    id_ticket: resp.id_ticket || 0,
                    id_pc: seleccion.id_pc,
                    tipo_falla: tipoFalla,
                    estado: "pendiente",
                    fecha_reporte: new Date().toISOString().slice(0, 19).replace("T", " "),
                    etiqueta: seleccion.etiqueta
                };
                dibujarPlano();
                renderCajon();
            } else {
                error.textContent = resp.mensaje || "No se pudo registrar el reporte";
                error.hidden = false;
            }
        })
        .catch(err => {
            console.error("Error al enviar el reporte:", err);
            // Fallback: el comportamiento previo era recargar; lo mantenemos
            // si la comunicación con el servidor falla.
            window.location.reload();
        });
}

// Foto de control: POST a guardar-foto-control.php. No cambia estado ni crea ticket.
function enviarFotoControl(idPC, idInput) {
    const input = document.getElementById(idInput);
    const boton = idPC === null ? $("#btn-control-sala") : $("#btn-control-eq");
    if (!input || !input.files || !input.files[0]) return;

    const datos = new FormData();
    if (idPC !== null) datos.append("id_pc", idPC);
    datos.append("foto", input.files[0]);
    boton.disabled = true;
    boton.textContent = "Guardando…";

    fetch("guardar-foto-control.php", { method: "POST", body: datos })
        .then(r => r.json())
        .then(resp => {
            if (resp.exito) {
                toast(resp.mensaje || "Foto guardada.");
                // Agregar al listado local (la sala completa no tiene etiqueta)
                DATOS.fotos.unshift({
                    id_foto: 0, tipo: "control", archivo: resp.archivo,
                    fecha_subida: resp.fecha, id_pc: idPC || null,
                    etiqueta: idPC !== null ? seleccion.etiqueta : null
                });
                renderCajon();
            } else {
                toast(resp.mensaje || "No se pudo guardar la foto.");
                boton.disabled = false;
                boton.textContent = "Guardar foto";
            }
        })
        .catch(err => {
            console.error("Error al guardar la foto:", err);
            toast("Error de conexión al guardar la foto.");
            boton.disabled = false;
            boton.textContent = "Guardar foto";
        });
}

// ============================================================
// POLLING: "Actualizado hace X s" + refresco cada 20 s
// ============================================================
function iniciarPolling() {
    setInterval(() => {
        segsActualizacion = (segsActualizacion + 1) % 21;
        const hace = $("#hace");
        if (hace) hace.textContent = segsActualizacion;
        if (segsActualizacion === 0) refrescarSala();
    }, 1000);
}

// Vuelve a pedir la MISMA página y reemplaza el JSON de datos.
// Si algún equipo cambió de estado, se resalta con la clase .cambia.
function refrescarSala() {
    fetch(window.location.href, { headers: { "X-Requested-With": "fetch" } })
        .then(r => r.text())
        .then(html => {
            const doc = new DOMParser().parseFromString(html, "text/html");
            const nuevo = doc.getElementById("datos-sala");
            if (!nuevo) return;
            const datosNuevos = JSON.parse(nuevo.textContent);
            const antes = {};
            DATOS.equipos.forEach(e => { antes[e.id_pc] = e.estado_actual; });

            DATOS = datosNuevos;
            // mantener selección si el equipo sigue existiendo
            if (seleccion) {
                seleccion = DATOS.equipos.find(e => String(e.id_pc) === String(seleccion.id_pc)) || null;
            }
            dibujarPlano();
            if (!seleccion) renderCajon();

            // resaltar cambios de estado respecto a la versión anterior
            DATOS.equipos.forEach(e => {
                if (antes[e.id_pc] && antes[e.id_pc] !== e.estado_actual) {
                    const g = document.querySelector(`.eq[data-id-pc="${e.id_pc}"]`);
                    if (g) {
                        g.classList.add("cambia");
                        setTimeout(() => g.classList.remove("cambia"), 4000);
                    }
                }
            });
        })
        .catch(() => { /* silencioso: el próximo ciclo reintenta */ });
}

// ============================================================
// Arranque
// ============================================================
document.addEventListener("DOMContentLoaded", function () {
    const nodo = document.getElementById("datos-sala");
    if (!nodo) return;
    DATOS = JSON.parse(nodo.textContent);

    dibujarPlano();
    renderCajon();
    iniciarPolling();
});
