<?php
// ============================================================
// SIGRA-ETSR · Componente B
// includes/ui.php — Estructura común del app: rail lateral + franja de contexto
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

/**
 * Imprime <!DOCTYPE> ... apertura del app (rail + principal/contexto).
 * $datos se recibe como array de strings HTML listas para imprimir.
 */
function ui_head($opciones) {
    $titulo_vista = $opciones['titulo'] ?? 'SIGRA-ETSR';
    $activo       = $opciones['activo'] ?? '';
    $datos        = $opciones['datos'] ?? [];
    $vivo         = $opciones['vivo'] ?? false;

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
    $rol_texto = $roles_texto[$rol] ?? ucfirst($rol);

    // ── Links del usuario (los mismos destinos que las barras actuales) ──
    $link_volver = '';
    if (isset($_SERVER['HTTP_REFERER'])) {
        $link_volver = '<a href="javascript:history.back()">Volver atrás</a> ';
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
    <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
    <title>SIGRA-ETSR · <?php echo htmlspecialchars($titulo_vista); ?></title>
    <link rel="stylesheet" href="/css/theme.css">
    <link rel="stylesheet" href="/css/estilos.css?v=5">
    <link rel="stylesheet" href="/css/mapa.css?v=7"><!-- acá viven .app/.rail/.contexto -->
</head>
<body class="cuerpo-app">

<div class="app">

    <aside class="rail">
        <div class="marca">
            <span class="tile"><img src="/img/logo-accessnet.png" alt="AccessNet"></span>
            <span class="sis">SIGRA-ETSR</span>
        </div>
        <nav aria-label="Principal">
<?php foreach ($rol_config as $clave => $item): list($texto, $href, $extras) = [$item[0], $item[1], $item[2] ?? []];
                $es_activa = ($clave === $activo)
                    || in_array(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), $extras, true);
?>
            <a href="<?php echo $href; ?>"<?php echo $es_activa ? ' aria-current="page"' : ''; ?>><?php echo $texto; ?></a>
<?php endforeach; ?>
        </nav>
        <div class="usuario">
            <b><?php echo $nombre; ?></b><?php echo $rol_texto; ?><br>
            <a href="/logout.php">Cerrar sesión</a>
            <?php echo $link_volver; ?>
        </div>
    </aside>

    <div class="principal">
        <header class="contexto">
            <h1><?php echo htmlspecialchars($titulo_vista); ?></h1>
<?php foreach ($datos as $dato): ?>
            <span class="dato"><?php echo $dato; ?></span>
<?php endforeach; ?>
<?php if ($vivo): ?>
            <span class="vivo" aria-live="off">Actualizado hace <b id="hace">0</b> s</span>
<?php endif; ?>
        </header>
        <div class="contenido-vista">

<?php
}

/** Cierra el app. */
function ui_foot() {
?>
        </div>
    </div>
</div>

</body>
</html>
<?php
}
