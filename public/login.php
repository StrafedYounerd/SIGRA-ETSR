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
    <title>SIGRA-ETSR · Iniciar sesión</title>
    <link rel="stylesheet" href="../css/estilos.css">
</head>
<body>

    <div class="caja">
        <h1>SIGRA-ETSR</h1>
        <p style="font-size:13px; color:#555555; margin-top:-10px;">
            Sistema de Gestión y Relevamiento de Aulas
        </p>

        <?php if ($error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario"
                   value="<?php echo htmlspecialchars($usuario ?? ''); ?>">

            <label for="contraseña">Contraseña</label>
            <input type="password" id="contraseña" name="contraseña">

            <button type="submit">Ingresar</button>
        </form>
    </div>

</body>
</html>