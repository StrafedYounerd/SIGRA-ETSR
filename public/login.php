<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/login.php — CU-01: Iniciar sesión
// ============================================================
// Implementa el pseudocódigo PROCESO Login() de la Fase 3.

require '../includes/auth.php';
require '../config/db.php';
require '../includes/clave.php';

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

        // PASO 4: verificar la contraseña contra el hash guardado.
        // Acepta bcrypt (nuevo) y SHA-256 legado (seed inicial);
        // ver includes/clave.php. Los hashes legados se reescriben
        // como bcrypt recién tras verificar también activo y rol.
        } elseif (!verificarContrasena($contraseña, $registro['contraseña'])) {
            $error = "Usuario o contraseña incorrectos";

        // PASO 5: verificar que la cuenta está activa.
        } elseif ($registro['activo'] == 0) {
            $error = "Cuenta desactivada. Contactá al administrador.";

        // PASO 6: credenciales válidas, crear la sesión.
        } else {
            // Endurecimiento: renovar el identificador de sesión tras
            // el login (mitiga session fixation). delete_old_session
            // (true) descarta el ID viejo del lado del servidor; la
            // cookie nueva se emite con los parámetros fijados en
            // auth.php antes de session_start().
            session_regenerate_id(true);

            $_SESSION['id_usuario'] = $registro['id_usuario'];
            $_SESSION['nombre']     = $registro['nombre'];
            $_SESSION['rol']        = $registro['rol'];

            // Migración a bcrypt: si el hash guardado era legado
            // (SHA-256) y la contraseña es correcta, se reescribe
            // como bcrypt AHORA, en este mismo login. Si el UPDATE
            // falla no se interrumpe el login (ver migrarHashLegado):
            // el usuario entra igual y se reintenta la próxima vez.
            if (!esHashBcrypt($registro['contraseña'])) {
                migrarHashLegado($conexion, $registro['id_usuario'], $contraseña);
            }

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
    <link rel="stylesheet" href="/css/estilos.css?v=9">
    <link rel="stylesheet" href="/css/login.css?v=8">
</head>
<body class="cuerpo-login">

<div class="login">

    <!-- Panel de marca: gradiente navy→aqua con la red animada (canvas).
         La animación se puede pausar; con prefers-reduced-motion queda estática.
         En móvil (<=720px) queda una franja con logo + título, sin canvas. -->
    <div class="login-marca">
        <canvas id="red" aria-hidden="true"></canvas>
        <button class="pausa" id="pausa" type="button" aria-pressed="false">Pausar movimiento</button>
        <div class="texto">
            <span class="logo-pastilla">
                <a href="https://accessnet.sigra-etsr.online/" target="_blank" rel="noopener">
                    <img src="/img/logo-accessnet.png" alt="Logo de AccessNet">
                </a>
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
            <a href="https://accessnet.sigra-etsr.online/" target="_blank" rel="noopener">
                <img src="/img/logo-accessnet.png" alt="Logo de AccessNet" class="logo-accessnet">
            </a>
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
        <p class="pie-login">Desarrollado por AccessNet S.R.L. para la Escuela Técnica Superior de Rivera.</p>
    </div>

</div>

<script src="/js/login-animations.js?v=11"></script>
<script>
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
