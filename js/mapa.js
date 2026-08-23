// ============================================================
// SIGRA-ETSR · Componente B
// js/mapa.js — Lógica del mapa interactivo (clic en PC + reporte)
// ============================================================

// ── Catálogos de fallas por tipo de equipo ──
// Los "value" tienen que coincidir EXACTO con $tipos_validos en
// public/docente/reportar-falla.php (el servidor es quien valida de
// verdad; esto es solo para que el docente vea las opciones correctas
// según lo que clickeó).
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

// ── Carga las opciones de falla correctas según el tipo de equipo clickeado ──
function cargarOpcionesFalla(tipoEquipo) {
    const select = document.getElementById("campo-tipo-falla");
    const opciones = (tipoEquipo === "PC") ? FALLAS_PC : FALLAS_SWITCH_TV;

    select.innerHTML = "";

    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "-- Elegí un tipo --";
    select.appendChild(placeholder);

    opciones.forEach(function (tipo) {
        const opcion = document.createElement("option");
        opcion.value = tipo;
        opcion.textContent = tipo;
        select.appendChild(opcion);
    });
}

document.addEventListener("DOMContentLoaded", function () {

    // ── Clic en cada equipo del mapa (PC, Switch, TV/Proyector) ──
    const celdas = document.querySelectorAll(".equipo");
    celdas.forEach(function (celda) {
        celda.addEventListener("click", function () {
            abrirModalReporte(celda);
        });
    });

    // ── Cambio de tipo de falla: mostrar/ocultar el campo Detalle ──
    const selectTipo = document.getElementById("campo-tipo-falla");
    selectTipo.addEventListener("change", function () {
        const contenedorDetalle = document.getElementById("contenedor-detalle");
        const requiereDetalle = TIPOS_QUE_REQUIEREN_DETALLE.includes(selectTipo.value);
        contenedorDetalle.style.display = requiereDetalle ? "block" : "none";
    });

    // ── Botón "Confirmar reporte" ──
    // Antes este botón usaba onclick="enviarReporte()" directo en el HTML.
    // Ese patrón falla si el archivo .js no cargó a tiempo o quedó en
    // caché viejo, porque el HTML intenta llamar a una función que el
    // navegador todavía no registró. Usar addEventListener acá adentro
    // del DOMContentLoaded es más seguro: si este bloque se ejecuta,
    // significa que mapa.js SÍ cargó completo, y recién ahí conectamos
    // los botones.
    const botonConfirmar = document.getElementById("btn-confirmar-reporte");
    botonConfirmar.addEventListener("click", enviarReporte);

    // ── Botón "Cerrar" del modal ──
    const botonCerrar = document.getElementById("btn-cerrar-modal");
    botonCerrar.addEventListener("click", cerrarModal);

});


// ============================================================
// FUNCIÓN: abrirModalReporte(celda)
// Muestra el modal con la información de la PC clickeada y
// prepara el formulario para un reporte nuevo.
// ============================================================
function abrirModalReporte(celda) {
    const idPC      = celda.getAttribute("data-id-pc");
    const etiqueta  = celda.getAttribute("data-etiqueta");
    const estado    = celda.getAttribute("data-estado");
    const tipoEquipo = celda.getAttribute("data-tipo");

    const infoPC           = document.getElementById("modal-pc-info");
    const mensaje          = document.getElementById("modal-mensaje");
    const bloqueFormulario = document.getElementById("modal-formulario");

    // Cargamos el catálogo de fallas correcto ANTES de limpiar el select
    // (PC tiene un listado, Switch/TV/Proyector otro distinto).
    cargarOpcionesFalla(tipoEquipo);

    // Limpiamos mensajes y formulario de una apertura anterior.
    mensaje.innerHTML = "";
    document.getElementById("campo-tipo-falla").value = "";
    document.getElementById("campo-detalle").value = "";
    document.getElementById("contenedor-detalle").style.display = "none";
    document.getElementById("campo-id-pc").value = idPC;

    infoPC.textContent = etiqueta;

    // Si la PC ya tiene falla activa (según lo que vemos en el mapa),
    // ocultamos el formulario directamente. La validación real y
    // definitiva la hace igual el servidor en reportar-falla.php,
    // esto es solo para no mostrar un formulario inútil de entrada.
    if (estado === "falla") {
        mensaje.innerHTML = '<div class="mensaje-error">Esta PC ya tiene un reporte activo.</div>';
        bloqueFormulario.style.display = "none";
    } else {
        bloqueFormulario.style.display = "block";
    }

    const modal = document.getElementById("modal-reporte");
    modal.classList.remove("modal-oculto");
    modal.classList.add("modal-visible");
}


// ============================================================
// FUNCIÓN: cerrarModal()
// ============================================================
function cerrarModal() {
    const modal = document.getElementById("modal-reporte");
    modal.classList.remove("modal-visible");
    modal.classList.add("modal-oculto");
}


// ============================================================
// FUNCIÓN: enviarReporte()
// Envía los datos del formulario a reportar-falla.php usando fetch().
// Implementa en el frontend la misma secuencia de pasos del
// pseudocódigo ReportarFalla() (la validación real y definitiva
// siempre ocurre del lado del servidor).
// ============================================================
function enviarReporte() {
    const idPC         = document.getElementById("campo-id-pc").value;
    const tipoFalla    = document.getElementById("campo-tipo-falla").value;
    const detalleTexto = document.getElementById("campo-detalle").value;
    const mensaje      = document.getElementById("modal-mensaje");

    // Validación liviana en el frontend (UX), no reemplaza la del servidor.
    if (tipoFalla === "") {
        mensaje.innerHTML = '<div class="mensaje-error">Seleccioná el tipo de falla</div>';
        return;
    }
    if (TIPOS_QUE_REQUIEREN_DETALLE.includes(tipoFalla) && detalleTexto.trim() === "") {
        mensaje.innerHTML = '<div class="mensaje-error">Especificá el detalle de la falla</div>';
        return;
    }

    // Armamos los datos como FormData, formato que PHP lee directo
    // desde $_POST sin configuración adicional.
    const datos = new FormData();
    datos.append("id_pc", idPC);
    datos.append("tipo_falla", tipoFalla);
    datos.append("detalle_texto", detalleTexto);

    fetch("reportar-falla.php", {
        method: "POST",
        body: datos
    })
    .then(function (respuesta) {
        return respuesta.json();
    })
    .then(function (datosRespuesta) {
        if (datosRespuesta.exito) {
            mensaje.innerHTML = '<div class="mensaje-ok">' + datosRespuesta.mensaje + '</div>';
            document.getElementById("modal-formulario").style.display = "none";

            // Recargamos la página después de un momento para que el
            // mapa se actualice y muestre la PC en rojo. No usamos
            // ninguna animación ni temporizador artificial elaborado:
            // simplemente esperamos a que el docente lea el mensaje.
            setTimeout(function () {
                window.location.reload();
            }, 1200);
        } else {
            mensaje.innerHTML = '<div class="mensaje-error">' + datosRespuesta.mensaje + '</div>';
        }
    })
    .catch(function (error) {
        // Mostramos el error real en consola para poder depurar más
        // fácil si vuelve a fallar algo (antes solo decía el mensaje
        // genérico, sin detalle del problema real).
        console.error("Error al enviar el reporte:", error);
        mensaje.innerHTML = '<div class="mensaje-error">Error de conexión con el servidor</div>';
    });
}