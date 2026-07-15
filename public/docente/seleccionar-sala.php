<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/docente/seleccionar-sala.php — CU-02: Seleccionar sala
// ============================================================
// Implementa CU-02 de la ESRE. Precondición: CU-01 (login) ya cumplido.

require '../../includes/auth.php';
require '../../config/db.php';

// Verificamos sesión y que el rol sea docente. requerirRol() ya hace
// internamente la llamada a verificarSesion(), no hace falta repetirla.
requerirRol(['docente']);

$error = "";

// ── Traer la lista de salas activas para mostrar en el formulario ──
// Solo mostramos salas con activa = 1 (regla de negocio: no mostrar
// salas dadas de baja).
$resultado_salas = $conexion->query("SELECT id_sala, nombre_sala FROM salas WHERE activa = 1");

// ── Procesar el formulario cuando el docente lo envía ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id_sala = trim($_POST['id_sala'] ?? '');
    $grupo   = trim($_POST['grupo'] ?? '');

    if ($id_sala === '' || $grupo === '') {
        $error = "Seleccioná una sala e ingresá el grupo";
    } else {

        // Verificamos que la sala elegida existe y está activa
        // (protección por si alguien manipula el valor del select).
        $consulta = $conexion->prepare(
            "SELECT id_sala, nombre_sala FROM salas WHERE id_sala = ? AND activa = 1"
        );
        $consulta->bind_param("i", $id_sala); // "i" = el parámetro es un entero (integer)
        $consulta->execute();
        $sala = $consulta->get_result()->fetch_assoc();

        if (!$sala) {
            $error = "La sala seleccionada no es válida";
        } else {
            // Guardamos la sala y el grupo en la sesión del docente.
            // Esto es lo que después usa mapa-sala.php y reportar-falla.php
            // para saber "en qué sala está trabajando este docente ahora".
            $_SESSION['id_sala']      = $sala['id_sala'];
            $_SESSION['nombre_sala']  = $sala['nombre_sala'];
            $_SESSION['grupo']        = $grupo;

            // Registramos la sesión de clase en sesiones_docente,
            // tal como define la tabla del MER (D-02).
            $registro = $conexion->prepare(
                "INSERT INTO sesiones_docente (id_usuario, id_sala, grupo, fecha_entrada)
                 VALUES (?, ?, ?, NOW())"
            );
            $registro->bind_param("iis", $_SESSION['id_usuario'], $sala['id_sala'], $grupo);
            $registro->execute();

            // Redirigimos al mapa de la sala recién seleccionada.
            header("Location: mapa-sala.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SIGRA-ETSR · Seleccionar sala</title>
    <link rel="stylesheet" href="../../css/estilos.css">
</head>
<body>

    <div class="barra-superior">
        <?php echo htmlspecialchars($_SESSION['nombre']); ?> (Docente)
        <a href="../logout.php">Cerrar sesión</a>
    </div>

    <div class="caja">
        <h1>Seleccionar sala</h1>

        <?php if ($error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="seleccionar-sala.php">
            <label for="id_sala">Sala</label>
            <select id="id_sala" name="id_sala">
                <option value="">-- Elegí una sala --</option>
                <?php while ($fila = $resultado_salas->fetch_assoc()): ?>
                    <option value="<?php echo $fila['id_sala']; ?>">
                        <?php echo htmlspecialchars($fila['nombre_sala']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="grupo">Grupo</label>
            <input type="text" id="grupo" name="grupo" placeholder="Ej: 3 DH">

            <button type="submit">Continuar</button>
        </form>
    </div>

</body>
</html>