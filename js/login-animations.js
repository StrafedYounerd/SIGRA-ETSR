/*=============================================================
 * login-animations.js
 * Animaciones del panel de marca de la página de login
 * Versión 0.2 – 2026-10-30
 *=============================================================
 * Autor: Claude Code
 * (El código está libre de dependencias externas)
 */

(() => {
    /** ── CONFIGURACIÓN ----------------------- */
    const ANIM_MODES = {
        HUB: 0,          // Hub de cursor con órbitas y partículas
        PULSO: 1,        // Red de pulsos "escaneo" (la original del login.php)
        MURAL: 2,        // Campo de partículas orgánicas con el símbolo (FLOW)
        LLUVIA: 3,       // Lluvia de datos / matrix style
        GOTAS: 4         // Gotas de agua cayendo, rebotan y huyen del cursor
    };
    const STORAGE_KEY = 'loginAnimMode';
    // Aleatorio al cargar si no hay preferencia guardada
    let saved = parseInt(localStorage.getItem(STORAGE_KEY));
    let mode = Number.isInteger(saved) ? saved : Math.floor(Math.random() * 5);

    /** ── EL CANVAS --------------------------- */
    const cv = document.getElementById('red');
    if (!cv) return;
    const cx = cv.getContext('2d');
    const movil = window.matchMedia("(max-width: 720px)");
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const cursorPos = {x: 0, y: 0};

    /** ── VARIABLES GLOBALES ------------------- */
    let raf;                // id de requestAnimationFrame
    let nodes = [];         // objetos que usarán las variaciones
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
        reinitNodes();
    }

    /** ── MODO HUB: órbitas alrededor del cursor ── */
    function initHub() {
        const n = movil.matches ? 3 : 6;
        nodes = Array.from({length: n}, (_, i) => ({
            angle: (Math.PI * 2) * (i / n),
            radius: 60 + Math.random() * 80,
            speed: 0.005 + Math.random() * 0.008,
            size: 2 + Math.random() * 3,
            hue: 180 + Math.random() * 30,
            alpha: 0.4 + Math.random() * 0.3
        }));
    }

    /** ── MODO PULSO: anillos que se expanden desde centros ── */
    function initPulso() {
        const n = movil.matches ? 3 : 6;
        nodes = Array.from({length: n}, _ => ({
            x: Math.random() * W,
            y: Math.random() * H,
            r: 0,
            maxR: 100 + Math.random() * 100,
            alpha: 1,
            thickness: 1.5 + Math.random() * 1.5
        }));
    }

    /** ── MODO FLOW: campo de partículas orgánicas con el símbolo ── */
    function initMural() {
        const img = new Image();
        img.src = '/img/favicon-32.png';
        img.onload = () => { cv.logoImg = img; };
        const count = movil.matches ? 18 : 35;
        nodes = Array.from({length: count}, () => ({
            x: Math.random() * W,
            y: Math.random() * H,
            vx: (Math.random() - 0.5) * 0.6,
            vy: (Math.random() - 0.5) * 0.6,
            size: 18 + Math.random() * 22,
            alpha: 0.03 + Math.random() * 0.08,
            rot: Math.random() * Math.PI * 2,
            rotSpeed: (Math.random() - 0.5) * 0.003,
            hue: 180 + Math.random() * 40
        }));
    }

    /** ── MODO LLUVIA: matrix style / datos cayendo ── */
    function initLluvia() {
        const cols = Math.floor((W || 900) / 18);
        nodes = Array.from({length: cols}, (_, i) => ({
            x: i * 18 + Math.random() * 6,
            y: Math.random() * H,
            speed: 0.8 + Math.random() * 1.2,
            length: 8 + Math.random() * 18,
            chars: '01ABCDEF<>[]{}',
            hue: 180 + Math.random() * 40
        }));
    }

    /** ── MODO GOTAS: gotas de agua cayendo, rebotan y huyen del cursor ── */
    function initGotas() {
        const count = movil.matches ? 25 : 50;
        nodes = Array.from({length: count}, () => ({
            x: Math.random() * W,
            y: Math.random() * H,
            vx: 0,
            vy: 0.5 + Math.random() * 1.5,
            r: 3 + Math.random() * 5,
            baseAlpha: 0.4 + Math.random() * 0.3,
            alpha: 0,
            phase: Math.random() * Math.PI * 2,
            life: 0
        }));
    }

    function reinitNodes() {
        switch (mode) {
            case ANIM_MODES.HUB:     initHub();     break;
            case ANIM_MODES.PULSO:   initPulso();   break;
            case ANIM_MODES.MURAL:   initMural();   break;
            case ANIM_MODES.LLUVIA:  initLluvia();  break;
            case ANIM_MODES.GOTAS:   initGotas();   break;
            default: break;
        }
    }

    /** ── RENDER ── */
    function frame() {
        cx.clearRect(0, 0, W, H);

        switch (mode) {
            case ANIM_MODES.HUB: {
                if (W === 0 || H === 0) break;
                // mantener cursor dentro del canvas
                cursorPos.x = Math.max(0, Math.min(W, cursorPos.x));
                cursorPos.y = Math.max(0, Math.min(H, cursorPos.y));

                if (!paused) {
                    nodes.forEach(p => {
                        p.angle += p.speed;
                    });
                }

                // dibujar órbitas
                nodes.forEach(p => {
                    const x = cursorPos.x + Math.cos(p.angle) * p.radius;
                    const y = cursorPos.y + Math.sin(p.angle) * p.radius;
                    cx.fillStyle = `hsla(${p.hue}, 80%, 45%, ${p.alpha})`;
                    cx.beginPath();
                    cx.arc(x, y, p.size, 0, 2 * Math.PI);
                    cx.fill();

                    // línea del cursor al nodo
                    cx.strokeStyle = `hsla(${p.hue}, 80%, 45%, ${p.alpha * 0.3})`;
                    cx.lineWidth = 1;
                    cx.beginPath();
                    cx.moveTo(cursorPos.x, cursorPos.y);
                    cx.lineTo(x, y);
                    cx.stroke();
                });

                // punto central
                cx.fillStyle = 'rgba(0,196,204,0.8)';
                cx.beginPath();
                cx.arc(cursorPos.x, cursorPos.y, 6, 0, 2 * Math.PI);
                cx.fill();
                break;
            }

            case ANIM_MODES.PULSO: {
                nodes.forEach(p => {
                    if (W === 0 || H === 0) return;
                    cx.globalAlpha = p.alpha * 0.5;
                    cx.strokeStyle = `rgba(0,196,204,${p.alpha * 0.5})`;
                    cx.lineWidth = p.thickness;
                    cx.beginPath();
                    cx.arc(p.x, p.y, p.r, 0, 2 * Math.PI);
                    cx.stroke();
                    cx.globalAlpha = 1;

                    if (!paused) {
                        p.r += 0.8;
                        p.alpha = 1 - (p.r / p.maxR);
                        if (p.alpha <= 0) {
                            p.r = 0;
                            p.alpha = 1;
                            p.x = Math.random() * W;
                            p.y = Math.random() * H;
                        }
                    }
                });
                break;
            }

            case ANIM_MODES.MURAL: {
                const img = cv.logoImg;
                if (!img || W === 0 || H === 0) break;

                if (!paused) {
                    nodes.forEach(p => {
                        // física simple: movimiento + rebote en bordes
                        p.x += p.vx;
                        p.y += p.vy;
                        if (p.x < 0 || p.x > W) p.vx *= -1;
                        if (p.y < 0 || p.y > H) p.vy *= -1;

                        // atracción suave al cursor
                        const dx = cursorPos.x - p.x;
                        const dy = cursorPos.y - p.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 200 && dist > 1) {
                            const force = (200 - dist) / 200 * 0.02;
                            p.vx += (dx / dist) * force;
                            p.vy += (dy / dist) * force;
                        }

                        p.rot += p.rotSpeed;
                    });
                }

                nodes.forEach(p => {
                    cx.save();
                    cx.translate(p.x, p.y);
                    cx.rotate(p.rot);
                    cx.globalAlpha = p.alpha;
                    const s = p.size;
                    cx.drawImage(img, -s / 2, -s / 2, s, s);
                    cx.restore();
                });
                break;
            }

            case ANIM_MODES.LLUVIA: {
                if (W === 0 || H === 0) break;

                if (!paused) {
                    nodes.forEach(col => {
                        col.y += col.speed;
                        if (col.y > H + col.length * 14) {
                            col.y = -col.length * 14;
                            col.speed = 0.8 + Math.random() * 1.2;
                        }
                    });
                }

                nodes.forEach(col => {
                    const chars = col.chars;
                    for (let i = 0; i < col.length; i++) {
                        const y = col.y - i * 14;
                        if (y < -14 || y > H + 14) continue;
                        const alpha = 1 - i / col.length;
                        const char = chars[Math.floor(Math.random() * chars.length)];
                        cx.fillStyle = `hsla(${col.hue}, 90%, 45%, ${alpha * 0.6})`;
                        cx.font = '12px monospace';
                        cx.fillText(char, col.x, y);
                    }
                });
                break;
            }

            case ANIM_MODES.GOTAS: {
                if (W === 0 || H === 0) break;

                if (!paused) {
                    nodes.forEach(d => {
                        d.vy += 0.05;                  // gravedad
                        d.y += d.vy;
                        d.x += d.vx;

                        // rebote en el suelo
                        if (d.y > H - d.r) {
                            d.y = H - d.r;
                            d.vy *= -0.55;
                            d.vx *= 0.9;
                            d.alpha = d.baseAlpha;
                        }

                        // repulsión del cursor
                        const dx = d.x - cursorPos.x;
                        const dy = d.y - cursorPos.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        if (dist < 80 && dist > 1) {
                            const force = (80 - dist) / 80 * 0.8;
                            d.vx += (dx / dist) * force;
                            d.vy += (dy / dist) * force;
                        }

                        // desvanecimiento arriba
                        d.alpha = Math.max(0, d.baseAlpha * (1 - d.y / H * 0.3));
                    });
                }

                nodes.forEach(d => {
                    cx.globalAlpha = d.alpha;
                    cx.fillStyle = 'rgba(0,196,204,1)';
                    cx.beginPath();
                    cx.arc(d.x, d.y, d.r, 0, 2 * Math.PI);
                    cx.fill();
                });
                cx.globalAlpha = 1;
                break;
            }
        }

        if (!paused) raf = requestAnimationFrame(frame);
    }

    /** ── CONSTRUCTORA DE MODOS ── */
    function initMode() {
        reinitNodes();
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
    const labels = ['Hub', 'Pulso', 'Mural', 'Lluvia', 'Gotas'];
    const toggleBtn = document.createElement('button');
    toggleBtn.textContent = labels[mode];
    toggleBtn.style.position = 'absolute';
    toggleBtn.style.top = '12px';
    toggleBtn.style.left = '12px';
    toggleBtn.style.padding = '6px 10px';
    toggleBtn.style.background = 'rgba(255,255,255,0.8)';
    toggleBtn.style.border = '1px solid #006a7a';
    toggleBtn.style.borderRadius = '4px';
    toggleBtn.style.cursor = 'pointer';
    toggleBtn.style.zIndex = '100';
    toggleBtn.style.fontSize = '12px';
    toggleBtn.style.fontFamily = 'inherit';
    toggleBtn.addEventListener('click', () => {
        mode = (mode + 1) % 5;
        localStorage.setItem(STORAGE_KEY, mode);
        toggleBtn.textContent = labels[mode];
        cancelAnimationFrame(raf);
        initMode();
    });
    cv.parentElement.appendChild(toggleBtn);

    /** ── INICIO ── */
    function start() {
        size();
        if (!reduce && !movil.matches && W > 0 && H > 0) {
            initMode();
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