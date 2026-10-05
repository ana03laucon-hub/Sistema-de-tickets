<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$error = null;
$exito = null;

$categorias = $pdo->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre')->fetchAll();
$prioridades = $pdo->query('SELECT * FROM prioridades ORDER BY nivel')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario inválida, intente de nuevo.';
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $idCategoria = (int)($_POST['id_categoria'] ?? 0);
        $idPrioridad = (int)($_POST['id_prioridad'] ?? 0);

        if (!validarTexto($titulo, 5, 150)) {
            $error = 'El título debe tener entre 5 y 150 caracteres.';
        } elseif (!validarTexto($descripcion, 10, 2000)) {
            $error = 'La descripción debe tener entre 10 y 2000 caracteres.';
        } elseif (!existeEnCatalogo($pdo, 'categorias', 'id_categoria', $idCategoria)) {
            $error = 'La categoría seleccionada no es válida.';
        } elseif (!existeEnCatalogo($pdo, 'prioridades', 'id_prioridad', $idPrioridad)) {
            $error = 'La prioridad seleccionada no es válida.';
        } else {
            try {
                $nombreArchivo = null;
                if (!empty($_FILES['adjunto']['name'])) {
                    $nombreArchivo = subirArchivo($_FILES['adjunto']);
                }

                // Transacción: el ticket, su historial inicial y las notificaciones
                // deben registrarse de forma atómica (todo o nada) para mantener
                // la integridad y consistencia de la información almacenada.
                $pdo->beginTransaction();

                $folio = generarFolio($pdo);
                $stmt = $pdo->prepare(
                    'INSERT INTO tickets
                        (folio, titulo, descripcion, id_categoria, id_prioridad, id_estado,
                         id_usuario_creador, id_departamento, archivo_adjunto)
                     VALUES
                        (:folio, :titulo, :descripcion, :categoria, :prioridad, 1,
                         :creador, :departamento, :archivo)'
                );
                $stmt->execute([
                    'folio'        => $folio,
                    'titulo'       => limpiarTexto($titulo, 150),
                    'descripcion'  => limpiarTexto($descripcion, 2000),
                    'categoria'    => $idCategoria,
                    'prioridad'    => $idPrioridad,
                    'creador'      => $_SESSION['id_usuario'],
                    'departamento' => $_SESSION['id_departamento'] ?? null,
                    'archivo'      => $nombreArchivo,
                ]);

                $idTicket = (int)$pdo->lastInsertId();
                registrarHistorialEstado($pdo, $idTicket, null, 1, $_SESSION['id_usuario']);

                // Notificar a todos los administradores del nuevo ticket
                $admins = $pdo->query('SELECT id_usuario FROM usuarios WHERE id_rol = 1 AND activo = 1')->fetchAll();
                foreach ($admins as $admin) {
                    crearNotificacion($pdo, $admin['id_usuario'], "Nuevo ticket $folio: $titulo", $idTicket);
                }

                $pdo->commit();

                redirigir('/modules/tickets/detalle.php?id=' . $idTicket . '&creado=1');
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

$tituloPagina = 'Nueva solicitud';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($error): ?><div class="alerta alerta-error"><?= h($error) ?></div><?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" class="formulario">
    <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">

    <label for="titulo">Título de la solicitud *</label>
    <input type="text" id="titulo" name="titulo" maxlength="150" required
           value="<?= h($_POST['titulo'] ?? '') ?>">

    <label for="id_categoria">Categoría *</label>
    <select id="id_categoria" name="id_categoria" required>
        <option value="">Seleccione...</option>
        <?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>"><?= h($c['nombre']) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="id_prioridad">Prioridad *</label>
    <select id="id_prioridad" name="id_prioridad" required>
        <option value="">Seleccione...</option>
        <?php foreach ($prioridades as $p): ?>
            <option value="<?= (int)$p['id_prioridad'] ?>"><?= h($p['nombre']) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="descripcion">Descripción detallada *</label>
    <textarea id="descripcion" name="descripcion" rows="6" required
              placeholder="Describa el problema: equipo, ubicación, mensajes de error, etc."><?= h($_POST['descripcion'] ?? '') ?></textarea>

    <label for="adjunto">Archivo adjunto (opcional, máx. 5 MB)</label>
    <input type="file" id="adjunto" name="adjunto">

    <button type="submit" class="btn btn-primario">Enviar solicitud</button>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
