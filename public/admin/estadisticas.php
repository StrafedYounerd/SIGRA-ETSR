<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/estadisticas.php — Módulo de estadísticas matemáticas
// ============================================================
// Vista exclusiva para administradores que muestra análisis estadísticos
// de los datos del sistema basado en las sugerencias del profesor de matemáticas.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']); // Solo administradores pueden acceder

// Obtener el período seleccionado por defecto (último mes)
$periodo = $_GET['periodo'] ?? 'ultimo_mes';

// Determinar rango de fechas según período seleccionado
$fecha_hoy = new DateTime();
switch ($periodo) {
    case 'ultima_semana':
        $fecha_desde = (new DateTime())->modify('-7 days')->format('Y-m-d');
        break;
    case 'ultimo_mes':
        $fecha_desde = (new DateTime())->modify('-1 month')->format('Y-m-d');
        break;
    case 'ultimos_3_meses':
        $fecha_desde = (new DateTime())->modify('-3 months')->format('Y-m-d');
        break;
    case 'ultimo_año':
        $fecha_desde = (new DateTime())->modify('-1 year')->format('Y-m-d');
        break;
    case 'todo':
    default:
        $fecha_desde = '1970-01-01'; // Desde el inicio
        break;
}
$fecha_hasta = $fecha_hoy->format('Y-m-d');

require '../../includes/ui.php';

/**
 * Barras horizontales en CSS puro (la intranet no tiene internet: nada de CDN).
 * $filas = [['label'=>..., 'valor'=>número, 'texto'=>string mostrado, 'color'=>(opcional) CSS], ...]
 * El ancho de cada barra es su valor relativo al máximo.
 */
function graf_barras($filas) {
    if (!$filas) return;
    $max = max(array_column($filas, 'valor'));
    if ($max <= 0) $max = 1;
    echo '<div class="graf-barras">';
    foreach ($filas as $f) {
        $ancho = round($f['valor'] / $max * 100, 1);
        echo '<div class="graf-fila">'
           . '<span class="graf-etiqueta">' . htmlspecialchars($f['label']) . '</span>'
           . '<span class="graf-barra-track"><span class="graf-barra" style="width: ' . $ancho . '%;' . (isset($f['color']) ? ' background: ' . $f['color'] . ';' : '') . '"></span></span>'
           . '<span class="graf-valor">' . htmlspecialchars($f['texto']) . '</span>'
           . '</div>';
    }
    echo '</div>';
}

ui_head(['titulo' => 'Estadísticas matemáticas', 'activo' => 'estadisticas']);
?>
    <div class="caja caja-ancho-1200">

        <!-- Selector de período -->
        <div style="margin-bottom: 20px;">
            <label for="periodo">Período de análisis:</label>
            <select id="periodo" name="periodo">
                <option value="ultima_semana" <?php echo ($periodo === 'ultima_semana') ? 'selected' : ''; ?>>Última semana</option>
                <option value="ultimo_mes" <?php echo ($periodo === 'ultimo_mes') ? 'selected' : ''; ?>>Último mes</option>
                <option value="ultimos_3_meses" <?php echo ($periodo === 'ultimos_3_meses') ? 'selected' : ''; ?>>Últimos 3 meses</option>
                <option value="ultimo_año" <?php echo ($periodo === 'ultimo_año') ? 'selected' : ''; ?>>Último año</option>
                <option value="todo" <?php echo ($periodo === 'todo') ? 'selected' : ''; ?>>Todo el historial</option>
            </select>
            <button type="button" onclick="location.href='estadisticas.php?periodo='+this.previousElementSibling.value">Actualizar</button>
        </div>

        <div class="estadisticas-grid">
            <!-- FRECUENCIAS Y DISTRIBUCIÓN -->
            <section class="estadistica-block">
                <h2>1. Frecuencias y Distribución</h2>

                <?php
                // Consulta para frecuencia absoluta por tipo de falla
                $sql_tipos = "SELECT
                                tipo_falla,
                                COUNT(*) as frecuencia_absoluta
                              FROM tickets
                              WHERE fecha_reporte >= ? AND fecha_reporte <= ?
                              GROUP BY tipo_falla
                              ORDER BY frecuencia_absoluta DESC";
                $stmt_tipos = $conexion->prepare($sql_tipos);
                $stmt_tipos->bind_param("ss", $fecha_desde, $fecha_hasta);
                $stmt_tipos->execute();
                $resultado_tipos = $stmt_tipos->get_result();

                // Total de tickets para calcular frecuencias relativas
                $sql_total = "SELECT COUNT(*) as total FROM tickets WHERE fecha_reporte >= ? AND fecha_reporte <= ?";
                $stmt_total = $conexion->prepare($sql_total);
                $stmt_total->bind_param("ss", $fecha_desde, $fecha_hasta);
                $stmt_total->execute();
                $result_total = $stmt_total->get_result()->fetch_assoc();
                $total_tickets = $result_total['total'] ?? 0;
                ?>

                <h3>Frecuencia de fallas por tipo</h3>
                <?php if ($total_tickets > 0):
                    $tipos_data = [];
                    while ($tipo = $resultado_tipos->fetch_assoc()) {
                        $tipos_data[] = [
                            'label' => $tipo['tipo_falla'],
                            'valor' => $tipo['frecuencia_absoluta'],
                            'rel'   => $tipo['frecuencia_absoluta'] / $total_tickets,
                        ];
                    }
                ?>
                <?php graf_barras(array_map(fn($t) => ['label' => $t['label'], 'valor' => $t['valor'],
                    'texto' => $t['valor'] . ' (' . number_format($t['rel'] * 100, 2) . '%)'], $tipos_data)); ?>
                <table class="tabla-frecuencias">
                    <thead>
                        <tr>
                            <th>Tipo de falla</th>
                            <th>Frecuencia absoluta</th>
                            <th>Frecuencia relativa</th>
                            <th>Porcentaje (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos_data as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['label']); ?></td>
                            <td><?php echo $t['valor']; ?></td>
                            <td><?php echo number_format($t['rel'], 4); ?></td>
                            <td><?php echo number_format($t['rel'] * 100, 2); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No hay tickets en el período seleccionado.</p>
                <?php endif; ?>

                <?php
                // Frecuencia por sala
                $sql_salas = "SELECT
                                s.nombre_sala,
                                COUNT(*) as frecuencia_absoluta
                              FROM tickets t
                              INNER JOIN pcs p ON t.id_pc = p.id_pc
                              INNER JOIN salas s ON p.id_sala = s.id_sala
                              WHERE t.fecha_reporte >= ? AND t.fecha_reporte <= ?
                              GROUP BY s.id_sala, s.nombre_sala
                              ORDER BY frecuencia_absoluta DESC";
                $stmt_salas = $conexion->prepare($sql_salas);
                $stmt_salas->bind_param("ss", $fecha_desde, $fecha_hasta);
                $stmt_salas->execute();
                $resultado_salas = $stmt_salas->get_result();
                ?>
                <h3>Frecuencia de fallas por sala</h3>
                <?php if ($resultado_salas->num_rows > 0): ?>
                <?php
                $salas_filas = [];
                while ($sala = $resultado_salas->fetch_assoc()) {
                    $salas_filas[] = $sala;
                }
                graf_barras(array_map(fn($x) => ['label' => $x['nombre_sala'], 'valor' => $x['frecuencia_absoluta'],
                    'texto' => $x['frecuencia_absoluta'] . ' (' . number_format($x['frecuencia_absoluta'] / $total_tickets * 100, 2) . '%)'], $salas_filas));
                ?>
                <table class="tabla-frecuencias">
                    <thead>
                        <tr>
                            <th>Sala</th>
                            <th>Frecuencia absoluta</th>
                            <th>Frecuencia relativa</th>
                            <th>Porcentaje (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salas_filas as $sala):
                            $frecuencia_absoluta = $sala['frecuencia_absoluta'];
                            $frecuencia_relativa = $frecuencia_absoluta / $total_tickets;
                            $porcentaje = $frecuencia_relativa * 100;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sala['nombre_sala']); ?></td>
                            <td><?php echo $frecuencia_absoluta; ?></td>
                            <td><?php echo number_format($frecuencia_relativa, 4); ?></td>
                            <td><?php echo number_format($porcentaje, 2); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>

            <!-- TIEMPOS DE REPARACIÓN -->
            <section class="estadistica-block">
                <h2>2. Tiempos de Reparación</h2>

                <?php
                // Consulta para tiempos de reparación (solo tickets solucionados)
                $sql_tiempos = "SELECT
                                  TIMESTAMPDIFF(HOUR, fecha_reporte, fecha_cierre) as horas_reparacion
                                FROM tickets
                                WHERE estado = 'solucionado'
                                AND fecha_cierre IS NOT NULL
                                AND fecha_reporte >= ? AND fecha_reporte <= ?
                                AND fecha_cierre >= ? AND fecha_cierre <= ?";
                $stmt_tiempos = $conexion->prepare($sql_tiempos);
                $stmt_tiempos->bind_param("ssss", $fecha_desde, $fecha_hasta, $fecha_desde, $fecha_hasta);
                $stmt_tiempos->execute();
                $resultado_tiempos = $stmt_tiempos->get_result();

                $horas = [];
                while ($fila = $resultado_tiempos->fetch_assoc()) {
                    if ($fila['horas_reparacion'] !== null) {
                        $horas[] = $fila['horas_reparacion'];
                    }
                }

                if (count($horas) > 0):
                    // Calcular estadísticas
                    sort($horas);
                    $total_horas = array_sum($horas);
                    $media = $total_horas / count($horas);

                    // Mediana
                    $medio = count($horas) / 2;
                    if (count($horas) % 2) {
                        $mediana = $horas[floor($medio)];
                    } else {
                        $mediana = ($horas[$medio - 1] + $horas[$medio]) / 2;
                    }

                    $minimo = min($horas);
                    $maximo = max($horas);
                    $rango = $maximo - $minimo;
                ?>
                <div class="metricas-tiempos">
                    <div class="metrica">
                        <h3>Media</h3>
                        <p><?php echo number_format($media, 2); ?> horas</p>
                    </div>
                    <div class="metrica">
                        <h3>Mediana</h3>
                        <p><?php echo number_format($mediana, 2); ?> horas</p>
                    </div>
                    <div class="metrica">
                        <h3>Rango</h3>
                        <p><?php echo number_format($rango, 2); ?> horas</p>
                    </div>
                    <div class="metrica">
                        <h3>Mínimo</h3>
                        <p><?php echo number_format($minimo, 2); ?> horas</p>
                    </div>
                    <div class="metrica">
                        <h3>Máximo</h3>
                        <p><?php echo number_format($maximo, 2); ?> horas</p>
                    </div>
                </div>

                <?php
                // Histograma: 5 intervalos de igual amplitud entre mínimo y máximo.
                $clases = 5;
                $amplitud = $rango > 0 ? $rango / $clases : 1;
                $bins = array_fill(0, $clases, 0);
                foreach ($horas as $h) {
                    $k = $rango > 0 ? min($clases - 1, (int) floor(($h - $minimo) / $amplitud)) : 0;
                    $bins[$k]++;
                }
                $hist = [];
                foreach ($bins as $k => $n) {
                    $desde = $minimo + $k * $amplitud;
                    $hist[] = ['label' => number_format($desde, 1) . ' – ' . number_format($desde + $amplitud, 1) . ' h',
                               'valor' => $n, 'texto' => $n . ' tickets'];
                }
                graf_barras($hist);
                ?>
                <?php else: ?>
                <p>No hay tickets solucionados en el período seleccionado para calcular tiempos de reparación.</p>
                <?php endif; ?>
            </section>

            <!-- PROPORCIONES Y EVOLUTIVO -->
            <section class="estadistica-block">
                <h2>3. Proporciones y Evolutivo</h2>

                <?php
                // Proporción de equipos operativos vs fallados (estado actual de PCs)
                $sql_pcs_estado = "SELECT
                                    estado_actual,
                                    COUNT(*) as total
                                  FROM pcs
                                  GROUP BY estado_actual";
                $resultado_pcs = $conexion->query($sql_pcs_estado);

                $total_pcs = 0;
                $pcs_estado = [];
                while ($pc = $resultado_pcs->fetch_assoc()) {
                    $total_pcs += $pc['total'];
                    $pcs_estado[$pc['estado_actual']] = $pc['total'];
                }

                $funcionales = $pcs_estado['funcional'] ?? 0;
                $falladas = $pcs_estado['falla'] ?? 0;
                $sin_eval = $pcs_estado['sin_eval'] ?? 0;
                ?>
                <h3>Estado actual de los equipos</h3>
                <?php if ($total_pcs > 0): ?>
                <?php graf_barras([
                    // RNF01: colores de estado fijos y con indicador textual (el turquesa nunca es color de estado)
                    ['label' => 'OK · Funcionales', 'valor' => $funcionales, 'texto' => (string) $funcionales, 'color' => '#b9e6b9'],
                    ['label' => 'FALLA · Con falla', 'valor' => $falladas,   'texto' => (string) $falladas,    'color' => '#f0a8a8'],
                    ['label' => '? · Sin evaluar',  'valor' => $sin_eval,    'texto' => (string) $sin_eval,    'color' => '#d9d9d9'],
                ]); ?>
                <div class="metricas-pcs">
                    <div class="metrica">
                        <h3>Funcionales</h3>
                        <p><?php echo $funcionales; ?> (<?php echo number_format(($funcionales/$total_pcs)*100, 2); ?>%)</p>
                    </div>
                    <div class="metrica">
                        <h3>Con falla</h3>
                        <p><?php echo $falladas; ?> (<?php echo number_format(($falladas/$total_pcs)*100, 2); ?>%)</p>
                    </div>
                    <div class="metrica">
                        <h3>Sin evaluar</h3>
                        <p><?php echo $sin_eval; ?> (<?php echo number_format(($sin_eval/$total_pcs)*100, 2); ?>%)</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Evolutivo: fallas registradas por semana (últimos 6 meses por defecto)
                $sql_semanales = "SELECT
                                    DATE_FORMAT(fecha_reporte, '%Y-%u') as ano_semana,
                                    COUNT(*) as total_fallas
                                  FROM tickets
                                  WHERE fecha_reporte >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                  GROUP BY ano_semana
                                  ORDER BY ano_semana";
                $resultado_semanales = $conexion->query($sql_semanales);
                ?>
                <h3>Evolutivo de fallas (últimos 6 meses)</h3>
                <?php if ($resultado_semanales->num_rows > 0): ?>
                <?php
                $semanas = [];
                while ($sem = $resultado_semanales->fetch_assoc()) {
                    $semanas[] = $sem;
                }
                graf_barras(array_map(fn($x) => ['label' => $x['ano_semana'], 'valor' => $x['total_fallas'],
                    'texto' => (string) $x['total_fallas']], $semanas));
                ?>
                <table class="tabla-evolutivo">
                    <thead>
                        <tr>
                            <th>Año-Semana</th>
                            <th>Fallas registradas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($semanas as $semana): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($semana['ano_semana']); ?></td>
                            <td><?php echo $semana['total_fallas']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No hay datos evolutivos disponibles.</p>
                <?php endif; ?>
            </section>

            <!-- PROBABILIDAD EMPIRICA -->
            <section class="estadistica-block">
                <h2>4. Probabilidad Empírica</h2>

                <?php
                // Probabilidad histórica de falla por PC (tickets totales / tiempo en servicio aproximado)
                // Como no tenemos fecha de instalación, usamos probabilidad sencilla: tickets por PC
                $sql_prob_pc = "SELECT
                                  p.etiqueta,
                                  s.nombre_sala,
                                  COUNT(t.id_ticket) as total_tickets
                                FROM pcs p
                                INNER JOIN salas s ON p.id_sala = s.id_sala
                                LEFT JOIN tickets t ON p.id_pc = t.id_pc
                                GROUP BY p.id_pc, p.etiqueta, s.nombre_sala
                                ORDER BY total_tickets DESC
                                LIMIT 10";
                $resultado_prob = $conexion->query($sql_prob_pc);
                ?>
                <h3>Top 10 PCs con mayor probabilidad de falla (histórico)</h3>
                <?php if ($resultado_prob->num_rows > 0): ?>
                <?php
                $pcs_top = [];
                while ($pc = $resultado_prob->fetch_assoc()) {
                    $pcs_top[] = $pc;
                }
                graf_barras(array_map(fn($x) => ['label' => $x['etiqueta'] . ' · ' . $x['nombre_sala'],
                    'valor' => $x['total_tickets'], 'texto' => (string) $x['total_tickets']], $pcs_top));
                ?>
                <table class="tabla-probabilidad">
                    <thead>
                        <tr>
                            <th>PC</th>
                            <th>Sala</th>
                            <th>Total de tickets históricos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pcs_top as $pc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pc['etiqueta']); ?></td>
                            <td><?php echo htmlspecialchars($pc['nombre_sala']); ?></td>
                            <td><?php echo $pc['total_tickets']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No hay datos de probabilidad disponibles.</p>
                <?php endif; ?>

                <?php
                // Probabilidad por sala
                $sql_prob_sala = "SELECT
                                    s.nombre_sala,
                                    COUNT(t.id_ticket) as total_tickets
                                  FROM salas s
                                  LEFT JOIN pcs p ON s.id_sala = p.id_sala
                                  LEFT JOIN tickets t ON p.id_pc = t.id_pc
                                  GROUP BY s.id_sala, s.nombre_sala
                                  ORDER BY total_tickets DESC";
                $resultado_prob_sala = $conexion->query($sql_prob_sala);
                ?>
                <h3>Probabilidad de falla por sala (histórico)</h3>
                <?php if ($resultado_prob_sala->num_rows > 0): ?>
                <?php
                $salas_prob = [];
                while ($sp = $resultado_prob_sala->fetch_assoc()) {
                    $salas_prob[] = $sp;
                }
                graf_barras(array_map(fn($x) => ['label' => $x['nombre_sala'], 'valor' => $x['total_tickets'],
                    'texto' => (string) $x['total_tickets']], $salas_prob));
                ?>
                <table class="tabla-probabilidad-sala">
                    <thead>
                        <tr>
                            <th>Sala</th>
                            <th>Total de tickets históricos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salas_prob as $sala): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sala['nombre_sala']); ?></td>
                            <td><?php echo $sala['total_tickets']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <style>
        /* Estilos propios de estadísticas; consumen las variables de
           css/theme.css para mantener la paleta unificada. */
        .estadisticas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(440px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .estadistica-block {
            background-color: var(--color-superficie);
            border: 1px solid var(--color-borde);
            border-radius: var(--radio);
            box-shadow: var(--sombra);
            padding: 20px;
        }

        .estadistica-block h2 {
            border-bottom: 1px solid var(--color-borde);
            padding-bottom: 10px;
        }

        .estadistica-block h3 {
            margin: 26px 0 10px;
        }
        .estadistica-block h2 + h3 {
            margin-top: 0;
        }

        .tabla-frecuencias, .tabla-evolutivo, .tabla-probabilidad, .tabla-probabilidad-sala {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabla-frecuencias th, .tabla-frecuencias td,
        .tabla-evolutivo th, .tabla-evolutivo td,
        .tabla-probabilidad th, .tabla-probabilidad td,
        .tabla-probabilidad-sala th, .tabla-probabilidad-sala td {
            border-bottom: 1px solid var(--color-borde-suave);
            padding: 6px 8px;
            text-align: left;
            font-size: 13px;
        }

        .tabla-frecuencias th, .tabla-evolutivo th,
        .tabla-probabilidad th, .tabla-probabilidad-sala th {
            background-color: var(--color-superficie-alt);
            color: var(--color-primario);
        }

        .tabla-frecuencias tr:hover td, .tabla-evolutivo tr:hover td,
        .tabla-probabilidad tr:hover td, .tabla-probabilidad-sala tr:hover td {
            background-color: #eef7f8;
        }

        .metricas-tiempos, .metricas-pcs {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .metrica {
            background-color: var(--color-superficie-alt);
            border: 1px solid var(--color-borde);
            border-top: 3px solid var(--color-primario);
            border-radius: var(--radio-chico);
            padding: 15px;
            text-align: center;
            flex: 1;
            min-width: 120px;
        }

        .metrica h3 {
            margin: 0 0 5px 0;
            font-size: 1em;
            color: var(--color-texto-suave);
            font-weight: 600;
        }

        .metrica p {
            margin: 0;
            font-size: 1.2em;
            font-weight: bold;
            color: var(--color-primario);
        }

        .graf-barras { display: grid; gap: 6px; margin: 12px 0; }
        .graf-fila { display: grid; grid-template-columns: minmax(110px, 38%) 1fr auto; gap: 10px; align-items: center; font-size: 13px; }
        .graf-etiqueta { color: var(--color-texto-suave); overflow-wrap: anywhere; }
        .graf-barra-track { background: var(--color-superficie-alt); border-radius: 3px; height: 14px; overflow: hidden; }
        .graf-barra { display: block; height: 100%; background: var(--color-primario); border-radius: 3px; }
        .graf-valor { font-variant-numeric: tabular-nums; min-width: 70px; text-align: right; }
    </style>
<?php ui_foot(); ?>