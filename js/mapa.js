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

// Las coordenadas del plano y sus constantes (ORIENTACION,
// MAX_POR_FILA, calcularLayout) viven en js/layout-plano.js, que se
// carga ANTES de este archivo: el editor de admin usa la misma fuente
// y por eso dibuja los lugares en el mismo lugar que el mapa del
// docente.
//
// Asigna a cada equipo sus coordenadas con calcularLayout y devuelve el
// layout para que dibujarPlano ajuste el viewBox. El Switch y la TV
// siguen fijos en el frente y se corren con las paredes si el plano
// crece (switch junto a la puerta derecha, TV a la derecha del pizarrón).
function asignarCoordenadas(equipos) {
    // fila normalizada (cualquier fila rara va a la columna derecha) y
    // posición recortada a [1, MAX_POR_FILA].
    const norm = e => ({
        f: Number(e.fila) === 2 || Number(e.fila) === 3 ? Number(e.fila) : 1,
        p: Math.min(MAX_POR_FILA, Math.max(1, Number(e.posicion) || 1))
    });

    // Primera pasada: la MAYOR posición por fila define el tamaño.
    const maxPos = {};
    equipos.forEach(e => {
        if (e.tipo_equipo !== "PC") return;
        const { f, p } = norm(e);
        maxPos[f] = Math.max(maxPos[f] || 0, p);
    });

    const L = calcularLayout(maxPos);
    let tvAsignada = false, switchAsignado = false;

    equipos.forEach(e => {
        if (e.tipo_equipo === "PC") {
            const { f, p } = norm(e);
            const c = L.posiciones[f][p - 1];
            if (c) { e.x = c.x; e.y = c.y; e.o = c.o; }
        } else if (e.tipo_equipo === "Switch") {
            e.x = 742 + (L.W - 900); e.y = 540 + L.dy; e.w = 118; e.h = 46; e.o = "obj";
            switchAsignado = true;
        } else { // TV / Proyector
            e.x = 430; e.y = 552 + L.dy; e.w = 128; e.h = 34; e.o = "obj";
            tvAsignada = true;
        }
    });
    L.tvAsignada = tvAsignada;
    L.switchAsignado = switchAsignado;
    return L;
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
    const L = asignarCoordenadas(equipos);
    // viewBox dinámico: crece con la sala (columnas → más alto,
    // fila del fondo → más ancho).
    svg.setAttribute("viewBox", `0 0 ${L.W} ${L.H}`);
    const pcs = equipos.filter(e => e.tipo_equipo === "PC");
    const ok = pcs.filter(e => e.estado_actual === "funcional").length;
    const fa = pcs.filter(e => e.estado_actual === "falla").length;
    const na = pcs.filter(e => e.estado_actual === "sin_eval").length;

    let s = `<rect x="8" y="8" width="${L.W - 16}" height="${L.H - 16}" rx="4" class="sala-fondo"/>`;

    // Frente de sala: pizarrón a la IZQUIERDA
    s += `<rect class="frente-obj" x="170" y="${590 + L.dy}" width="170" height="10" rx="2"/>
          <text class="etiq-zona" x="255" y="${584 + L.dy}" text-anchor="middle">Pizarrón</text>`;

    // Frente de sala: TV a la DERECHA del frente (si hay TV registrada).
    // El rectángulo del equipo TV se dibuja igual en su lugar (abajo);
    // esta marca solo indica la zona del frente.
    const tv = equipos.find(e => e.tipo_equipo === "TV" || e.tipo_equipo === "Proyector");
    if (tv && !tv.x) {
        s += `<text class="etiq-zona" x="${L.W - 130}" y="${584 + L.dy}" text-anchor="middle">Zona TV / Proyector</text>`;
    }

    // Puerta: abajo a la DERECHA, junto al switch. Si el plano se ensanchó,
    // la pared derecha se corrió (dx) y la puerta va con ella.
    const dx = L.W - 900;
    s += `<rect class="puerta" x="${620 + dx}" y="${606 + L.dy}" width="76" height="12"/>
          <path class="puerta-arco" d="M${620 + dx} ${610 + L.dy} A76 76 0 0 1 ${696 + dx} ${534 + L.dy}"/>
          <line class="puerta-linea" x1="${696 + dx}" y1="${610 + L.dy}" x2="${696 + dx}" y2="${534 + L.dy}"/>
          <text class="etiq-zona etiq-puerta" x="${612 + dx}" y="${602 + L.dy}" text-anchor="end">Puerta</text>`;

    // Resumen en el centro de la U
    s += `<text class="resumen-cifra" x="${L.W / 2}" y="${268 + L.dy / 2}" text-anchor="middle">${ok}<tspan class="resumen-total">/${pcs.length}</tspan></text>`;
    s += `<text class="resumen-texto" x="${L.W / 2}" y="${302 + L.dy / 2}" text-anchor="middle">computadoras operativas</text>`;
    const seg = pcs.slice().sort((a, b) =>
        ORDEN_SEGMENTOS.indexOf(a.estado_actual) - ORDEN_SEGMENTOS.indexOf(b.estado_actual));
    seg.forEach((p, i) => {
        s += `<rect class="seg-${p.estado_actual}" x="${L.W / 2 - pcs.length * 9 + i * 18}" y="${320 + L.dy / 2}" width="14" height="14" rx="2"/>`;
    });
    s += `<text class="resumen-detalle" x="${L.W / 2}" y="${362 + L.dy / 2}" text-anchor="middle">${fa} con falla · ${na} sin evaluar</text>`;

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

// ============================================================
// FOTOS: selector, compresión en el navegador y vista previa
// ============================================================
// Las fotos de celular pesan 3-8 MB y el servidor corta en 2 MB. En vez de
// rechazarlas, se reducen acá (lado mayor 1600 px, JPEG) antes de subirlas:
// quedan en ~300-600 KB, suben rápido por el wifi del liceo y se ven igual.
const LADO_MAX_FOTO = 1600;
const CALIDAD_FOTO = 0.82;
const UMBRAL_SIN_TOCAR = 600 * 1024;

// Foto ya procesada por cada selector (clave = id del input).
const FOTOS_LISTAS = new Map();
// Compresiones en curso: frena el redibujado del cajón (polling).
let fotosEnProceso = 0;
// La foto del reporte (campo-foto) se está comprimiendo: bloquea el
// envío pero NO deshabilita el botón (la foto es opcional).
let comprimiendoFoto = false;

// ── A. Selector de archivos ABIERTO ──
// Mientras el usuario está eligiendo el archivo en el diálogo del
// sistema, el polling NO puede redibujar el cajón: al redibujarlo se
// recrea el <input type="file"> y el 'change' del archivo elegido
// queda sobre un elemento desconectado del DOM (la foto se sube pero
// el docente nunca ve la vista previa).
//
// La bandera se libera en: 'change' del input, 'cancel' del input,
// el regreso del foco a la ventana (1 s de gracia, solo si no llegó un
// 'change') y un tope de seguridad de 2 minutos. Nunca queda trabada.
let selectorAbierto = false;
let selectorTope = null;          // temporizador de seguridad (2 minutos)
let selectorFocoPerdido = false;  // ¿el diálogo del sistema nos quitó el foco?
let selectorToken = 0;            // invalida temporizadores viejos

const SELECTOR_TOPE_MS = 120000;  // 2 minutos
const SELECTOR_ESPERA_MS = 1000;  // gracia al volver el foco a la ventana

function abrirSelector() {
    selectorAbierto = true;
    selectorFocoPerdido = false;
    selectorToken++;
    const token = selectorToken;
    clearTimeout(selectorTope);
    selectorTope = setTimeout(() => {
        // Si en estos 2 minutos no llegó ningún 'change'/'cancel',
        // liberamos la bandera igual: no puede quedar trabada.
        if (token === selectorToken) cerrarSelector();
    }, SELECTOR_TOPE_MS);
}

function cerrarSelector() {
    selectorAbierto = false;
    selectorFocoPerdido = false;
    selectorToken++;               // invalida los temporizadores pendientes
    clearTimeout(selectorTope);
    selectorTope = null;
}

// El diálogo del sistema puede habernos quitado el foco: al volver a la
// ventana esperamos 1 s y, si en ese tiempo no llegó un 'change', damos
// el selector por cerrado (el docente canceló la elección).
// Si el 'change' llega tarde (diálogo lento), el 'change' vuelve a abrir
// la bandera: el input sigue conectado y el polling no lo destruye.
window.addEventListener("blur", () => {
    if (selectorAbierto) selectorFocoPerdido = true;
});
window.addEventListener("focus", () => {
    if (!selectorAbierto || !selectorFocoPerdido) return;
    const token = selectorToken;
    setTimeout(() => {
        if (token === selectorToken && selectorAbierto) cerrarSelector();
    }, SELECTOR_ESPERA_MS);
});

function pesoLegible(bytes) {
    return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace(".", ",") + " MB"
                            : Math.max(1, Math.round(bytes / 1024)) + " KB";
}

async function prepararFoto(archivo) {
    if (!archivo.type.startsWith("image/"))
        throw new Error("Formato no permitido. Usá una imagen JPG o PNG.");
    // HEIC (foto de iPhone): el navegador no lo decodifica y el servidor
    // tampoco lo acepta; mejor un mensaje claro antes de subir nada.
    if (/^image\/hei[cf]/.test(archivo.type) || /\.hei[cf]$/i.test(archivo.name || ""))
        throw new Error("Formato no permitido (HEIC). Usá JPG o PNG.");
    if (archivo.type === "image/gif" || archivo.size <= UMBRAL_SIN_TOCAR) return archivo;

    // Camino principal: createImageBitmap (rápido y respeta la rotación
    // EXIF). Camino alternativo: <img> + URL.createObjectURL, para los
    // navegadores que no lo implementan.
    // Ambos devuelven { ancho, alto, fuente, cerrar }.
    let img = null;
    try {
        const bmp = await createImageBitmap(archivo, { imageOrientation: "from-image" });
        img = {
            ancho: bmp.width,
            alto: bmp.height,
            fuente: bmp,
            cerrar: () => bmp.close()
        };
    } catch (e) {
        try {
            img = await decodificarConImg(archivo);
        } catch (e2) {
            // Ni createImageBitmap ni <img> pudieron decodificar la foto:
            // no se puede reducir, así que se avisa en el selector y no se
            // sube nada. El catch del 'change' muestra el mensaje y
            // limpiar() deja el botón utilizable.
            throw new Error("No se pudo reducir la foto; elegí una más chica o en formato JPG/PNG.");
        }
    }

    const escala = Math.min(1, LADO_MAX_FOTO / Math.max(img.ancho, img.alto));
    const w = Math.round(img.ancho * escala), h = Math.round(img.alto * escala);
    const lienzo = document.createElement("canvas");
    lienzo.width = w; lienzo.height = h;
    const ctx = lienzo.getContext("2d");
    ctx.fillStyle = "#fff";            // PNG con transparencia -> fondo blanco
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(img.fuente, 0, 0, w, h);
    if (typeof img.cerrar === "function") img.cerrar();
    const blob = await new Promise(r => lienzo.toBlob(r, "image/jpeg", CALIDAD_FOTO));
    if (!blob || blob.size >= archivo.size) return archivo;
    const base = (archivo.name || "foto").replace(/\.[^.]+$/, "");
    return new File([blob], base + ".jpg", { type: "image/jpeg" });
}

// Camino alternativo de decodificación: <img> + URL.createObjectURL.
// Se usa cuando createImageBitmap no existe o falla. La URL temporal
// se revoca siempre, tanto si carga como si falla.
function decodificarConImg(archivo) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(archivo);
        const el = new Image();
        el.onload = () => {
            URL.revokeObjectURL(url);
            resolve({
                ancho: el.naturalWidth || el.width,
                alto: el.naturalHeight || el.height,
                fuente: el,
                cerrar: () => {}
            });
        };
        el.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error("No se pudo decodificar la imagen"));
        };
        el.src = url;
    });
}

function htmlSelectorFoto(idInput, idPreview, etiqueta) {
    return `
    <div class="bloque-foto">
        <span class="etiq-foto">${etiqueta}</span>
        <input type="file" id="${idInput}" class="input-foto" accept="image/*">
        <label for="${idInput}" class="btn-elegir-foto">Elegir o tomar foto</label>
        <div class="previa-foto" id="${idPreview}" hidden>
            <img alt="Vista previa de la foto elegida">
            <div class="previa-datos">
                <span class="previa-info"></span>
                <button type="button" class="btn-quitar-foto">Quitar</button>
            </div>
        </div>
        <div class="error-foto" role="alert" hidden></div>
    </div>`;
}

// Conecta input + vista previa + quitar. alCambiar(archivo|null, procesando)
// avisa al botón de guardar/enviar; "procesando" es true mientras se comprime.
function conectarSelectorFoto(idInput, idPreview, alCambiar) {
    const input = document.getElementById(idInput);
    const previa = document.getElementById(idPreview);
    if (!input || !previa) return null;
    const imagen = previa.querySelector("img");
    const info = previa.querySelector(".previa-info");
    const error = previa.parentElement.querySelector(".error-foto");
    const aviso = alCambiar || (() => {});

    function limpiar() {
        FOTOS_LISTAS.delete(idInput);
        if (imagen.src.startsWith("blob:")) URL.revokeObjectURL(imagen.src);
        imagen.removeAttribute("src");
        previa.hidden = true;
        input.value = "";
        aviso(null);
    }
    function mostrarError(texto) {
        error.textContent = texto;
        error.hidden = !texto;
    }

    input.addEventListener("change", async () => {
        mostrarError("");
        cerrarSelector();   // A: el archivo ya está elegido
        const original = input.files && input.files[0];
        if (!original) { limpiar(); return; }
        previa.hidden = false;
        info.textContent = "Procesando…";
        aviso(null, true);
        fotosEnProceso++;
        try {
            const lista = await prepararFoto(original);
            const limite = DATOS.limite_foto || 2097152;
            if (lista.size > limite) {
                limpiar();
                // Si la foto volvió sin comprimir (el navegador no pudo
                // reducirla), el mensaje dice qué hacer; si venía
                // comprimida y aun así no entra, es el límite del servidor.
                if (lista === original) {
                    mostrarError("No se pudo reducir la foto; elegí una más chica o en formato JPG/PNG.");
                } else {
                    mostrarError(`La foto pesa ${pesoLegible(lista.size)} y el máximo es ${pesoLegible(limite)}.`);
                }
                return;
            }
            FOTOS_LISTAS.set(idInput, lista);
            if (imagen.src.startsWith("blob:")) URL.revokeObjectURL(imagen.src);
            imagen.src = URL.createObjectURL(lista);
            info.textContent = pesoLegible(lista.size);
            aviso(lista, false);
        } catch (e) {
            limpiar();
            mostrarError(e.message || "No se pudo leer la foto.");
        } finally {
            fotosEnProceso--;
        }
    });
    previa.querySelector(".btn-quitar-foto").addEventListener("click", () => {
        mostrarError("");
        limpiar();
    });
    // A: el usuario canceló el diálogo del sistema.
    input.addEventListener("cancel", () => cerrarSelector());

    // A: el clic en el label abre el diálogo del sistema. Lo marcamos
    // para que el polling no redibuje el cajón mientras está abierto.
    const etiqueta = document.querySelector(`label[for="${idInput}"]`);
    if (etiqueta) etiqueta.addEventListener("click", abrirSelector);
    return input;
}

// Cajón: resumen de reportes activos (sin selección), detalle de equipo,
// formulario de reporte de falla o sección de foto de control.
function renderCajon() {
    const c = $("#cajon");
    if (!c) return;
    FOTOS_LISTAS.clear();   // los selectores se crean de nuevo en cada render

    // ── Sin equipo seleccionado: resumen de la sala ──
    if (!seleccion) {
        const activos = Object.values(DATOS.reportes);
        let h = `<h2>Reportes activos</h2>`;
        h += `<div id="lista-reportes">`;
        h += activos.length
            ? `<ul class="lista-act">` + activos.map(r =>
                `<li><button type="button" data-ir="${r.id_pc}">
                    <span class="c">${esc(r.etiqueta)}</span><span>${esc(r.tipo_falla)}</span>
                 </button></li>`).join("") + `</ul>`
            : `<p class="nota">No hay fallas abiertas en esta sala.</p>`;
        h += `</div>`;
        // Foto de control de la sala completa
        h += `<div class="seccion-control">
                <h3>Foto de control de la sala</h3>
                <p class="nota">Guardá cómo quedó la sala al terminar la clase. No reporta ninguna falla.</p>
                ${htmlSelectorFoto("foto-control-sala", "previa-control-sala", "Foto de la sala")}
                <button type="button" class="btn-guardar-foto" id="btn-control-sala" disabled>Guardar foto</button>
              </div>`;
        // Últimas fotos de control guardadas
        const fotosControl = DATOS.fotos.filter(f => f.tipo === "control");
        if (fotosControl.length) {
            h += `<div class="historial-fotos"><h3>Fotos de control guardadas</h3><div class="galeria-fotos">` +
                fotosControl.map(f =>
                    `<a href="/uploads/fotos/${esc(f.archivo)}" target="_blank" rel="noopener"
                        title="${esc(formatoFecha(f.fecha_subida))}${f.etiqueta ? " · " + esc(f.etiqueta) : ""}">
                        <img src="/uploads/fotos/${esc(f.archivo)}" loading="lazy" decoding="async" alt="Foto de control">
                        <span>${f.etiqueta ? esc(f.etiqueta) : "Sala"}</span>
                     </a>`).join("") +
                `</div></div>`;
        }
        h += `<p class="nota">Tocá un equipo del plano para ver su estado o reportar una falla.</p>`;
        c.innerHTML = h;

        c.querySelectorAll("button[data-ir]").forEach(b => b.addEventListener("click", () => {
            seleccion = DATOS.equipos.find(e => String(e.id_pc) === b.dataset.ir) || null;
            dibujarPlano(); renderCajon();
        }));

        const inputSala = conectarSelectorFoto("foto-control-sala", "previa-control-sala", () => {
            $("#btn-control-sala").disabled = !FOTOS_LISTAS.has("foto-control-sala");
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
                    <img src="/uploads/fotos/${esc(fotosRep[0].archivo)}" loading="lazy" decoding="async" alt="Foto adjunta del reporte">
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
            ${htmlSelectorFoto("campo-foto", "previa-foto-reporte", "Foto del problema (opcional)")}
            <div id="cajon-error" class="nota error-nota" hidden></div>
            <button type="button" class="btn-turq" id="btn-confirmar-reporte">Reportar falla</button>`;
    }

    // Foto de control del equipo + botón volver
    h += `<div class="seccion-control">
            <h3>Foto de control del equipo</h3>
            <p class="nota">Registra el estado actual del equipo. No reporta una falla.</p>
            ${htmlSelectorFoto("foto-control-eq", "previa-control-eq", "Foto del equipo")}
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
        $("#btn-control-eq").disabled = !FOTOS_LISTAS.has("foto-control-eq");
    });
    $("#btn-control-eq").addEventListener("click", () => enviarFotoControl(e.id_pc, "foto-control-eq"));

    // Foto del reporte (opcional): con vista previa, pero SIN frenar el
    // botón — solo se bloquea el envío mientras se comprime.
    const inputReporte = conectarSelectorFoto("campo-foto", "previa-foto-reporte", (_foto, procesando) => {
        comprimiendoFoto = !!procesando;
        const btn = $("#btn-confirmar-reporte");
        if (!btn) return;
        btn.textContent = procesando ? "Preparando foto…" : "Reportar falla";
    });
    if (!inputReporte) comprimiendoFoto = false;

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

// B. Lee la respuesta del servidor distinguiendo los casos que antes
// terminaban todos como "sin conexión":
//   · la sesión venció → el servidor redirigió a /login.php;
//   · el cuerpo no es JSON → un warning de PHP se coló delante del JSON
//     (típico cuando el POST supera post_max_size) o el servidor falló;
//   · un fallo real de red → lo maneja el .catch() de fetch.
// Devuelve { ok: true, datos } o { ok: false, mensaje }.
async function leerRespuesta(r) {
    // Sesión vencida: PHP redirige a /login.php y el fetch sigue el 302.
    const urlFinal = r.url || "";
    if (r.redirected || /\/login\.php/.test(urlFinal)) {
        return {
            ok: false,
            mensaje: "Tu sesión venció. Volvé a iniciar sesión (los datos que escribiste se conservan)."
        };
    }
    let texto = "";
    try { texto = await r.text(); } catch (e) { texto = ""; }
    let datos = null;
    try { datos = JSON.parse(texto); } catch (e) { datos = null; }
    if (datos && typeof datos === "object") return { ok: true, datos };
    // El cuerpo no es JSON: el PHP imprimió un warning antes de la
    // respuesta (por ejemplo, POST más grande que post_max_size).
    if (/exceeds the limit/i.test(texto)) {
        return { ok: false, mensaje: "La foto es demasiado grande para el servidor" };
    }
    if (r.status >= 500) {
        return { ok: false, mensaje: "El servidor tuvo un error; probá de nuevo" };
    }
    return { ok: false, mensaje: "El servidor devolvió una respuesta inesperada. Probá de nuevo." };
}

// Reporte de falla: POST a reportar-falla.php con id_pc, tipo_falla,
// detalle_texto y (opcional) foto. En éxito actualiza el plano en el
// momento (sin recargar); si algo falla, recarga como fallback.
function enviarReporte() {
    const idPC = seleccion.id_pc;
    const tipoFalla = $("#campo-tipo-falla").value;
    const detalleTexto = $("#campo-detalle").value;
    const error = $("#cajon-error");
    const boton = $("#btn-confirmar-reporte");
    if (boton.disabled) return;   // evita doble envío
    if (comprimiendoFoto) {      // la foto aún se está comprimiendo
        error.textContent = "Preparando foto…";
        error.hidden = false;
        return;
    }

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
    const fotoLista = FOTOS_LISTAS.get("campo-foto");
    if (fotoLista) datos.append("foto", fotoLista, fotoLista.name);

    boton.disabled = true;
    boton.textContent = fotoLista ? "Enviando con foto…" : "Enviando…";

    fetch("reportar-falla.php", { method: "POST", body: datos })
        .then(leerRespuesta)
        .then(r => {
            if (!r.ok) {
                error.textContent = r.mensaje;
                error.hidden = false;
                boton.disabled = false;
                boton.textContent = "Reportar falla";
                return;
            }
            const resp = r.datos;
            if (resp.exito) {
                toast(resp.mensaje || "Falla reportada.");
                FOTOS_LISTAS.delete("campo-foto");   // limpiar solo tras éxito
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
                if (resp.foto) {
                    DATOS.fotos.unshift({
                        id_foto: 0, tipo: "falla", archivo: resp.foto,
                        fecha_subida: new Date().toISOString().slice(0, 19).replace("T", " "),
                        id_pc: seleccion.id_pc, id_ticket: resp.id_ticket || 0,
                        etiqueta: seleccion.etiqueta
                    });
                }
                dibujarPlano();
                renderCajon();
            } else {
                error.textContent = resp.mensaje || "No se pudo registrar el reporte";
                error.hidden = false;
                boton.disabled = false;
                boton.textContent = "Reportar falla";
            }
        })
        .catch(err => {
            // Solo un fallo real de red llega acá: el servidor no
            // respondió. Antes se recargaba la página y se perdía lo
            // escrito y la foto; ahora se avisa y se conserva el formulario.
            console.error("Error al enviar el reporte:", err);
            error.textContent = "No hay conexión con el servidor. Probá de nuevo.";
            error.hidden = false;
            boton.disabled = false;
            boton.textContent = "Reportar falla";
        });
}

// Foto de control: POST a guardar-foto-control.php. No cambia estado ni crea ticket.
function enviarFotoControl(idPC, idInput) {
    const boton = idPC === null ? $("#btn-control-sala") : $("#btn-control-eq");
    const foto = FOTOS_LISTAS.get(idInput);
    if (!foto || boton.disabled) return;

    const datos = new FormData();
    if (idPC !== null) datos.append("id_pc", idPC);
    datos.append("foto", foto, foto.name);
    boton.disabled = true;
    boton.textContent = "Guardando…";

    fetch("guardar-foto-control.php", { method: "POST", body: datos })
        .then(leerRespuesta)
        .then(r => {
            if (!r.ok) {
                toast(r.mensaje);
                boton.disabled = false;
                boton.textContent = "Guardar foto";
                return;
            }
            const resp = r.datos;
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
            // Solo un fallo real de red.
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

function cajonEnUso() {
    // ¿Hay algo en curso que un redibujado destruiría? No depende del foco:
    // foto elegida o comprimiéndose, tipo de falla elegido o texto escrito.
    // A: también mientras el selector de archivos del sistema está abierto
    // (si el polling redibuja el cajón, el <input> se recrea y el 'change'
    // del archivo elegido cae sobre un elemento desconectado del DOM).
    const tipo = $("#campo-tipo-falla");
    const detalle = $("#campo-detalle");
    const fotoElegida = [...document.querySelectorAll("#cajon input.input-foto")]
        .some(i => i.files && i.files.length > 0);
    return selectorAbierto
        || FOTOS_LISTAS.size > 0
        || fotosEnProceso > 0
        || fotoElegida
        || (tipo && !!tipo.value)
        || !!(detalle && detalle.value.trim());
}

// Refresca SOLO la lista de reportes activos del cajón (la parte que el
// polling necesita mantener al día), sin tocar inputs ni selects.
function actualizarListaReportes() {
    const cont = $("#lista-reportes");
    if (!cont) return;
    const activos = Object.values(DATOS.reportes);
    cont.innerHTML = activos.length
        ? `<ul class="lista-act">` + activos.map(r =>
            `<li><button type="button" data-ir="${r.id_pc}">
                <span class="c">${esc(r.etiqueta)}</span><span>${esc(r.tipo_falla)}</span>
             </button></li>`).join("") + `</ul>`
        : `<p class="nota">No hay fallas abiertas en esta sala.</p>`;
    cont.querySelectorAll("button[data-ir]").forEach(b => b.addEventListener("click", () => {
        seleccion = DATOS.equipos.find(e => String(e.id_pc) === b.dataset.ir) || null;
        dibujarPlano(); renderCajon();
    }));
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
            // Sin equipo seleccionado el cajón se redibuja para mostrar reportes
            // nuevos; pero no si hay algo en curso (foto, tipo, texto), que se
            // perdería. En ese caso solo se refresca la lista de reportes.
            if (!seleccion && !cajonEnUso()) renderCajon();
            else if (!seleccion) actualizarListaReportes();

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
