// ============================================================
// SIGRA-ETSR · js/shell.js — Menú de usuario de la topbar
// ============================================================
// Abre/cierra el panel con "Cerrar sesión". Cierra con clic afuera
// o con Escape. Sin librerías: la intranet no tiene internet.
(function () {
    "use strict";
    var btn = document.querySelector(".usermenu__btn");
    var panel = document.getElementById("um-panel");
    if (!btn || !panel) return;

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
})();
