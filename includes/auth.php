<?php
// ============================================================
// SIGRA-ETSR · Componente B
// includes/auth.php — Funciones de autenticación y control de roles
// ============================================================
//
// Este archivo se incluye (require) al principio de TODA página
// protegida del sistema. Centraliza la lógica de sesión para no
// repetirla en cada archivo de public/.

// ── Iniciar la sesión PHP ──
// session_start() debe llamarse ANTES de cualquier salida HTML.
// Si ya hay una sesión iniciada (por ejemplo porque login.php ya la
// inició), PHP no la reinicia, solo continúa usando la existente.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ============================================================
// FUNCIÓN: verificarSesion()
// Corresponde al subproceso VerificarSesion() del pseudocódigo
// del Login (Fase 3). Se llama al inicio de cada página protegida.
// ============================================================
function verificarSesion() {
    // Si no existe id_usuario en la sesión, significa que nadie
    // inició sesión (o la sesión expiró). Se corta la ejecución
    // y se redirige al login.
    if (!isset($_SESSION['id_usuario'])) {
        // El "../" depende de en qué carpeta esté la página que llama
        // a esta función. Se ajusta la ruta en cada página específica
        // (ver nota al final del archivo).
        header("Location: /login.php");
        exit(); // exit() es obligatorio: sin esto, el resto del script
                // seguiría ejecutándose aunque ya mandamos la redirección.
    }
}


// ============================================================
// FUNCIÓN: obtenerRol()
// Devuelve el rol del usuario actualmente logueado.
// Se usa para decidir qué mostrar o qué permitir en cada página.
// ============================================================
function obtenerRol() {
    // Devuelve el rol guardado en sesión, o NULL si no hay sesión activa.
    // El "?? null" evita un error de PHP si la clave no existe todavía.
    return $_SESSION['rol'] ?? null;
}


// ============================================================
// FUNCIÓN: obtenerIdUsuario()
// Devuelve el id_usuario del usuario logueado.
// Se usa, por ejemplo, para saber quién está reportando una falla.
// ============================================================
function obtenerIdUsuario() {
    return $_SESSION['id_usuario'] ?? null;
}


// ============================================================
// FUNCIÓN: obtenerNombreUsuario()
// Devuelve el nombre completo del usuario logueado (para mostrar
// en pantalla, ej: "Bienvenido, Juan Pérez").
// ============================================================
function obtenerNombreUsuario() {
    return $_SESSION['nombre'] ?? null;
}


// ============================================================
// FUNCIÓN: requerirRol($rolesPermitidos)
// Verifica que el usuario logueado tenga uno de los roles permitidos.
// Si no lo tiene, corta la ejecución con un error.
//
// Esto es lo que hace cumplir en código la separación de roles:
// por ejemplo, un docente nunca puede ejecutar actualizar-ticket.php
// porque esa página va a llamar a requerirRol(['tecnico', 'admin'])
// y el docente no está en esa lista.
// ============================================================
function requerirRol($rolesPermitidos) {
    // Primero, siempre verificar que hay sesión activa.
    verificarSesion();

    $rolActual = obtenerRol();

    // in_array() revisa si $rolActual está dentro del array $rolesPermitidos.
    // Ejemplo de uso: requerirRol(['tecnico', 'admin']);
    if (!in_array($rolActual, $rolesPermitidos)) {
        // Se corta la ejecución con un mensaje claro. En una versión más
        // avanzada esto podría redirigir a una página de "Acceso denegado"
        // en vez de mostrar el mensaje crudo, pero para esta fase es
        // suficiente y deja bien visible qué pasó durante las pruebas.
        die("Acceso denegado: esta acción no está permitida para tu rol.");
    }
}


// ============================================================
// FUNCIÓN: cerrarSesion()
// Corresponde a CU-10. Destruye todos los datos de sesión.
// ============================================================
function cerrarSesion() {
    // session_unset() borra todas las variables de la sesión actual.
    session_unset();
    // session_destroy() elimina la sesión completa del servidor.
    session_destroy();
    // Redirige al login después de cerrar sesión.
    header("Location: /login.php");
    exit();
}
?>