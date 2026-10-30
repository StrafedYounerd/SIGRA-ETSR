<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/admin/plano-guardar.php — CU-09: Guardar PCs en lote
// ============================================================
// Recibe la lista de lugares libres que el admin eligió en el
// editor de plano y los guarda como PCs nuevas de esa sala.
//
// Reglas:
//   - Es TODO o NADA: si un equipo falla la validación, no se
//     inserta ninguno (transacción + rollback).
//   - El servidor revalida TODO: que la sala exista y esté activa,
//     que fila/posición sean válidos, que no haya códigos repetidos
//     ni lugares ya ocupados, y que no se pase del tope de
//     posiciones por fila (MAX_POR_FILA = 8).
//   - Los chequeos contra la base se hacen DENTRO de la transacción
//     con SELECT ... FOR UPDATE: la fila de la sala queda bloqueada,
//     así dos admins que guarden a la vez no pueden pisarse (uno
//     espera, relee el estado real y ve el lugar ya ocupado).
//   - No se confía en nada de lo que manda el navegador: las
//     coordenadas del plano y el tope se recalculan en el servidor.

require '../../includes/auth.php';
require '../../config/db.php';

requerirRol(['admin']);

header('Content-Type: application/json; charset=utf-8');

// ── Solo POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

// ── La sala tiene que estar indicada ──
$id_sala = intval($_POST['id_sala'] ?? 0);
if ($id_sala <= 0) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Sala no indicada']);
    exit();
}

// ── Lugares elegidos ──
// Vienen como JSON: [{etiqueta, fila, posicion}, ...]
$crudo = trim($_POST['lugares'] ?? '');
$lugares = $crudo !== '' ? json_decode($crudo, true) : [];
if (!is_array($lugares)) $lugares = [];

// ── Equipos a eliminar ──
// Vienen como JSON: [id_pc, id_pc, ...]
$crudo_eliminados = trim($_POST['eliminados'] ?? '[]');
$eliminados = json_decode($crudo_eliminados, true);
if (!is_array($eliminados)) $eliminados = [];

if (empty($lugares) && empty($eliminados)) {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'No se envió ninguna acción']);
    exit();
}

// ── Tope de posiciones por fila (mismo número que js/layout-plano.js) ──
$max_por_fila = 8;   // debe coincidir con MAX_POR_FILA de
                      // js/layout-plano.js (y con $max_por_fila de
                      // public/admin/plano-editor.php).

// ── Normalización de la etiqueta ──
// La etiqueta que llega del navegador se normaliza SIEMPRE antes de
// validar el largo y de comparar duplicados:
//   1) se recortan los espacios de los extremos;
//   2) los espacios internos se colapsan en uno solo;
//   3) el prefijo "PC-" va en mayúsculas y se agrega si falta
//      ("pc-7281" → "PC-7281", "9999" → "PC-9999").
// Solo el prefijo se pasa a mayúsculas: el resto de la etiqueta se
// respeta tal como la escribió el usuario.
// Es la MISMA normalización que hace js/plano-editor.js: el servidor
// es la autoridad y no confía en lo que mande el navegador.
function normalizarEtiqueta($texto) {
    $t = trim((string)$texto);
    // Colapsar los espacios internos (incluidos tabs y saltos de línea).
    $t = preg_replace('/\s+/u', ' ', $t);
    if ($t === '') {
        return '';
    }
    if (preg_match('/^PC-/i', $t)) {
        // Solo el prefijo pasa a mayúsculas.
        return 'PC-' . mb_substr($t, 3);
    }
    return 'PC-' . $t;
}

// ── Validación de cada equipo (sin tocar la base todavía) ──
$errores = [];
$vistos = [];        // equipos válidos de esta tanda
$ocupados = [];      // "fila:posicion" ya usadas en ESTA tanda
$vistos_labels = []; // etiquetas ya vistas en ESTA tanda

foreach ($lugares as $i => $lugar) {
    $etiqueta = normalizarEtiqueta($lugar['etiqueta'] ?? '');
    $fila     = intval($lugar['fila'] ?? 0);
    $posicion = intval($lugar['posicion'] ?? 0);

    if ($etiqueta === '') {
        $errores[] = "Equipo #$i: falta la etiqueta";
        continue;
    }
    if (mb_strlen($etiqueta) > 20) {
        $errores[] = "Equipo \"$etiqueta\": la etiqueta no puede pasar de 20 caracteres";
        continue;
    }
    if (!in_array($fila, [1, 2, 3], true)) {
        $errores[] = "Equipo \"$etiqueta\": fila inválida";
        continue;
    }
    if ($posicion < 1 || $posicion > $max_por_fila) {
        $errores[] = "Equipo \"$etiqueta\": la posición debe estar entre 1 y $max_por_fila";
        continue;
    }

    $clave_lugar = "$fila:$posicion";
    if (isset($ocupados[$clave_lugar])) {
        $errores[] = "Equipo \"$etiqueta\": el lugar fila $fila posición $posicion está repetido";
        continue;
    }
    $ocupados[$clave_lugar] = true;

    $clave_label = mb_strtolower($etiqueta);
    if (isset($vistos_labels[$clave_label])) {
        $errores[] = "Equipo \"$etiqueta\": esa etiqueta ya está en la lista";
        continue;
    }
    $vistos_labels[$clave_label] = true;

    $vistos[] = [
        'etiqueta' => $etiqueta,
        'fila'     => $fila,
        'posicion' => $posicion,
    ];
}

if ($errores) {
    http_response_code(422);
    echo json_encode([
        'exito'   => false,
        'mensaje' => implode('. ', $errores),
        'errores' => $errores,
    ]);
    exit();
}

// ── Guardado: TODO O NADA, con bloqueo de la sala ──
// begin_transaction() + SELECT ... FOR UPDATE sobre la fila de salas:
// desde acá hasta el commit, la sala queda bloqueada para otros
// guardados concurrentes. Todos los chequeos contra la base se
// rehacen DENTRO de la transacción con el estado ya bloqueado.
$conexion->begin_transaction();
try {
    // (1) Bloquear y releer la sala (pudo desactivarse mientras el
    //     admin tenía el editor abierto).
    $consulta_sala = $conexion->prepare(
        "SELECT id_sala, nombre_sala FROM salas WHERE id_sala = ? AND activa = 1 FOR UPDATE"
    );
    if (!$consulta_sala) {
        throw new Exception('No se pudo preparar el bloqueo de la sala: ' . $conexion->error);
    }
    $consulta_sala->bind_param("i", $id_sala);
    $consulta_sala->execute();
    $sala = $consulta_sala->get_result()->fetch_assoc();
    if (!$sala) {
        $conexion->rollback();
        http_response_code(404);
        echo json_encode(['exito' => false, 'mensaje' => 'La sala no existe o está desactivada']);
        exit();
    }

    // (2) Cada lugar tiene que seguir libre (bloqueado con FOR UPDATE).
    $consulta_ocupados = $conexion->prepare(
        "SELECT etiqueta FROM pcs
         WHERE id_sala = ? AND tipo_equipo = 'PC' AND fila = ? AND posicion = ?
         FOR UPDATE"
    );
    if (!$consulta_ocupados) {
        throw new Exception('No se pudo preparar el chequeo de lugares: ' . $conexion->error);
    }
    foreach ($vistos as $v) {
        $consulta_ocupados->bind_param("iii", $id_sala, $v['fila'], $v['posicion']);
        $consulta_ocupados->execute();
        $existente = $consulta_ocupados->get_result()->fetch_assoc();
        if ($existente) {
            $errores[] = "El lugar fila {$v['fila']} posición {$v['posicion']} ya lo ocupa "
                       . $existente['etiqueta'];
        }
    }
    if ($errores) {
        $conexion->rollback();
        http_response_code(422);
        echo json_encode([
            'exito'   => false,
            'mensaje' => implode('. ', $errores),
            'errores' => $errores,
        ]);
        exit();
    }

    // (3) La etiqueta no puede existir ya en esta sala (bloqueada con FOR UPDATE).
    $consulta_etiqueta = $conexion->prepare(
        "SELECT etiqueta FROM pcs
         WHERE id_sala = ? AND LOWER(etiqueta) = LOWER(?)
         FOR UPDATE"
    );
    if (!$consulta_etiqueta) {
        throw new Exception('No se pudo preparar el chequeo de etiquetas: ' . $conexion->error);
    }
    foreach ($vistos as $v) {
        $consulta_etiqueta->bind_param("is", $id_sala, $v['etiqueta']);
        $consulta_etiqueta->execute();
        if ($consulta_etiqueta->get_result()->fetch_assoc()) {
            $errores[] = "La etiqueta \"{$v['etiqueta']}\" ya existe en esta sala";
        }
    }
    if ($errores) {
        $conexion->rollback();
        http_response_code(422);
        echo json_encode([
            'exito'   => false,
            'mensaje' => implode('. ', $errores),
            'errores' => $errores,
        ]);
        exit();
    }

    // (4) No pasarse del tope por fila contando lo que ya hay.
    //     Se bloquean TODAS las PCs de la sala con FOR UPDATE y el
    //     máximo por fila se calcula en PHP (GROUP BY + FOR UPDATE
    //     no es soportado por MariaDB).
    $consulta_totales = $conexion->prepare(
        "SELECT fila, posicion FROM pcs
         WHERE id_sala = ? AND tipo_equipo = 'PC'
         FOR UPDATE"
    );
    if (!$consulta_totales) {
        throw new Exception('No se pudo preparar el conteo por fila: ' . $conexion->error);
    }
    $consulta_totales->bind_param("i", $id_sala);
    $consulta_totales->execute();
    $maximos = [];
    $resultado_totales = $consulta_totales->get_result();
    while ($fila = $resultado_totales->fetch_assoc()) {
        $f = (int)$fila['fila'];
        $maximos[$f] = max($maximos[$f] ?? 0, (int)$fila['posicion']);
    }
    foreach ($vistos as $v) {
        $f = $v['fila'];
        $maximos[$f] = max($maximos[$f] ?? 0, $v['posicion']);
    }
    foreach ($maximos as $f => $max) {
        if ($max > $max_por_fila) {
            $errores[] = "La fila $f llegaría a la posición $max y el máximo es $max_por_fila";
        }
    }
    if ($errores) {
        $conexion->rollback();
        http_response_code(422);
        echo json_encode([
            'exito'   => false,
            'mensaje' => implode('. ', $errores),
            'errores' => $errores,
        ]);
        exit();
    }

    // (5) Insertar TODO dentro de la misma transacción.
    $insertar = $conexion->prepare(
        "INSERT INTO pcs (id_sala, etiqueta, tipo_equipo, fila, posicion, estado_actual)
         VALUES (?, ?, 'PC', ?, ?, 'sin_eval')"
    );
    if (!$insertar) {
        throw new Exception('No se pudo preparar el guardado: ' . $conexion->error);
    }
    foreach ($vistos as $v) {
        $insertar->bind_param("isii", $id_sala, $v['etiqueta'], $v['fila'], $v['posicion']);
        if (!$insertar->execute()) {
            throw new Exception("No se pudo guardar \"{$v['etiqueta']}\": " . $insertar->error);
        }
    }

    // (6) Eliminar equipos marcados, con verificación de integridad.
    if (!empty($eliminados)) {
        // Verificar que ninguna PC a eliminar tenga tickets activos.
        $consulta_tickets = $conexion->prepare(
            "SELECT id_pc FROM tickets WHERE id_pc = ? AND estado IN ('pendiente', 'en_reparacion')"
        );
        if (!$consulta_tickets) {
            throw new Exception('No se pudo preparar la verificación de tickets: ' . $conexion->error);
        }
        foreach ($eliminados as $id_pc) {
            $consulta_tickets->bind_param("i", $id_pc);
            $consulta_tickets->execute();
            if ($consulta_tickets->get_result()->fetch_assoc()) {
                throw new Exception("No se puede eliminar la PC #{$id_pc} porque tiene tickets activos");
            }
        }

        // Eliminar dentro de la misma transacción.
        $eliminar = $conexion->prepare(
            "DELETE FROM pcs WHERE id_pc = ? AND id_sala = ?"
        );
        if (!$eliminar) {
            throw new Exception('No se pudo preparar la eliminación: ' . $conexion->error);
        }
        foreach ($eliminados as $id_pc) {
            $eliminar->bind_param("ii", $id_pc, $id_sala);
            if (!$eliminar->execute()) {
                throw new Exception("No se pudo eliminar la PC #{$id_pc}: " . $eliminar->error);
            }
        }
    }

    // (6) Recién acá se hace efectivo: un solo commit atómico.
    $conexion->commit();
} catch (Exception $e) {
    // Cualquier fallo deja la base exactamente como estaba.
    $conexion->rollback();
    http_response_code(500);
    echo json_encode([
        'exito'   => false,
        'mensaje' => 'No se guardó ningún equipo: ' . $e->getMessage(),
    ]);
    exit();
}

echo json_encode([
    'exito' => true,
    'mensaje' =>
        (count($vistos) > 0 && count($eliminados) > 0)
            ? 'Se agregaron ' . count($vistos) . ' equipos y se eliminaron ' . count($eliminados) . ' equipos de ' . $sala['nombre_sala']
            : (count($vistos) > 0
                ? (count($vistos) === 1 ? 'Se agregó 1 equipo a ' . $sala['nombre_sala'] : 'Se agregaron ' . count($vistos) . ' equipos a ' . $sala['nombre_sala'])
                : 'Se eliminaron ' . count($eliminados) . ' equipos de ' . $sala['nombre_sala']
            ),
    'total' => count($vistos),
    'eliminados' => count($eliminados),
]);
