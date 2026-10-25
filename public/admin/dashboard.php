<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/dashboard.php — Panel de administración
// ============================================================

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']);

// ── Contadores simples para el resumen ──
$total_usuarios  = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1")->fetch_assoc()['total'];
$total_salas     = $conexion->query("SELECT COUNT(*) AS total FROM salas WHERE activa = 1")->fetch_assoc()['total'];
$total_pcs       = $conexion->query("SELECT COUNT(*) AS total FROM pcs WHERE tipo_equipo = 'PC'")->fetch_assoc()['total'];
$tickets_activos = $conexion->query("SELECT COUNT(*) AS total FROM tickets WHERE estado IN ('pendiente', 'en_reparacion')")->fetch_assoc()['total'];

require '../../includes/ui.php';
ui_head(['titulo' => 'Panel de administración', 'activo' => 'panel']);
?>
    <div class="caja caja-ancho-1200">

        <!-- Resumen en tarjetas: Usuarios activos, Salas activas,
             Dispositivos registrados y Tickets activos. -->
        <div class="panel-resumen">
            <div class="tarjeta-resumen">
                <div class="tarjeta-cifra" data-countup><?php echo $total_usuarios; ?></div>
                <div class="tarjeta-etiqueta">Usuarios activos</div>
            </div>
            <div class="tarjeta-resumen">
                <div class="tarjeta-cifra" data-countup><?php echo $total_salas; ?></div>
                <div class="tarjeta-etiqueta">Salas activas</div>
            </div>
            <div class="tarjeta-resumen">
                <div class="tarjeta-cifra" data-countup><?php echo $total_pcs; ?></div>
                <div class="tarjeta-etiqueta">Dispositivos registrados</div>
            </div>
            <div class="tarjeta-resumen">
                <div class="tarjeta-cifra" data-countup><?php echo $tickets_activos; ?></div>
                <div class="tarjeta-etiqueta">Tickets activos</div>
            </div>
        </div>

        <div class="menu-acciones">
            <a href="usuarios.php"><button type="button" class="btn-primary">Gestionar usuarios</button></a>
            <a href="salas.php"><button type="button" class="btn-secondary">Gestionar salas y dispositivos</button></a>
            <a href="historial.php"><button type="button" class="btn-secondary">Ver historial completo</button></a>
            <a href="estadisticas.php"><button type="button" class="btn-secondary">Ver estadísticas</button></a>
        </div>
    </div>

<?php ui_foot(); ?>
