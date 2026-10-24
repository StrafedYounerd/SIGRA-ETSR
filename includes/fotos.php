<?php
// ============================================================
// SIGRA-ETSR · includes/fotos.php — Validación y guardado de imágenes
// ============================================================
// Helper compartido por reportar-falla.php (foto opcional del
// reporte) y guardar-foto-control.php (foto de control).
//
// Reglas de seguridad:
//   * El tipo REAL del archivo se verifica con finfo (no se confía
//     en la extensión ni en el MIME que declara el navegador).
//   * Máximo 5 MB (controlado también en php.ini como segunda capa
//     vía upload_max_filesize del contenedor).
//   * El nombre final es ALEATORIO (bytes random hex) con extensión
//     derivada del tipo real detectado: nunca el nombre del cliente.
//   * Se guarda en public/uploads/fotos/ (directorio sin ejecución).

define('FOTO_MAX_BYTES', 5 * 1024 * 1024); // 5 MB

function validar_y_guardar_foto($archivo_subido) {
    // ── Error de PHP en la subida (tamaño, permisos, etc.) ──
    if (!isset($archivo_subido) || !is_array($archivo_subido)) {
        return ['error' => "No se recibió ninguna foto"];
    }
    if ($archivo_subido['error'] !== UPLOAD_ERR_OK) {
        if ($archivo_subido['error'] === UPLOAD_ERR_INI_SIZE || $archivo_subido['error'] === UPLOAD_ERR_FORM_SIZE) {
            return ['error' => "La foto supera el máximo de 5 MB"];
        }
        return ['error' => "Error al subir la foto (código " . $archivo_subido['error'] . ")"];
    }

    // ── Tamaño (chequeo explícito aunque php.ini ya filtre) ──
    if ($archivo_subido['size'] <= 0) {
        return ['error' => "El archivo está vacío"];
    }
    if ($archivo_subido['size'] > FOTO_MAX_BYTES) {
        return ['error' => "La foto supera el máximo de 5 MB"];
    }

    // ── Tipo REAL del archivo: se lee el contenido con finfo ──
    $tipos_aceptados = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($archivo_subido['tmp_name']);

    if (!isset($tipos_aceptados[$mime])) {
        return ['error' => "El archivo no es una imagen válida (JPEG, PNG, WebP o GIF)"];
    }

    // ── Verificación extra: que el archivo decodifique como imagen real.
    // getimagesize() lee la firma y dimensiones del contenido (no confía
    // en la extensión). Se usa en vez de GD porque el contenedor no lo
    // incluye; finfo + getimagesize cubren archivos disfrazados. ──
    $info = @getimagesize($archivo_subido['tmp_name']);
    if ($info === false || empty($info['mime']) || !isset($tipos_aceptados[$info['mime']])) {
        return ['error' => "La imagen está dañada o no se puede leer"];
    }

    // ── Nombre aleatorio (nunca el que mandó el cliente) ──
    $nombre_final = bin2hex(random_bytes(16)) . '.' . $tipos_aceptados[$mime];

    $directorio = dirname(__DIR__) . '/public/uploads/fotos';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0775, true);
    }

    $destino = $directorio . '/' . $nombre_final;
    if (!move_uploaded_file($archivo_subido['tmp_name'], $destino)) {
        return ['error' => "No se pudo guardar la foto en el servidor"];
    }
    @chmod($destino, 0644);

    return ['archivo' => $nombre_final];
}
