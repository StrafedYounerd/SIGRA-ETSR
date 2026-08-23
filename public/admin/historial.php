<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/historial.php — Historial completo (solo admin)
// ============================================================
// Vista exclusiva para administradores que muestra el historial completo
// de tickets con los mismos filtros que la versión técnica.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']); // Solo administradores pueden acceder

// ── Leer los filtros desde la URL (GET) ──
// Todos son opcionales: si vienen vacíos, no se aplica esa condición.
$filtro_sala         = trim($_GET['sala'] ?? '');
$filtro_pc           = trim($_GET['pc'] ?? '');
$filtro_estado       = trim($_GET['estado'] ?? '');
$filtro_fecha_desde  = trim($_GET['desde'] ?? '');
$filtro_fecha_hasta  = trim($_GET['hasta'] ?? '');

// ── Construir la consulta dinámicamente según los filtros activos ──
// Empezamos con la base fija (los 4 JOINs) y vamos agregando
// condiciones WHERE solo para los filtros que el usuario completó.
// Usamos sentencias preparadas igual que en el resto del sistema,
// armando el array de parámetros en paralelo a la consulta SQL.

$sql = "SELECT
            t.id_ticket, t.tipo_falla, t.detalle_texto, t.estado,
            t.fecha_reporte, t.fecha_cierre,
            p.etiqueta AS etiqueta_pc,
            s.id_sala, s.nombre_sala,
            u.nombre AS nombre_docente
        FROM tickets t
        INNER JOIN pcs p ON t.id_pc = p.id_pc
        INNER JOIN salas s ON p.id_sala = s.id_sala
        INNER JOIN usuarios u ON t.id_docente = u.id_usuario
        WHERE 1=1";

$parametros = [];
$tipos      = ""; // acumula los tipos para bind_param ("i", "s", etc.)

if ($filtro_sala !== '') {
    $sql .= " AND s.id_sala = ?";
    $parametros[] = $filtro_sala;
    $tipos .= "i";
}

if ($filtro_pc !== '') {
    // LIKE para permitir buscar parte de la etiqueta (ej: "PC-210-01")
    $sql .= " AND p.etiqueta LIKE ?";
    $parametros[] = "%" . $filtro_pc . "%";
    $tipos .= "s";
}

if ($filtro_estado !== '') {
    $sql .= " AND t.estado = ?";
    $parametros[] = $filtro_estado;
    $tipos .= "s";
}

if ($filtro_fecha_desde !== '') {
    $sql .= " AND t.fecha_reporte >= ?";
    $parametros[] = $filtro_fecha_desde . " 00:00:00";
    $tipos .= "s";
}

if ($filtro_fecha_hasta !== '') {
    $sql .= " AND t.fecha_reporte <= ?";
    $parametros[] = $filtro_fecha_hasta . " 23:59:59";
    $tipos .= "s";
}

$sql .= " ORDER BY t.fecha_reporte DESC";

$consulta = $conexion->prepare($sql);

// bind_param necesita los parámetros como argumentos individuales,
// no como array. Usamos el operador "..." (spread) para desempaquetar
// el array $parametros, y pasamos $tipos como primer argumento.
// Esto solo se ejecuta si hay al menos un filtro activo; si no,
// la consulta no tiene "?" y no hace falta bind_param.
if (count($parametros) > 0) {
    $consulta->bind_param($tipos, ...$parametros);
}

$consulta->execute();
$resultado = $consulta->get_result();

// ── Traer la lista de salas para el desplegable del filtro ──
$salas_disponibles = $conexion->query("SELECT id_sala, nombre_sala FROM salas WHERE activa = 1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Historial completo (Administrador)</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Administrador)
        <a href="dashboard.php">Volver al panel</a>
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 900px;">
        <h1>Historial completo de tickets</h1>

        <!-- ── Formulario de filtros (GET, para que la URL sea compartible) ── -->
        <form method="GET" action="historial.php">
            <label for="sala">Sala</label>
            <select id="sala" name="sala">
                <option value="">-- Todas --</option>
                <?php while ($s = $salas_disponibles->fetch_assoc()): ?>
                    <option value="<?php echo $s['id_sala']; ?>"
                        <?php echo ($filtro_sala == $s['id_sala']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['nombre_sala']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="pc">PC (etiqueta)</label>
            <input type="text" id="pc" name="pc" placeholder="Ej: PC-210-01"
                   value="<?php echo htmlspecialchars($filtro_pc); ?>">

            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="">-- Todos --</option>
                <option value="pendiente"     <?php echo ($filtro_estado === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                <option value="en_reparacion" <?php echo ($filtro_estado === 'en_reparacion') ? 'selected' : ''; ?>>En reparación</option>
                <option value="solucionado"   <?php echo ($filtro_estado === 'solucionado') ? 'selected' : ''; ?>>Solucionado</option>
            </select>

            <label for="desde">Fecha desde</label>
            <input type="date" id="desde" name="desde"
                   value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">

            <label for="hasta">Fecha hasta</label>
            <input type="date" id="hasta" name="hasta"
                   value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">

            <button type="submit">Filtrar</button>
            <a href="historial.php" style="margin-left:10px; font-size:13px;">Limpiar filtros</a>
        </form>

        <hr style="margin: 20px 0; border: none; border-top: 1px solid #999999;">

        <?php if ($resultado->num_rows === 0): ?>
            <p>No se encontraron registros con los filtros seleccionados.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Ticket</th>
                    <th>Sala</th>
                    <th>PC</th>
                    <th>Tipo de falla</th>
                    <th>Detalle</th>
                    <th>Docente</th>
                    <th>Reportado</th>
                    <th>Cerrado</th>
                    <th>Estado</th>
                </tr>
                <?php while ($t = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $t['id_ticket']; ?></td>
                        <td><?php echo htmlspecialchars($t['nombre_sala']); ?></td>
                        <td><?php echo htmlspecialchars($t['etiqueta_pc']); ?></td>
                        <td><?php echo htmlspecialchars($t['tipo_falla']); ?></td>
                        <td><?php echo htmlspecialchars($t['detalle_texto'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($t['nombre_docente']); ?></td>
                        <td><?php echo htmlspecialchars($t['fecha_reporte']); ?></td>
                        <td><?php echo htmlspecialchars($t['fecha_cierre'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($t['estado']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>

</body>
</html>