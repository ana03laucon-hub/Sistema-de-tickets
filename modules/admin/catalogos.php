<?php
require_once __DIR__ . '/../../config/config.php';
requerirRol([1]);

$pdo = conectarDB();
$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario inválida, intente de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'crear_categoria') {
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            if (!validarTexto($nombre, 3, 100)) {
                $error = 'El nombre de la categoría debe tener entre 3 y 100 caracteres.';
            } else {
                $pdo->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (:nombre, :descripcion)')
                    ->execute(['nombre' => limpiarTexto($nombre, 100), 'descripcion' => limpiarTexto($descripcion, 255)]);
                $exito = 'Categoría agregada.';
            }
        } elseif ($accion === 'toggle_categoria') {
            $pdo->prepare('UPDATE categorias SET activo = NOT activo WHERE id_categoria = :id')
                ->execute(['id' => (int)$_POST['id']]);
            $exito = 'Categoría actualizada.';
        } elseif ($accion === 'crear_departamento') {
            $nombre = trim($_POST['nombre_departamento'] ?? '');
            if (!validarTexto($nombre, 3, 100)) {
                $error = 'El nombre del departamento debe tener entre 3 y 100 caracteres.';
            } else {
                $pdo->prepare('INSERT INTO departamentos (nombre) VALUES (:nombre)')->execute(['nombre' => limpiarTexto($nombre, 100)]);
                $exito = 'Departamento agregado.';
            }
        } elseif ($accion === 'toggle_departamento') {
            $pdo->prepare('UPDATE departamentos SET activo = NOT activo WHERE id_departamento = :id')
                ->execute(['id' => (int)$_POST['id']]);
            $exito = 'Departamento actualizado.';
        }
    }
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
$departamentos = $pdo->query('SELECT * FROM departamentos ORDER BY nombre')->fetchAll();
$roles = $pdo->query(
    "SELECT r.id_rol, r.nombre_rol, COUNT(u.id_usuario) AS total_usuarios
     FROM roles r LEFT JOIN usuarios u ON u.id_rol = r.id_rol
     GROUP BY r.id_rol ORDER BY r.id_rol"
)->fetchAll();
$prioridades = $pdo->query('SELECT * FROM prioridades ORDER BY nivel')->fetchAll();

$tituloPagina = 'Catálogos';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($error): ?><div class="alerta alerta-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($exito): ?><div class="alerta alerta-exito"><?= h($exito) ?></div><?php endif; ?>

<section class="grid-2">
    <div class="panel">
        <h3>Categorías de soporte</h3>
        <form method="POST" class="formulario formulario-en-linea">
            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
            <input type="hidden" name="accion" value="crear_categoria">
            <input type="text" name="nombre" placeholder="Nombre de la categoría" required>
            <input type="text" name="descripcion" placeholder="Descripción breve">
            <button type="submit" class="btn btn-primario">Agregar</button>
        </form>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Descripción</th><th>Estatus</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?= h($c['nombre']) ?></td>
                    <td><?= h($c['descripcion']) ?></td>
                    <td><?= $c['activo'] ? 'Activa' : 'Inactiva' ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                            <input type="hidden" name="accion" value="toggle_categoria">
                            <input type="hidden" name="id" value="<?= (int)$c['id_categoria'] ?>">
                            <button type="submit" class="btn btn-enlace"><?= $c['activo'] ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>Departamentos / áreas</h3>
        <form method="POST" class="formulario formulario-en-linea">
            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
            <input type="hidden" name="accion" value="crear_departamento">
            <input type="text" name="nombre_departamento" placeholder="Nombre del departamento" required>
            <button type="submit" class="btn btn-primario">Agregar</button>
        </form>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Estatus</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($departamentos as $d): ?>
                <tr>
                    <td><?= h($d['nombre']) ?></td>
                    <td><?= $d['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                            <input type="hidden" name="accion" value="toggle_departamento">
                            <input type="hidden" name="id" value="<?= (int)$d['id_departamento'] ?>">
                            <button type="submit" class="btn btn-enlace"><?= $d['activo'] ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="grid-2">
    <div class="panel">
        <h3>Roles del sistema</h3>
        <p class="texto-suave">Los roles son fijos porque forman parte de la lógica de permisos del sistema; no se crean ni eliminan desde aquí, solo se muestran a modo de consulta.</p>
        <table class="tabla">
            <thead><tr><th>Rol</th><th>Usuarios con este rol</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><?= h($r['nombre_rol']) ?></td>
                    <td><?= (int)$r['total_usuarios'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>Niveles de prioridad</h3>
        <p class="texto-suave">Catálogo fijo utilizado para clasificar la urgencia de cada solicitud.</p>
        <table class="tabla">
            <thead><tr><th>Prioridad</th><th>Nivel</th></tr></thead>
            <tbody>
            <?php foreach ($prioridades as $p): ?>
                <tr>
                    <td><span style="color:<?= h($p['color']) ?>; font-weight:600"><?= h($p['nombre']) ?></span></td>
                    <td><?= (int)$p['nivel'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
