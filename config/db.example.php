<?php
// ============================================================
// SIGRA-ETSR · Plantilla de configuración de base de datos
// config/db.example.php
// ============================================================
//
// Copiar este archivo a config/db.php y completar los valores.
// db.php está ignorado por git; este archivo es la plantilla
// versionada.
//
// Los valores pueden venir de variables de entorno (las define
// docker-compose.yml) o escribirse directo como fallback, igual
// que en config/db.php. Con docker-compose alcanza con copiar
// el archivo sin editar nada si el .env ya tiene los valores.

$host     = getenv('MYSQL_HOST')     ?: "COMPLETAR_HOST";
$dbname   = getenv('MYSQL_DATABASE') ?: "COMPLETAR_BASE";
$user     = getenv('MYSQL_USER')     ?: "COMPLETAR_USUARIO";
$password = getenv('MYSQL_PASSWORD') ?: "COMPLETAR_PASSWORD";

$conn = new mysqli($host, $user, $password, $dbname);
$conexion = $conn;

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
