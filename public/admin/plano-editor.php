<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/plano-editor.php — CU-09: Agregar PCs en el plano
// ============================================================
// Editor visual para agregar varias PCs a una sala de una sola vez.
// El admin hace clic en los lugares libres del plano, escribe el
// código de cada equipo y guarda todo junto.
//
// NO carga js/mapa.js (ese archivo tiene el polling y la lógica del
// docente). Carga js/layout-plano.js, la misma función que usa el
// plano del docente, así los lugares se ven en el mismo sitio.

require '../../includes/auth.php';
require '../../config/db.php';
require '../../includes/ui.php';

requerirRol(['admin']);

$id_sala = intval($_GET['id_sala'] ?? 0);

// Mensaje de vuelta de plano-guardar.php (tras guardar y recargar).
$mensaje = trim($_GET['msg'] ?? '');

// ── Sala (tiene que existir) ──
$sala = null;
if ($id_sala > 0) {
    $consulta = $conexion->prepare(
        "SELECT id_sala, nombre_sala, descripcion, activa FROM salas WHERE id_sala = ?"
    );
    $consulta->bind_param("i", $id_sala);
    $consulta->execute();
    $sala = $consulta->get_result()->fetch_assoc();
}

// ── Salas activas para el desplegable ──
$salas = $conexion->query(
    "SELECT id_sala, nombre_sala FROM salas WHERE activa = 1 ORDER BY nombre_sala ASC"
)->fetch_all(MYSQLI_ASSOC);

// ── Equipos de la sala ──
$equipos = [];
$max_pos = [1 => 0, 2 => 0, 3 => 0];
if ($sala) {
    $consulta = $conexion->prepare(
        "SELECT id_pc, etiqueta, tipo_equipo, fila, posicion, estado_actual
         FROM pcs WHERE id_sala = ? ORDER BY tipo_equipo ASC, fila ASC, posicion ASC"
    );
    $consulta->bind_param("i", $id_sala);
    $consulta->execute();
    // get_result() UNA sola vez, fuera del while: si se pide el
    // result set en cada iteración, mysqli tira "Commands out of sync".
    $resultado_equipos = $consulta->get_result();
    while ($e = $resultado_equipos->fetch_assoc()) {
        $equipos[] = $e;
        if ($e['tipo_equipo'] === 'PC') {
            $f = (int)$e['fila'];
            if (in_array($f, [1, 2, 3], true)) {
                $max_pos[$f] = max($max_pos[$f], (int)$e['posicion']);
            }
        }
    }
}

// ── Equipos ya usados como código (para no proponerlos de nuevo) ──
$codigos_usados = [];
foreach ($equipos as $e) {
    $codigos_usados[] = strtoupper($e['etiqueta']);
}

// Tope de posiciones por fila (el mismo que js/layout-plano.js).
$max_por_fila = 8;   // debe coincidir con MAX_POR_FILA de
                      // js/layout-plano.js (y con $max_por_fila de
                      // public/admin/plano-guardar.php).

// Datos para el JS: los equipos que ya están (no se tocan) y los
// lugares ocupados por (fila, posición).
$json_equipos = [];
$ocupados = [];
foreach ($equipos as $e) {
    $json_equipos[] = [
        'id_pc'         => (int)$e['id_pc'],
        'etiqueta'      => $e['etiqueta'],
        'tipo_equipo'   => $e['tipo_equipo'],
        'fila'          => (int)$e['fila'],
        'posicion'      => (int)$e['posicion'],
        'estado_actual' => $e['estado_actual'],
    ];
    if ($e['tipo_equipo'] === 'PC' && in_array((int)$e['fila'], [1, 2, 3], true)) {
        $ocupados[(int)$e['fila'] . ':' . (int)$e['posicion']] = true;
    }
}

ui_head([
    'titulo' => 'Agregar PCs en el plano',
    'activo' => 'salas',
]);
?>
<link rel="stylesheet" href="/css/editor-plano.css?v=9">
    <div class="editor-plano">

        <!-- ── Barra superior: sala y acciones ── -->
        <div class="editor-plano__barra">
            <div class="editor-plano__sala">
                <label for="sel-sala">Sala</label>
                <select id="sel-sala">
                    <?php if (!$sala): ?>
                        <option value="" selected disabled>-- Elegí una sala --</option>
                    <?php endif; ?>
                    <?php foreach ($salas as $s): ?>
                        <option value="<?php echo (int)$s['id_sala']; ?>"
                            <?php echo ($sala && (int)$s['id_sala'] === $id_sala) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['nombre_sala']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="editor-plano__acciones" role="group" aria-label="Modo de trabajo">
                <button type="button" class="btn-editor is-active" id="btn-modo-agregar"
                        aria-pressed="true">Modo Agregar</button>
                <button type="button" class="btn-editor btn-editor--danger" id="btn-modo-eliminar"
                        aria-pressed="false">
                    <span class="btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                            <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                        </svg>
                    </span> Modo Eliminar
                </button>
                <button type="button" class="btn-editor btn-editor--principal" id="btn-guardar" disabled>
                    Guardar cambios
                </button>
            </div>
            <!-- Banner de modo eliminar -->
            <div class="editor-plano__banner-eliminar" id="banner-eliminar" role="status" hidden>
                <span class="banner-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                </span>
                <span class="banner-texto">Modo eliminar activo — tocá una PC del plano para marcarla o desmarcarla. Después guardá para confirmar.</span>
                <button type="button" class="btn-banner-cerrar" id="btn-cancelar-eliminar-modo"
                        aria-label="Salir del modo eliminar">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
        </div>

        <?php if (!$sala): ?>
            <div class="caja">
                <p class="nota">Elegí una sala para empezar a agregar equipos.</p>
            </div>
        <?php else: ?>

            <div class="editor-plano__cuerpo">

                <!-- ── Plano: los lugares libres son botones ── -->
                <div class="editor-plano__plano">
                    <div class="editor-plano__pista" role="status" aria-live="polite">
                        <span id="pista"><?php echo $mensaje !== ''
                            ? htmlspecialchars($mensaje)
                            : 'Tocá un lugar libre del plano para agregar una PC.'; ?></span>
                    </div>
                    <p class="editor-plano__aviso-scroll">
                        Deslizá el plano hacia los costados para ver todo
                    </p>
                    <div class="editor-plano__svg-wrap" id="plano-wrap">
                        <svg id="plano-editor" viewBox="0 0 900 620" role="group"
                             aria-label="Plano de la <?php echo htmlspecialchars($sala['nombre_sala']); ?> para agregar equipos">
                        </svg>
                    </div>
                </div>

                <!-- ── Panel: los equipos que se van a agregar ── -->
                <aside class="editor-plano__panel" aria-label="Equipos a agregar">
                    <h2>Equipos a agregar</h2>

                    <ul class="editor-plano__lista" id="lista"></ul>

                    <p class="nota" id="nota-lista">Todavía no agregaste equipos.</p>

                    <div class="editor-plano__total">
                        Total: <b id="total">0</b> equipo(s)
                    </div>
                </aside>
            </div>

            <!-- ── Alternativa móvil: lista de lugares libres con
                 checkbox (el plano scanea en horizontal) ── -->
            <div class="editor-plano__movil" id="lista-movil">
                <h3>Lugares libres</h3>
                <ul></ul>
            </div>

            <!-- ── Diálogo de confirmación de eliminación ── -->
            <dialog id="dlg-eliminar" class="editor-plano__dlg">
                <form method="dialog">
                    <h2>Confirmar eliminación</h2>
                    <p id="texto-eliminar">¿Estás seguro de que quieres eliminar este equipo?</p>
                    <div class="editor-plano__dlg-acciones">
                        <button type="button" class="btn-editor" id="btn-cancelar-eliminar">Cancelar</button>
                        <button type="button" class="btn-editor btn-editor--principal" id="btn-confirmar-eliminar">Eliminar</button>
                    </div>
                </form>
            </dialog>

            <!-- ── Diálogo de código para un lugar ── -->
            <dialog id="dlg-codigo" class="editor-plano__dlg">
                <form method="dialog">
                    <h2 id="titulo-codigo">Código del equipo</h2>
                    <div id="campos-lugar" hidden>
                        <label for="sel-fila-cod">Fila</label>
                        <select id="sel-fila-cod">
                            <option value="1">Fila 1 (columna derecha)</option>
                            <option value="2">Fila 2 (fila del fondo)</option>
                            <option value="3">Fila 3 (columna izquierda)</option>
                        </select>
                        <label for="sel-pos-cod">Posición</label>
                        <select id="sel-pos-cod"></select>
                    </div>
                    <p class="nota" id="lugar-fijo" hidden></p>
                    <label for="txt-codigo">Etiqueta</label>
                    <input type="text" id="txt-codigo" maxlength="20" placeholder="Ej: PC-305-01"
                           autocomplete="off">
                    <p class="nota" id="nota-codigo" role="alert" hidden></p>
                    <div class="editor-plano__dlg-acciones">
                        <button type="button" class="btn-editor" id="btn-cancelar-codigo">Cancelar</button>
                        <button type="button" class="btn-editor btn-editor--principal" id="btn-confirmar-codigo">Agregar</button>
                    </div>
                </form>
            </dialog>

            <!-- ── Datos para el JS ──
                 Las flags JSON_HEX_* impiden que una etiqueta de equipo
                 pueda cerrar el <script> o romper el HTML (< > & ' "). -->
            <script id="datos-editor" type="application/json">
            <?php echo json_encode([
                'sala'         => ['id' => (int)$sala['id_sala'], 'nombre' => $sala['nombre_sala']],
                'equipos'      => $json_equipos,
                'ocupados'     => array_keys($ocupados),
                'max_por_fila' => $max_por_fila,
                'max_pos'      => $max_pos,
                'codigos'      => $codigos_usados,
                'guardar_url'  => 'plano-guardar.php',
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            </script>
        <?php endif; ?>

    </div>

<?php if ($sala): ?>
<script src="/js/layout-plano.js?v=1"></script>
<script src="/js/plano-editor.js?v=12"></script>
<?php endif; ?>

<?php ui_foot(); ?>
