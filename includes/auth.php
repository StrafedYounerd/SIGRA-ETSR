<?php
// ============================================================
// SIGRA-ETSR · Componente B
// includes/auth.php — Funciones de autenticación y control de roles
// ============================================================
//
// Este archivo se incluye (require) al principio de TODA página
// protegida del sistema. Centraliza la lógica de sesión para no
// repetirla en cada archivo de public/.

// ── Parámetros de la cookie de sesión ──
// Endurecimiento: HttpOnly (JS no lee la cookie), SameSite=Lax
// (mitiga CSRF en peticiones cross-site) y strict mode (PHP rechaza
// identificadores de sesión que no creó él). La bandera Secure SOLO
// se activa si la petición llegó por HTTPS real: no se confía en
// cabeceras X-Forwarded-*, y hoy el sistema corre por HTTP.
// Se usa en TODOS los lugares que crean o borran la cookie para que
// el navegador las considere la misma (path/samesite/httponly deben
// coincidir exactamente para poder sobrescribir la cookie).
function parametrosCookieSesion() {
    return [
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ];
}

// ── Iniciar la sesión PHP ──
// session_start() debe llamarse ANTES de cualquier salida HTML.
// Si ya hay una sesión iniciada (por ejemplo porque login.php ya la
// inició), PHP no la reinicia, solo continúa usando la existente.
// Los parámetros de cookie (httponly/samesite/secure) deben fijarse
// ANTES de cada session_start() para que apliquen; use_strict_mode
// es una directiva de php.ini, no un parámetro de cookie, así que se
// fija con ini_set().
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(array_merge(
        ['lifetime' => 0, 'path' => '/'],
        parametrosCookieSesion()
    ));
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
        // Ruta absoluta: Apache sirve public/ como DocumentRoot (ver Dockerfile),
        // así que "/login.php" siempre apunta a public/login.php sin importar
        // desde qué subcarpeta (admin/, docente/, tecnico/) se llame esta función.
        $rutaLogin = '/login.php';
        header("Location: $rutaLogin");
        exit(); // exit() es obligatorio: sin esto, el resto del script
                // seguiría ejecutándose aunque ya mandamos la redirección.
    }

    // Verificar timeout de sesión por inactividad (10 minutos = 600 segundos)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 600) {
        // Sesión expirada por inactividad
        session_unset();
        session_destroy();
        // Eliminar cookies de sesión (con los MISMOS parámetros con
        // los que se creó: path/samesite/httponly/secure deben
        // coincidir para que el navegador la sobrescriba de verdad;
        // samesite va en el array de opciones)
        if (ini_get("session.use_cookies")) {
            $params = array_merge(session_get_cookie_params(), parametrosCookieSesion());
            setcookie(session_name(), '', array(
                'expires'  => time() - 42000,
                'path'     => $params["path"],
                'domain'   => $params["domain"],
                'secure'   => $params["secure"],
                'httponly' => $params["httponly"],
                'samesite' => $params['samesite'],
            ));
        }
        header("Location: /login.php?timeout=1");
        exit();
    }
    // Actualizar el timestamp de última actividad
    $_SESSION['last_activity'] = time();
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
    // Borra también la cookie del navegador con los MISMOS parámetros
    // con los que se creó (path/samesite/httponly/secure deben
    // coincidir para que el navegador la sobrescriba de verdad;
    // samesite va en el array de opciones).
    if (ini_get("session.use_cookies")) {
        $params = array_merge(session_get_cookie_params(), parametrosCookieSesion());
        setcookie(session_name(), '', array(
            'expires'  => time() - 42000,
            'path'     => $params["path"],
            'domain'   => $params["domain"],
            'secure'   => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => $params['samesite'],
        ));
    }
    // Redirige al login después de cerrar sesión.
    header("Location: /login.php");
    exit();
}
?>
