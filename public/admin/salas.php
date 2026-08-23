<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/salas.php — CU-09: Gestionar salas y dispositivos
// ============================================================
// ABM de salas (alta, desactivación) y gestión de dispositivos dentro
// de cada sala. Una sola página con varias acciones, igual patrón
// que usuarios.php.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']);

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';

// Initialize variables for edit forms
$edit_sala_id = '';
$edit_sala_nombre = '';
$edit_sala_descripcion = '';
$edit_sala_activa = '';
$edit_sala_has_switch = false;
$edit_sala_has_tv = false;

$edit_pc_id = '';
$edit_pc_id_sala = '';
$edit_pc_etiqueta = '';
$edit_pc_fila = '';
$edit_pc_posicion = '';
$edit_pc_estado_actual = '';
$edit_pc_tipo_equipo = '';

// Handle AJAX request for equipment counts
if ($accion === 'get_equipos_count') {
    header('Content-Type: application/json');
    $id_sala = $_GET['id_sala'] ?? '';

    if ($id_sala === '') {
        echo json_encode(['error' => 'ID de sala requerido']);
        exit();
    }

    // Count Switches
    $q_switch = $conexion->prepare("SELECT COUNT(*) AS total FROM pcs WHERE id_sala = ? AND tipo_equipo = 'Switch'");
    $q_switch->bind_param("i", $id_sala);
    $q_switch->execute();
    $switch_count = $q_switch->get_result()->fetch_assoc()['total'];

    // Count TVs and Projectors
    $q_tv = $conexion->prepare("SELECT COUNT(*) AS total FROM pcs WHERE id_sala = ? AND tipo_equipo IN ('TV', 'Proyector')");
    $q_tv->bind_param("i", $id_sala);
    $q_tv->execute();
    $tv_proyector_count = $q_tv->get_result()->fetch_assoc()['total'];

    echo json_encode([
        'switch_count' => (int)$switch_count,
        'tv_proyector_count' => (int)$tv_proyector_count
    ]);
    exit();
}

// ============================================================
// ACCIÓN: editar_sala
// ============================================================
if ($accion === 'editar_sala') {
    // Mostrar formulario de edición
    if (isset($_GET['id'])) {
        $edit_sala_id = $_GET['id'];
        $consulta = $conexion->prepare("SELECT * FROM salas WHERE id_sala = ?");
        $consulta->bind_param("i", $edit_sala_id);
        $consulta->execute();
        $sala = $consulta->get_result()->fetch_assoc();

        if (!$sala) {
            header("Location: salas.php?error=" . urlencode("Sala no encontrada"));
            exit();
        }

        $edit_sala_nombre = $sala['nombre_sala'];
        $edit_sala_descripcion = isset($sala['descripcion']) ? $sala['descripcion'] : '';
        $edit_sala_activa = $sala['activa'] ? '1' : '0';

        // Check Switch as per user request
        $q_sw = $conexion->prepare("SELECT id_pc FROM pcs WHERE id_sala = ? AND tipo_equipo = 'Switch'");
        $q_sw->bind_param("i", $edit_sala_id);
        $q_sw->execute();
        $edit_sala_has_switch = ($q_sw->get_result()->num_rows > 0);
        $q_sw->close();

        // Check TV as per user request
        $q_tv = $conexion->prepare("SELECT id_pc FROM pcs WHERE id_sala = ? AND tipo_equipo = 'TV'");
        $q_tv->bind_param("i", $edit_sala_id);
        $q_tv->execute();
        $edit_sala_has_tv = ($q_tv->get_result()->num_rows > 0);
        $q_tv->close();
    }

    // Procesar envío del formulario de edición
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_sala'])) {
        $id_sala      = trim($_POST['id_sala'] ?? '');
        $nombre_sala  = trim($_POST['nombre_sala'] ?? '');
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $activa       = isset($_POST['activa']) ? 1 : 0;
        $include_switch = isset($_POST['has_switch']) ? 1 : 0;
        $include_tv = isset($_POST['has_tv']) ? 1 : 0;

        if ($nombre_sala === '') {
            $error = "El nombre de la sala es obligatorio";
        } else {
            // Verificar que el nombre de sala no esté en uso por otra sala
            $verificar = $conexion->prepare("SELECT id_sala FROM salas WHERE nombre_sala = ? AND id_sala != ?");
            $verificar->bind_param("si", $nombre_sala, $id_sala);
            $verificar->execute();

            if ($verificar->get_result()->fetch_assoc()) {
                $error = "Ya existe una sala con ese nombre";
            } else {
                $actualizar = $conexion->prepare(
                    "UPDATE salas SET nombre_sala = ?, descripcion = ?, activa = ? WHERE id_sala = ?"
                );
                $actualizar->bind_param("ssii", $nombre_sala, $descripcion, $activa, $id_sala);
                $actualizar->execute();

                // Handle Switch - add if checked and doesn't exist, remove if unchecked and exists
                if ($include_switch) {
                    // Check if switch already exists
                    $check_switch = $conexion->prepare("SELECT COUNT(*) AS total FROM pcs WHERE id_sala = ? AND tipo_equipo = 'Switch'");
                    $check_switch->bind_param("i", $id_sala);
                    $check_switch->execute();
                    $switch_exists = $check_switch->get_result()->fetch_assoc()['total'] > 0;
                    $check_switch->close();

                    if (!$switch_exists) {
                        // Create switch
                        $estado_actual_default = 'sin_eval';
                        $fila = 0;
                        $posicion = 0;
                        $etiqueta = 'SW-' . $nombre_sala;
                        $tipo_equipo = 'Switch';

                        $insertar_switch = $conexion->prepare(
                            "INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $insertar_switch->bind_param("issiis", $id_sala, $etiqueta, $tipo_equipo, $fila, $posicion, $estado_actual_default);
                        $insertar_switch->execute();
                        $insertar_switch->close();
                    }
                } else {
                    // Remove switch if exists
                    $eliminar_switch = $conexion->prepare("DELETE FROM pcs WHERE id_sala = ? AND tipo_equipo = 'Switch'");
                    $eliminar_switch->bind_param("i", $id_sala);
                    $eliminar_switch->execute();
                    $eliminar_switch->close();
                }

                // Handle TV - add if checked and doesn't exist, remove if unchecked and exists
                if ($include_tv) {
                    // Check if TV already exists
                    $check_tv = $conexion->prepare("SELECT COUNT(*) AS total FROM pcs WHERE id_sala = ? AND tipo_equipo IN ('TV', 'Proyector')");
                    $check_tv->bind_param("i", $id_sala);
                    $check_tv->execute();
                    $tv_exists = $check_tv->get_result()->fetch_assoc()['total'] > 0;
                    $check_tv->close();

                    if (!$tv_exists) {
                        // Create TV
                        $estado_actual_default = 'sin_eval';
                        $fila = 0;
                        $posicion = 0;
                        $etiqueta = 'TV-' . $nombre_sala;
                        $tipo_equipo = 'TV';

                        $insertar_tv = $conexion->prepare(
                            "INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $insertar_tv->bind_param("issiis", $id_sala, $etiqueta, $tipo_equipo, $fila, $posicion, $estado_actual_default);
                        $insertar_tv->execute();
                        $insertar_tv->close();
                    }
                } else {
                    // Remove TV/Proyector if exists
                    $eliminar_tv = $conexion->prepare("DELETE FROM pcs WHERE id_sala = ? AND tipo_equipo IN ('TV', 'Proyector')");
                    $eliminar_tv->bind_param("i", $id_sala);
                    $eliminar_tv->execute();
                    $eliminar_tv->close();
                }

                header("Location: salas.php?msg=" . urlencode("Sala actualizada correctamente"));
                exit();
            }
        }
    }
}

// ============================================================
// ACCIÓN: editar_pc
// ============================================================
if ($accion === 'editar_pc') {
    // Mostrar formulario de edición
    if (isset($_GET['id'])) {
        $edit_pc_id = $_GET['id'];
        $consulta = $conexion->prepare("SELECT id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual FROM pcs WHERE id_pc = ?");
        $consulta->bind_param("i", $edit_pc_id);
        $consulta->execute();
        $pc = $consulta->get_result()->fetch_assoc();

        if (!$pc) {
            header("Location: salas.php?error=" . urlencode("PC no encontrada"));
            exit();
        }

        $edit_pc_id_sala = $pc['id_sala'];
        $edit_pc_etiqueta = $pc['etiqueta'];
        $edit_pc_tipo_equipo = $pc['tipo_equipo'];
        $edit_pc_fila = $pc['fila'];
        $edit_pc_posicion = $pc['posicion'];
        $edit_pc_estado_actual = $pc['estado_actual'];
    }

    // Procesar envío del formulario de edición
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pc'])) {
        $id_pc         = trim($_POST['id_pc'] ?? '');
        $id_sala       = trim($_POST['id_sala'] ?? '');
        $etiqueta      = trim($_POST['etiqueta'] ?? '');
        $fila          = trim($_POST['fila'] ?? '');
        $posicion      = trim($_POST['posicion'] ?? '');
        $tipo_equipo = trim($_POST['tipo_equipo'] ?? '');
        $fila          = trim($_POST['fila'] ?? '');
        $posicion      = trim($_POST['posicion'] ?? '');
        $estado_actual = trim($_POST['estado_actual'] ?? '');

        if ($id_sala === '' || $etiqueta === '' || $tipo_equipo === '') {
            $error = "Completá todos los campos del equipo";
        } else {
            // Default values for fila and posicion
            $filaValue = 0;
            $posicionValue = 0;

            // Validar fila y posicion solo si el tipo es PC
            if ($tipo_equipo === 'PC') {
                $fila = trim($_POST['fila'] ?? '');
                $posicion = trim($_POST['posicion'] ?? '');
                if ($fila === '' || $posicion === '') {
                    $error = "Para PCs, completá fila y posición";
                } else {
                    // Convert to integer for database
                    $filaValue = intval($fila);
                    $posicionValue = intval($posicion);
                }
            } else {
                // Para non-PCs, usar 0 como valores por defecto
                $filaValue = 0;
                $posicionValue = 0;
            }

            if (!empty($error)) {
                // Error already set
            } elseif (!in_array($estado_actual, ['funcional', 'falla', 'sin_eval'])) {
                $error = "Estado del equipo no válido";
            } else {
                // Verificar que la sala exista y esté activa (opcional, pero buena práctica)
                $check_sala = $conexion->prepare("SELECT id_sala FROM salas WHERE id_sala = ? AND activa = 1");
                $check_sala->bind_param("i", $id_sala);
                $check_sala->execute();
                if (!$check_sala->get_result()->fetch_assoc()) {
                    $error = "La sala seleccionada no existe o está desactivada";
                } else {
                    // Use try-catch for better error handling
                    try {
                        $actualizar = $conexion->prepare(
                            "UPDATE pcs SET id_sala = ?, etiqueta = ?, tipo_equipo = ?, fila = ?, posicion = ?, estado_actual = ? WHERE id_pc = ?"
                        );
                        if (!$actualizar) {
                            throw new Exception("Error en la preparación de la consulta: " . $conexion->error);
                        }

                        $estado_actual_update = $estado_actual;
                        $actualizar->bind_param("issiis", $id_sala, $etiqueta, $tipo_equipo, $filaValue, $posicionValue, $estado_actual_update);
                        if (!$actualizar->execute()) {
                            throw new Exception("Error en la ejecución de la consulta: " . $actualizar->error);
                        }

                        header("Location: salas.php?msg=" . urlencode("Equipo actualizado correctamente"));
                        exit();
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

// ============================================================
// ACCIÓN: eliminar_pc
// Eliminación segura con verificación de integridad referencial
// ============================================================
if ($accion === 'eliminar_pc' && isset($_GET['id'])) {
    $id_pc = $_GET['id'];

    // Verificar si la PC tiene tickets activos (pendientes o en reparación)
    $consulta = $conexion->prepare(
        "SELECT COUNT(*) AS total FROM tickets WHERE id_pc = ? AND estado IN ('pendiente', 'en_reparacion')"
    );
    $consulta->bind_param("i", $id_pc);
    $consulta->execute();
    $tickets_activos = $consulta->get_result()->fetch_assoc()['total'];

    if ($tickets_activos > 0) {
        header("Location: salas.php?error=" . urlencode(
            "No se puede eliminar: la PC tiene $tickets_activos ticket(s) activo(s)"
        ));
        exit();
    }

    // Si no hay tickets activos, eliminar la PC
    $eliminar = $conexion->prepare("DELETE FROM pcs WHERE id_pc = ?");
    $eliminar->bind_param("i", $id_pc);
    $eliminar->execute();

    header("Location: salas.php?msg=" . urlencode("PC eliminada correctamente"));
    exit();
}

// ============================================================
// ACCIÓN: crear_sala
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_sala') {

    $nombre_sala    = trim($_POST['nombre_sala'] ?? '');
    $descripcion    = trim($_POST['descripcion'] ?? '');
    $include_switch = isset($_POST['include_switch']) ? 1 : 0;
    $include_tv     = isset($_POST['include_tv']) ? 1 : 0;

    if ($nombre_sala === '') {
        header("Location: salas.php?error=" . urlencode("El nombre de la sala es obligatorio"));
        exit();
    }

    // Verificar que no exista ya una sala con ese nombre.
    $verificar = $conexion->prepare("SELECT id_sala FROM salas WHERE nombre_sala = ?");
    $verificar->bind_param("s", $nombre_sala);
    $verificar->execute();

    if ($verificar->get_result()->fetch_assoc()) {
        header("Location: salas.php?error=" . urlencode("Ya existe una sala con ese nombre"));
        exit();
    }

    $insertar = $conexion->prepare(
        "INSERT INTO salas (nombre_sala, descripcion, activa) VALUES (?, ?, 1)"
    );
    $insertar->bind_param("ss", $nombre_sala, $descripcion);
    $insertar->execute();
    $id_sala_nueva = $conexion->insert_id;

    // Si se marcaron los checkboxes, dar de alta el Switch y/o TV de una vez
    // (mismo criterio que usa la edición de sala).
    if ($include_switch) {
        $fila = 0; $posicion = 0;
        $etiqueta = 'SW-' . $nombre_sala;
        $tipo_equipo = 'Switch';
        $estado_default = 'sin_eval';
        $ins_sw = $conexion->prepare(
            "INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins_sw->bind_param("issiis", $id_sala_nueva, $etiqueta, $tipo_equipo, $fila, $posicion, $estado_default);
        $ins_sw->execute();
    }

    if ($include_tv) {
        $fila = 0; $posicion = 0;
        $etiqueta = 'TV-' . $nombre_sala;
        $tipo_equipo = 'TV';
        $estado_default = 'sin_eval';
        $ins_tv = $conexion->prepare(
            "INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins_tv->bind_param("issiis", $id_sala_nueva, $etiqueta, $tipo_equipo, $fila, $posicion, $estado_default);
        $ins_tv->execute();
    }

    header("Location: salas.php?msg=" . urlencode("Sala creada correctamente"));
    exit();
}

// ============================================================
// ACCIÓN: desactivar_sala
// Implementa la regla de negocio: no se puede eliminar/desactivar
// una sala que tiene tickets activos en alguna de sus PCs.
// ============================================================
if ($accion === 'desactivar_sala' && isset($_GET['id'])) {

    $id_sala = $_GET['id'];

    // Contar tickets activos de cualquier PC que pertenezca a esta sala.
    $consulta = $conexion->prepare(
        "SELECT COUNT(*) AS total
         FROM tickets t
         INNER JOIN pcs p ON t.id_pc = p.id_pc
         WHERE p.id_sala = ? AND t.estado IN ('pendiente', 'en_reparacion')"
    );
    $consulta->bind_param("i", $id_sala);
    $consulta->execute();
    $tickets_activos = $consulta->get_result()->fetch_assoc()['total'];

    if ($tickets_activos > 0) {
        header("Location: salas.php?error=" . urlencode(
            "No se puede desactivar: la sala tiene $tickets_activos ticket(s) activo(s)"
        ));
        exit();
    }

    $desactivar = $conexion->prepare("UPDATE salas SET activa = 0 WHERE id_sala = ?");
    $desactivar->bind_param("i", $id_sala);
    $desactivar->execute();

    header("Location: salas.php?msg=" . urlencode("Sala desactivada"));
    exit();
}

// ============================================================
// ACCIÓN: reactivar_sala
// ============================================================
if ($accion === 'reactivar_sala' && isset($_GET['id'])) {
    $id_sala = $_GET['id'];

    $reactivar = $conexion->prepare("UPDATE salas SET activa = 1 WHERE id_sala = ?");
    $reactivar->bind_param("i", $id_sala);
    $reactivar->execute();

    header("Location: salas.php?msg=" . urlencode("Sala reactivada"));
    exit();
}

// ============================================================
// ACCIÓN: crear_pc
// Alta de una PC nueva dentro de una sala existente.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_pc') {

    $id_sala     = trim($_POST['id_sala'] ?? '');
    $etiqueta    = trim($_POST['etiqueta'] ?? '');
    $tipo_equipo = trim($_POST['tipo_equipo'] ?? '');

    // Default values for fila and posicion
    $fila = 0;
    $posicion = 0;

    // Validar fila y posicion solo si el tipo es PC
    if ($tipo_equipo === 'PC') {
        $fila = trim($_POST['fila'] ?? '');
        $posicion = trim($_POST['posicion'] ?? '');
        if ($fila === '' || $posicion === '') {
            header("Location: salas.php?error=" . urlencode("Para PCs, completá fila y posición"));
            exit();
        }
        // Convert to integer for database
        $fila = intval($fila);
        $posicion = intval($posicion);
    }

    if ($id_sala === '' || $etiqueta === '' || $tipo_equipo === '') {
        header("Location: salas.php?error=" . urlencode("Completá todos los campos del equipo"));
        exit();
    }

    // Validar que el tipo de equipo sea válido
    if (!in_array($tipo_equipo, ['PC', 'Switch', 'TV', 'Proyector'])) {
        header("Location: salas.php?error=" . urlencode("Tipo de equipo no válido"));
        exit();
    }

    // Validar límite de Switches y TVs por sala (solo 1 de cada tipo permitido)
    if ($tipo_equipo === 'Switch') {
        $check = $conexion->prepare("SELECT COUNT(*) AS total FROM pcs WHERE id_sala = ? AND tipo_equipo = 'Switch'");
        $check->bind_param("i", $id_sala);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        if ($result['total'] > 0) {
            header("Location: salas.php?error=" . urlencode("La sala ya cuenta con un Switch asignado"));
            exit();
        }
    } elseif ($tipo_equipo === 'TV' || $tipo_equipo === 'Proyector') {
        $check = $conexion->prepare("SELECT COUNT(*) AS total FROM pcs WHERE id_sala = ? AND tipo_equipo IN ('TV', 'Proyector')");
        $check->bind_param("i", $id_sala);
        $check->execute();
        $result = $check->get_result()->fetch_assoc();
        if ($result['total'] > 0) {
            header("Location: salas.php?error=" . urlencode("La sala ya cuenta con un TV o Proyector asignado"));
            exit();
        }
    }

    // Use try-catch for better error handling
    try {
        $insertar = $conexion->prepare(
            "INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$insertar) {
            throw new Exception("Error en la preparación de la consulta: " . $conexion->error);
        }

        $estado_actual_default = 'sin_eval';
        $insertar->bind_param("issiis", $id_sala, $etiqueta, $tipo_equipo, $fila, $posicion, $estado_actual_default);
        if (!$insertar->execute()) {
            throw new Exception("Error en la ejecución de la consulta: " . $insertar->error);
        }

        header("Location: salas.php?msg=" . urlencode("Equipo agregado correctamente"));
        exit();
    } catch (Exception $e) {
        header("Location: salas.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// ── Mensajes de redirección anterior ──
$mensaje_ok    = $_GET['msg'] ?? '';
$mensaje_error = $_GET['error'] ?? '';

// ── Traer todas las salas con su cantidad de PCs ──
$lista_salas = $conexion->query(
    "SELECT s.id_sala, s.nombre_sala, s.descripcion, s.activa,
            COUNT(p.id_pc) AS cantidad_pcs
     FROM salas s
     LEFT JOIN pcs p ON p.id_sala = s.id_sala
     GROUP BY s.id_sala
     ORDER BY s.nombre_sala ASC"
);

// ── Salas activas, para los desplegables de sala (alta y edición de equipos) ──
// fetch_all() en vez de un resultado "vivo" porque este mismo listado se
// recorre dos veces en la página (form de editar equipo y form de agregar PC).
$salas_para_pc = $conexion->query("SELECT id_sala, nombre_sala FROM salas WHERE activa = 1")->fetch_all(MYSQLI_ASSOC);

// ── Todos los dispositivos (PC, Switch, TV, Proyector) de todas las salas ──
$lista_dispositivos = $conexion->query(
    "SELECT p.id_pc, p.etiqueta, p.tipo_equipo, p.fila, p.posicion, p.estado_actual,
            s.id_sala, s.nombre_sala
     FROM pcs p
     INNER JOIN salas s ON s.id_sala = p.id_sala
     ORDER BY s.nombre_sala ASC, p.tipo_equipo ASC, p.fila ASC, p.posicion ASC"
);

// Etiquetas legibles para el estado, mismo criterio que la leyenda del mapa de sala.
$etiquetas_estado = [
    'funcional' => 'Funcional',
    'falla'     => 'Con falla',
    'sin_eval'  => 'No evaluada',
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Gestionar salas y dispositivos</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Administrador)
        <a href="dashboard.php">Volver al panel</a>
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 700px;">
        <h1>Gestionar salas y dispositivos</h1>

        <?php if ($mensaje_ok !== ''): ?>
            <div class="mensaje-ok"><?php echo htmlspecialchars($mensaje_ok); ?></div>
        <?php endif; ?>
        <?php if ($mensaje_error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>

        <h2>Salas existentes</h2>
        <table>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Dispositivos cargados</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
            <?php while ($s = $lista_salas->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['nombre_sala']); ?></td>
                    <td><?php echo htmlspecialchars($s['descripcion'] ?? '—'); ?></td>
                    <td><?php echo $s['cantidad_pcs']; ?></td>
                    <td><?php echo $s['activa'] ? 'Activa' : 'Desactivada'; ?></td>
                    <td>
                        <?php if ($s['activa']): ?>
                            <a href="salas.php?accion=editar_sala&id=<?php echo $s['id_sala']; ?>">Editar</a> |
                            <a href="salas.php?accion=desactivar_sala&id=<?php echo $s['id_sala']; ?>"
                               onclick="return confirm('¿Desactivar la sala <?php echo htmlspecialchars($s['nombre_sala']); ?>?');">
                                Desactivar
                            </a>
                        <?php else: ?>
                            <a href="salas.php?accion=editar_sala&id=<?php echo $s['id_sala']; ?>">Editar</a> |
                            <a href="salas.php?accion=reactivar_sala&id=<?php echo $s['id_sala']; ?>">
                                Reactivar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <?php if ($edit_sala_id !== ''): ?>
        <h2 style="margin-top: 25px;">Editar sala</h2>
        <form method="POST" action="salas.php">
            <input type="hidden" name="accion" value="editar_sala">
            <input type="hidden" name="id_sala" value="<?php echo $edit_sala_id; ?>">

            <label for="nombre_sala">Nombre de la sala</label>
            <input type="text" id="nombre_sala" name="nombre_sala" value="<?php echo htmlspecialchars($edit_sala_nombre); ?>" required>

            <label for="descripcion">Descripción (opcional)</label>
            <textarea id="descripcion" name="descripcion" rows="2"><?php echo htmlspecialchars($edit_sala_descripcion); ?></textarea>

            <div style="margin-top: 15px;">
                <label for="activa">Sala Activa</label>
                <input type="checkbox" id="activa" name="activa" value="1" <?php echo $edit_sala_activa == '1' ? 'checked' : ''; ?>>
            </div>

            <div style="margin-top: 15px;">
                <label for="has_switch">Incluir Switch de Red</label>
                <input type="checkbox" id="has_switch" name="has_switch" value="1" <?php echo $edit_sala_has_switch ? 'checked' : ''; ?>>
            </div>
            <div style="margin-top: 5px;">
                <label for="has_tv">Incluir TV / Proyector</label>
                <input type="checkbox" id="has_tv" name="has_tv" value="1" <?php echo $edit_sala_has_tv ? 'checked' : ''; ?>>
            </div>

            <button type="submit">Actualizar sala</button>
        </form>
        <?php endif; ?>

        <h2 style="margin-top: 25px;">Crear nueva sala</h2>
        <form method="POST" action="salas.php">
            <input type="hidden" name="accion" value="crear_sala">

            <label for="nombre_sala">Nombre de la sala</label>
            <input type="text" id="nombre_sala" name="nombre_sala" placeholder="Ej: Sala 305" required>

            <label for="descripcion">Descripción (opcional)</label>
            <textarea id="descripcion" name="descripcion" rows="2"></textarea>

            <div style="margin-top: 15px;">
                <label for="include_switch">Incluir Switch de Red</label>
                <input type="checkbox" id="include_switch" name="include_switch" value="1">
            </div>
            <div style="margin-top: 5px;">
                <label for="include_tv">Incluir TV / Proyector</label>
                <input type="checkbox" id="include_tv" name="include_tv" value="1">
            </div>

            <button type="submit">Crear sala</button>
        </form>

        <h2 style="margin-top: 25px;">Dispositivos existentes</h2>
        <table>
            <tr>
                <th>Sala</th>
                <th>Etiqueta</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
            <?php if ($lista_dispositivos->num_rows === 0): ?>
                <tr><td colspan="5">Todavía no hay dispositivos cargados.</td></tr>
            <?php endif; ?>
            <?php while ($d = $lista_dispositivos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($d['nombre_sala']); ?></td>
                    <td><?php echo htmlspecialchars($d['etiqueta']); ?></td>
                    <td><?php echo htmlspecialchars($d['tipo_equipo']); ?></td>
                    <td><?php echo $etiquetas_estado[$d['estado_actual']] ?? htmlspecialchars($d['estado_actual']); ?></td>
                    <td>
                        <a href="salas.php?accion=editar_pc&id=<?php echo $d['id_pc']; ?>">Editar</a> |
                        <a href="salas.php?accion=eliminar_pc&id=<?php echo $d['id_pc']; ?>"
                           onclick="return confirm('¿Eliminar el equipo <?php echo htmlspecialchars($d['etiqueta']); ?> de forma definitiva? Esta acción no se puede deshacer.');">
                            Eliminar
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <?php if ($edit_pc_id !== ''): ?>
        <h2 style="margin-top: 25px;">Editar equipo</h2>
        <form method="POST" action="salas.php">
            <input type="hidden" name="accion" value="editar_pc">
            <input type="hidden" name="id_pc" value="<?php echo $edit_pc_id; ?>">
            <input type="hidden" name="tipo_equipo" value="<?php echo htmlspecialchars($edit_pc_tipo_equipo); ?>">

            <label for="edit_id_sala">Sala</label>
            <select id="edit_id_sala" name="id_sala">
                <?php foreach ($salas_para_pc as $s): ?>
                    <option value="<?php echo $s['id_sala']; ?>" <?php echo ($s['id_sala'] == $edit_pc_id_sala) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nombre_sala']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Tipo de equipo</label>
            <p style="margin: 4px 0 12px 0;">
                <?php echo htmlspecialchars($edit_pc_tipo_equipo); ?>
                <?php if ($edit_pc_tipo_equipo !== 'PC'): ?>
                    <span style="font-size:12px; color:#555555;">
                        (para dar de baja este <?php echo htmlspecialchars($edit_pc_tipo_equipo); ?>, desmarcá el
                        checkbox correspondiente en "Editar sala")
                    </span>
                <?php endif; ?>
            </p>

            <label for="edit_etiqueta">Etiqueta del equipo</label>
            <input type="text" id="edit_etiqueta" name="etiqueta" value="<?php echo htmlspecialchars($edit_pc_etiqueta); ?>" required>

            <?php if ($edit_pc_tipo_equipo === 'PC'): ?>
                <label for="edit_fila">Fila (posición lógica en el mapa)</label>
                <input type="text" id="edit_fila" name="fila" value="<?php echo htmlspecialchars($edit_pc_fila); ?>" required>

                <label for="edit_posicion">Posición dentro de la fila</label>
                <input type="text" id="edit_posicion" name="posicion" value="<?php echo htmlspecialchars($edit_pc_posicion); ?>" required>
            <?php endif; ?>

            <label for="edit_estado_actual">Estado actual</label>
            <select id="edit_estado_actual" name="estado_actual">
                <option value="funcional" <?php echo $edit_pc_estado_actual === 'funcional' ? 'selected' : ''; ?>>Funcional</option>
                <option value="falla" <?php echo $edit_pc_estado_actual === 'falla' ? 'selected' : ''; ?>>Con falla</option>
                <option value="sin_eval" <?php echo $edit_pc_estado_actual === 'sin_eval' ? 'selected' : ''; ?>>No evaluada</option>
            </select>

            <button type="submit" style="margin-top: 10px;">Actualizar equipo</button>
        </form>
        <?php endif; ?>

        <h2 style="margin-top: 25px;">Agregar PC a la sala</h2>
        <form method="POST" action="salas.php">
            <input type="hidden" name="accion" value="crear_pc">
            <input type="hidden" name="tipo_equipo" value="PC">

            <label for="id_sala">Sala</label>
            <select id="id_sala" name="id_sala">
                <option value="">-- Elegí una sala --</option>
                <?php foreach ($salas_para_pc as $s): ?>
                    <option value="<?php echo $s['id_sala']; ?>">
                        <?php echo htmlspecialchars($s['nombre_sala']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="etiqueta">Etiqueta del equipo</label>
            <input type="text" id="etiqueta" name="etiqueta" placeholder="Ej: PC-305-01">

            <div id="fila-posicion-fields">
                <label for="fila">Fila (posición lógica en el mapa)</label>
                <input type="text" id="fila" name="fila" placeholder="Ej: 1" required>

                <label for="posicion">Posición dentro de la fila</label>
                <input type="text" id="posicion" name="posicion" placeholder="Ej: 1" required>
            </div>

            <button type="submit" id="submit-button">Agregar PC</button>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const salaSelect = document.getElementById('id_sala');
            const filaPosicionFields = document.getElementById('fila-posicion-fields');
            const submitButton = document.getElementById('submit-button');

            function updateForm() {
                filaPosicionFields.style.display = 'block';
                // Make fields required
                filaPosicionFields.querySelectorAll('input').forEach(input => {
                    input.required = true;
                });
                submitButton.textContent = 'Agregar PC';
            }

            // Initialize
            updateForm();

            // Update on change (though not really needed now since we only have PC option)
            salaSelect.addEventListener('change', updateForm);
        });
        </script>
    </div>

</body>
</html>