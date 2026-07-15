<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/salas.php — CU-09: Gestionar salas y PCs
// ============================================================
// ABM de salas (alta, desactivación) y alta simple de PCs dentro
// de cada sala. Una sola página con varias acciones, igual patrón
// que usuarios.php.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']);

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';

// ============================================================
// ACCIÓN: crear_sala
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_sala') {

    $nombre_sala = trim($_POST['nombre_sala'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

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

    $id_sala  = trim($_POST['id_sala'] ?? '');
    $etiqueta = trim($_POST['etiqueta'] ?? '');
    $fila     = trim($_POST['fila'] ?? '');
    $posicion = trim($_POST['posicion'] ?? '');

    if ($id_sala === '' || $etiqueta === '' || $fila === '' || $posicion === '') {
        header("Location: salas.php?error=" . urlencode("Completá todos los campos de la PC"));
        exit();
    }

    $insertar = $conexion->prepare(
        "INSERT INTO pcs (id_sala, etiqueta, fila, posicion, estado_actual)
         VALUES (?, ?, ?, ?, 'sin_eval')"
    );
    $insertar->bind_param("isii", $id_sala, $etiqueta, $fila, $posicion);
    $insertar->execute();

    header("Location: salas.php?msg=" . urlencode("PC agregada correctamente"));
    exit();
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

// ── Salas activas, para el desplegable del formulario de alta de PC ──
$salas_para_pc = $conexion->query("SELECT id_sala, nombre_sala FROM salas WHERE activa = 1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Gestionar salas y PCs</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Administrador)
        <a href="dashboard.php">Volver al panel</a>
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 700px;">
        <h1>Gestionar salas y PCs</h1>

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
                <th>PCs cargadas</th>
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
                            <a href="salas.php?accion=desactivar_sala&id=<?php echo $s['id_sala']; ?>"
                               onclick="return confirm('¿Desactivar la sala <?php echo htmlspecialchars($s['nombre_sala']); ?>?');">
                                Desactivar
                            </a>
                        <?php else: ?>
                            <a href="salas.php?accion=reactivar_sala&id=<?php echo $s['id_sala']; ?>">
                                Reactivar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <h2 style="margin-top: 25px;">Crear nueva sala</h2>
        <form method="POST" action="salas.php">
            <input type="hidden" name="accion" value="crear_sala">

            <label for="nombre_sala">Nombre de la sala</label>
            <input type="text" id="nombre_sala" name="nombre_sala" placeholder="Ej: Sala 305">

            <label for="descripcion">Descripción (opcional)</label>
            <textarea id="descripcion" name="descripcion" rows="2"></textarea>

            <button type="submit">Crear sala</button>
        </form>

        <h2 style="margin-top: 25px;">Agregar PC a una sala</h2>
        <form method="POST" action="salas.php">
            <input type="hidden" name="accion" value="crear_pc">

            <label for="id_sala">Sala</label>
            <select id="id_sala" name="id_sala">
                <option value="">-- Elegí una sala --</option>
                <?php while ($s = $salas_para_pc->fetch_assoc()): ?>
                    <option value="<?php echo $s['id_sala']; ?>">
                        <?php echo htmlspecialchars($s['nombre_sala']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="etiqueta">Etiqueta de la PC</label>
            <input type="text" id="etiqueta" name="etiqueta" placeholder="Ej: PC-305-01">

            <label for="fila">Fila (posición lógica en el mapa)</label>
            <input type="text" id="fila" name="fila" placeholder="Ej: 1">

            <label for="posicion">Posición dentro de la fila</label>
            <input type="text" id="posicion" name="posicion" placeholder="Ej: 1">

            <button type="submit">Agregar PC</button>
        </form>
    </div>

</body>
</html>