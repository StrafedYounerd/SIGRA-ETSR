<?php
// ============================================================
// SIGRA-ETSR · Componente B
// public/docente/seleccionar-sala.php — CU-02: Seleccionar sala
// ============================================================
// Implementa CU-02 de la ESRE. Precondición: CU-01 (login) ya cumplido.

require '../../includes/auth.php';
require '../../config/db.php';
require '../../includes/ui.php';

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
<?php
ui_head([
    'titulo' => 'Cambiar sala',
    'activo' => 'sala',
]);
?>

    <div class="caja caja-ancho-700">
        <h1>Elegí tu sala</h1>
        <p class="nota-sala">Tocá la sala donde estás dando clase y escribí el grupo.</p>

        <?php if ($error !== ''): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($resultado_salas->num_rows === 0): ?>
            <p>No hay salas activas configuradas. Avisale al técnico.</p>
        <?php else: ?>

        <!-- Mismos campos de siempre (id_sala + grupo): el backend no
             distingue si el valor vino de un <select> o de estos radios. -->
        <form method="POST" action="seleccionar-sala.php" class="form-elegir-sala">
            <fieldset class="roomgrid">
                <legend class="sr-only">Sala</legend>
                <?php while ($fila = $resultado_salas->fetch_assoc()): ?>
                <label class="room">
                    <input class="room__input" type="radio" name="id_sala"
                           value="<?php echo $fila['id_sala']; ?>" required>
                    <span class="room__nombre"><?php echo htmlspecialchars($fila['nombre_sala']); ?></span>
                    <span class="room__check" aria-hidden="true"></span>
                </label>
                <?php endwhile; ?>
            </fieldset>

            <label class="campo-grupo" for="grupo">Grupo</label>
            <input type="text" id="grupo" name="grupo" placeholder="Ej: 3 DH" required>

            <button type="submit">Continuar</button>
        </form>

        <?php endif; ?>
    </div>

<?php ui_foot(); ?>