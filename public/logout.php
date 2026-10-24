<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/logout.php — CU-10: Cerrar sesión
// ============================================================
// Este archivo no muestra nada en pantalla: solo ejecuta la
// función cerrarSesion() de auth.php, que ya se encarga de
// destruir la sesión y redirigir al login.

require '../includes/auth.php';

cerrarSesion();
?>