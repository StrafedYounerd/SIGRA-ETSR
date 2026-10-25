<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/login.php — CU-01: Iniciar sesión
// ============================================================
// Implementa el pseudocódigo PROCESO Login() de la Fase 3.

require '../includes/auth.php';
require '../config/db.php';

// Si el usuario ya tiene una sesión activa, no tiene sentido que
// vea el formulario de login de nuevo: lo mandamos directo a su panel.
if (isset($_SESSION['id_usuario'])) {
    redirigirSegunRol($_SESSION['rol']);
}

// Variable donde vamos a guardar el mensaje de error, si lo hay.
$error = "";

// ── PASO: revisar si el formulario fue enviado (método POST) ──
// Si la petición es GET, significa que la página recién se cargó
// y todavía no hay nada que procesar: se muestra el formulario vacío.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recibimos los datos del formulario.
    // trim() saca espacios en blanco al principio/final por si el
    // usuario los tipeó sin querer.
    $usuario     = trim($_POST['usuario'] ?? '');
    $contraseña  = trim($_POST['contraseña'] ?? '');

    // PASO 1 del pseudocódigo: validar que los campos no estén vacíos.
    if ($usuario === '' || $contraseña === '') {
        $error = "Completá todos los campos";
    } else {

        // PASO 2: buscar el usuario en la base de datos.
        // Usamos sentencias preparadas (prepare/bind_param) para evitar
        // inyección SQL — esto es indispensable aunque no esté en el
        // pseudocódigo explícitamente, porque es la forma correcta y
        // segura de hacer cualquier consulta con datos del usuario.
        $consulta = $conexion->prepare(
            "SELECT id_usuario, nombre, contraseña, rol, activo
             FROM usuarios WHERE usuario = ?"
        );
        $consulta->bind_param("s", $usuario); // "s" = el parámetro es texto (string)
        $consulta->execute();
        $resultado = $consulta->get_result();
        $registro  = $resultado->fetch_assoc(); // NULL si no hay coincidencia

        // PASO 3: verificar que el usuario existe.
        if (!$registro) {
            $error = "Usuario o contraseña incorrectos";

        // PASO 4: verificar la contraseña comparando el hash.
        // hash('sha256', ...) genera el mismo tipo de hash que usamos
        // al cargar los datos de prueba en el script SQL.
        } elseif (hash('sha256', $contraseña) !== $registro['contraseña']) {
            $error = "Usuario o contraseña incorrectos";

        // PASO 5: verificar que la cuenta está activa.
        } elseif ($registro['activo'] == 0) {
            $error = "Cuenta desactivada. Contactá al administrador.";

        // PASO 6: credenciales válidas, crear la sesión.
        } else {
            $_SESSION['id_usuario'] = $registro['id_usuario'];
            $_SESSION['nombre']     = $registro['nombre'];
            $_SESSION['rol']        = $registro['rol'];

            // PASO 7: redirigir según el rol.
            redirigirSegunRol($registro['rol']);
        }
    }
}

// ============================================================
// FUNCIÓN: redirigirSegunRol()
// Implementa el SEGÚN...HACER del pseudocódigo (paso 7).
// Se define acá porque solo la usa esta página.
// ============================================================
function redirigirSegunRol($rol) {
    switch ($rol) {
        case 'docente':
            header("Location: /docente/seleccionar-sala.php");
            break;
        case 'tecnico':
            header("Location: /tecnico/panel.php");
            break;
        case 'admin':
            header("Location: /admin/dashboard.php");
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
    <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
    <title>SIGRA-ETSR · Iniciar sesión</title>
    <link rel="stylesheet" href="/css/theme.css">
    <link rel="stylesheet" href="/css/estilos.css?v=8">
    <link rel="stylesheet" href="/css/login.css?v=5">
</head>
<body class="cuerpo-login">

<div class="login">

    <!-- Panel de marca: gradiente navy→aqua con la red animada (canvas).
         La animación se puede pausar; con prefers-reduced-motion queda estática.
         En móvil (<=720px) queda una franja con logo + título, sin canvas. -->
    <div class="login-marca">
        <canvas id="red" aria-hidden="true"></canvas>
        <button class="pausa" id="pausa" type="button">Pausar movimiento</button>
        <div class="texto">
            <span class="logo-pastilla">
                <img src="/img/logo-accessnet.png" alt="Logo de AccessNet">
            </span>
            <h1>SIGRA-ETSR</h1>
            <p class="frase">Sistema de gestión y relevamiento de aulas</p>
            <ul class="puntos">
                <li>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5 5.2 4l7.3 8.6L19.8 4 22 6.5 12.5 18.5z"/><rect x="3" y="19.5" width="18" height="2.5"/></svg>
                    Mapa de sala en tiempo real
                </li>
                <li>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8.8L4 20V5z"/><path d="M8 9h8M8 12h5" class="trazo"/></svg>
                    Reportes de fallas con foto
                </li>
                <li>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.7 5.6 6.3.8-4.6 4.3 1.2 6.2L12 17l-5.6 2.9 1.2-6.2L3 9.4l6.3-.8z"/></svg>
                    Seguimiento de tickets de soporte
                </li>
            </ul>
        </div>
    </div>

    <!-- Formulario -->
    <div class="login-form">
        <div class="marcas">
            <img src="/img/logo-accessnet.png" alt="Logo de AccessNet" class="logo-accessnet">
            <i></i>
            <img src="/img/logo-etsr.png" alt="Logo de la Escuela Técnica Superior de Rivera (UTU)" class="logo-etsr">
        </div>
        <h2>SIGRA-ETSR</h2>
        <p class="sub">Sistema de gestión y relevamiento de aulas</p>

        <?php if ($error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="form-login">
            <label class="campo">Usuario
                <input type="text" id="usuario" name="usuario" autocomplete="username"
                       value="<?php echo htmlspecialchars($usuario ?? ''); ?>">
            </label>
            <label class="campo">Contraseña
                <input type="password" id="contraseña" name="contraseña" autocomplete="current-password">
            </label>
            <button class="btn" type="submit">Ingresar</button>
        </form>
        <div class="pie-escudo">
            <img src="/img/logo-etsr.png" alt="Escudo de la Escuela Técnica Superior de Rivera (UTU)" class="escudo">
            <p class="pie-login">Desarrollado por AccessNet S.R.L. para la Escuela Técnica Superior de Rivera.</p>
        </div>
    </div>

</div>

<script>
/* Red animada del panel de marca: nodos aqua y blancos que derivan,
   se enlazan entre sí y sueltan paquetes. Máximo 60 nodos en
   escritorio; 0 en móvil (<=720px, el canvas ni se ve). Se pausa con
   el botón, con document.hidden y con prefers-reduced-motion. */
(function () {
    var cv = document.getElementById("red"), cx = cv.getContext("2d");
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var movil = window.matchMedia("(max-width: 720px)");
    var run = !reduce;
    var W = 0, H = 0, N = [], P = [], raf;

    function size() {
        var r = cv.getBoundingClientRect();
        if (!r.width) return;
        var d = window.devicePixelRatio || 1;
        W = r.width; H = r.height;
        cv.width = W * d; cv.height = H * d;
        cx.setTransform(d, 0, 0, d, 0, 0);
        var n = movil.matches ? 0 : Math.round(Math.min(60, W * H / 14000));
        N = [];
        for (var i = 0; i < n; i++) {
            N.push({
                x: Math.random() * W, y: Math.random() * H,
                vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25,
                blanco: Math.random() < .35   /* algunos nodos blancos, baja opacidad */
            });
        }
        P = [];
        frame(true);
    }

    function frame(once) {
        cx.clearRect(0, 0, W, H);
        N.forEach(function (a) {
            if (run && !once) {
                a.x += a.vx; a.y += a.vy;
                if (a.x < 0 || a.x > W) a.vx *= -1;
                if (a.y < 0 || a.y > H) a.vy *= -1;
            }
        });
        var L = [];
        for (var i = 0; i < N.length; i++) {
            for (var j = i + 1; j < N.length; j++) {
                var dx = N[i].x - N[j].x, dy = N[i].y - N[j].y;
                var d = Math.hypot(dx, dy);
                if (d < 150) {
                    L.push([i, j]);
                    /* Alternar enlaces aqua y blancos, ambos con opacidad baja */
                    var base = (i + j) % 2 === 0 ? "0,196,204" : "255,255,255";
                    cx.strokeStyle = "rgba(" + base + "," + (.30 * (1 - d / 150)) + ")";
                    cx.lineWidth = 1;
                    cx.beginPath(); cx.moveTo(N[i].x, N[i].y); cx.lineTo(N[j].x, N[j].y); cx.stroke();
                }
            }
        }
        if (run && !once && L.length && Math.random() < .06) {
            P.push({ l: L[Math.floor(Math.random() * L.length)], t: 0 });
        }
        P = P.filter(function (p) { return p.t < 1; });
        P.forEach(function (p) {
            p.t += .018;
            var a = N[p.l[0]], b = N[p.l[1]];
            cx.fillStyle = "rgba(0,196,204,.9)";
            cx.beginPath();
            cx.arc(a.x + (b.x - a.x) * p.t, a.y + (b.y - a.y) * p.t, 2.6, 0, 7);
            cx.fill();
        });
        N.forEach(function (a) {
            cx.fillStyle = a.blanco ? "rgba(255,255,255,.55)" : "rgba(0,196,204,.85)";
            cx.beginPath(); cx.arc(a.x, a.y, 2.4, 0, 7); cx.fill();
        });
        if (!once && run) raf = requestAnimationFrame(function () { frame(); });
    }

    document.getElementById("pausa").addEventListener("click", function () {
        run = !run;
        this.textContent = run ? "Pausar movimiento" : "Reanudar movimiento";
        if (run) frame();
    });
    window.addEventListener("resize", size);
    document.addEventListener("visibilitychange", function () {
        cancelAnimationFrame(raf);
        if (!document.hidden && run) frame();
    });
    size();
})();

/* Botón "Ingresar": al enviar, spinner + texto atenuado. El botón NO
   se deshabilita: el submit sigue su curso normal. */
(function () {
    var form = document.getElementById("form-login");
    var btn = form && form.querySelector(".btn");
    if (form && btn) {
        form.addEventListener("submit", function () {
            btn.classList.add("is-loading");
        });
    }
})();
</script>

</body>
</html>
