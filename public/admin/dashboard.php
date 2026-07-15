<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/dashboard.php — Panel de administración
// ============================================================
// Punto de entrada del rol admin. Muestra un resumen rápido del
// estado del sistema y accesos a las dos pantallas de gestión.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']);

// ── Contadores simples para el resumen ──
$total_usuarios = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1")->fetch_assoc()['total'];
$total_salas    = $conexion->query("SELECT COUNT(*) AS total FROM salas WHERE activa = 1")->fetch_assoc()['total'];
$total_pcs      = $conexion->query("SELECT COUNT(*) AS total FROM pcs")->fetch_assoc()['total'];
$tickets_activos = $conexion->query("SELECT COUNT(*) AS total FROM tickets WHERE estado IN ('pendiente', 'en_reparacion')")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Panel de administración</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Administrador)
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 600px;">
        <h1>Panel de administración</h1>

        <table>
            <tr>
                <td>Usuarios activos</td>
                <td><?php echo $total_usuarios; ?></td>
            </tr>
            <tr>
                <td>Salas activas</td>
                <td><?php echo $total_salas; ?></td>
            </tr>
            <tr>
                <td>PCs registradas</td>
                <td><?php echo $total_pcs; ?></td>
            </tr>
            <tr>
                <td>Tickets activos</td>
                <td><?php echo $tickets_activos; ?></td>
            </tr>
        </table>

        <p style="margin-top: 20px;">
            <a href="usuarios.php"><button type="button">Gestionar usuarios</button></a>
            <a href="salas.php"><button type="button">Gestionar salas y PCs</button></a>
            <a href="../tecnico/historial.php"><button type="button">Ver historial completo</button></a>
        </p>
    </div>

</body>
</html>