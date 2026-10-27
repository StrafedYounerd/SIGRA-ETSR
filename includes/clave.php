<?php
// ============================================================
// SIGRA-ETSR · Endurecimiento de seguridad
// includes/clave.php — Hash y verificación de contraseñas
// ============================================================
//
// Reemplaza el SHA-256 plano (RNF-02 original) por bcrypt vía
// password_hash() / password_verify(), manteniendo compatibilidad
// con los hashes legados que ya viven en la base.
//
// ┌────────────────────────────────────────────────────────┐
// │ IMPORTANTE — migración con reescritura automática:      │
// │ tras el PRIMER login correcto de cada usuario, su hash  │
// │ queda reescrito como bcrypt en la tabla usuarios, y ya   │
// │ NO puede verificarse con el código viejo                │
// │ (hash('sha256', ...) !== ...). Volver al código anterior │
// │ exige restaurar la base desde un respaldo previo a la   │
// │ migración (por eso hay que respaldar ANTES de desplegar).│
// └────────────────────────────────────────────────────────┘

// ── ¿Es un hash bcrypt? ──
// password_hash() con PASSWORD_BCRYPT produce hashes que empiezan
// con "$2y$". Cualquier otra cosa en la columna (hoy: 64 caracteres
// hexadecimales de SHA-256) se trata como hash LEGADO.
function esHashBcrypt($hash) {
    return is_string($hash) && str_starts_with($hash, '$2y$');
}

// ── Crear hash (alta y cambio de contraseña) ──
function hashearContrasena($contrasena) {
    return password_hash($contrasena, PASSWORD_BCRYPT);
}

// ── Verificar contraseña contra el hash guardado ──
// Devuelve true/false. Acepta ambos formatos:
//  - bcrypt  → password_verify (constante en el tiempo interno).
//  - legado  → hash_equals(hash('sha256', ...), $hash), que es
//              constante en el tiempo a diferencia del !== que
//              había antes.
function verificarContrasena($contrasena, $hashGuardado) {
    if (esHashBcrypt($hashGuardado)) {
        return password_verify($contrasena, $hashGuardado);
    }
    // Hash legado (SHA-256 en hex): comparación segura.
    return hash_equals(hash('sha256', $contrasena), (string)$hashGuardado);
}

// ── Migrar hash legado a bcrypt tras un login correcto ──
// UPDATE preparado. Si falla, NO interrumpe el login: se registra
// el error en el log de PHP (error_log) y se sigue; el usuario
// queda logueado con su hash legado intacto y se reintentará en
// el próximo login.
function migrarHashLegado($conexion, $id_usuario, $contrasena) {
    $nuevo = hashearContrasena($contrasena);
    if ($nuevo === false || $nuevo === null) {
        error_log("SIGRA: password_hash devolvió un hash inválido al migrar el usuario id=$id_usuario");
        return false;
    }
    $actualizar = $conexion->prepare(
        "UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?"
    );
    if ($actualizar === false) {
        error_log("SIGRA: fallo prepare() al migrar hash legado del usuario id=$id_usuario: " . $conexion->error);
        return false;
    }
    $actualizar->bind_param("si", $nuevo, $id_usuario);
    if (!$actualizar->execute()) {
        error_log("SIGRA: fallo UPDATE al migrar hash legado del usuario id=$id_usuario: " . $actualizar->error);
        return false;
    }
    return true;
}
?>
