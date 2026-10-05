<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$idUsuario = $_SESSION['id_usuario'];
$rol = rolActual();
$error = null;
$exito = null;

$stmt = $pdo->prepare(
    'SELECT u.*, r.nombre_rol, d.nombre AS departamento_nombre
     FROM usuarios u
     JOIN roles r ON r.id_rol = u.id_rol
     LEFT JOIN departamentos d ON d.id_departamento = u.id_departamento
     WHERE u.id_usuario = :id'
);
$stmt->execute(['id' => $idUsuario]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario inválida, intente de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'datos') {
            $nombre = trim($_POST['nombre_completo'] ?? '');
            $correo = trim($_POST['correo'] ?? '');

            if (!validarTexto($nombre, 3, 150)) {
                $error = 'El nombre completo debe tener entre 3 y 150 caracteres.';
            } elseif ($correo !== '' && !validarCorreo($correo)) {
                $error = 'El correo electrónico no tiene un formato válido.';
            } else {
                try {
                    $pdo->prepare(
                        'UPDATE usuarios SET nombre_completo = :nombre, correo = :correo
                         WHERE id_usuario = :id'
                    )->execute([
                        'nombre' => limpiarTexto($nombre, 150),
                        'correo' => $correo !== '' ? limpiarTexto($correo, 150) : null,
                        'id' => $idUsuario,
                    ]);
                    $_SESSION['nombre_completo'] = limpiarTexto($nombre, 150);
                    $exito = 'Tus datos se actualizaron correctamente.';
                } catch (PDOException $e) {
                    $error = str_contains($e->getMessage(), 'Duplicate')
                        ? 'Ese correo ya está en uso por otra cuenta.'
                        : 'Ocurrió un error al actualizar tus datos.';
                }
            }
        } elseif ($accion === 'contrasena' && $rol === 1) {
            // Requisito: únicamente el Administrador puede modificar contraseñas,
            // incluyendo la propia. Técnicos y Usuarios no tienen esta opción.
            $actual = $_POST['contrasena_actual'] ?? '';
            $nueva = $_POST['contrasena_nueva'] ?? '';
            $confirmar = $_POST['contrasena_confirmar'] ?? '';

            if (!password_verify($actual, $usuario['contrasena'])) {
                $error = 'La contraseña actual no es correcta.';
            } elseif (strlen($nueva) < 8) {
                $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } elseif ($nueva !== $confirmar) {
                $error = 'La confirmación no coincide con la nueva contraseña.';
            } else {
                $pdo->prepare('UPDATE usuarios SET contrasena = :contrasena WHERE id_usuario = :id')
                    ->execute(['contrasena' => password_hash($nueva, PASSWORD_BCRYPT), 'id' => $idUsuario]);
                $exito = 'Tu contraseña se actualizó correctamente.';
            }
        }

        // Recargar datos tras cualquier actualización
        $stmt->execute(['id' => $idUsuario]);
        $usuario = $stmt->fetch();
    }
}

$tituloPagina = 'Mi perfil';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($error): ?><div class="alerta alerta-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($exito): ?><div class="alerta alerta-exito"><?= h($exito) ?></div><?php endif; ?>

<section class="grid-2">
    <div class="panel">
        <h3>Datos de la cuenta</h3>
        <p><strong>Usuario de acceso:</strong> <?= h($usuario['usuario']) ?> <span class="texto-suave">(no se puede modificar)</span></p>
        <p><strong>Rol:</strong> <?= h($usuario['nombre_rol']) ?></p>
        <p><strong>Departamento:</strong> <?= h($usuario['departamento_nombre'] ?? 'Sin asignar') ?></p>

        <form method="POST" class="formulario">
            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
            <input type="hidden" name="accion" value="datos">

            <label for="nombre_completo">Nombre completo</label>
            <input type="text" id="nombre_completo" name="nombre_completo" required
                   value="<?= h($usuario['nombre_completo']) ?>">

            <label for="correo">Correo institucional (opcional)</label>
            <input type="email" id="correo" name="correo" value="<?= h($usuario['correo'] ?? '') ?>">

            <button type="submit" class="btn btn-primario">Guardar cambios</button>
        </form>
    </div>

    <?php if ($rol === 1): ?>
    <div class="panel">
        <h3>Cambiar mi contraseña</h3>
        <p class="texto-suave">Solo el Administrador puede modificar contraseñas. Para restablecer la contraseña de un Técnico o Usuario, hazlo desde "Usuarios".</p>
        <form method="POST" class="formulario">
            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
            <input type="hidden" name="accion" value="contrasena">

            <label for="contrasena_actual">Contraseña actual</label>
            <input type="password" id="contrasena_actual" name="contrasena_actual" required>

            <label for="contrasena_nueva">Nueva contraseña</label>
            <input type="password" id="contrasena_nueva" name="contrasena_nueva" required minlength="8">

            <label for="contrasena_confirmar">Confirmar nueva contraseña</label>
            <input type="password" id="contrasena_confirmar" name="contrasena_confirmar" required minlength="8">

            <button type="submit" class="btn btn-secundario">Actualizar contraseña</button>
        </form>
    </div>
    <?php else: ?>
    <div class="panel">
        <h3>Contraseña</h3>
        <p class="texto-suave">Por política del sistema, solo el Administrador puede cambiar tu contraseña. Si necesitas restablecerla, solicítalo a la Coordinación de Gobierno Electrónico.</p>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
