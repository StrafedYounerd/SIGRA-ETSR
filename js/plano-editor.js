// ============================================================
// SIGRA-ETSR · Componente B
// js/plano-editor.js — Editor de plano del admin (CU-09)
// ============================================================
// Dibuja el plano de la sala con la MISMA función de layout que el
// mapa del docente (js/layout-plano.js → calcularLayout), así los
// lugares se ven en el mismo sitio.
//
// NO carga js/mapa.js: ese archivo tiene el polling y la lógica del
// docente. Acá no hay polling.
//
// Reglas de este archivo:
//   - Los nombres que escribe el usuario (etiquetas, códigos pegados)
//     NUNCA entran por innerHTML: se usan textContent /
//     setAttribute. El servidor sigue siendo la autoridad; el JS
//     solo replica las validaciones para avisar antes de enviar.
//   - Guardar manda TODO en UNA sola petición a plano-guardar.php.
//   - Si el guardado falla, lo escrito se conserva y el error queda
//     marcado en el lugar.
// ============================================================

"use strict";

// ── Datos que imprime plano-editor.php en #datos-editor ──
const DATOS_EDITOR = JSON.parse(
    document.getElementById("datos-editor").textContent
);

// ── Límite de posiciones por fila ──
// El número vive en js/layout-plano.js (MAX_POR_FILA) y el PHP manda
// el mismo en "max_por_fila". NO se redeclara MAX_POR_FILA acá: los
// scripts clásicos comparten el scope global y volver a declararlo
// con const tiraría "Identifier has already been declared".
const MAX_EDITOR = DATOS_EDITOR.max_por_fila || MAX_POR_FILA;

const FILAS = [1, 2, 3];

// ── Estado del editor ──
//   elegidos: Map clave "fila:posicion" → { etiqueta, error }
//   slots:    cuántas posiciones existen en cada fila (las que se
//             ven en el plano, ocupadas o libres)
//   errores:  Map clave → mensaje de error (para marcar en el plano)
const estado = {
    elegidos: new Map(),
    slots: { 1: 0, 2: 0, 3: 0 },
    errores: new Map(),
};

// Lugares ya ocupados por equipos que existen en la sala: Set "fila:pos"
const OCUPADOS = new Set(DATOS_EDITOR.ocupados || []);

// Etiquetas que ya existen en la sala (no se pueden reutilizar)
const CODIGOS_USADOS = new Set(
    (DATOS_EDITOR.codigos || []).map(c => c.toLowerCase())
);

// ── Ayudas de DOM ──
const $ = sel => document.querySelector(sel);
// Estado extra para eliminación
const eliminados = new Set(); // IDs de PCs marcados para eliminar
let modoEliminar = false;


const claveLugar = (fila, posicion) => `${fila}:${posicion}`;

function el(id) {
    return document.getElementById(id);
}

// Crea un elemento del SVG. El texto va SIEMPRE por textContent:
// ningún dato del usuario se inyecta como HTML.
function svgEl(nombre, atributos, texto) {
    const n = document.createElementNS("http://www.w3.org/2000/svg", nombre);
    for (const k in (atributos || {})) {
        n.setAttribute(k, atributos[k]);
    }
    if (texto !== undefined && texto !== null) {
        n.textContent = texto;
    }
    return n;
}

// ── Normalización de etiquetas ──
// El admin escribe "305-01" o "PC-305-01": siempre se guarda con el
// prefijo "PC-" y nunca duplicado ("PC-PC-305-01" no puede pasar).
// El prefijo va SIEMPRE en mayúsculas ("pc-7281" → "PC-7281"); el
// resto de la etiqueta se respeta tal como lo escribió el usuario.
function normalizarEtiqueta(texto) {
    let t = String(texto || "").trim().replace(/\s+/g, " ");
    if (t === "") return "";
    if (/^PC-/i.test(t)) {
        // Solo el prefijo se pasa a mayúsculas.
        t = "PC-" + t.slice(3);
    } else {
        t = "PC-" + t;
    }
    return t;
}

// Para mostrar en el plano se saca el prefijo, igual que el mapa
// del docente (mapa.js: e.etiqueta.slice(3)).
function etiquetaCorta(etiqueta) {
    return /^PC-/i.test(etiqueta) ? etiqueta.slice(3) : etiqueta;
}

// ============================================================
// CÁLCULO DE LUGARES
// ============================================================
// Cuántas posiciones se ven en cada fila: las que ya existen (ocupadas)
// más las que el admin eligió en esta sesión. Se respeta el tope MAX_POR_FILA.
function recalcularSlots() {
    FILAS.forEach(f => {
        const base = (DATOS_EDITOR.max_pos && DATOS_EDITOR.max_pos[f]) || 0;
        let extra = 0;
        estado.elegidos.forEach((datos, clave) => {
            const [ff, pp] = clave.split(":").map(Number);
            if (ff === f) extra = Math.max(extra, pp);
        });
        estado.slots[f] = Math.min(MAX_EDITOR, Math.max(base, extra));
    });
}

// ¿La posición está libre? (no ocupada por un equipo existente ni
// elegida por el admin en este momento)
function estaLibre(fila, posicion) {
    const clave = claveLugar(fila, posicion);
    if (OCUPADOS.has(clave)) return false;
    if (estado.elegidos.has(clave)) return false;
    return posicion <= MAX_EDITOR;
}

// Devuelve los equipos existentes (no PC) y los PCs para dibujar.
function equiposDeLaSala() {
    return DATOS_EDITOR.equipos || [];
}

// ============================================================
// DIBUJO DEL PLANO
// ============================================================
// Usa calcularLayout() de js/layout-plano.js: la MISMA función que
// usa el mapa real del docente.
function dibujarPlano() {
    const svg = el("plano-editor");
    if (!svg) return;
    // Actualizar el título contextual según el modo
    const pista = el("pista");
    if (pista) {
        if (modoEliminar) {
            pista.textContent = "Modo Eliminar: clickeá un equipo existente para marcarlo para borrar.";
        } else {
            pista.textContent = "Tocá un lugar libre del plano para agregar una PC.";
        }
    }

    recalcularSlots();

    const L = calcularLayout(estado.slots);

    // ── Medidas del plano ──
    // Todo se calcula desde las medidas del layout: no hay números
    // mágicos para saber dónde termina la sala. La tira de botones
    // "+ fila N" se ubica DEBAJO de lo más bajo que se dibuja (la
    // pared inferior o la puerta), así no tapa ni la pared ni el
    // pizarrón.
    const MARGEN_SALA = 8;                  // borde del rect de la sala
    const PIZ_Y = 590 + L.dy, PIZ_H = 10;   // pizarrón
    const PUERTA_Y = 606 + L.dy, PUERTA_H = 12;   // puerta
    const ALTO_BOTON = 34, SEP_BOTON = 16;  // tira de "+ fila N"

    const paredInferior = MARGEN_SALA + (L.H - 2 * MARGEN_SALA);   // = L.H - 8
    const yTira = Math.max(paredInferior, PIZ_Y + PIZ_H, PUERTA_Y + PUERTA_H) + 16;
    const altoLienzo = yTira + ALTO_BOTON + 16;

    // El lienzo se agranda lo necesario para que la tira entre.
    svg.setAttribute("viewBox", `0 0 ${L.W} ${altoLienzo}`);

    const frag = document.createDocumentFragment();

    // Fondo de la sala
    frag.appendChild(svgEl("rect", {
        x: 8, y: 8, width: L.W - 16, height: L.H - 16, rx: 4,
        fill: "#fbfdfe", stroke: "var(--navy)", "stroke-width": 3,
    }));

    // Pizarrón (frente, a la izquierda)
    frag.appendChild(svgEl("rect", {
        x: 170, y: PIZ_Y, width: 170, height: PIZ_H, rx: 2,
        fill: "#dbe4ea", stroke: "#8aa0af",
    }));
    frag.appendChild(svgEl("text", {
        x: 255, y: PIZ_Y - 6, "text-anchor": "middle",
        "font-size": 11, fill: "var(--muted)",
    }, "Pizarrón"));

    // Puerta (abajo a la derecha, junto al switch)
    const dx = L.W - 900;
    frag.appendChild(svgEl("rect", {
        x: 620 + dx, y: PUERTA_Y, width: 76, height: PUERTA_H, fill: "#f1f5f8",
    }));
    frag.appendChild(svgEl("path", {
        d: `M${620 + dx} ${610 + L.dy} A76 76 0 0 1 ${696 + dx} ${534 + L.dy}`,
        fill: "none", stroke: "#8aa0af", "stroke-dasharray": "4 4",
    }));
    frag.appendChild(svgEl("line", {
        x1: 696 + dx, y1: 610 + L.dy, x2: 696 + dx, y2: 534 + L.dy, stroke: "#8aa0af",
    }));
    frag.appendChild(svgEl("text", {
        x: 612 + dx, y: 602 + L.dy, "text-anchor": "end",
        "font-size": 11, fill: "var(--muted)",
    }, "Puerta"));

    // Equipos existentes (atenuados, NO clickeables) y lugares libres.
    equiposDeLaSala().forEach(e => {
        if (e.tipo_equipo !== "PC") return;
        const f = Number(e.fila), p = Number(e.posicion);
        const c = L.posiciones[f] && L.posiciones[f][p - 1];
        if (!c) return;
        frag.appendChild(dibujarMesaOcupada(c, e));
    });

    // Las PCs nuevas que ya eligió el admin (dibujadas encima).
    estado.elegidos.forEach((datos, clave) => {
        const [f, p] = clave.split(":").map(Number);
        const c = L.posiciones[f] && L.posiciones[f][p - 1];
        if (!c) return;
        frag.appendChild(dibujarMesaElegida(c, datos.etiqueta, clave));
    });

    // Lugares libres (botones operables con teclado).
    if (!modoEliminar) {
        FILAS.forEach(f => {
            for (let p = 1; p <= estado.slots[f]; p++) {
                const clave = claveLugar(f, p);
                if (OCUPADOS.has(clave)) continue;
                if (estado.elegidos.has(clave)) continue;
                const c = L.posiciones[f][p - 1];
                if (!c) continue;
                frag.appendChild(dibujarLugarLibre(c, f, p));
            }
        });
    }

    // Resumen en el centro de la U
    frag.appendChild(svgEl("text", {
        x: L.W / 2, y: 268 + L.dy / 2, "text-anchor": "middle",
        "font-size": 34, "font-weight": 800, fill: "var(--navy)",
    }, String(estado.elegidos.size)));
    frag.appendChild(svgEl("text", {
        x: L.W / 2, y: 302 + L.dy / 2, "text-anchor": "middle",
        "font-size": 15, fill: "var(--muted)",
    }, "equipos a agregar"));

    svg.replaceChildren(frag);

    // Un solo listener por lugar (delegación): el SVG se redibuja
    // entero en cada cambio, así que se escucha en el contenedor.
    // Los <button> de los lugares ya hacen su click con Enter y Espacio
    // solos (son HTML); el listener del SVG cubre el <g> de "+ fila N".
    svg.onclick = null; // Delegación eliminada para evitar conflictos con listeners directos
    svg.onkeydown = null;

    // En pantallas chicas el plano scanea en horizontal: se deja
    // scrolleado en el primer lugar libre, que es donde el admin
    // quiere empezar a trabajar.
    scrollAlPrimerLibre(svg);
}

// ── Scroll horizontal al primer lugar libre ──
// Solo tiene efecto si el plano realmente no entra en pantalla: en
// escritorio no mueve nada.
function scrollAlPrimerLibre(svg) {
    const wrap = el("plano-wrap");
    if (!wrap) return;
    const libre = svg.querySelector("button.lugar-libre");
    if (!libre) return;
    // Si el plano entra entero, no hace falta scrollear.
    if (svg.getBoundingClientRect().width <= wrap.clientWidth + 1) return;
    const wrapRect = wrap.getBoundingClientRect();
    const libreRect = libre.getBoundingClientRect();
    // Se deja el primer lugar libre pegado al borde izquierdo de la
    // ventana de scroll (con un margen), sea la fila que sea.
    const margen = 12;
    const destino = wrap.scrollLeft + (libreRect.left - wrapRect.left) - margen;
    wrap.scrollLeft = Math.max(0, destino);
}

// ── Dibujo de una mesa ──
// Monitor del lado de la pared y teclado hacia el centro, igual que
// el mapa del docente.
function dibujarMesa(c, clase, texto, extra) {
    const tam = tamanoMesa(c.o);
    const g = svgEl("g", Object.assign({ class: clase }, extra || {}));
    g.appendChild(svgEl("rect", {
        x: c.x, y: c.y, width: tam.w, height: tam.h, rx: 6,
        fill: "var(--color-superficie-alt)", stroke: "var(--line)", "stroke-width": 1.5,
    }));
    if (c.o === "top") {
        g.appendChild(svgEl("rect", {
            x: c.x + tam.w / 2 - 22, y: c.y + 6, width: 44, height: 9, rx: 2,
            fill: "#b9bec2",
        }));
    } else if (c.o === "izq") {
        g.appendChild(svgEl("rect", {
            x: c.x + 6, y: c.y + tam.h / 2 - 22, width: 9, height: 44, rx: 2,
            fill: "#b9bec2",
        }));
    } else if (c.o === "der") {
        g.appendChild(svgEl("rect", {
            x: c.x + tam.w - 15, y: c.y + tam.h / 2 - 22, width: 9, height: 44, rx: 2,
            fill: "#b9bec2",
        }));
    }
    return g;
}

// Equipo YA existente en la sala: atenuado, NO clickeable.
function dibujarMesaOcupada(c, equipo) {
    const etiqueta = equipo.etiqueta;
    const g = dibujarMesa(c, "mesa-ocupada", null);
    g.setAttribute("aria-hidden", "true");
    // Hacer clickeable si estamos en modo eliminar
    if (modoEliminar) {
        g.style.cursor = "pointer";
        g.setAttribute("data-id-pc", equipo.id_pc);
        g.addEventListener("click", () => toggleEliminar(equipo.id_pc));
        if (eliminados.has(equipo.id_pc)) {
            g.classList.add("mesa-a-eliminar");
            // Opcional: tachar el texto
            const textEl = g.querySelector("text");
            if (textEl) textEl.style.textDecoration = "line-through";
        } else {
            // Agregar indicador visual "clickeable para eliminar" en modo eliminar
            const tam = tamanoMesa(c.o);
            // Pequeño círculo rojo con X en la esquina superior derecha de la mesa
            const indicador = svgEl("g", { class: "indicador-eliminar" });
            const radioIndicador = 10;
            const offsetX = c.o === "izq" ? tam.w - radioIndicador - 4 : (c.o === "der" ? 4 : tam.w / 2);
            const offsetY = radioIndicador + 4;
            // Fondo rojo circular
            indicador.appendChild(svgEl("circle", {
                cx: c.x + offsetX,
                cy: c.y + offsetY,
                r: radioIndicador,
                fill: "#dc3545",
            }));
            // X blanca
            const tamX = 8;
            indicador.appendChild(svgEl("line", {
                x1: c.x + offsetX - tamX/2,
                y1: c.y + offsetY - tamX/2,
                x2: c.x + offsetX + tamX/2,
                y2: c.y + offsetY + tamX/2,
                stroke: "#fff",
                "stroke-width": 2,
                "stroke-linecap": "round",
            }));
            indicador.appendChild(svgEl("line", {
                x1: c.x + offsetX + tamX/2,
                y1: c.y + offsetY - tamX/2,
                x2: c.x + offsetX - tamX/2,
                y2: c.y + offsetY + tamX/2,
                stroke: "#fff",
                "stroke-width": 2,
                "stroke-linecap": "round",
            }));
            g.appendChild(indicador);
        }
    }
    const tam = tamanoMesa(c.o);
    const cx = c.x + tam.w / 2, cy = c.y + tam.h / 2;
    const dx = c.o === "der" ? -8 : (c.o === "izq" ? 8 : 0);
    g.appendChild(svgEl("text", {
        x: cx + dx, y: cy + 4, "text-anchor": "middle",
        "font-size": 12, fill: "#6b767d",
    }, etiquetaCorta(etiqueta)));
    return g;
}

// PC nueva elegida por el admin: muestra el código escrito.
function dibujarMesaElegida(c, etiqueta, clave) {
    const conError = estado.errores.has(clave);
    const tam = tamanoMesa(c.o);
    const fo = svgEl("foreignObject", { x: c.x, y: c.y, width: tam.w, height: tam.h });

    // Se dibuja como un <button> en un <foreignObject>, igual que los
    // lugares libres: así el admin puede tocarlo para cambiar el código.
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "lugar-libre lugar-elegido" + (conError ? " lugar-error" : "");
    btn.dataset.fila = String(clave.split(":")[0]);
    btn.dataset.posicion = String(clave.split(":")[1]);
    btn.dataset.elegido = "1";
    btn.setAttribute("aria-label", `Equipo ${etiqueta}, fila ${btn.dataset.fila}, posición ${btn.dataset.posicion}. Cambiar el código`);
    const txt = document.createElement("span");
    txt.className = "etiqueta";
    txt.textContent = etiquetaCorta(etiqueta);
    btn.appendChild(txt);
    // Listener directo: click en un equipo ya elegido → editar su código
    btn.addEventListener("click", (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        const f = Number(btn.dataset.fila);
        const p = Number(btn.dataset.posicion);
        activarLugar(f, p);
    });
    fo.appendChild(btn);

    if (conError) {
        fo.appendChild(svgEl("title", {}, estado.errores.get(clave)));
    }
    return fo;
}

// Lugar LIBRE: un <button> real dentro de un <foreignObject>.
// (SVG2 no define <button>: dentro de <svg> no se renderiza. El
// foreignObject mete HTML en el SVG, así que el botón es de verdad
// pulsable y operable con el teclado, con su foco y su aria-label.)
function dibujarLugarLibre(c, fila, posicion) {
    const tam = tamanoMesa(c.o);
    const fo = svgEl("foreignObject", {
        x: c.x, y: c.y, width: tam.w, height: tam.h,
    });
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "lugar-libre";
    btn.dataset.fila = String(fila);
    btn.dataset.posicion = String(posicion);
    btn.setAttribute("aria-label", `Lugar libre, fila ${fila}, posición ${posicion}`);
    const txt = document.createElement("span");
    txt.className = "etiqueta";
    txt.textContent = `${fila}.${posicion}`;
    btn.appendChild(txt);
    // Listener directo: el click en el botón del lugar libre abre el diálogo
    btn.addEventListener("click", (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        activarLugar(Number(fila), Number(posicion));
    });
    fo.appendChild(btn);
    return fo;
}


// ============================================================
// SELECCIÓN Y VALIDACIÓN
// ============================================================
// El servidor es la autoridad: acá solo se replica la validación para
// avisar ANTES de enviar. Cada error se marca en su lugar (en el
// plano y en la lista del panel).
function validar() {
    estado.errores.clear();
    const vistos = new Map();   // etiqueta en minúsculas → clave del lugar

    estado.elegidos.forEach((datos, clave) => {
        const et = datos.etiqueta.trim();
        if (et === "") {
            estado.errores.set(clave, "Falta la etiqueta");
            return;
        }
        if (et.length > 20) {
            estado.errores.set(clave, "La etiqueta no puede pasar de 20 caracteres");
            return;
        }
        const minus = et.toLowerCase();
        if (CODIGOS_USADOS.has(minus)) {
            estado.errores.set(clave, `La etiqueta "${et}" ya existe en esta sala`);
            return;
        }
        if (vistos.has(minus)) {
            estado.errores.set(clave, `La etiqueta "${et}" ya está en la lista`);
            return;
        }
        vistos.set(minus, clave);
    });
    return estado.errores.size === 0;
}

// Agrega un lugar (fila, posición) al conjunto elegido.
function elegirLugar(fila, posicion, etiqueta) {
    const clave = claveLugar(fila, posicion);
    if (OCUPADOS.has(clave)) {
        marcarPista("Ese lugar ya está ocupado por un equipo existente.", "error");
        return;
    }
    estado.elegidos.set(clave, { fila, posicion, etiqueta: etiqueta || "" });
    estado.errores.delete(clave);
    refrescar();
}

// Saca un lugar del conjunto elegido.
function quitarLugar(fila, posicion) {
    const clave = claveLugar(fila, posicion);
    estado.elegidos.delete(clave);
    estado.errores.delete(clave);
    refrescar();
}

// ── Eliminación de equipos existentes ──
// Busca un equipo de la sala por su id_pc (el que manda el servidor
// en DATOS_EDITOR.equipos).
function buscarEquipoPorId(id_pc) {
    return equiposDeLaSala().find(e => Number(e.id_pc) === Number(id_pc));
}

// Solo el id pendiente de confirmar en el diálogo.
let pcPendienteEliminar = null;

// Marca o desmarca un equipo para eliminarlo. Desmarcar es inmediato;
// marcar pide confirmación (borrar un dispositivo no se deshace).
function toggleEliminar(id_pc) {
    const equipo = buscarEquipoPorId(id_pc);
    if (!equipo) return;

    if (eliminados.has(id_pc)) {
        eliminados.delete(id_pc);
        refrescar();
        marcarPista(`Se desmarcó ${equipo.etiqueta}. No se va a eliminar.`);
        return;
    }

    pcPendienteEliminar = id_pc;
    const dlg = el("dlg-eliminar");
    const texto = el("texto-eliminar");
    if (texto) {
        texto.textContent = `¿Seguro que querés eliminar ${equipo.etiqueta}? `
            + "Se borra del plano y de la sala. Esta acción no se puede deshacer.";
    }
    if (dlg) dlg.showModal();
}

// Activa un lugar: si ya estaba elegido, se abre el diálogo para
// editar su código; si estaba libre, se abre el diálogo para escribir
// el código desde cero.
function activarLugar(fila, posicion) {
    const clave = claveLugar(fila, posicion);
    const datos = estado.elegidos.get(clave);
    abrirDialogoCodigo(fila, posicion, datos ? datos.etiqueta : "");
}

// ============================================================
// MENSAJES DE LA PISTA
// ============================================================
function marcarPista(texto, tipo) {
    const pista = el("pista");
    if (!pista) return;
    pista.textContent = texto;
    pista.classList.remove("is-error", "is-ok");
    if (tipo === "error") pista.classList.add("is-error");
    else if (tipo === "ok") pista.classList.add("is-ok");
}

// ============================================================
// PANEL LATERAL: LISTA DE EQUIPOS ELEGIDOS
// ============================================================
// La lista va en orden de numeración: primero la fila 1, después la 2
// y la 3; dentro de cada fila, por posición.
function lugaresOrdenados() {
    return Array.from(estado.elegidos.entries()).sort((a, b) => {
        const [fa, pa] = a[0].split(":").map(Number);
        const [fb, pb] = b[0].split(":").map(Number);
        if (fa !== fb) return fa - fb;
        return pa - pb;
    });
}

function renderLista() {
    const lista = el("lista");
    const nota = el("nota-lista");
    const total = el("total");
    if (!lista) return;

    lista.replaceChildren();
    const lugares = lugaresOrdenados();

    lugares.forEach(([clave, datos]) => {
        const li = document.createElement("li");
        if (estado.errores.has(clave)) li.classList.add("con-error");

        const izq = document.createElement("div");

        const lugar = document.createElement("span");
        lugar.className = "lugar";
        lugar.textContent = `Fila ${datos.fila}, pos ${datos.posicion}`;
        izq.appendChild(lugar);

        const codigo = document.createElement("span");
        codigo.className = "codigo";
        codigo.textContent = datos.etiqueta || "(sin código)";
        izq.appendChild(codigo);

        const error = estado.errores.get(clave);
        if (error) {
            const err = document.createElement("span");
            err.className = "lugar-error";
            err.textContent = error;
            izq.appendChild(err);
        }

        const quitar = document.createElement("button");
        quitar.type = "button";
        quitar.className = "quitar";
        quitar.textContent = "×";
        quitar.setAttribute("aria-label", `Quitar el lugar de la fila ${datos.fila}, posición ${datos.posicion}`);
        quitar.addEventListener("click", () => quitarLugar(datos.fila, datos.posicion));

        li.appendChild(izq);
        li.appendChild(quitar);
        lista.appendChild(li);
    });

    if (nota) {
        nota.hidden = lugares.length > 0;
        nota.textContent = "Todavía no agregaste equipos.";
    }
    if (total) total.textContent = String(lugares.length);

    // ── Equipos marcados para eliminar (solo en modo eliminar) ──
    // Se listan aparte, con su propio contador, para que el admin vea
    // qué se va a borrar antes de confirmar el guardado.
    let contEliminados = el("lista-eliminados");
    if (!contEliminados) {
        contEliminados = document.createElement("ul");
        contEliminados.id = "lista-eliminados";
        contEliminados.className = "editor-plano__lista eliminados-lista";
        lista.parentNode.appendChild(contEliminados);
    }
    contEliminados.replaceChildren();
    contEliminados.hidden = eliminados.size === 0;
    if (eliminados.size > 0) {
        const titulo = document.createElement("li");
        titulo.className = "eliminados-titulo";
        titulo.textContent = `Para eliminar (${eliminados.size})`;
        contEliminados.appendChild(titulo);

        eliminados.forEach(id_pc => {
            const equipo = buscarEquipoPorId(id_pc);
            const li = document.createElement("li");
            li.className = "eliminado";
            const texto = document.createElement("span");
            texto.textContent = equipo ? equipo.etiqueta : `Equipo #${id_pc}`;
            li.appendChild(texto);

            const quitar = document.createElement("button");
            quitar.type = "button";
            quitar.className = "quitar";
            quitar.textContent = "×";
            quitar.setAttribute("aria-label",
                `Desmarcar ${equipo ? equipo.etiqueta : "equipo"} de la eliminación`);
            quitar.addEventListener("click", () => toggleEliminar(id_pc));
            li.appendChild(quitar);
            contEliminados.appendChild(li);
        });
    }

    // El botón de guardar se habilita si hay algo que agregar O algo
    // que eliminar, y cambia de texto según lo pendiente.
    const btn = el("btn-guardar");
    if (btn) {
        const hayAlgo = lugares.length > 0 || eliminados.size > 0;
        btn.disabled = !hayAlgo;
        if (eliminados.size > 0) {
            btn.textContent = lugares.length > 0
                ? `Guardar y eliminar (${lugares.length + eliminados.size})`
                : `Eliminar ${eliminados.size} equipo(s)`;
        } else {
            btn.textContent = "Guardar equipos";
        }
    }
}

// Refresca toda la vista (plano + lista).
function refrescar() {
    dibujarPlano();
    renderLista();
    renderListaMovil();
}

// ============================================================
// VISTA MÓVIL: LISTA ALTERNATIVA DE LUGARES LIBRES
// ============================================================
// En pantallas chicas el plano scanea en horizontal; esta lista da
// una forma de elegir los lugares con checkbox.
function renderListaMovil() {
    const cont = el("lista-movil");
    if (!cont) return;

    const ul = cont.querySelector("ul");
    if (!ul) return;
    ul.replaceChildren();

    FILAS.forEach(f => {
        for (let p = 1; p <= estado.slots[f]; p++) {
            const clave = claveLugar(f, p);
            if (OCUPADOS.has(clave)) continue;

            const li = document.createElement("li");
            const label = document.createElement("label");

            const check = document.createElement("input");
            check.type = "checkbox";
            check.checked = estado.elegidos.has(clave);
            check.addEventListener("change", () => {
                if (check.checked) elegirLugar(f, p, "");
                else quitarLugar(f, p);
            });

            const texto = document.createElement("span");
            const datos = estado.elegidos.get(clave);
            texto.textContent = `Fila ${f}, posición ${p}`
                + (datos && datos.etiqueta ? ` — ${datos.etiqueta}` : "");

            label.appendChild(check);
            label.appendChild(texto);
            li.appendChild(label);
            ul.appendChild(li);
        }
    });
}

// ============================================================
// DIÁLOGO: CÓDIGO DE UN LUGAR
// ============================================================
// Se abre al tocar un lugar libre (o uno ya elegido, para cambiarle
// el código). Si el lugar es fijo (viene del "+ agregar lugar"), el
// selector de fila/posición queda oculto.
let lugarEnEdicion = null;

function abrirDialogoCodigo(fila, posicion, etiquetaActual) {
    const dlg = el("dlg-codigo");
    if (!dlg) return;

    lugarEnEdicion = { fila, posicion };
    const titulo = el("titulo-codigo");
    const txt = el("txt-codigo");
    const nota = el("nota-codigo");
    const campos = el("campos-lugar");
    const fijo = el("lugar-fijo");

    if (titulo) {
        titulo.textContent = `Código del equipo — fila ${fila}, posición ${posicion}`;
    }
    if (nota) {
        nota.hidden = true;
        nota.textContent = "";
        nota.classList.remove("is-error");
    }
    if (txt) {
        txt.value = etiquetaActual || "";
        txt.placeholder = "Ej: 305-01";
    }
    if (campos) campos.hidden = true;
    if (fijo) {
        fijo.hidden = false;
        fijo.textContent = `Fila ${fila}, posición ${posicion}`;
    }

    dlg.showModal();
    if (txt) txt.focus();
}

// Confirma el código del lugar abierto.
function confirmarCodigo() {
    if (!lugarEnEdicion) return;
    const dlg = el("dlg-codigo");
    const txt = el("txt-codigo");
    const nota = el("nota-codigo");

    const etiqueta = normalizarEtiqueta(txt ? txt.value : "");
    const { fila, posicion } = lugarEnEdicion;

    if (etiqueta === "") {
        if (nota) {
            nota.hidden = false;
            nota.classList.add("is-error");
            nota.textContent = "Escribí un código para el equipo.";
        }
        return;
    }
    if (etiqueta.length > 20) {
        if (nota) {
            nota.hidden = false;
            nota.classList.add("is-error");
            nota.textContent = "La etiqueta no puede pasar de 20 caracteres.";
        }
        return;
    }
    // La etiqueta no puede ser una que ya existe en la sala ni una
    // repetida dentro de esta tanda.
    const minus = etiqueta.toLowerCase();
    if (CODIGOS_USADOS.has(minus)) {
        if (nota) {
            nota.hidden = false;
            nota.classList.add("is-error");
            nota.textContent = `La etiqueta "${etiqueta}" ya existe en esta sala.`;
        }
        return;
    }
    const clave = claveLugar(fila, posicion);
    let repetida = false;
    estado.elegidos.forEach((d, c) => {
        if (c !== clave && d.etiqueta.toLowerCase() === minus) repetida = true;
    });
    if (repetida) {
        if (nota) {
            nota.hidden = false;
            nota.classList.add("is-error");
            nota.textContent = `La etiqueta "${etiqueta}" ya está en la lista.`;
        }
        return;
    }

    estado.elegidos.set(clave, { fila, posicion, etiqueta });
    estado.errores.delete(clave);
    lugarEnEdicion = null;
    if (dlg) dlg.close();
    refrescar();
    marcarPista(`Se cargó ${etiqueta} en la fila ${fila}, posición ${posicion}.`);
}

// ============================================================
// GUARDAR: TODO EN UNA SOLA PETICIÓN
// ============================================================
// Si falla, lo escrito se conserva y el error queda marcado en el
// lugar (nada se pierde).
function guardar() {
    if (estado.elegidos.size === 0 && eliminados.size === 0) {
        marcarPista("No hay equipos para guardar ni eliminar.", "error");
        return;
    }
    if (!validar()) {
        refrescar();
        marcarPista("Revisá los equipos marcados en rojo antes de guardar.", "error");
        return;
    }

    const btn = el("btn-guardar");
    if (btn) btn.disabled = true;
    marcarPista("Guardando los equipos...");

    const lugares = lugaresOrdenados().map(([clave, d]) => ({
        etiqueta: d.etiqueta,
        fila: d.fila,
        posicion: d.posicion,
    }));

    const cuerpo = new URLSearchParams();
    cuerpo.set("id_sala", String(DATOS_EDITOR.sala.id));
    cuerpo.set("lugares", JSON.stringify(lugares));
    cuerpo.set("eliminados", JSON.stringify(Array.from(eliminados)));

    fetch(DATOS_EDITOR.guardar_url || "plano-guardar.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            "Accept": "application/json",
        },
        body: cuerpo.toString(),
        credentials: "same-origin",
    })
        .catch(() => ({ tipo: "conexion" }))   // el fetch no llegó al servidor
        .then(r => (r.tipo === "conexion" ? r : leerRespuesta(r)))
        .then(resultado => {
            if (resultado.tipo === "conexion") {
                refrescar();
                marcarPista(
                    "No hay conexión con el servidor. Lo que escribiste se conserva; probá de nuevo.",
                    "error"
                );
                return;
            }
            if (resultado.tipo === "sesion" || resultado.tipo === "servidor" || resultado.tipo === "json") {
                // NUNCA se recarga la página ante un error: el estado
                // del editor (lo que escribió el admin) se conserva.
                refrescar();
                marcarPista(resultado.mensaje, "error");
                return;
            }
            // tipo === "json_ok": llegó un JSON con exito
            const datos = resultado.datos;
            if (datos.exito === true) {
                marcarPista(datos.mensaje || "Equipos guardados.", "ok");
                // Solo en el éxito se recarga, para traer el plano nuevo.
                window.setTimeout(() => {
                    window.location.href = window.location.pathname
                        + "?id_sala=" + encodeURIComponent(DATOS_EDITOR.sala.id)
                        + "&msg=" + encodeURIComponent(datos.mensaje || "");
                }, 900);
                return;
            }
            // JSON con errores: se marca cada equipo en su lugar.
            const errores = datos.errores || [];
            aplicarErroresDelServidor(errores);
            refrescar();
            marcarPista(datos.mensaje || "No se pudo guardar.", "error");
        })
        .catch(() => {
            // Cualquier otra excepción (por ejemplo, JSON ilegible):
            // se trata como error del servidor, sin perder lo escrito.
            refrescar();
            marcarPista(
                "El servidor tuvo un error y no se guardó nada; probá de nuevo.",
                "error"
            );
        })
        .finally(() => {
            if (btn) btn.disabled = estado.elegidos.size === 0;
        });
}

// ── Lectura y clasificación de la respuesta del servidor ──
// La respuesta puede no ser JSON: el servidor redirige al login si
// venció la sesión o si el rol no tiene permiso, y ante un error
// fatal de PHP devuelve HTML. En esos casos NO se puede usar
// r.json() a ciegas: se clasifica la respuesta y se avisa.
//   tipo "conexion" → no hubo respuesta del servidor
//   tipo "sesion"   → respuesta no JSON por sesión vencida o sin permiso
//   tipo "servidor" → respuesta no JSON con status >= 500
//   tipo "json"     → la respuesta dice ser JSON pero no se puede leer
//   tipo "json_ok"  → JSON válido (puede traer exito true o false)
function leerRespuesta(r) {
    const MSJ_SESION = "Tu sesión venció o no tenés permiso para guardar. "
        + "Volvé a iniciar sesión en otra pestaña y reintentá: lo que escribiste se conserva.";
    const MSJ_SERVIDOR = "El servidor tuvo un error y no se guardó nada; probá de nuevo.";

    const contentType = r.headers.get("Content-Type") || "";
    const esJson = contentType.toLowerCase().includes("application/json");
    const redirigida = r.redirected || r.url.indexOf("/login.php") !== -1;

    if (!esJson || redirigida) {
        if (r.status >= 500) {
            return { tipo: "servidor", mensaje: MSJ_SERVIDOR };
        }
        return { tipo: "sesion", mensaje: MSJ_SESION };
    }

    return r.text().then(texto => {
        try {
            return { tipo: "json_ok", datos: JSON.parse(texto) };
        } catch (e) {
            return { tipo: "json", mensaje: MSJ_SERVIDOR };
        }
    });
}

// El servidor manda errores en texto ("Equipo "PC-A": fila inválida").
// Se marca el lugar cuando se puede deducir de qué equipo se trata.
function aplicarErroresDelServidor(errores) {
    if (!errores || !errores.length) return;
    errores.forEach(msg => {
        const m = /"([^"]+)"/.exec(msg);
        const etiqueta = m ? m[1] : null;
        if (!etiqueta) return;
        const minus = etiqueta.toLowerCase();
        let encontrado = false;
        estado.elegidos.forEach((d, clave) => {
            if (!encontrado && d.etiqueta.toLowerCase() === minus) {
                estado.errores.set(clave, msg);
                encontrado = true;
            }
        });
        if (!encontrado) {
            // Error general (lugar ocupado, tope por fila): se deja
            // en la pista, que ya lo muestra.
            estado.errores.set("general", msg);
        }
    });
}

// ============================================================
// ARRANQUE
// ============================================================
function conectarEventos() {
    // Cambiar de sala: recarga la página con la sala elegida.
    const selSala = el("sel-sala");
    if (selSala) {
        selSala.addEventListener("change", () => {
            window.location.href = "plano-editor.php?id_sala=" + encodeURIComponent(selSala.value);
        });
    }

    // Modos Agregar / Eliminar
    const btnModoAgregar = el("btn-modo-agregar");
    const btnModoEliminar = el("btn-modo-eliminar");
    const bannerEliminar = el("banner-eliminar");
    const btnCancelarEliminarModo = el("btn-cancelar-eliminar-modo");

    function actualizarUIModoEliminar() {
        // La clase en el contenedor habilita los estilos de hover/indicador
        // de los equipos (css/editor-plano.css → .modo-eliminar ...).
        const raiz = document.querySelector(".editor-plano");
        if (raiz) {
            raiz.classList.toggle("modo-eliminar", modoEliminar);
            raiz.classList.toggle("modo-agregar", !modoEliminar);
        }

        if (modoEliminar) {
            btnModoEliminar.classList.add("is-active");
            btnModoEliminar.setAttribute("aria-pressed", "true");
            btnModoAgregar.classList.remove("is-active");
            btnModoAgregar.setAttribute("aria-pressed", "false");
            if (bannerEliminar) bannerEliminar.hidden = false;
        } else {
            btnModoEliminar.classList.remove("is-active");
            btnModoEliminar.setAttribute("aria-pressed", "false");
            btnModoAgregar.classList.add("is-active");
            btnModoAgregar.setAttribute("aria-pressed", "true");
            if (bannerEliminar) bannerEliminar.hidden = true;
        }
    }

    if (btnModoAgregar && btnModoEliminar) {
        btnModoAgregar.addEventListener("click", () => {
            modoEliminar = false;
            actualizarUIModoEliminar();
            refrescar();
        });
        btnModoEliminar.addEventListener("click", () => {
            modoEliminar = true;
            actualizarUIModoEliminar();
            refrescar();
        });
    }

    // Cancelar modo eliminar desde el banner
    if (btnCancelarEliminarModo) {
        btnCancelarEliminarModo.addEventListener("click", () => {
            modoEliminar = false;
            actualizarUIModoEliminar();
            refrescar();
        });
    }

    // Inicializar estado de botones
    actualizarUIModoEliminar();

    // Diálogo del código.
    const dlgCodigo = el("dlg-codigo");
    const btnConfCod = el("btn-confirmar-codigo");
    if (btnConfCod) btnConfCod.addEventListener("click", confirmarCodigo);
    const btnCancCod = el("btn-cancelar-codigo");
    if (btnCancCod && dlgCodigo) {
        btnCancCod.addEventListener("click", () => {
            lugarEnEdicion = null;
            dlgCodigo.close();
        });
    }

    // Diálogo de eliminación.
    const dlgEliminar = el("dlg-eliminar");
    const btnConfEliminar = el("btn-confirmar-eliminar");
    const btnCancEliminar = el("btn-cancelar-eliminar");
    if (btnConfEliminar) {
        btnConfEliminar.addEventListener("click", () => {
            if (pcPendienteEliminar !== null) {
                eliminados.add(pcPendienteEliminar);
                const equipo = buscarEquipoPorId(pcPendienteEliminar);
                if (equipo) marcarPista(`Se marcó ${equipo.etiqueta} para eliminar.`);
                pcPendienteEliminar = null;
                refrescar();
            }
            if (dlgEliminar) dlgEliminar.close();
        });
    }
    if (btnCancEliminar && dlgEliminar) {
        btnCancEliminar.addEventListener("click", () => {
            pcPendienteEliminar = null;
            dlgEliminar.close();
        });
    }
    // Enter en el campo de código confirma.
    const txtCodigo = el("txt-codigo");
    if (txtCodigo) {
        txtCodigo.addEventListener("keydown", ev => {
            if (ev.key === "Enter") { ev.preventDefault(); confirmarCodigo(); }
        });
    }

    // Guardar.
    const btnGuardar = el("btn-guardar");
    if (btnGuardar) btnGuardar.addEventListener("click", guardar);
}

// ── Arranque ──
document.addEventListener("DOMContentLoaded", () => {
    conectarEventos();
    refrescar();
});
