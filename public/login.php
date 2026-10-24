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
    <title>SIGRA-ETSR · Iniciar sesión</title>
    <link rel="stylesheet" href="/css/theme.css">
    <link rel="stylesheet" href="/css/estilos.css?v=5">
    <link rel="stylesheet" href="/css/login.css?v=2">
</head>
<body class="cuerpo-login">

<div class="login">

    <!-- Panel de marca: navy con la red animada (canvas) y el titular.
         La animación se puede pausar; con prefers-reduced-motion queda estática. -->
    <div class="login-marca">
        <canvas id="red" aria-hidden="true"></canvas>
        <button class="pausa" id="pausa" type="button">Pausar movimiento</button>
        <div class="texto">
            <h1>La sala, a la vista antes de empezar la clase.</h1>
            <p>Reportá fallas en un toque y seguí cada arreglo hasta que el equipo vuelve a funcionar.</p>
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

        <form method="POST" action="login.php">
            <label class="campo">Usuario
                <input type="text" id="usuario" name="usuario" autocomplete="username"
                       value="<?php echo htmlspecialchars($usuario ?? ''); ?>">
            </label>
            <label class="campo">Contraseña
                <input type="password" id="contraseña" name="contraseña" autocomplete="current-password">
            </label>
            <button class="btn" type="submit">Ingresar</button>
        </form>
        <p class="pie-login">Desarrollado por AccessNet S.R.L. para la Escuela Técnica Superior de Rivera.</p>
    </div>

</div>

<script>
/* Red animada del panel de marca (igual a la maqueta):
   nodos turquesa que derivan, se enlazan entre sí y sueltan
   paquetes. Se pausa con el botón; con prefers-reduced-motion
   arranca estático. */
(function () {
    var cv = document.getElementById("red"), cx = cv.getContext("2d");
    var run = !window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var W = 0, H = 0, N = [], P = [], raf;

    function size() {
        var r = cv.getBoundingClientRect();
        if (!r.width) return;
        var d = window.devicePixelRatio || 1;
        W = r.width; H = r.height;
        cv.width = W * d; cv.height = H * d;
        cx.setTransform(d, 0, 0, d, 0, 0);
        var n = Math.round(Math.min(46, W * H / 16000));
        N = [];
        for (var i = 0; i < n; i++) {
            N.push({
                x: Math.random() * W, y: Math.random() * H,
                vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25
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
                    cx.strokeStyle = "rgba(0,196,204," + (.34 * (1 - d / 150)) + ")";
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
            cx.fillStyle = "#00c4cc";
            cx.beginPath();
            cx.arc(a.x + (b.x - a.x) * p.t, a.y + (b.y - a.y) * p.t, 2.6, 0, 7);
            cx.fill();
        });
        N.forEach(function (a) {
            cx.fillStyle = "rgba(0,196,204,.85)";
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
</script>

</body>
</html>
