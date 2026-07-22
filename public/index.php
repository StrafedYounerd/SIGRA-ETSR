<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/index.php — Punto de entrada del sistema
// ============================================================
// Si hay sesión activa, manda al panel correspondiente.
// Si no hay sesión, manda al login. Esto evita que alguien
// entre a la URL raíz del sitio y vea una página en blanco.

require '../includes/auth.php';

if (isset($_SESSION['id_usuario'])) {
    switch ($_SESSION['rol']) {
        case 'docente':
            header("Location: /docente/seleccionar-sala.php");
            break;
        case 'tecnico':
            header("Location: /tecnico/panel.php");
            break;
        case 'admin':
            header("Location: /admin/dashboard.php");
            break;
    }
} else {
    header("Location: /login.php");
}
exit();
?>