<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/docente/mapa-sala.php — CU-03: Ver mapa de la sala
// ============================================================

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['docente']);

if (!isset($_SESSION['id_sala'])) {
    header("Location: seleccionar-sala.php");
    exit();
}

$consulta = $conexion->prepare(
    "SELECT id_pc, etiqueta, fila, posicion, estado_actual
     FROM pcs WHERE id_sala = ?
     ORDER BY fila ASC, posicion ASC"
);
$consulta->bind_param("i", $_SESSION['id_sala']);
$consulta->execute();
$resultado_pcs = $consulta->get_result();

$pcs_por_fila = [1 => [], 2 => [], 3 => []];
while ($pc = $resultado_pcs->fetch_assoc()) {
    $pcs_por_fila[$pc['fila']][] = $pc;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Mapa de sala</title>
    <link rel="stylesheet" href="../../css/estilos.css">
    <link rel="stylesheet" href="../../css/mapa.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Docente) —
        <?php echo htmlspecialchars($_SESSION['nombre_sala']); ?> ·
        Grupo <?php echo htmlspecialchars($_SESSION['grupo']); ?>
        <a href="../logout.php">Cerrar sesión</a>
        <a href="seleccionar-sala.php">Cambiar sala</a>
    </div>

    <div class="caja" style="max-width: 750px;">
        <h1><?php echo htmlspecialchars($_SESSION['nombre_sala']); ?></h1>

        <p style="font-size:13px; color:#555555;">
            Hacé clic en una PC para reportar una falla.
            <span class="leyenda-color leyenda-funcional"></span> Funcional
            <span class="leyenda-color leyenda-falla"></span> Con falla
            <span class="leyenda-color leyenda-sin_eval"></span> No evaluada
        </p>

        <div id="mensaje-reporte"></div>

        <!-- ============================================================
             DISPOSICIÓN EN U: 3 ARRIBA (centradas), 5 ABAJO (extremos)
             ============================================================ -->

        <!-- Fila superior: 3 PCs centradas -->
<div class="fila-superior-tres">
            <?php foreach (array_reverse($pcs_por_fila[2]) as $pc): ?>
                <div class="pc pc-<?php echo $pc['estado_actual']; ?>"
                     data-id-pc="<?php echo $pc['id_pc']; ?>"
                     data-etiqueta="<?php echo htmlspecialchars($pc['etiqueta']); ?>"
                     data-estado="<?php echo $pc['estado_actual']; ?>">
                    <?php echo htmlspecialchars($pc['etiqueta']); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="filas-inferiores-extremos">
            <div class="pata-izquierda">
                <?php foreach ($pcs_por_fila[3] as $pc): ?>
                    <div class="pc pc-<?php echo $pc['estado_actual']; ?>"
                         data-id-pc="<?php echo $pc['id_pc']; ?>"
                         data-etiqueta="<?php echo htmlspecialchars($pc['etiqueta']); ?>"
                         data-estado="<?php echo $pc['estado_actual']; ?>">
                        <?php echo htmlspecialchars($pc['etiqueta']); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pata-derecha">
                <?php foreach (array_reverse($pcs_por_fila[1]) as $pc): ?>
                    <div class="pc pc-<?php echo $pc['estado_actual']; ?>"
                         data-id-pc="<?php echo $pc['id_pc']; ?>"
                         data-etiqueta="<?php echo htmlspecialchars($pc['etiqueta']); ?>"
                         data-estado="<?php echo $pc['estado_actual']; ?>">
                        <?php echo htmlspecialchars($pc['etiqueta']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Modal de reporte de falla, oculto hasta que se haga clic en una PC -->
        <div id="modal-reporte" class="modal-oculto">
            <div class="modal-caja">
                <h2>Reportar falla</h2>
                <p id="modal-pc-info"></p>

                <div id="modal-mensaje"></div>

                <!-- Este bloque se oculta si la PC ya tiene un ticket activo -->
                <div id="modal-formulario">
                    <input type="hidden" id="campo-id-pc">

                    <label for="campo-tipo-falla">Tipo de falla</label>
                    <select id="campo-tipo-falla">
                        <option value="">-- Elegí un tipo --</option>
                        <option value="No enciende">No enciende</option>
                        <option value="Falta mouse">Falta mouse</option>
                        <option value="Falta teclado">Falta teclado</option>
                        <option value="Sin internet">Sin internet</option>
                        <option value="Pantalla rota">Pantalla rota</option>
                        <option value="Falta de software">Falta de software</option>
                        <option value="Otro">Otro</option>
                    </select>

                    <!-- Solo visible cuando el tipo es "Falta de software" u "Otro" -->
                    <div id="contenedor-detalle" style="display:none;">
                        <label for="campo-detalle">Detalle</label>
                        <textarea id="campo-detalle" rows="3"
                                  placeholder="Ej: falta instalar Python"></textarea>
                    </div>

                    <button type="button" id="btn-confirmar-reporte">Confirmar reporte</button>
                </div>

                <button type="button" id="btn-cerrar-modal">Cerrar</button>
            </div>
        </div>

    </div>

    <!--
        IMPORTANTE: el "?v=2" al final de la URL es un parámetro de
        cache-busting. Cada vez que modifiques mapa.js y los cambios
        no se reflejen en el navegador, subí este número (v=3, v=4...)
        para forzar a que el navegador descargue la versión nueva en
        vez de usar una copia vieja guardada en caché.
    -->
    <script src="../../js/mapa.js?v=2"></script>
</body>
</html>