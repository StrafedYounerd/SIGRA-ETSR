<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/tecnico/actualizar-ticket.php — CU-06: Actualizar estado de ticket
// ============================================================
// Implementa el pseudocódigo PROCESO ActualizarEstadoTicket() de la Fase 3.
// Recibe el formulario de panel.php, procesa, y redirige de vuelta
// al panel con un mensaje de éxito o error (patrón Post-Redirect-Get,
// evita que recargar la página reenvíe el formulario por error).

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['tecnico', 'admin']);

// ── Función auxiliar para redirigir al panel con un mensaje ──
// Evita repetir el header() + exit() en cada validación.
function volverConMensaje($tipo, $texto) {
    $url = "panel.php?tipo=" . urlencode($tipo) . "&msg=" . urlencode($texto);
    header("Location: " . $url);
    exit();
}

// Solo aceptamos POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverConMensaje('error', "Método no permitido");
}

$id_ticket    = trim($_POST['id_ticket'] ?? '');
$nuevo_estado = trim($_POST['nuevo_estado'] ?? '');

// PASO 2 del pseudocódigo: verificar que el nuevo_estado recibido
// es un valor válido. "pendiente" nunca es destino válido, porque
// un ticket nace ahí y nunca se le vuelve a asignar ese valor.
if ($nuevo_estado !== 'en_reparacion' && $nuevo_estado !== 'solucionado') {
    volverConMensaje('error', "Estado no válido");
}

// PASO 3: buscar el ticket en la base de datos.
$consulta_ticket = $conexion->prepare("SELECT * FROM tickets WHERE id_ticket = ?");
$consulta_ticket->bind_param("i", $id_ticket);
$consulta_ticket->execute();
$ticket = $consulta_ticket->get_result()->fetch_assoc();

if (!$ticket) {
    volverConMensaje('error', "El ticket no existe");
}

// PASO 4: verificar que el ticket no está ya solucionado.
if ($ticket['estado'] === 'solucionado') {
    volverConMensaje('error', "Este ticket ya fue solucionado y no puede modificarse");
}

// PASO 5: verificar que el nuevo estado no retrocede.
// Se define el orden numérico de los estados para comparar, igual
// que en el pseudocódigo de la Fase 3.
$orden = [
    'pendiente'     => 1,
    'en_reparacion' => 2,
    'solucionado'   => 3
];

if ($orden[$nuevo_estado] <= $orden[$ticket['estado']]) {
    volverConMensaje('error', "No se puede retroceder el estado de un ticket");
}

// PASO 6: registrar el cambio en historial_tickets, siempre antes
// de modificar la tabla tickets, para mantener la trazabilidad.
// La observación queda vacía por ahora (no había un campo de texto
// en el formulario del panel; se puede agregar más adelante si
// el equipo lo pide).
$observacion = "";

$insertar_historial = $conexion->prepare(
    "INSERT INTO historial_tickets
     (id_ticket, id_usuario, estado_anterior, estado_nuevo, observacion, fecha_cambio)
     VALUES (?, ?, ?, ?, ?, NOW())"
);
$insertar_historial->bind_param(
    "iisss",
    $id_ticket,
    $_SESSION['id_usuario'],
    $ticket['estado'],
    $nuevo_estado,
    $observacion
);
$insertar_historial->execute();

// PASO 7: actualizar el estado del ticket, bifurcando según si es
// "solucionado" (necesita fecha_cierre y liberar la PC) o no.
if ($nuevo_estado === 'solucionado') {

    $actualizar_ticket = $conexion->prepare(
        "UPDATE tickets SET estado = 'solucionado', fecha_cierre = NOW() WHERE id_ticket = ?"
    );
    $actualizar_ticket->bind_param("i", $id_ticket);
    $actualizar_ticket->execute();

    // Actualizar el estado visual de la PC en el mapa del docente.
    $actualizar_pc = $conexion->prepare(
        "UPDATE pcs SET estado_actual = 'funcional' WHERE id_pc = ?"
    );
    $actualizar_pc->bind_param("i", $ticket['id_pc']);
    $actualizar_pc->execute();

} else {

    $actualizar_ticket = $conexion->prepare(
        "UPDATE tickets SET estado = ? WHERE id_ticket = ?"
    );
    $actualizar_ticket->bind_param("si", $nuevo_estado, $id_ticket);
    $actualizar_ticket->execute();

}

// PASO 8: confirmar al técnico, redirigiendo al panel.
volverConMensaje('ok', "Ticket #" . $id_ticket . " actualizado a: " . $nuevo_estado);
?>