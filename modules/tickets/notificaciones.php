<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$idUsuario = $_SESSION['id_usuario'];

// Marcar todas como leídas al visitar la página
$pdo->prepare('UPDATE notificaciones SET leida = 1 WHERE id_usuario = :id')->execute(['id' => $idUsuario]);

$stmt = $pdo->prepare(
    'SELECT * FROM notificaciones WHERE id_usuario = :id ORDER BY fecha_creacion DESC LIMIT 50'
);
$stmt->execute(['id' => $idUsuario]);
$notificaciones = $stmt->fetchAll();

$tituloPagina = 'Notificaciones';
require_once __DIR__ . '/../../includes/header.php';
?>

<ul class="lista-notificaciones">
    <?php foreach ($notificaciones as $n): ?>
        <li>
            <?php if (!empty($n['id_equipo'])): ?>
                <a href="<?= BASE_URL ?>/modules/tickets/areaEntrega.php"><?= h($n['mensaje']) ?></a>
            <?php elseif (!empty($n['id_ticket'])): ?>
                <a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= (int)$n['id_ticket'] ?>"><?= h($n['mensaje']) ?></a>
            <?php else: ?>
                <?= h($n['mensaje']) ?>
            <?php endif; ?>
            <span class="texto-suave"> — <?= formatoFecha($n['fecha_creacion']) ?></span>
        </li>
    <?php endforeach; ?>
    <?php if (empty($notificaciones)): ?>
        <li class="texto-suave">No tiene notificaciones.</li>
    <?php endif; ?>
</ul>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
