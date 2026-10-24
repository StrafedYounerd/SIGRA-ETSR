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
require '../../includes/fotos.php';

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

// Cuerpo demasiado grande: PHP vacía $_POST/$_FILES (ver includes/fotos.php).
if (subida_excede_post()) {
    responder(false, mensaje_foto_grande());
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
// Lista de tipos válidos (incluye tipos para PC, Switch y TV/Proyector).
$tipos_validos = [
    // Para PC
    'No enciende', 'Falta mouse', 'Falta teclado', 'Sin internet',
    'Pantalla rota', 'Falta de software', 'Otro',
    // Para Switch y TV/Proyector
    'Sin alimentación / No prende', 'Roto / Daño físico',
    'No funcional / No da señal', 'Equipo faltante', 'Otro (especificar)'
];

if ($tipo_falla === '' || !in_array($tipo_falla, $tipos_validos)) {
    responder(false, "Seleccioná un tipo de falla válido");
}

// PASO 5: verificar detalle_texto si el tipo lo requiere.
if (($tipo_falla === 'Falta de software' ||
     $tipo_falla === 'Otro' ||
     $tipo_falla === 'Otro (especificar)') && $detalle_texto === '') {
    responder(false, "Especificá el detalle de la falla");
}

// PASO 7 (opcional): guardar la foto adjunta si el docente eligió una.
// Si la foto falla la validación, el reporte NO se registra: se le avisa
// para que la quite o elija otra (evita tickets sin imagen a medias).
$foto_archivo = null;
if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
    $resultado_foto = validar_y_guardar_foto($_FILES['foto']);
    if (isset($resultado_foto['error'])) {
        responder(false, $resultado_foto['error']);
    }
    $foto_archivo = $resultado_foto['archivo'];
}

// PASOS 8 a 10 en UNA transacción: ticket + estado de la PC + foto.
// Si algo falla no queda un ticket a medias ni un archivo huérfano en disco.
try {
    $conexion->begin_transaction();

    // PASO 8: insertar el ticket. NOW() en el SQL, nunca una fecha del
    // navegador (restricción R-04 del D-04: timestamp del servidor).
    $insertar = $conexion->prepare(
        "INSERT INTO tickets (id_pc, id_docente, tipo_falla, detalle_texto, estado, fecha_reporte)
         VALUES (?, ?, ?, ?, 'pendiente', NOW())"
    );
    $insertar->bind_param("iiss", $id_pc, $_SESSION['id_usuario'], $tipo_falla, $detalle_texto);
    $insertar->execute();
    $id_ticket_nuevo = $insertar->insert_id;

    // PASO 9: actualizar el estado visual de la PC en el mapa.
    $actualizar_pc = $conexion->prepare("UPDATE pcs SET estado_actual = 'falla' WHERE id_pc = ?");
    $actualizar_pc->bind_param("i", $id_pc);
    $actualizar_pc->execute();

    // PASO 10: registrar la foto (si la hay) asociada al ticket recién creado.
    if ($foto_archivo !== null) {
        $registrar_foto = $conexion->prepare(
            "INSERT INTO fotos (id_pc, id_sala, id_ticket, id_docente, tipo, archivo, fecha_subida)
             VALUES (?, ?, ?, ?, 'falla', ?, NOW())"
        );
        $registrar_foto->bind_param("iiiis", $id_pc, $_SESSION['id_sala'], $id_ticket_nuevo, $_SESSION['id_usuario'], $foto_archivo);
        $registrar_foto->execute();
    }

    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    if ($foto_archivo !== null) borrar_foto($foto_archivo);
    error_log("reportar-falla: " . $e->getMessage());
    responder(false, "No se pudo registrar el reporte. Probá de nuevo.");
}

// PASO 11: confirmar al docente (el JS usa id_ticket y foto para actualizar el cajón).
responder(true, "Falla registrada correctamente. El técnico fue notificado.", [
    'id_ticket' => $id_ticket_nuevo,
    'foto'      => $foto_archivo,
]);
?>