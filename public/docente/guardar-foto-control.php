<?php
// ============================================================
// SIGRA-ETSR · public/docente/guardar-foto-control.php
// ============================================================
// Foto de CONTROL: registra cómo está un equipo (o la sala completa,
// sin equipo seleccionado) SIN reportar una falla. No crea ticket y
// NO cambia el estado del equipo en el mapa.
// Devuelve JSON (lo llama el JavaScript del cajón con fetch()).

require '../../includes/auth.php';
require '../../config/db.php';
require '../../includes/fotos.php';

requerirRol(['docente']);

header('Content-Type: application/json');

function responder_foto($exito, $mensaje, $datosExtra = []) {
    echo json_encode(array_merge(['exito' => $exito, 'mensaje' => $mensaje], $datosExtra));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_foto(false, "Método no permitido");
}

if (!isset($_SESSION['id_sala'])) {
    responder_foto(false, "No hay una sala activa en tu sesión");
}

if (subida_excede_post()) {
    responder_foto(false, mensaje_foto_grande());
}

// ── id_pc puede venir vacío: foto de control de la sala completa ──
$id_pc = trim($_POST['id_pc'] ?? '');

if ($id_pc !== '') {
    // Si viene un equipo, verificar que existe y pertenece a la sala activa.
    $consulta_pc = $conexion->prepare("SELECT id_sala FROM pcs WHERE id_pc = ?");
    $consulta_pc->bind_param("i", $id_pc);
    $consulta_pc->execute();
    $pc = $consulta_pc->get_result()->fetch_assoc();

    if (!$pc) {
        responder_foto(false, "El equipo seleccionado no existe");
    }
    if ($pc['id_sala'] != $_SESSION['id_sala']) {
        responder_foto(false, "Este equipo no pertenece a tu sala activa");
    }
}

// ── La foto es obligatoria acá (no hay reporte de texto) ──
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
    responder_foto(false, "Elegí una foto para guardar");
}

$resultado_foto = validar_y_guardar_foto($_FILES['foto']);
if (isset($resultado_foto['error'])) {
    responder_foto(false, $resultado_foto['error']);
}
$archivo = $resultado_foto['archivo'];

// ── Registrar: id_pc NULL si es de la sala completa; sin ticket ──
$id_pc_db = ($id_pc !== '') ? (int)$id_pc : null;
// id_sala: sin esto la foto de la sala completa no se podía atribuir a ninguna.
try {
    $registrar = $conexion->prepare(
        "INSERT INTO fotos (id_pc, id_sala, id_ticket, id_docente, tipo, archivo, fecha_subida)
         VALUES (?, ?, NULL, ?, 'control', ?, NOW())"
    );
    $registrar->bind_param("iiis", $id_pc_db, $_SESSION['id_sala'], $_SESSION['id_usuario'], $archivo);
    $registrar->execute();
} catch (Throwable $e) {
    borrar_foto($archivo);
    error_log("guardar-foto-control: " . $e->getMessage());
    responder_foto(false, "No se pudo guardar la foto. Probá de nuevo.");
}

responder_foto(true, "Foto de control guardada.", [
    'archivo' => $archivo,
    'fecha'   => date('d/m/Y H:i'),
]);
?>
