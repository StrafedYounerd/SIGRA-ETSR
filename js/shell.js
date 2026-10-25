// ============================================================
// SIGRA-ETSR · js/shell.js — Comportamientos del shell común
// ============================================================
// 1. Menú de usuario (abre/cierra con clic afuera o Escape).
// 2. Contadores [data-countup]: cuentan de 0 al valor en 800ms.
// 3. Selección de sala: tilde SVG que se inyecta acá (la página
//    no se toca) + clase .is-checked como respaldo de :has().
// 4. sigraFlash(): utilidad que marca un elemento que cambió tras
//    una actualización de datos (halo aqua que se desvanece).
//
// Sin librerías: la intranet no tiene internet. Con
// prefers-reduced-motion: reduce nada se anima y los valores
// aparecen directamente en su estado final.
(function () {
    "use strict";

    var reduceMov = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    /* ── 1. Menú de usuario ── */
    var btn = document.querySelector(".usermenu__btn");
    var panel = document.getElementById("um-panel");
    if (btn && panel) {
        function toggle(abrir) {
            btn.setAttribute("aria-expanded", String(abrir));
            panel.hidden = !abrir;
        }

        btn.addEventListener("click", function (e) {
            e.stopPropagation();
            toggle(panel.hidden);
        });

        document.addEventListener("click", function (e) {
            if (!panel.contains(e.target)) toggle(false);
        });

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") toggle(false);
        });
    }

    /* ── 2. Contadores: de 0 al valor en 800ms (enteros solamente) ──
       Solo elementos con data-countup cuyo texto sea un entero.
       requestAnimationFrame con easing de salida (cúbico). */
    document.querySelectorAll("[data-countup]").forEach(function (el) {
        var texto = (el.textContent || "").trim();
        var fin = parseInt(texto, 10);

        // Si no es un entero limpio, no se anima (queda como está).
        if (!Number.isInteger(fin) || String(fin) !== texto) return;
        if (reduceMov || fin === 0) return;

        var inicio = null;
        var DUR = 800;

        function paso(ts) {
            if (inicio === null) inicio = ts;
            var p = Math.min(1, (ts - inicio) / DUR);
            var suave = 1 - Math.pow(1 - p, 3);   // easing de salida
            el.textContent = String(Math.round(fin * suave));
            if (p < 1) requestAnimationFrame(paso);
        }

        requestAnimationFrame(paso);
    });

    /* ── 3. Selección de sala: tilde SVG + clase .is-checked ──
       El SVG se dibuja con stroke-dashoffset en shell.css. La
       clase .is-checked respalda a :has() y permite reiniciar el
       "pop" si el docente vuelve a elegir la misma sala. */
    var salas = document.querySelectorAll(".room");
    if (salas.length) {
        salas.forEach(function (room) {
            var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            svg.setAttribute("viewBox", "0 0 24 24");
            svg.setAttribute("class", "room__tilde");
            svg.setAttribute("aria-hidden", "true");
            var path = document.createElementNS("http://www.w3.org/2000/svg", "path");
            path.setAttribute("d", "M4.5 12.5l5 5L19.5 7");
            svg.appendChild(path);
            room.appendChild(svg);
        });

        document.querySelectorAll(".room__input").forEach(function (input) {
            input.addEventListener("change", function () {
                // Limpio la marca de las tarjetas que quedaron sin elegir
                salas.forEach(function (room) {
                    if (!room.querySelector(".room__input:checked")) {
                        room.classList.remove("is-checked");
                    }
                });

                var room = input.closest(".room");
                if (room && input.checked) {
                    // Reinicio el "pop" si se elige la misma sala otra vez
                    room.classList.remove("is-checked");
                    void room.offsetWidth;
                    room.classList.add("is-checked");
                }
            });
        });
    }

    /* ── 4. sigraFlash(): halo aqua fugaz en un elemento que cambió ──
       Uso: sigraFlash(document.getElementById("contador"));
       Hoy no hay auto-refresco que la llame; queda lista para
       cuando el mapa o el panel se actualicen sin recargar. */
    window.sigraFlash = function (el) {
        if (!el || reduceMov) return;
        el.classList.remove("flash-change");
        void el.offsetWidth;   // reinicia la animación si ya estaba
        el.classList.add("flash-change");
    };
})();
