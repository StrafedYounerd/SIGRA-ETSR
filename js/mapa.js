// ============================================================
// SIGRA-ETSR · Componente B
// js/mapa.js — Lógica del mapa interactivo (clic en PC + reporte)
// ============================================================

document.addEventListener("DOMContentLoaded", function () {

    // ── Clic en cada PC del mapa ──
    const celdasPC = document.querySelectorAll(".pc");
    celdasPC.forEach(function (celda) {
        celda.addEventListener("click", function () {
            abrirModalReporte(celda);
        });
    });

    // ── Cambio de tipo de falla: mostrar/ocultar el campo Detalle ──
    const selectTipo = document.getElementById("campo-tipo-falla");
    selectTipo.addEventListener("change", function () {
        const contenedorDetalle = document.getElementById("contenedor-detalle");
        const requiereDetalle = (selectTipo.value === "Falta de software" || selectTipo.value === "Otro");
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

    const infoPC           = document.getElementById("modal-pc-info");
    const mensaje          = document.getElementById("modal-mensaje");
    const bloqueFormulario = document.getElementById("modal-formulario");

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
    if ((tipoFalla === "Falta de software" || tipoFalla === "Otro") && detalleTexto.trim() === "") {
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