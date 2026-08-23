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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Estadísticas matemáticas</title>
    <link rel="stylesheet" href="../../css/estilos.css">
    <!-- Incluir Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Administrador)
        <a href="dashboard.php">Volver al panel</a>
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja" style="max-width: 1200px;">
        <h1>Estadísticas matemáticas del SIGRA-ETSR</h1>

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
                <?php if ($total_tickets > 0): ?>
                <canvas id="graficoTipos" width="400" height="200"></canvas>
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
                        <?php
                        $tipos_data = [];
                        while ($tipo = $resultado_tipos->fetch_assoc()):
                            $frecuencia_absoluta = $tipo['frecuencia_absoluta'];
                            $frecuencia_relativa = $frecuencia_absoluta / $total_tickets;
                            $porcentaje = $frecuencia_relativa * 100;
                            $tipos_data[] = [
                                'label' => $tipo['tipo_falla'],
                                'valor' => $frecuencia_absoluta
                            ];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tipo['tipo_falla']); ?></td>
                            <td><?php echo $frecuencia_absoluta; ?></td>
                            <td><?php echo number_format($frecuencia_relativa, 4); ?></td>
                            <td><?php echo number_format($porcentaje, 2); ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No hay datos de tickets en el período seleccionado.</p>
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
                <canvas id="graficoSalas" width="400" height="200"></canvas>
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
                        <?php while ($sala = $resultado_salas->fetch_assoc()):
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
                        <?php endwhile; ?>
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

                <canvas id="graficoTiempos" width="400" height="200"></canvas>
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
                <canvas id="graficoEstadoPCs" width="400" height="200"></canvas>
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
                <canvas id="graficoSemanales" width="400" height="200"></canvas>
                <table class="tabla-evolutivo">
                    <thead>
                        <tr>
                            <th>Año-Semana</th>
                            <th>Fallas registradas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($semana = $resultado_semanales->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($semana['ano_semana']); ?></td>
                            <td><?php echo $semana['total_fallas']; ?></td>
                        </tr>
                        <?php endwhile; ?>
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
                <canvas id="graficoProbPC" width="400" height="200"></canvas>
                <table class="tabla-probabilidad">
                    <thead>
                        <tr>
                            <th>PC</th>
                            <th>Sala</th>
                            <th>Total de tickets históricos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pc = $resultado_prob->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pc['etiqueta']); ?></td>
                            <td><?php echo htmlspecialchars($pc['nombre_sala']); ?></td>
                            <td><?php echo $pc['total_tickets']; ?></td>
                        </tr>
                        <?php endwhile; ?>
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
                <canvas id="graficoProbSala" width="400" height="200"></canvas>
                <table class="tabla-probabilidad-sala">
                    <thead>
                        <tr>
                            <th>Sala</th>
                            <th>Total de tickets históricos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sala = $resultado_prob_sala->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sala['nombre_sala']); ?></td>
                            <td><?php echo $sala['total_tickets']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
        // Gráfico de frecuencias por tipo de falla
        <?php if (isset($tipos_data) && count($tipos_data) > 0): ?>
        const labelsTipos = <?php echo json_encode(array_column($tipos_data, 'label')); ?>;
        const dataTipos = <?php echo json_encode(array_column($tipos_data, 'valor')); ?>;

        const ctxTipos = document.getElementById('graficoTipos').getContext('2d');
        new Chart(ctxTipos, {
            type: 'bar',
            data: {
                labels: labelsTipos,
                datasets: [{
                    label: 'Frecuencia absoluta de fallas por tipo',
                    data: dataTipos,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de fallas por tipo'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico de frecuencias por sala
        <?php if (isset($resultado_salas) && $resultado_salas->num_rows > 0): ?>
        // Necesitamos reconstruir los datos ya que el resultado se consumió arriba
        <?php
        // Reconectar para obtener los datos de salas nuevamente
        $stmt_salas2 = $conexion->prepare($sql_salas);
        $stmt_salas2->bind_param("ss", $fecha_desde, $fecha_hasta);
        $stmt_salas2->execute();
        $resultado_salas2 = $stmt_salas2->get_result();
        $salas_data = [];
        while ($sala = $resultado_salas2->fetch_assoc()) {
            $salas_data[] = $sala;
        }
        ?>
        const labelsSalas = <?php echo json_encode(array_column($salas_data, 'nombre_sala')); ?>;
        const dataSalas = <?php echo json_encode(array_column($salas_data, 'frecuencia_absoluta')); ?>;

        const ctxSalas = document.getElementById('graficoSalas').getContext('2d');
        new Chart(ctxSalas, {
            type: 'bar',
            data: {
                labels: labelsSalas,
                datasets: [{
                    label: 'Frecuencia absoluta de fallas por sala',
                    data: dataSalas,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de fallas por sala'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico de tiempos de reparación
        <?php if (count($horas) > 0): ?>
        // Create histogram-like data by grouping hours into bins
        const horasData = <?php echo json_encode($horas); ?>;
        // Create bins for histogram (0-5h, 5-10h, 10-15h, etc.)
        const bins = {};
        const binSize = 5; // 5-hour bins
        horasData.forEach(hora => {
            const binIndex = Math.floor(hora / binSize);
            const binKey = `${binIndex * binSize}-${(binIndex + 1) * binSize} horas`;
            bins[binKey] = (bins[binKey] || 0) + 1;
        });
        // Sort bins by lower bound to ensure proper order
        const binEntries = Object.entries(bins);
        binEntries.sort((a, b) => {
            const lowerA = parseInt(a[0].split('-')[0]);
            const lowerB = parseInt(b[0].split('-')[0]);
            return lowerA - lowerB;
        });
        const labelsTiempos = binEntries.map(entry => entry[0]);
        const dataTiempos = binEntries.map(entry => entry[1]);

        const ctxTiempos = document.getElementById('graficoTiempos').getContext('2d');
        new Chart(ctxTiempos, {
            type: 'bar',
            data: {
                labels: labelsTiempos,
                datasets: [{
                    label: 'Distribución de tiempos de reparación (horas)',
                    data: dataTiempos,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribución de tiempos de reparación'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico de estado de PCs
        <?php if ($total_pcs > 0): ?>
        const labelsPCs = ['Funcionales', 'Con falla', 'Sin evaluar'];
        const dataPCs = [<?php echo $funcionales; ?>, <?php echo $falladas; ?>, <?php echo $sin_eval; ?>];

        const ctxPCs = document.getElementById('graficoEstadoPCs').getContext('2d');
        new Chart(ctxPCs, {
            type: 'pie',
            data: {
                labels: labelsPCs,
                datasets: [{
                    label: 'Estado actual de los equipos',
                    data: dataPCs,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(255, 206, 86, 0.5)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Estado actual de los equipos'
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico evolutivo semanal
        <?php if (isset($resultado_semanales) && $resultado_semanales->num_rows > 0): ?>
        // Reconsultar para obtener los datos
        <?php
        $stmt_semanales2 = $conexion->prepare($sql_semanales);
        $stmt_semanales2->execute();
        $resultado_semanales2 = $stmt_semanales2->get_result();
        $semanales_data = [];
        while ($semana = $resultado_semanales2->fetch_assoc()) {
            $semanales_data[] = $semana;
        }
        ?>
        const labelsSemanales = <?php echo json_encode(array_column($semanales_data, 'ano_semana')); ?>;
        const dataSemanales = <?php echo json_encode(array_column($semanales_data, 'total_fallas')); ?>;

        const ctxSemanales = document.getElementById('graficoSemanales').getContext('2d');
        new Chart(ctxSemanales, {
            type: 'line',
            data: {
                labels: labelsSemanales,
                datasets: [{
                    label: 'Fallas registradas por semana',
                    data: dataSemanales,
                    fill: false,
                    borderColor: 'rgba(153, 102, 255, 1)',
                    backgroundColor: 'rgba(153, 102, 255, 0.5)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Evolutivo de fallas por semana'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico de probabilidad por PC
        <?php if (isset($resultado_prob) && $resultado_prob->num_rows > 0): ?>
        // Reconsultar para obtener los datos
        <?php
        $stmt_prob2 = $conexion->prepare($sql_prob_pc);
        $stmt_prob2->execute();
        $resultado_prob2 = $stmt_prob2->get_result();
        $prob_pc_data = [];
        while ($pc = $resultado_prob2->fetch_assoc()) {
            $prob_pc_data[] = $pc;
        }
        ?>
        const labelsProbPC = <?php echo json_encode(array_column($prob_pc_data, 'etiqueta')); ?>;
        const dataProbPC = <?php echo json_encode(array_column($prob_pc_data, 'total_tickets')); ?>;
        const labelsProbPCSala = <?php echo json_encode(array_column($prob_pc_data, 'nombre_sala')); ?>;

        const ctxProbPC = document.getElementById('graficoProbPC').getContext('2d');
        new Chart(ctxProbPC, {
            type: 'bar',
            data: {
                labels: labelsProbPC,
                datasets: [{
                    label: 'Tickets históricos por PC',
                    data: dataProbPC,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Probabilidad empírica de falla por PC (top 10)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Gráfico de probabilidad por sala
        <?php if (isset($resultado_prob_sala) && $resultado_prob_sala->num_rows > 0): ?>
        // Reconsultar para obtener los datos
        <?php
        $stmt_prob_sala2 = $conexion->prepare($sql_prob_sala);
        $stmt_prob_sala2->execute();
        $resultado_prob_sala2 = $stmt_prob_sala2->get_result();
        $prob_sala_data = [];
        while ($sala = $resultado_prob_sala2->fetch_assoc()) {
            $prob_sala_data[] = $sala;
        }
        ?>
        const labelsProbSala = <?php echo json_encode(array_column($prob_sala_data, 'nombre_sala')); ?>;
        const dataProbSala = <?php echo json_encode(array_column($prob_sala_data, 'total_tickets')); ?>;

        const ctxProbSala = document.getElementById('graficoProbSala').getContext('2d');
        new Chart(ctxProbSala, {
            type: 'bar',
            data: {
                labels: labelsProbSala,
                datasets: [{
                    label: 'Tickets históricos por sala',
                    data: dataProbSala,
                    backgroundColor: 'rgba(199, 199, 199, 0.5)',
                    borderColor: 'rgba(199, 199, 199, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Probabilidad empírica de falla por sala'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>

    <style>
        .estadisticas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .estadistica-block {
            background-color: #ffffff;
            border: 1px solid #999999;
            padding: 20px;
        }

        .estadistica-block h2 {
            border-bottom: 1px solid #999999;
            padding-bottom: 10px;
        }

        .estadistica-block h3 {
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
            border: 1px solid #999999;
            padding: 6px 8px;
            text-align: left;
            font-size: 13px;
        }

        .tabla-frecuencias th, .tabla-evolutivo th,
        .tabla-probabilidad th, .tabla-probabilidad-sala th {
            background-color: #dddddd;
        }

        .metricas-tiempos, .metricas-pcs {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .metrica {
            background-color: #f0f0f0;
            border: 1px solid #999999;
            padding: 15px;
            text-align: center;
            flex: 1;
            min-width: 120px;
        }

        .metrica h3 {
            margin: 0 0 5px 0;
            font-size: 1em;
        }

        .metrica p {
            margin: 0;
            font-size: 1.2em;
            font-weight: bold;
            color: #222222;
        }

        canvas {
            max-width: 100%;
            height: 250px !important;
            margin-top: 15px;
        }
    </style>
</body>
</html>