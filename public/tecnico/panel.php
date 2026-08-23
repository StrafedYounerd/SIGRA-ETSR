<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/tecnico/panel.php — CU-05: Ver panel de tickets activos
// ============================================================
// Implementa CU-05 de la ESRE. Muestra todos los tickets en estado
// "pendiente" o "en_reparacion", con la info completa de cada uno,
// y un formulario por fila para avanzar el estado (dispara CU-06).

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['tecnico', 'admin']);

// ── Mensaje de confirmación/error que puede venir de actualizar-ticket.php ──
// Se pasa por la URL como parámetro GET después de la redirección
// (patrón "Post-Redirect-Get": evita reenvíos accidentales del
// formulario si el usuario recarga la página).
$mensaje_tipo = $_GET['tipo'] ?? '';
$mensaje_texto = $_GET['msg'] ?? '';

// ── Traer todos los tickets activos, con los datos de PC, sala y docente ──
// JOIN con pcs (para etiqueta y sala), salas (para nombre_sala) y
// usuarios (para el nombre del docente que reportó).
$consulta = $conexion->query(
    "SELECT
        t.id_ticket, t.tipo_falla, t.detalle_texto, t.estado, t.fecha_reporte,
        p.etiqueta AS etiqueta_pc,
        s.nombre_sala,
        u.nombre AS nombre_docente
     FROM tickets t
     INNER JOIN pcs p ON t.id_pc = p.id_pc
     INNER JOIN salas s ON p.id_sala = s.id_sala
     INNER JOIN usuarios u ON t.id_docente = u.id_usuario
     WHERE t.estado IN ('pendiente', 'en_reparacion')
     ORDER BY t.fecha_reporte DESC"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Panel del técnico</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Técnico)
        <a href="javascript:history.back()">Volver atrás</a>
        <a href="historial.php">Ver historial completo</a>
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 800px;">
        <h1>Tickets activos</h1>

        <?php if ($mensaje_texto !== ''): ?>
            <div class="mensaje-<?php echo $mensaje_tipo === 'ok' ? 'ok' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje_texto); ?>
            </div>
        <?php endif; ?>

        <?php if ($consulta->num_rows === 0): ?>
            <p>No hay fallas pendientes en este momento.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Sala</th>
                    <th>PC</th>
                    <th>Tipo de falla</th>
                    <th>Detalle</th>
                    <th>Docente</th>
                    <th>Fecha de reporte</th>
                    <th>Estado actual</th>
                    <th>Acción</th>
                </tr>
                <?php while ($ticket = $consulta->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ticket['nombre_sala']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['etiqueta_pc']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['tipo_falla']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['detalle_texto'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['nombre_docente']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['fecha_reporte']); ?></td>
                        <td><?php echo htmlspecialchars($ticket['estado']); ?></td>
                        <td>
                            <!-- Formulario simple por fila: cada botón envía
                                 directo a actualizar-ticket.php con el estado
                                 destino correspondiente. -->
                            <form method="POST" action="actualizar-ticket.php" style="margin:0;">
                                <input type="hidden" name="id_ticket" value="<?php echo $ticket['id_ticket']; ?>">

                                <?php if ($ticket['estado'] === 'pendiente'): ?>
                                    <input type="hidden" name="nuevo_estado" value="en_reparacion">
                                    <button type="submit">Marcar en reparación</button>
                                <?php elseif ($ticket['estado'] === 'en_reparacion'): ?>
                                    <input type="hidden" name="nuevo_estado" value="solucionado">
                                    <button type="submit">Marcar solucionado</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>

</body>
</html>