<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/docente/reportar-falla.php — CU-04: Reportar falla
// ============================================================
// Implementa el pseudocódigo PROCESO ReportarFalla() de la Fase 3.
// Este archivo NO devuelve HTML: devuelve JSON, porque lo llama
// el JavaScript del mapa con fetch(), no un formulario tradicional.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['docente']);

// Le decimos al navegador que la respuesta es JSON, no HTML.
header('Content-Type: application/json');

// ── Función auxiliar para responder y cortar la ejecución ──
// Evita repetir "echo json_encode(...); exit();" en cada validación.
function responder($exito, $mensaje, $datosExtra = []) {
    echo json_encode(array_merge(
        ['exito' => $exito, 'mensaje' => $mensaje],
        $datosExtra
    ));
    exit();
}

// Solo aceptamos POST. Si alguien intenta acceder por GET, se corta acá.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, "Método no permitido");
}

// ── Recibimos los datos enviados por el JavaScript ──
$id_pc         = trim($_POST['id_pc'] ?? '');
$tipo_falla    = trim($_POST['tipo_falla'] ?? '');
$detalle_texto = trim($_POST['detalle_texto'] ?? '');

// PASO 2 del pseudocódigo: verificar que la PC pertenece a la sala activa.
if (!isset($_SESSION['id_sala'])) {
    responder(false, "No hay una sala activa en tu sesión");
}

$consulta_pc = $conexion->prepare("SELECT id_sala FROM pcs WHERE id_pc = ?");
$consulta_pc->bind_param("i", $id_pc);
$consulta_pc->execute();
$pc = $consulta_pc->get_result()->fetch_assoc();

if (!$pc) {
    responder(false, "La PC seleccionada no existe");
}

if ($pc['id_sala'] != $_SESSION['id_sala']) {
    responder(false, "Esta PC no pertenece a tu sala activa");
}

// PASO 3: verificar que no existe un ticket activo para esa PC.
$consulta_ticket = $conexion->prepare(
    "SELECT id_ticket FROM tickets
     WHERE id_pc = ? AND estado IN ('pendiente', 'en_reparacion')"
);
$consulta_ticket->bind_param("i", $id_pc);
$consulta_ticket->execute();
$ticket_activo = $consulta_ticket->get_result()->fetch_assoc();

if ($ticket_activo) {
    responder(false, "Esta PC ya tiene un reporte activo (Ticket #" . $ticket_activo['id_ticket'] . ")");
}

// PASO 4: verificar que tipo_falla no está vacío.
// Lista cerrada de tipos válidos (coincide con el D-04 actualizado:
// sin "PC lenta", con "Falta de software").
$tipos_validos = ['No enciende', 'Falta mouse', 'Falta teclado', 'Sin internet',
                   'Pantalla rota', 'Falta de software', 'Otro'];

if ($tipo_falla === '' || !in_array($tipo_falla, $tipos_validos)) {
    responder(false, "Seleccioná un tipo de falla válido");
}

// PASO 5: verificar detalle_texto si el tipo lo requiere.
if (($tipo_falla === 'Falta de software' || $tipo_falla === 'Otro') && $detalle_texto === '') {
    responder(false, "Especificá el detalle de la falla");
}

// PASO 6: insertar el ticket en la base de datos.
// Usamos NOW() directo en el SQL, nunca una fecha que venga del navegador
// (esto cumple la restricción R-04 del D-04: timestamp del servidor).
$insertar = $conexion->prepare(
    "INSERT INTO tickets (id_pc, id_docente, tipo_falla, detalle_texto, estado, fecha_reporte)
     VALUES (?, ?, ?, ?, 'pendiente', NOW())"
);
$insertar->bind_param("iiss", $id_pc, $_SESSION['id_usuario'], $tipo_falla, $detalle_texto);
$insertar->execute();

// PASO 7: actualizar el estado visual de la PC en el mapa.
$actualizar_pc = $conexion->prepare("UPDATE pcs SET estado_actual = 'falla' WHERE id_pc = ?");
$actualizar_pc->bind_param("i", $id_pc);
$actualizar_pc->execute();

// PASO 8: confirmar al docente.
responder(true, "Falla registrada correctamente. El técnico fue notificado.");
?>