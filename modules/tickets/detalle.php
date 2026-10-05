<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$rol = rolActual();
$idUsuario = $_SESSION['id_usuario'];
$idTicket = (int)($_GET['id'] ?? 0);
$error = null;

$stmt = $pdo->prepare(
    "SELECT t.*, e.nombre AS estado_nombre, e.color AS estado_color,
            p.nombre AS prioridad_nombre, p.color AS prioridad_color,
            c.nombre AS categoria_nombre,
            ut.nombre_completo AS tecnico_nombre,
            uc.nombre_completo AS creador_nombre, uc.correo AS creador_correo
     FROM tickets t
     JOIN estados e ON e.id_estado = t.id_estado
     JOIN prioridades p ON p.id_prioridad = t.id_prioridad
     JOIN categorias c ON c.id_categoria = t.id_categoria
     JOIN usuarios uc ON uc.id_usuario = t.id_usuario_creador
     LEFT JOIN usuarios ut ON ut.id_usuario = t.id_tecnico_asignado
     WHERE t.id_ticket = :id"
);
$stmt->execute(['id' => $idTicket]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    die('Ticket no encontrado.');
}

// Control de acceso: la información de una solicitud solo debe estar
// disponible para usuarios autorizados (administrador, el técnico
// asignado, o el usuario que la creó).
if (!usuarioPuedeVerTicket($pdo, $idTicket, $idUsuario, $rol)) {
    http_response_code(403);
    die('No tiene permiso para ver este ticket.');
}

$estados = $pdo->query('SELECT * FROM estados ORDER BY id_estado')->fetchAll();
$tecnicos = $rol === 1
    ? $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE id_rol = 2 AND activo = 1 ORDER BY nombre_completo")->fetchAll()
    : [];

// ------------------------------------------------------------
// Acciones (POST)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Sesión de formulario inválida, intente de nuevo.';
    } else {
        $accion = $_POST['accion'] ?? '';

        try {
            // Todas las acciones que modifican datos se ejecutan dentro de una
            // transacción: si notificar o registrar el historial fallara, el
            // cambio de estado tampoco se guarda (integridad y consistencia).
            $pdo->beginTransaction();

            // --- Asignar técnico (solo administrador) ---
            if ($accion === 'asignar' && $rol === 1) {
                $idTecnico = (int)($_POST['id_tecnico'] ?? 0);
                $tecnicoValido = $pdo->prepare('SELECT COUNT(*) AS total FROM usuarios WHERE id_usuario = :id AND id_rol = 2 AND activo = 1');
                $tecnicoValido->execute(['id' => $idTecnico]);
                if ((int)$tecnicoValido->fetch()['total'] === 0) {
                    throw new RuntimeException('El técnico seleccionado no es válido.');
                }

                $pdo->prepare(
                    "UPDATE tickets SET id_tecnico_asignado = :tecnico, id_estado = 2, fecha_asignacion = NOW()
                     WHERE id_ticket = :id"
                )->execute(['tecnico' => $idTecnico, 'id' => $idTicket]);

                registrarHistorialEstado($pdo, $idTicket, (int)$ticket['id_estado'], 2, $idUsuario);
                crearNotificacion($pdo, $idTecnico, "Se te asignó el ticket {$ticket['folio']}", $idTicket);
                crearNotificacion($pdo, (int)$ticket['id_usuario_creador'], "Tu ticket {$ticket['folio']} fue asignado a un técnico", $idTicket);

            // --- Cambiar estado (técnico o administrador) ---
            } elseif ($accion === 'cambiar_estado' && in_array($rol, [1, 2], true)) {
                $nuevoEstado = (int)$_POST['id_estado'];
                if (!existeEnCatalogo($pdo, 'estados', 'id_estado', $nuevoEstado)) {
                    throw new RuntimeException('El estado seleccionado no es válido.');
                }

                if ($nuevoEstado === 4) {
                    // Requerimiento funcional: registrar la solución aplicada a la solicitud.
                    $solucion = trim($_POST['solucion'] ?? '');
                    if (!validarTexto($solucion, 10, 2000)) {
                        throw new RuntimeException('Debe describir la solución aplicada (mínimo 10 caracteres) para marcar el ticket como resuelto.');
                    }
                    $pdo->prepare('UPDATE tickets SET id_estado = :estado, solucion = :solucion, fecha_resolucion = NOW() WHERE id_ticket = :id')
                        ->execute(['estado' => $nuevoEstado, 'solucion' => limpiarTexto($solucion, 2000), 'id' => $idTicket]);
                } else {
                    $pdo->prepare('UPDATE tickets SET id_estado = :estado WHERE id_ticket = :id')
                        ->execute(['estado' => $nuevoEstado, 'id' => $idTicket]);
                }

                registrarHistorialEstado($pdo, $idTicket, (int)$ticket['id_estado'], $nuevoEstado, $idUsuario);
                crearNotificacion($pdo, (int)$ticket['id_usuario_creador'], "Tu ticket {$ticket['folio']} cambió de estado", $idTicket);

            // --- Cerrar ticket con calificación (solo el usuario que lo creó) ---
            } elseif ($accion === 'cerrar' && $rol === 3 && (int)$ticket['id_usuario_creador'] === $idUsuario) {
                $calificacion = (int)($_POST['calificacion'] ?? 0);
                if ($calificacion < 1 || $calificacion > 5) {
                    throw new RuntimeException('La calificación debe ser un valor entre 1 y 5.');
                }
                $comentarioCierre = limpiarTexto(trim($_POST['comentario_cierre'] ?? ''), 1000);

                $pdo->prepare(
                    "UPDATE tickets SET id_estado = 5, fecha_cierre = NOW(), calificacion = :cal, comentario_cierre = :com
                     WHERE id_ticket = :id"
                )->execute(['cal' => $calificacion, 'com' => $comentarioCierre, 'id' => $idTicket]);

                registrarHistorialEstado($pdo, $idTicket, (int)$ticket['id_estado'], 5, $idUsuario);

            // --- Reabrir ticket (solo el usuario que lo creó, si está resuelto) ---
            } elseif ($accion === 'reabrir' && $rol === 3 && (int)$ticket['id_usuario_creador'] === $idUsuario) {
                $pdo->prepare("UPDATE tickets SET id_estado = 6 WHERE id_ticket = :id")->execute(['id' => $idTicket]);
                registrarHistorialEstado($pdo, $idTicket, (int)$ticket['id_estado'], 6, $idUsuario);
                if ($ticket['id_tecnico_asignado']) {
                    crearNotificacion($pdo, (int)$ticket['id_tecnico_asignado'], "El ticket {$ticket['folio']} fue reabierto", $idTicket);
                }

            // --- Agregar comentario (cualquiera con acceso al ticket) ---
            } elseif ($accion === 'comentar') {
                $comentario = trim($_POST['comentario'] ?? '');
                if (!validarTexto($comentario, 1, 2000)) {
                    throw new RuntimeException('El comentario no puede estar vacío y debe tener como máximo 2000 caracteres.');
                }
                $nombreArchivo = null;
                if (!empty($_FILES['adjunto']['name'])) {
                    $nombreArchivo = subirArchivo($_FILES['adjunto']);
                }
                $pdo->prepare(
                    'INSERT INTO comentarios (id_ticket, id_usuario, comentario, archivo_adjunto)
                     VALUES (:ticket, :usuario, :comentario, :archivo)'
                )->execute([
                    'ticket' => $idTicket, 'usuario' => $idUsuario,
                    'comentario' => limpiarTexto($comentario, 2000), 'archivo' => $nombreArchivo,
                ]);

                // Notificar a la otra parte involucrada
                $destinatario = ($idUsuario === (int)$ticket['id_usuario_creador'])
                    ? (int)$ticket['id_tecnico_asignado']
                    : (int)$ticket['id_usuario_creador'];
                if ($destinatario) {
                    crearNotificacion($pdo, $destinatario, "Nuevo comentario en el ticket {$ticket['folio']}", $idTicket);
                }
            }

            $pdo->commit();
            redirigir('/modules/tickets/detalle.php?id=' . $idTicket);
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// Recargar ticket por si hubo cambios
$stmt->execute(['id' => $idTicket]);
$ticket = $stmt->fetch();

$comentarios = $pdo->prepare(
    "SELECT co.*, u.nombre_completo, u.id_rol
     FROM comentarios co
     JOIN usuarios u ON u.id_usuario = co.id_usuario
     WHERE co.id_ticket = :id
     ORDER BY co.fecha_creacion ASC"
);
$comentarios->execute(['id' => $idTicket]);
$comentarios = $comentarios->fetchAll();

$historial = $pdo->prepare(
    "SELECT h.*, ea.nombre AS estado_anterior_nombre, en.nombre AS estado_nuevo_nombre, u.nombre_completo
     FROM historial_estados h
     LEFT JOIN estados ea ON ea.id_estado = h.id_estado_anterior
     JOIN estados en ON en.id_estado = h.id_estado_nuevo
     JOIN usuarios u ON u.id_usuario = h.id_usuario
     WHERE h.id_ticket = :id
     ORDER BY h.fecha ASC"
);
$historial->execute(['id' => $idTicket]);
$historial = $historial->fetchAll();

$tituloPagina = $ticket['folio'] . ' — ' . $ticket['titulo'];
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (isset($_GET['creado'])): ?>
    <div class="alerta alerta-exito">Su solicitud fue registrada correctamente con el folio <?= h($ticket['folio']) ?>.</div>
<?php endif; ?>
<?php if ($error): ?><div class="alerta alerta-error"><?= h($error) ?></div><?php endif; ?>

<div class="detalle-encabezado">
    <?= badgeEstado($ticket['estado_nombre'], $ticket['estado_color']) ?>
</div>

<section class="grid-2">
    <div class="panel">
        <h3>Información de la solicitud</h3>
        <p><strong>Categoría:</strong> <?= h($ticket['categoria_nombre']) ?></p>
        <p><strong>Prioridad:</strong> <span class="badge" style="color:<?= h($ticket['prioridad_color']) ?>"><?= h($ticket['prioridad_nombre']) ?></span></p>
        <p><strong>Solicitante:</strong> <?= h($ticket['creador_nombre']) ?><?= $ticket['creador_correo'] ? ' (' . h($ticket['creador_correo']) . ')' : '' ?></p>
        <p><strong>Técnico asignado:</strong> <?= h($ticket['tecnico_nombre'] ?? 'Sin asignar') ?></p>
        <p><strong>Creado:</strong> <?= formatoFecha($ticket['fecha_creacion']) ?></p>
        <p><strong>Asignado:</strong> <?= formatoFecha($ticket['fecha_asignacion']) ?></p>
        <p><strong>Resuelto:</strong> <?= formatoFecha($ticket['fecha_resolucion']) ?></p>
        <p><strong>Cerrado:</strong> <?= formatoFecha($ticket['fecha_cierre']) ?></p>
        <p><strong>Descripción:</strong></p>
        <p><?= nl2br(h($ticket['descripcion'])) ?></p>
        <?php if (!empty($ticket['solucion'])): ?>
            <p><strong>Solución aplicada:</strong></p>
            <p class="caja-solucion"><?= nl2br(h($ticket['solucion'])) ?></p>
        <?php endif; ?>
        <?php if ($ticket['archivo_adjunto']): ?>
            <p><a href="<?= BASE_URL ?>/modules/tickets/descargar.php?archivo=<?= h($ticket['archivo_adjunto']) ?>" target="_blank">Ver archivo adjunto original</a></p>
        <?php endif; ?>
        <?php if ($ticket['calificacion']): ?>
            <p><strong>Calificación del usuario:</strong> <?= str_repeat('★', (int)$ticket['calificacion']) . str_repeat('☆', 5 - (int)$ticket['calificacion']) ?></p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Acciones</h3>

        <?php if ($rol === 1 && !$ticket['id_tecnico_asignado']): ?>
            <form method="POST" class="formulario">
                <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                <input type="hidden" name="accion" value="asignar">
                <label>Asignar a técnico</label>
                <select name="id_tecnico" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= (int)$t['id_usuario'] ?>"><?= h($t['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primario">Asignar</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($rol, [1, 2], true) && !in_array((int)$ticket['id_estado'], [5], true)): ?>
            <form method="POST" class="formulario" id="form-cambiar-estado">
                <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                <input type="hidden" name="accion" value="cambiar_estado">
                <label>Cambiar estado</label>
                <select name="id_estado" id="select-estado" required>
                    <?php foreach ($estados as $e): ?>
                        <option value="<?= (int)$e['id_estado'] ?>" <?= (int)$ticket['id_estado'] === (int)$e['id_estado'] ? 'selected' : '' ?>>
                            <?= h($e['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="campo-solucion" style="display:none">
                    <label for="solucion">Solución aplicada (obligatoria para marcar como Resuelto)</label>
                    <textarea name="solucion" id="solucion" rows="3" placeholder="Describa la causa del problema y la solución aplicada..."></textarea>
                </div>
                <button type="submit" class="btn btn-secundario">Actualizar estado</button>
            </form>
            <script>
                (function () {
                    var select = document.getElementById('select-estado');
                    var campo = document.getElementById('campo-solucion');
                    var textarea = document.getElementById('solucion');
                    function actualizar() {
                        var esResuelto = select.value === '4';
                        campo.style.display = esResuelto ? 'block' : 'none';
                        textarea.required = esResuelto;
                    }
                    select.addEventListener('change', actualizar);
                    actualizar();
                })();
            </script>
        <?php endif; ?>

        <?php if ($rol === 3 && (int)$ticket['id_usuario_creador'] === $idUsuario): ?>
            <?php if ((int)$ticket['id_estado'] === 4): ?>
                <form method="POST" class="formulario">
                    <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                    <input type="hidden" name="accion" value="cerrar">
                    <label>Calificar y cerrar solicitud</label>
                    <select name="calificacion" required>
                        <option value="5">★★★★★ Excelente</option>
                        <option value="4">★★★★ Buena</option>
                        <option value="3">★★★ Regular</option>
                        <option value="2">★★ Mala</option>
                        <option value="1">★ Muy mala</option>
                    </select>
                    <textarea name="comentario_cierre" rows="3" placeholder="Comentario final (opcional)"></textarea>
                    <button type="submit" class="btn btn-primario">Confirmar y cerrar</button>
                </form>
                <form method="POST" class="formulario">
                    <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
                    <input type="hidden" name="accion" value="reabrir">
                    <button type="submit" class="btn btn-alerta">No quedó resuelto, reabrir</button>
                </form>
            <?php else: ?>
                <p class="texto-suave">Podrá calificar y cerrar la solicitud una vez que el técnico la marque como resuelta.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h3>Historial de estatus</h3>
    <ul class="linea-tiempo">
        <?php foreach ($historial as $h): ?>
            <li>
                <strong><?= h($h['nombre_completo']) ?></strong>
                cambió de <em><?= h($h['estado_anterior_nombre'] ?? 'N/A') ?></em>
                a <em><?= h($h['estado_nuevo_nombre']) ?></em>
                — <?= formatoFecha($h['fecha']) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="panel">
    <h3>Comentarios y seguimiento</h3>
    <div class="lista-comentarios">
        <?php foreach ($comentarios as $c): ?>
            <div class="comentario <?= (int)$c['id_rol'] === 3 ? 'comentario-usuario' : 'comentario-staff' ?>">
                <strong><?= h($c['nombre_completo']) ?></strong>
                <span class="texto-suave"><?= formatoFecha($c['fecha_creacion']) ?></span>
                <p><?= nl2br(h($c['comentario'])) ?></p>
                <?php if ($c['archivo_adjunto']): ?>
                    <a href="<?= BASE_URL ?>/modules/tickets/descargar.php?archivo=<?= h($c['archivo_adjunto']) ?>" target="_blank">Ver adjunto</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (empty($comentarios)): ?>
            <p class="texto-suave">Aún no hay comentarios en este ticket.</p>
        <?php endif; ?>
    </div>

    <form method="POST" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="csrf_token" value="<?= h(generarTokenCSRF()) ?>">
        <input type="hidden" name="accion" value="comentar">
        <textarea name="comentario" rows="3" placeholder="Escriba un comentario o actualización..." required></textarea>
        <input type="file" name="adjunto">
        <button type="submit" class="btn btn-primario">Agregar comentario</button>
    </form>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
