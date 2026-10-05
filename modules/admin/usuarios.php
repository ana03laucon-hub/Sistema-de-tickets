<?php
require_once __DIR__ . '/../../config/config.php';
requerirRol([1]);

$pdo = conectarDB();
$error = null;
$exito = null;

$roles = $pdo->query('SELECT * FROM roles ORDER BY id_rol')->fetchAll();
$departamentos = $pdo->query('SELECT * FROM departamentos WHERE activo = 1 ORDER BY nombre')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario inválida, intente de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre_completo'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';
            $idRol = (int)($_POST['id_rol'] ?? 0);
            $idDepartamento = (int)($_POST['id_departamento'] ?? 0) ?: null;

            if (!validarTexto($nombre, 3, 150)) {
                $error = 'El nombre completo debe tener entre 3 y 150 caracteres.';
            } elseif ($correo !== '' && !validarCorreo($correo)) {
                $error = 'El correo electrónico no tiene un formato válido.';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
                $error = 'El usuario de acceso debe tener entre 3 y 50 caracteres (letras, números, puntos o guiones, sin espacios).';
            } elseif (strlen($contrasena) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif (!existeEnCatalogo($pdo, 'roles', 'id_rol', $idRol)) {
                $error = 'El rol seleccionado no es válido.';
            } elseif ($idDepartamento !== null && !existeEnCatalogo($pdo, 'departamentos', 'id_departamento', $idDepartamento)) {
                $error = 'El departamento seleccionado no es válido.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO usuarios (nombre_completo, correo, usuario, contrasena, id_rol, id_departamento)
                         VALUES (:nombre, :correo, :usuario, :contrasena, :rol, :departamento)'
                    );
                    $stmt->execute([
                        'nombre'      => limpiarTexto($nombre, 150),
                        'correo'      => $correo !== '' ? limpiarTexto($correo, 150) : null,
                        'usuario'     => limpiarTexto($usuario, 50),
                        'contrasena'  => password_hash($contrasena, PASSWORD_BCRYPT),
                        'rol'         => $idRol,
                        'departamento' => $idDepartamento,
                    ]);
                    $exito = 'Usuario creado correctamente.';
                } catch (PDOException $e) {
                    $error = str_contains($e->getMessage(), 'Duplicate')
                        ? 'El usuario o correo ya existe.'
                        : 'Ocurrió un error al crear el usuario.';
                }
            }
        } elseif ($accion === 'cambiar_estatus') {
            $idUsuarioEditar = (int)$_POST['id_usuario'];
            if ($idUsuarioEditar === $_SESSION['id_usuario']) {
                $error = 'No puede desactivar su propia cuenta.';
            } else {
                $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id_usuario = :id')
                    ->execute(['id' => $idUsuarioEditar]);
                $exito = 'Estatus del usuario actualizado.';
            }
        } elseif ($accion === 'restablecer_contrasena') {
            // Requisito: solo el Administrador puede modificar contraseñas,
            // incluidas las de otros usuarios (Técnicos y Usuarios de departamento).
            $idUsuarioEditar = (int)$_POST['id_usuario'];
            $nuevaContrasena = $_POST['nueva_contrasena'] ?? '';

            if (!existeEnCatalogo($pdo, 'usuarios', 'id_usuario', $idUsuarioEditar)) {
                $error = 'El usuario indicado no existe.';
            } elseif (strlen($nuevaContrasena) < 8) {
                $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
            } else {
                $pdo->prepare('UPDATE usuarios SET contrasena = :contrasena WHERE id_usuario = :id')
                    ->execute(['contrasena' => password_hash($nuevaContrasena, PASSWORD_BCRYPT), 'id' => $idUsuarioEditar]);
                $exito = 'Contraseña restablecida correctamente.';
            }
        }
    }
}

$usuarios = $pdo->query(
    'SELECT u.*, r.nombre_rol, d.nombre AS departamento_nombre
     FROM usuarios u
     JOIN roles r ON r.id_rol = u.id_rol
     LEFT JOIN departamentos d ON d.id_departamento = u.id_departamento
     ORDER BY u.fecha_creacion DESC'
)->fetchAll();

$tituloPagina = 'Gestión de usuarios';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($error): ?><div class="alerta alerta-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($exito): ?><div class="alerta alerta-exito"><?= h($exito) ?></div><?php endif; ?>

<section class="panel">
    <h3>Registrar nuevo usuario</h3>
    <p class="texto-suave">
        Se recomienda que el acceso se registre a nombre del departamento
        (ej. "Tesorería", "Catastro"). Un mismo departamento puede tener
        más de un usuario si lo requiere; no es obligatorio que cada
        departamento tenga exactamente uno.
    </p>
    <form method="POST" class="formulario formulario-en-linea">
        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
        <input type="hidden" name="accion" value="crear">

        <input type="text" name="nombre_completo" placeholder="Nombre completo" required>
        <input type="text" name="usuario" placeholder="Usuario de acceso" required>
        <input type="password" name="contrasena" placeholder="Contraseña" required minlength="8">

        <select name="id_rol" required>
            <option value="">Rol</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= (int)$r['id_rol'] ?>"><?= h($r['nombre_rol']) ?></option>
            <?php endforeach; ?>
        </select>

        <input type="email" name="correo" placeholder="Correo institucional (opcional)">

        <select name="id_departamento">
            <option value="">Departamento </option>
            <?php foreach ($departamentos as $d): ?>
                <option value="<?= (int)$d['id_departamento'] ?>"><?= h($d['nombre']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primario">Crear usuario</button>
    </form>
</section>

<section class="panel">
    <h3>Usuarios registrados</h3>
    <table class="tabla">
        <thead>
            <tr><th>Nombre</th><th>Usuario</th><th>Correo</th><th>Rol</th><th>Departamento</th><th>Estatus</th><th></th><th>Restablecer contraseña</th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= h($u['nombre_completo']) ?></td>
                <td><?= h($u['usuario']) ?></td>
                <td><?= h($u['correo'] ?? '—') ?></td>
                <td><?= h($u['nombre_rol']) ?></td>
                <td><?= h($u['departamento_nombre'] ?? '—') ?></td>
                <td><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                        <input type="hidden" name="accion" value="cambiar_estatus">
                        <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                        <button type="submit" class="btn btn-enlace">
                            <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                </td>
                <td>
                    <form method="POST" class="formulario-restablecer">
                        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                        <input type="hidden" name="accion" value="restablecer_contrasena">
                        <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                        <input type="password" name="nueva_contrasena" placeholder="Nueva contraseña" minlength="8" required>
                        <button type="submit" class="btn btn-secundario">Restablecer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
