/*=============================================================
 * login-animations.js
 * Animaciones del panel de marca de la página de login
 * Versión 0.1 – 2026-10-30
 *=============================================================
 * Autor: Claude Code
 * (El código está libre de dependencias externas)
 */

(() => {
    /** ── CONFIGURACIÓN ----------------------- */
    const ANIM_MODES = {
        REPULSE: 0,       // “Red de nodos repelente”
        CURSOR_HUB: 1,   // “Hub de cursor”  ← default
        LOGO_MURAL: 2    // “Mural con símbolo” (solo el icono del favicon)
    };
    // Leemos modo guardado en localStorage (o iniciamos con CURSOR_HUB)
    const STORAGE_KEY = 'loginAnimMode';
    let mode = parseInt(localStorage.getItem(STORAGE_KEY)) || ANIM_MODES.CURSOR_HUB;

    /** ── EL CANVAS --------------------------- */
    const cv = document.getElementById('red');
    if (!cv) return;
    const cx = cv.getContext('2d');
    const movil = window.matchMedia("(max-width: 720px)");
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const cursorPos = {x: 0, y: 0};

    /** ── VARIABLE GLOBALS ------------------- */
    let raf;                // id de requestAnimationFrame
    let nodes = [];         // objetos que usarán las tres variaciones
    let paused = false;     // uso de botón PAUSA
    let W = 0, H = 0;       // tamaño CSS en píxeles

    /** ── TAMAÑO Y DPR ── */
    function size() {
        const r = cv.getBoundingClientRect();
        if (!r.width || (r.width === W && r.height === H)) return;
        const d = window.devicePixelRatio || 1;
        W = r.width; H = r.height;
        cv.width = W * d;
        cv.height = H * d;
        cx.setTransform(d, 0, 0, d, 0, 0);
        // Re-inicializar nodos con el nuevo tamaño
        reinitNodes();
    }

    /** ── BRAZO 1 – Red de nodos repelente */
    function initRepulse() {
        const n = movil.matches ? 3 : 6;
        nodes = Array.from({length: n}, _ => ({
            x: Math.random()*W,
            y: Math.random()*H,
            r: 0,
            maxR: 90 + Math.random()*70,
            alpha: 1,
            repelDist: 80
        }));
    }

    /** ── BRAZO 2 – Hub de cursor */
    function initCursorHub() {
        nodes = [];
    }

    /** ── BRAZO 2 – Mural con símbolo (icono del favicon) */
    function initLogoMural() {
        const img = new Image();
        img.src = '/img/favicon-32.png';
        img.onload = () => {
            cv.logoImg = img;
        };
        nodes = [];
    }

    function reinitNodes() {
        switch (mode) {
            case ANIM_MODES.REPULSE:     initRepulse();     break;
            case ANIM_MODES.CURSOR_HUB:  initCursorHub();  break;
            case ANIM_MODES.LOGO_MURAL:  initLogoMural();  break;
            default: break;
        }
    }

    /** ── RENDER ── */
    function frame() {
        cx.clearRect(0, 0, W, H);

        switch (mode) {
            case ANIM_MODES.REPULSE:
                nodes.forEach(p => {
                    if (W === 0 || H === 0) return;
                    const dx = p.x - cursorPos.x;
                    const dy = p.y - cursorPos.y;
                    const d = Math.sqrt(dx*dx+dy*dy);
                    if (d < p.repelDist) {
                        const factor = (p.repelDist - d) / p.repelDist;
                        p.x += dx * factor * 0.1;
                        p.y += dy * factor * 0.1;
                    }
                    // clamp
                    p.x = Math.max(0, Math.min(W, p.x));
                    p.y = Math.max(0, Math.min(H, p.y));
                    cx.globalAlpha = p.alpha * 0.5;
                    cx.strokeStyle = `rgba(0,196,204,${p.alpha * 0.5})`;
                    cx.lineWidth = 1.5;
                    cx.beginPath();
                    cx.arc(p.x, p.y, p.r, 0, 2*Math.PI);
                    cx.stroke();
                    cx.globalAlpha = 1;
                    if (!paused) {
                        p.r += 0.8;
                        p.alpha = 1 - (p.r / p.maxR);
                        if (p.alpha <= 0) {
                            p.r = 0;
                            p.alpha = 1;
                            p.x = Math.random()*W;
                            p.y = Math.random()*H;
                        }
                    }
                });
                break;

            case ANIM_MODES.CURSOR_HUB:
                if (W === 0 || H === 0) break;
                // mantener cursor dentro del canvas
                cursorPos.x = Math.max(0, Math.min(W, cursorPos.x));
                cursorPos.y = Math.max(0, Math.min(H, cursorPos.y));
                const maxLen = Math.min(300, Math.max(W, H)/2);
                const cnt = 8;
                cx.strokeStyle = 'rgba(0,196,204,0.25)';
                cx.lineWidth = 1;
                for (let i=0;i<cnt;i++) {
                    const angle = (Math.PI*2)*(i/cnt);
                    const d = Math.random()*maxLen;
                    const targetX = cursorPos.x + Math.cos(angle)*d;
                    const targetY = cursorPos.y + Math.sin(angle)*d;
                    cx.beginPath();
                    cx.moveTo(cursorPos.x, cursorPos.y);
                    cx.lineTo(targetX, targetY);
                    cx.stroke();
                }
                cx.fillStyle = 'rgba(0,196,204,0.6)';
                cx.beginPath();
                cx.arc(cursorPos.x, cursorPos.y, 6, 0, 2*Math.PI);
                cx.fill();
                break;

            case ANIM_MODES.LOGO_MURAL:
                const img = cv.logoImg;
                if (img && W > 0 && H > 0) {
                    const tx = 150;
                    const ty = 150;
                    for (let y=0; y<H; y+=ty) {
                        for (let x=0; x<W; x+=tx) {
                            cx.globalAlpha = 0.12;
                            cx.drawImage(img, x, y, 100, 100);
                        }
                    }
                    cx.globalAlpha = 1;
                }
                break;
        }

        if (!paused) raf = requestAnimationFrame(frame);
    }

    /** ── CONSTRUCTORA DE MODOS ── */
    function initMode() {
        switch (mode) {
            case ANIM_MODES.REPULSE:     initRepulse();     break;
            case ANIM_MODES.CURSOR_HUB:  initCursorHub();  break;
            case ANIM_MODES.LOGO_MURAL:  initLogoMural();  break;
            default: break;
        }
        if (!paused) raf = requestAnimationFrame(frame);
    }

    /** ── CONTROL DE PAUSA ── */
    const btnPa = document.getElementById('pausa');
    if (btnPa) {
        btnPa.addEventListener('click', () => {
            paused = !paused;
            btnPa.setAttribute('aria-pressed', paused ? 'true' : 'false');
            btnPa.textContent = paused ? 'Reanudar movimiento' : 'Pausar movimiento';
            if (!paused && !raf) raf = requestAnimationFrame(frame);
            if (paused && raf) cancelAnimationFrame(raf);
        });
    }

    /** ── CONTROL MOUSE ── */
    document.addEventListener('pointermove', e => {
        const r = cv.getBoundingClientRect();
        cursorPos.x = e.clientX - r.left;
        cursorPos.y = e.clientY - r.top;
    });

    /** ── BOTÓN CAMBIAR ANIMACIÓN ── */
    const toggleBtn = document.createElement('button');
    toggleBtn.textContent = mode===ANIM_MODES.LOGO_MURAL ? 'Mural' : 'Repulsión';
    toggleBtn.style.position = 'absolute';
    toggleBtn.style.top = '12px';
    toggleBtn.style.left = '12px';
    toggleBtn.style.padding = '6px 10px';
    toggleBtn.style.background = 'rgba(255,255,255,0.8)';
    toggleBtn.style.border = '1px solid #006a7a';
    toggleBtn.style.borderRadius = '4px';
    toggleBtn.style.cursor = 'pointer';
    toggleBtn.style.zIndex = '100';
    toggleBtn.addEventListener('click', () => {
        mode = (mode + 1) % 3;
        localStorage.setItem(STORAGE_KEY, mode);
        toggleBtn.textContent = mode===ANIM_MODES.LOGO_MURAL ? 'Mural' :
                                mode===ANIM_MODES.REPULSE ? 'Repulsión' : 'Hub';
        cancelAnimationFrame(raf);
        initMode();
    });
    cv.parentElement.appendChild(toggleBtn);

    /** ── INICIO ── */
    function start() {
        size();
        if (!reduce && !movil.matches) {
            // No iniciar en móvil: el canvas está oculto y consume CPU
            if (W > 0 && H > 0) initMode();
        } else if (!reduce && !movil.matches) {
            size();
        }
    }

    function iniciar() {
        if (window.ResizeObserver && cv) {
            new ResizeObserver(() => { size(); }).observe(cv);
        } else {
            window.addEventListener('resize', size);
        }
        start();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) cancelAnimationFrame(raf);
        else if (!paused && !reduce && !movil.matches) raf = requestAnimationFrame(frame);
    });
})();
