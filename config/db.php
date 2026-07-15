<?php
// ============================================================
// SIGRA-ETSR · Componente B
// config/db.php — Conexión centralizada a la base de datos
// ============================================================
//
// Este es el ÚNICO archivo que cambia entre entornos (red interna
// ETSR vs. hosting externo Hostinger). Ninguna otra página del
// sistema debe tener datos de conexión escritos directamente.
// (Cumple R-09 / RNF-09 de portabilidad de entorno).

// ── Datos de conexión para entorno LOCAL (XAMPP) ──
// host: "localhost" porque MySQL corre en la misma máquina que Apache.
// usuario: "root" es el usuario por defecto de XAMPP, sin contraseña.
// contraseña: vacía por defecto en XAMPP. NUNCA dejar esto vacío en
//             un servidor de producción real (Hostinger, etc.).
// base de datos: el nombre exacto que usamos en el script SQL (D-03).

$host       = "localhost";
$usuario_bd = "root";
$clave_bd   = "";
$nombre_bd  = "sigra_etsr";

// ── Crear la conexión usando mysqli (extensión nativa de PHP para MySQL) ──
// Se usa orientado a objetos: $conexion es un objeto con métodos como
// query(), prepare(), etc. que vamos a usar en el resto del sistema.
$conexion = new mysqli($host, $usuario_bd, $clave_bd, $nombre_bd);

// ── Verificar que la conexión fue exitosa ──
// connect_error no es NULO si algo falló (mal usuario, MySQL apagado,
// nombre de base de datos incorrecto, etc.)
if ($conexion->connect_error) {
    // die() detiene la ejecución del script inmediatamente.
    // En producción esto debería loguearse en un archivo, no mostrarse
    // directo en pantalla — pero para la fase de desarrollo local nos
    // sirve ver el error tal cual para depurar rápido.
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

// ── Forzar la codificación de caracteres a UTF-8 ──
// Esto evita que las tildes y la "ñ" se vean rotas (ej: "Á" en vez de "Á").
// Coincide con la codificación utf8mb4 que definimos en el script SQL.
$conexion->set_charset("utf8mb4");

// Nota: NO se cierra la conexión acá ($conexion->close()) porque este
// archivo se va a incluir (require) al principio de cada página que
// necesite hablar con la base de datos. La conexión se cierra sola
// cuando el script de PHP termina de ejecutarse.
?>