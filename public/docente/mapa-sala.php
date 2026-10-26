<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/docente/mapa-sala.php — CU-03: Ver mapa de la sala
// ============================================================

require '../../includes/auth.php';
require '../../config/db.php';
require '../../includes/ui.php';
require '../../includes/fotos.php';

requerirRol(['docente']);

if (!isset($_SESSION['id_sala'])) {
    header("Location: seleccionar-sala.php");
    exit();
}

$consulta = $conexion->prepare(
    "SELECT id_pc, etiqueta, fila, posicion, estado_actual, tipo_equipo
     FROM pcs WHERE id_sala = ?
     ORDER BY fila ASC, posicion ASC"
);
$consulta->bind_param("i", $_SESSION['id_sala']);
$consulta->execute();
$resultado = $consulta->get_result();

// Mapeo SOLO de presentación: el estado interno ('funcional'/'falla'/'sin_eval')
// no cambia; esto agrega un indicador textual para no depender solo del color (RNF01).
$indicadores = ['funcional' => 'OK', 'falla' => 'FALLA', 'sin_eval' => '?'];

$pcs_por_fila = [1 => [], 2 => [], 3 => []];
$switches = [];
$tvs = []; // TV y Proyector

while ($equipo = $resultado->fetch_assoc()) {
    if ($equipo['tipo_equipo'] === 'PC') {
        $pcs_por_fila[$equipo['fila']][] = $equipo;
    } elseif ($equipo['tipo_equipo'] === 'Switch') {
        $switches[] = $equipo;
    } elseif ($equipo['tipo_equipo'] === 'TV' || $equipo['tipo_equipo'] === 'Proyector') {
        $tvs[] = $equipo;
    }
}

// ── Reportes activos de esta sala (para el listado del cajón) ──
// JOIN con tickets abiertos por PC. Solo lectura: alimenta el panel lateral.
$reportes_activos = [];
$consulta_activos = $conexion->prepare(
    "SELECT t.id_ticket, t.tipo_falla, t.estado, t.fecha_reporte, t.id_pc,
            p.etiqueta
     FROM tickets t
     INNER JOIN pcs p ON t.id_pc = p.id_pc
     WHERE p.id_sala = ? AND t.estado IN ('pendiente', 'en_reparacion')
     ORDER BY t.fecha_reporte ASC"
);
$consulta_activos->bind_param("i", $_SESSION['id_sala']);
$consulta_activos->execute();
$resultado_activos = $consulta_activos->get_result();
while ($rep = $resultado_activos->fetch_assoc()) {
    $reportes_activos[$rep['id_pc']] = $rep;
}

// ── Fotos de esta sala para el cajón ──
// Últimas 20 de control + TODAS las de fallas con ticket abierto. Antes era
// un único LIMIT 20 sobre todo: las fotos de control empujaban afuera la foto
// de un reporte abierto y esta desaparecía del cajón.
// Si la migración bd/migracion_fotos.sql todavía no se aplicó, la tabla
// fotos no existe: la consulta falla y el sistema degrada sin fotos.
$fotos_sala = [];
$fotos_disponibles = false;
$consulta_fotos = @$conexion->prepare(
    "(SELECT f.id_foto, f.tipo, f.archivo, f.fecha_subida, f.id_pc, f.id_ticket, p.etiqueta
      FROM fotos f LEFT JOIN pcs p ON f.id_pc = p.id_pc
      WHERE f.id_sala = ? AND f.tipo = 'control'
      ORDER BY f.fecha_subida DESC LIMIT 20)
     UNION ALL
     (SELECT f.id_foto, f.tipo, f.archivo, f.fecha_subida, f.id_pc, f.id_ticket, p.etiqueta
      FROM fotos f
      INNER JOIN pcs p ON f.id_pc = p.id_pc
      INNER JOIN tickets t ON f.id_ticket = t.id_ticket
      WHERE f.id_sala = ? AND f.tipo = 'falla' AND t.estado IN ('pendiente', 'en_reparacion'))
     ORDER BY fecha_subida DESC"
);
if ($consulta_fotos) {
    $consulta_fotos->bind_param("ii", $_SESSION['id_sala'], $_SESSION['id_sala']);
    $consulta_fotos->execute();
    $resultado_fotos = $consulta_fotos->get_result();
    while ($foto = $resultado_fotos->fetch_assoc()) {
        $fotos_sala[] = $foto;
    }
    $fotos_disponibles = true;
}
ui_head([
    'titulo' => $_SESSION['nombre_sala'],
    'activo' => 'mapa',
    'datos'  => ['Grupo <b>' . htmlspecialchars($_SESSION['grupo']) . '</b>'],
    'vivo'   => true,
    'clase'  => 'vista-mapa', // el plano ocupa todo el alto disponible (mapa.css)
]);
?>
        <div class="sala">
            <div class="plano">
                <!-- Leyenda RNF01: verde = funcional, rojo = con falla, gris = no evaluada.
                     Los colores están fijados en mapa.css y NUNCA se tocan. -->
                <div class="leyenda" role="note" aria-label="Leyenda de estados">
                    <span><i class="leyenda-color leyenda-funcional"></i>Funcional</span>
                    <span><i class="leyenda-color leyenda-falla"></i>Con falla</span>
                    <span><i class="leyenda-color leyenda-sin_eval"></i>No evaluada</span>
                </div>

                <!-- Plano SVG: lo dibuja mapa.js a partir del JSON #datos-sala.
                     La puerta va abajo a la derecha (junto al switch) y en el
                     frente el pizarrón queda a la izquierda y la TV a la derecha. -->
                <svg id="plano" viewBox="0 0 900 620" role="group"
                     aria-label="Plano de la sala <?php echo htmlspecialchars($_SESSION['nombre_sala']); ?>"></svg>
            </div>

            <!-- Cajón lateral: reemplaza al modal. mapa.js pinta acá el
                 resumen de reportes, el detalle del equipo o el formulario. -->
            <aside class="cajon" id="cajon" aria-live="polite"></aside>
        </div>

<!-- Mensaje flotante para confirmaciones -->
<div class="toast" id="toast" role="status"></div>

<!-- ============================================================
     DATOS PARA MAPA.JS
     El PHP de arriba (consultas) serializa su resultado acá;
     mapa.js trabaja SOLO con este JSON. No hay lógica duplicada.
     ============================================================ -->
<script id="datos-sala" type="application/json">
<?php echo json_encode([
    'sala'     => $_SESSION['nombre_sala'],
    'grupo'    => $_SESSION['grupo'],
    'equipos'  => array_values(array_merge(
        array_merge(...array_values($pcs_por_fila)),
        $switches,
        $tvs
    )),
    'reportes' => $reportes_activos,
    'fotos'    => $fotos_sala,
    'fotos_ok' => $fotos_disponibles,
    'limite_foto' => limite_foto_bytes(),
]); ?>
</script>

<!--
    El "?v=N" es cache-busting: si modificás mapa.js o mapa.css y los
    cambios no se reflejan en el navegador, subí este número (v=5, v=6...)
    para forzar la descarga de la versión nueva.
-->
<script src="/js/mapa.js?v=6"></script>

<?php ui_foot(); ?>
