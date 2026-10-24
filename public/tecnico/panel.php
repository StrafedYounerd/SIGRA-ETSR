<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/tecnico/panel.php — CU-05: Ver panel de tickets activos
// ============================================================
// Implementa CU-05 de la ESRE. Muestra todos los tickets en estado
// "pendiente" o "en_reparacion" como TABLERO de 3 columnas
// (Pendiente / En reparación / Solucionado hoy), con antigüedad
// relativa y la foto adjunta si el docente envió una.
//
// El POST sigue yéndose a actualizar-ticket.php con id_ticket +
// nuevo_estado, igual que antes (CU-06 no cambia).

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
// usuarios (para el nombre del docente que reportó). Del más viejo
// al más nuevo: en un tablero operativo el reporte viejo urge más.
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
     ORDER BY t.fecha_reporte ASC"
);

$tickets = [];
while ($ticket = $consulta->fetch_assoc()) {
    $tickets[] = $ticket;
}

// ── "Solucionado hoy": tickets cerrados en la fecha de hoy ──
// Es el contenido de la tercera columna del tablero.
$hoy = date('Y-m-d');
$consulta_hoy = $conexion->prepare(
    "SELECT
        t.id_ticket, t.tipo_falla, t.detalle_texto, t.estado, t.fecha_reporte,
        p.etiqueta AS etiqueta_pc,
        s.nombre_sala,
        u.nombre AS nombre_docente
     FROM tickets t
     INNER JOIN pcs p ON t.id_pc = p.id_pc
     INNER JOIN salas s ON p.id_sala = s.id_sala
     INNER JOIN usuarios u ON t.id_docente = u.id_usuario
     WHERE t.estado = 'solucionado' AND DATE(t.fecha_cierre) = ?
     ORDER BY t.fecha_cierre DESC"
);
$consulta_hoy->bind_param("s", $hoy);
$consulta_hoy->execute();
$solucionados_hoy = $consulta_hoy->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Foto adjunta por ticket (si la migración de fotos está aplicada) ──
// Si la tabla no existe, el tablero funciona igual sin fotos
// (degradación elegante, mismo criterio que el mapa del docente).
$fotos_por_ticket = [];
$consulta_fotos = @$conexion->query(
    "SELECT id_ticket, archivo FROM fotos WHERE tipo = 'falla' AND id_ticket IS NOT NULL"
);
if ($consulta_fotos) {
    while ($f = $consulta_fotos->fetch_assoc()) {
        $fotos_por_ticket[$f['id_ticket']] = $f['archivo'];
    }
}

// ── Salas presentes en el tablero (para el filtro) ──
$salas_del_tablero = array_values(array_unique(array_column($tickets, 'nombre_sala')));

// Antigüedad relativa: "hoy", "hace 1 día", "hace N días"
function antiguedad_ticket($fecha) {
    $d = (int) floor((time() - strtotime($fecha)) / 86400);
    if ($d <= 0) return 'hoy';
    if ($d === 1) return 'hace 1 día';
    return 'hace ' . $d . ' días';
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
    <title>SIGRA-ETSR · Panel del técnico</title>
    <link rel="stylesheet" href="/css/theme.css">
    <link rel="stylesheet" href="/css/estilos.css?v=5">
    <link rel="stylesheet" href="/css/mapa.css?v=7">
</head>
<body class="cuerpo-app">

<div class="app">

    <aside class="rail">
        <div class="marca">
            <span class="tile"><img src="/img/logo-accessnet.png" alt="Logo de AccessNet"></span>
            <span class="sis">SIGRA-ETSR</span>
        </div>
        <nav aria-label="Principal">
            <a href="/tecnico/panel.php" aria-current="page">Tickets activos</a>
            <a href="/tecnico/historial.php">Historial completo</a>
        </nav>
        <div class="usuario">
            <b><?php echo htmlspecialchars($_SESSION['nombre']); ?></b>Técnico<br>
            <a href="/logout.php">Cerrar sesión</a>
        </div>
    </aside>

    <div class="principal">
        <header class="contexto">
            <h1>Tickets activos</h1>
            <span class="dato"><b><?php echo count($tickets); ?></b> sin resolver</span>
            <span class="vivo" aria-live="off">Actualizado hace <b id="hace">0</b> s</span>
        </header>

        <div class="contenido-vista">

        <?php if ($mensaje_texto !== ''): ?>
            <div class="mensaje-<?php echo $mensaje_tipo === 'ok' ? 'ok' : 'error'; ?>">
                <?php echo htmlspecialchars($mensaje_texto); ?>
            </div>
        <?php endif; ?>

        <?php if (count($tickets) === 0 && count($solucionados_hoy) === 0): ?>
            <div class="caja">
                <p>No hay fallas pendientes en este momento.</p>
            </div>
        <?php else: ?>

        <div class="filtros-tablero">
            <label>Sala
                <select id="filtro-sala">
                    <option value="">Todas</option>
                    <?php foreach ($salas_del_tablero as $sala): ?>
                        <option value="<?php echo htmlspecialchars($sala); ?>"><?php echo htmlspecialchars($sala); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <span class="nota-filtro">Ordenados del más antiguo al más nuevo</span>
        </div>

        <div class="tablero">

            <?php
            // Imprime una tarjeta de ticket (misma estructura para las 3 columnas).
            // El filtro de sala usa data-sala (no clases) porque el nombre
            // de la sala puede tener espacios.
            function tarjeta_ticket($ticket, $fotos_por_ticket) {
                $dias = (int) floor((time() - strtotime($ticket['fecha_reporte'])) / 86400);
                $vieja = $dias > 30 && $ticket['estado'] !== 'solucionado';
                $foto = $fotos_por_ticket[$ticket['id_ticket']] ?? null;
                ?>
                <article class="tarjeta-ticket <?php echo htmlspecialchars($ticket['estado']); ?>" data-sala="<?php echo htmlspecialchars($ticket['nombre_sala']); ?>">
                    <div class="fila-tarjeta">
                        <span class="codigo-tarjeta"><?php echo htmlspecialchars($ticket['etiqueta_pc']); ?></span>
                        <span class="edad<?php echo $vieja ? ' vieja' : ''; ?>"
                              <?php if ($vieja): ?>title="Más de 30 días sin resolver"<?php endif; ?>>
                            <?php echo antiguedad_ticket($ticket['fecha_reporte']); ?>
                        </span>
                    </div>
                    <p class="falla-tarjeta"><?php echo htmlspecialchars($ticket['tipo_falla']); ?></p>
                    <?php if (!empty($ticket['detalle_texto'])): ?>
                        <p class="detalle-tarjeta"><?php echo htmlspecialchars($ticket['detalle_texto']); ?></p>
                    <?php endif; ?>
                    <p class="meta-tarjeta">
                        <?php echo htmlspecialchars($ticket['nombre_sala']); ?> ·
                        <?php echo htmlspecialchars($ticket['nombre_docente']); ?>
                    </p>
                    <?php if ($foto): ?>
                        <div class="miniatura-foto">
                            <img src="/uploads/fotos/<?php echo htmlspecialchars($foto); ?>" loading="lazy" decoding="async" alt="Foto adjunta del reporte">
                            <a href="/uploads/fotos/<?php echo htmlspecialchars($foto); ?>" target="_blank" rel="noopener">Ver foto adjunta</a>
                        </div>
                    <?php endif; ?>
                    <!-- El POST usa los mismos campos de siempre: id_ticket +
                         nuevo_estado, directo a actualizar-ticket.php (CU-06). -->
                    <form method="POST" action="/tecnico/actualizar-ticket.php" class="form-tarjeta">
                        <input type="hidden" name="id_ticket" value="<?php echo $ticket['id_ticket']; ?>">
                        <?php if ($ticket['estado'] === 'pendiente'): ?>
                            <input type="hidden" name="nuevo_estado" value="en_reparacion">
                            <button type="submit" class="btn-secundario">Marcar en reparación</button>
                        <?php elseif ($ticket['estado'] === 'en_reparacion'): ?>
                            <input type="hidden" name="nuevo_estado" value="solucionado">
                            <button type="submit" class="btn-exito">Marcar solucionado</button>
                        <?php endif; ?>
                    </form>
                </article>
                <?php
            }
            ?>

            <section class="col" aria-label="Pendiente">
                <h2>Pendiente <em><?php echo count(array_filter($tickets, function ($t) { return $t['estado'] === 'pendiente'; })); ?></em></h2>
                <?php
                $pendientes = array_values(array_filter($tickets, function ($t) { return $t['estado'] === 'pendiente'; }));
                if ($pendientes) {
                    foreach ($pendientes as $t) tarjeta_ticket($t, $fotos_por_ticket);
                } else {
                    echo '<p class="vacio">No hay reportes nuevos.</p>';
                }
                ?>
            </section>

            <section class="col" aria-label="En reparación">
                <h2>En reparación <em><?php echo count(array_filter($tickets, function ($t) { return $t['estado'] === 'en_reparacion'; })); ?></em></h2>
                <?php
                $en_reparacion = array_values(array_filter($tickets, function ($t) { return $t['estado'] === 'en_reparacion'; }));
                if ($en_reparacion) {
                    foreach ($en_reparacion as $t) tarjeta_ticket($t, $fotos_por_ticket);
                } else {
                    echo '<p class="vacio">Nada en reparación ahora.</p>';
                }
                ?>
            </section>

            <section class="col" aria-label="Solucionado hoy">
                <h2>Solucionado hoy <em><?php echo count($solucionados_hoy); ?></em></h2>
                <?php
                if ($solucionados_hoy) {
                    foreach ($solucionados_hoy as $t) tarjeta_ticket($t, $fotos_por_ticket);
                } else {
                    echo '<p class="vacio">Los tickets que cierres hoy aparecen acá.</p>';
                }
                ?>
            </section>

        </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ── Filtro por sala (solo frontend: oculta/muestra tarjetas) ──
// El nombre de la sala puede tener espacios, por eso se compara
// con data-sala y no con clases CSS.
document.getElementById("filtro-sala").addEventListener("change", function () {
    var sala = this.value;
    document.querySelectorAll(".tarjeta-ticket").forEach(function (tarjeta) {
        if (sala === "" || tarjeta.getAttribute("data-sala") === sala) {
            tarjeta.style.display = "";
        } else {
            tarjeta.style.display = "none";
        }
    });
});

// ── Contador "Actualizado hace X s" (informativo: el tablero se
//    actualiza con cada POST y el navegador recarga la página) ──
var segs = 0;
setInterval(function () {
    segs = (segs + 1) % 21;
    var hace = document.getElementById("hace");
    if (hace) hace.textContent = segs;
}, 1000);
</script>

</body>
</html>
