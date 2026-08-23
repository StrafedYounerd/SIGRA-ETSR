<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/usuarios.php — CU-08: Gestionar usuarios
// ============================================================
// ABM de usuarios. Una sola página con varias acciones, distinguidas
// por el parámetro "accion" (crear, editar, desactivar, reactivar).

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']);

$error   = "";
$exito   = "";
$accion  = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';

// Initialize variables for edit form
$edit_id = '';
$edit_nombre = '';
$edit_usuario = '';
$edit_rol = '';
$edit_activo = '';

// ============================================================
// ACCIÓN: crear (procesar el formulario de alta)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear') {

    $nombre     = trim($_POST['nombre'] ?? '');
    $usuario    = trim($_POST['usuario'] ?? '');
    $contraseña = trim($_POST['contraseña'] ?? '');
    $rol        = trim($_POST['rol'] ?? '');

    if ($nombre === '' || $usuario === '' || $contraseña === '' || $rol === '') {
        $error = "Completá todos los campos";
    } elseif (!in_array($rol, ['docente', 'tecnico', 'admin'])) {
        $error = "Rol no válido";
    } else {

        // Verificar que el nombre de usuario no esté en uso.
        $verificar = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
        $verificar->bind_param("s", $usuario);
        $verificar->execute();

        if ($verificar->get_result()->fetch_assoc()) {
            $error = "Ese nombre de usuario ya está en uso. Elegí otro.";
        } else {
            $hash = hash('sha256', $contraseña);

            $insertar = $conexion->prepare(
                "INSERT INTO usuarios (nombre, usuario, contraseña, rol, activo)
                 VALUES (?, ?, ?, ?, 1)"
            );
            $insertar->bind_param("ssss", $nombre, $usuario, $hash, $rol);
            $insertar->execute();

            header("Location: usuarios.php?msg=" . urlencode("Usuario creado correctamente"));
            exit();
        }
    }
}

// ============================================================
// ACCIÓN: editar (mostrar formulario y procesar)
// ============================================================
if ($accion === 'editar') {
    // Mostrar formulario de edición
    if (isset($_GET['id'])) {
        $edit_id = $_GET['id'];
        $consulta = $conexion->prepare("SELECT nombre, usuario, rol, activo FROM usuarios WHERE id_usuario = ?");
        $consulta->bind_param("i", $edit_id);
        $consulta->execute();
        $usuario = $consulta->get_result()->fetch_assoc();

        if (!$usuario) {
            header("Location: usuarios.php?error=" . urlencode("Usuario no encontrado"));
            exit();
        }

        $edit_nombre = $usuario['nombre'];
        $edit_usuario = $usuario['usuario'];
        $edit_rol = $usuario['rol'];
        $edit_activo = $usuario['activo'] ? '1' : '0';
    }

    // Procesar envío del formulario de edición
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'])) {
        $id_usuario     = trim($_POST['id_usuario'] ?? '');
        $nombre         = trim($_POST['nombre'] ?? '');
        $usuario        = trim($_POST['usuario'] ?? '');
        $contraseña     = trim($_POST['contraseña'] ?? '');
        $rol            = trim($_POST['rol'] ?? '');
        $activo         = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '' || $usuario === '' || $rol === '') {
            $error = "Completá todos los campos";
        } elseif (!in_array($rol, ['docente', 'tecnico', 'admin'])) {
            $error = "Rol no válido";
        } else {
            // Verificar que el nombre de usuario no esté en uso por otro usuario
            $verificar = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ? AND id_usuario != ?");
            $verificar->bind_param("si", $usuario, $id_usuario);
            $verificar->execute();

            if ($verificar->get_result()->fetch_assoc()) {
                $error = "Ese nombre de usuario ya está en uso. Elegí otro.";
            } else {
                // Si se cambió la contraseña, hashearla; sino mantener la actual
                if ($contraseña !== '') {
                    $hash = hash('sha256', $contraseña);
                    $actualizar = $conexion->prepare(
                        "UPDATE usuarios SET nombre = ?, usuario = ?, contraseña = ?, rol = ?, activo = ?
                         WHERE id_usuario = ?"
                    );
                    $actualizar->bind_param("ssssii", $nombre, $usuario, $hash, $rol, $activo, $id_usuario);
                } else {
                    $actualizar = $conexion->prepare(
                        "UPDATE usuarios SET nombre = ?, usuario = ?, rol = ?, activo = ?
                         WHERE id_usuario = ?"
                    );
                    $actualizar->bind_param("sssii", $nombre, $usuario, $rol, $activo, $id_usuario);
                }

                $actualizar->execute();

                header("Location: usuarios.php?msg=" . urlencode("Usuario actualizado correctamente"));
                exit();
            }
        }
    }
}

// ============================================================
// ACCIÓN: desactivar
// Implementa la regla de negocio de la ESRE (CU-08, curso
// alternativo 2): no se puede desactivar el único admin del sistema.
// ============================================================
if ($accion === 'desactivar' && isset($_GET['id'])) {

    $id_usuario = $_GET['id'];

    // Buscar el usuario para saber su rol antes de tocar nada.
    $consulta = $conexion->prepare("SELECT rol FROM usuarios WHERE id_usuario = ?");
    $consulta->bind_param("i", $id_usuario);
    $consulta->execute();
    $usuario_objetivo = $consulta->get_result()->fetch_assoc();

    if (!$usuario_objetivo) {
        header("Location: usuarios.php?error=" . urlencode("El usuario no existe"));
        exit();
    }

    // Si el usuario a desactivar es admin, contar cuántos admins
    // activos quedan en el sistema. Si es el último, bloquear.
    if ($usuario_objetivo['rol'] === 'admin') {
        $contar = $conexion->query(
            "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin' AND activo = 1"
        )->fetch_assoc();

        if ($contar['total'] <= 1) {
            header("Location: usuarios.php?error=" . urlencode(
                "No se puede desactivar: es el único administrador activo del sistema"
            ));
            exit();
        }
    }

    // Si pasó la validación, desactivar.
    $desactivar = $conexion->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = ?");
    $desactivar->bind_param("i", $id_usuario);
    $desactivar->execute();

    header("Location: usuarios.php?msg=" . urlencode("Usuario desactivado"));
    exit();
}

// ============================================================
// ACCIÓN: eliminar (eliminación definitiva)
// NOTA: Solo se puede eliminar si no tiene tickets asociados
// ============================================================
if ($accion === 'eliminar' && isset($_GET['id'])) {

    $id_usuario = $_GET['id'];

    // Primero verificar que el usuario existe
    $consulta = $conexion->prepare("SELECT nombre, rol FROM usuarios WHERE id_usuario = ?");
    $consulta->bind_param("i", $id_usuario);
    $consulta->execute();
    $usuario_objetivo = $consulta->get_result()->fetch_assoc();

    if (!$usuario_objetivo) {
        header("Location: usuarios.php?error=" . urlencode("El usuario no existe"));
        exit();
    }

    // REGLA DE NEGOCIO: No se puede eliminar el último admin activo
    if ($usuario_objetivo['rol'] === 'admin') {
        $contar = $conexion->query(
            "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin' AND activo = 1"
        )->fetch_assoc();

        if ($contar['total'] <= 1) {
            header("Location: usuarios.php?error=" . urlencode(
                "No se puede eliminar: es el único administrador activo del sistema"
            ));
            exit();
        }
    }

    // REGLA DE NEGOCIO: No se puede eliminar si tiene tickets asociados
    $tickets_asociados = $conexion->prepare(
        "SELECT COUNT(*) AS total FROM tickets WHERE id_docente = ?"
    );
    $tickets_asociados->bind_param("i", $id_usuario);
    $tickets_asociados->execute();
    $total_tickets = $tickets_asociados->get_result()->fetch_assoc()['total'];

    if ($total_tickets > 0) {
        header("Location: usuarios.php?error=" . urlencode(
            "No se puede eliminar: el usuario tiene $total_tickets ticket(s) asociado(s)"
        ));
        exit();
    }

    // Si pasó todas las validaciones, eliminar definitivamente
    $eliminar = $conexion->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $eliminar->bind_param("i", $id_usuario);
    $eliminar->execute();

    header("Location: usuarios.php?msg=" . urlencode("Usuario eliminado correctamente"));
    exit();
}

// ============================================================
// ACCIÓN: reactivar
// ============================================================
if ($accion === 'reactivar' && isset($_GET['id'])) {
    $id_usuario = $_GET['id'];

    $reactivar = $conexion->prepare("UPDATE usuarios SET activo = 1 WHERE id_usuario = ?");
    $reactivar->bind_param("i", $id_usuario);
    $reactivar->execute();

    header("Location: usuarios.php?msg=" . urlencode("Usuario reactivado"));
    exit();
}

// ── Mensajes que pueden venir de una redirección anterior ──
$mensaje_ok    = $_GET['msg'] ?? '';
$mensaje_error = $_GET['error'] ?? '';

// ── Traer todos los usuarios para la tabla principal ──
$lista_usuarios = $conexion->query(
    "SELECT id_usuario, nombre, usuario, rol, activo FROM usuarios ORDER BY nombre ASC"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Gestionar usuarios</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Administrador)
        <a href="dashboard.php">Volver al panel</a>
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 700px;">
        <h1>Gestionar usuarios</h1>

        <?php if ($mensaje_ok !== ''): ?>
            <div class="mensaje-ok"><?php echo htmlspecialchars($mensaje_ok); ?></div>
        <?php endif; ?>
        <?php if ($mensaje_error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Formulario de edición (oculto por defecto, se muestra al editar) -->
        <?php if ($edit_id): ?>
        <h2>Editar usuario</h2>
        <form method="POST" action="usuarios.php">
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id_usuario" value="<?php echo $edit_id; ?>">

            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($edit_nombre); ?>" required>

            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" value="<?php echo htmlspecialchars($edit_usuario); ?>" required>

            <label for="contraseña">Contraseña (dejar vacío para no cambiar)</label>
            <input type="password" id="contraseña" name="contraseña">

            <label for="rol">Rol</label>
            <select id="rol" name="rol" required>
                <option value="">-- Elegí un rol --</option>
                <option value="docente" <?php echo $edit_rol === 'docente' ? 'selected' : ''; ?>>Docente</option>
                <option value="tecnico" <?php echo $edit_rol === 'tecnico' ? 'selected' : ''; ?>>Técnico</option>
                <option value="admin" <?php echo $edit_rol === 'admin' ? 'selected' : ''; ?>>Administrador</option>
            </select>

            <label>
                <input type="checkbox" name="activo" value="1" <?php echo $edit_activo == '1' ? 'checked' : ''; ?>>
                Usuario activo
            </label>

            <button type="submit">Actualizar usuario</button>
            <a href="usuarios.php" style="margin-left: 10px;">Cancelar</a>
        </form>
        <hr style="margin: 20px 0;">
        <?php endif; ?>

        <h2>Usuarios existentes</h2>
        <table>
            <tr>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
            <?php while ($u = $lista_usuarios->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                    <td><?php echo htmlspecialchars($u['rol']); ?></td>
                    <td><?php echo $u['activo'] ? 'Activo' : 'Desactivado'; ?></td>
                    <td>
                        <a href="usuarios.php?accion=editar&id=<?php echo $u['id_usuario']; ?>"
                           title="Editar usuario">Editar</a>
                        <?php if ($u['activo']): ?>
                            <a href="usuarios.php?accion=desactivar&id=<?php echo $u['id_usuario']; ?>"
                               onclick="return confirm('¿Desactivar a <?php echo htmlspecialchars($u['nombre']); ?>?');"
                               style="margin-left: 10px;">
                                Desactivar
                            </a>
                        <?php else: ?>
                            <a href="usuarios.php?accion=reactivar&id=<?php echo $u['id_usuario']; ?>"
                               style="margin-left: 10px;">
                                Reactivar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <h2 style="margin-top: 25px;">Crear nuevo usuario</h2>
        <form method="POST" action="usuarios.php">
            <input type="hidden" name="accion" value="crear">

            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre">

            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario">

            <label for="contraseña">Contraseña</label>
            <input type="password" id="contraseña" name="contraseña">

            <label for="rol">Rol</label>
            <select id="rol" name="rol">
                <option value="">-- Elegí un rol --</option>
                <option value="docente">Docente</option>
                <option value="tecnico">Técnico</option>
                <option value="admin">Administrador</option>
            </select>

            <button type="submit">Crear usuario</button>
        </form>
    </div>

</body>
</html>