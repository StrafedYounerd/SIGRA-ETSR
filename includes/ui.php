<?php
// ============================================================
// SIGRA-ETSR · Componente B
// includes/ui.php — Shell común: topbar (escritorio) + tabbar (móvil)
// ============================================================
// Partial de PRESENTACIÓN que llama cada vista interna. Solo LEE
// variables ya existentes ($_SESSION['nombre'], ['rol'], etc.);
// no toca la sesión, no valida permisos (eso sigue haciendo
// requerirRol() en auth.php), no consulta la base de datos.
//
// Uso desde una vista:
//   require '../../includes/ui.php';
//   ui_head([
//       'titulo'  => 'Sala 210',
//       'activo'  => 'mapa',              // clave de navegación activa
//       'datos'   => ['Grupo <b>3 DH</b>'],  // (opcional) spans de contexto, HTML
//       'vivo'    => true,                // (opcional) indicador "Actualizado hace X s"
//   ]);
//   ... contenido propio de la vista ...
//   ui_foot();
//
// Navegación: en escritorio es la fila de pestañas de la topbar;
// en pantallas de hasta 720px (css/shell.css) la misma lista pasa
// a una barra inferior fija (.tabbar). "Cerrar sesión" vive dentro
// del menú de usuario en ambos tamaños.

/**
 * Imprime <!DOCTYPE> ... topbar + tabbar + apertura del contenido.
 * $datos se recibe como array de strings HTML listas para imprimir.
 */
function ui_head($opciones) {
    $titulo_vista = $opciones['titulo'] ?? 'SIGRA-ETSR';
    $activo       = $opciones['activo'] ?? '';
    $datos        = $opciones['datos'] ?? [];
    $vivo         = $opciones['vivo'] ?? false;
    // Clase extra para .contenido-vista (p. ej. 'vista-mapa' en el plano
    // de sala, que necesita ocupar todo el alto sin padding).
    $clase_vista  = $opciones['clase'] ?? '';

    $nombre = htmlspecialchars($_SESSION['nombre'] ?? '');
    $rol    = $_SESSION['rol'] ?? '';

    // ── Navegación por rol (solo presentación: mismos href que ya usaban las vistas) ──
    $navegacion = [
        'docente' => [
            'mapa'    => ['Mapa de sala', '/docente/mapa-sala.php'],
            'sala'    => ['Cambiar sala', '/docente/seleccionar-sala.php'],
        ],
        'tecnico' => [
            'panel'     => ['Tickets activos', '/tecnico/panel.php'],
            'historial' => ['Historial completo', '/tecnico/historial.php'],
        ],
        'admin' => [
            'panel'  => ['Panel', '/admin/dashboard.php'],
            'usuarios' => ['Usuarios', '/admin/usuarios.php',
                ['/admin/usuarios.php?accion=crear', '/admin/usuarios.php?accion=editar']],
            'salas'  => ['Salas y dispositivos', '/admin/salas.php',
                ['/admin/salas.php?accion=crear', '/admin/salas.php?accion=editar', '/admin/salas.php?accion=equipo']],
            'historial' => ['Historial completo', '/admin/historial.php'],
            'estadisticas' => ['Estadísticas', '/admin/estadisticas.php'],
        ],
    ];

    $rol_config = $navegacion[$rol] ?? [];

    $roles_texto = ['docente' => 'Docente', 'tecnico' => 'Técnico', 'admin' => 'Administrador'];
    $rol_texto   = $roles_texto[$rol] ?? ucfirst($rol);

    // Iniciales para el avatar (primera letra del nombre + del apellido si hay).
    $partes_nombre = array_values(array_filter(explode(' ', trim($_SESSION['nombre'] ?? '')), 'strlen'));
    $iniciales = mb_strtoupper(mb_substr($partes_nombre[0] ?? '?', 0, 1)
        . (isset($partes_nombre[1]) ? mb_substr($partes_nombre[1], 0, 1) : ''));

    // Página actual (para marcar la pestaña activa). Con REQUEST_URI
    // cubre también las acciones ?accion=... del admin, igual que el
    // array de $extras de abajo.
    $ruta_actual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
    <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
    <title>SIGRA-ETSR · <?php echo htmlspecialchars($titulo_vista); ?></title>
    <link rel="stylesheet" href="/css/theme.css?v=6">
    <link rel="stylesheet" href="/css/estilos.css?v=7">
    <link rel="stylesheet" href="/css/mapa.css?v=9">
    <link rel="stylesheet" href="/css/shell.css?v=2"><!-- topbar + tabbar + usermenu + roomgrid -->
</head>
<body class="cuerpo-app">

<header class="topbar">
    <a class="brand" href="/">
        <img src="/img/logo-accessnet.png" alt="Logo de AccessNet">
        <span>SIGRA-ETSR</span>
    </a>

    <nav class="topnav" aria-label="Principal">
<?php foreach ($rol_config as $clave => $item): list($texto, $href, $extras) = [$item[0], $item[1], $item[2] ?? []];
            $es_activa = ($clave === $activo)
                || in_array($ruta_actual, $extras, true);
?>
        <a href="<?php echo $href; ?>"<?php echo $es_activa ? ' class="is-active" aria-current="page"' : ''; ?>><?php echo $texto; ?></a>
<?php endforeach; ?>
    </nav>

    <div class="usermenu">
        <button class="usermenu__btn" type="button" aria-expanded="false" aria-controls="um-panel">
            <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars($iniciales); ?></span>
            <span class="usermenu__who"><strong><?php echo $nombre; ?></strong><small><?php echo $rol_texto; ?></small></span>
        </button>
        <div class="usermenu__panel" id="um-panel" hidden>
            <div class="usermenu__head"><strong><?php echo $nombre; ?></strong><small><?php echo $rol_texto; ?></small></div>
            <a href="/logout.php">Cerrar sesión</a>
        </div>
    </div>
</header>

<nav class="tabbar" aria-label="Principal (móvil)">
<?php foreach ($rol_config as $clave => $item): list($texto, $href, $extras) = [$item[0], $item[1], $item[2] ?? []];
        $es_activa = ($clave === $activo)
            || in_array($ruta_actual, $extras, true);
?>
    <a href="<?php echo $href; ?>"<?php echo $es_activa ? ' class="is-active"' : ''; ?>><?php echo $texto; ?></a>
<?php endforeach; ?>
</nav>

<main class="contenido-app">
        <header class="contexto">
            <h1><?php echo htmlspecialchars($titulo_vista); ?></h1>
<?php foreach ($datos as $dato): ?>
            <span class="dato"><?php echo $dato; ?></span>
<?php endforeach; ?>
<?php if ($vivo): ?>
            <span class="vivo" aria-live="off">Actualizado hace <b id="hace">0</b> s</span>
<?php endif; ?>
        </header>
        <div class="contenido-vista<?php echo $clase_vista !== '' ? ' ' . htmlspecialchars($clase_vista) : ''; ?>">

<?php
}

/** Cierra el app. */
function ui_foot() {
?>
        </div>
</main>

<script src="/js/shell.js"></script>
</body>
</html>
<?php
}
