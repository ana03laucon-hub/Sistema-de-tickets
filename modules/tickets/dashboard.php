<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$idUsuario = $_SESSION['id_usuario'];
$rol = rolActual();

// ------------------------------------------------------------
// Estadísticas según el rol
// ------------------------------------------------------------
if ($rol === 1) {
    // Administrador: panorama general de todo el Ayuntamiento
    $stats = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM tickets) AS total,
            (SELECT COUNT(*) FROM tickets WHERE id_estado IN (1,2,3,6)) AS abiertos,
            (SELECT COUNT(*) FROM tickets WHERE id_estado = 4) AS resueltos,
            (SELECT COUNT(*) FROM tickets WHERE id_estado = 5) AS cerrados,
            (SELECT COUNT(*) FROM tickets WHERE id_prioridad = 4 AND id_estado NOT IN (4,5)) AS urgentes"
    )->fetch();

    $porTecnico = $pdo->query(
        "SELECT u.nombre_completo, COUNT(t.id_ticket) AS total
         FROM usuarios u
         LEFT JOIN tickets t ON t.id_tecnico_asignado = u.id_usuario AND t.id_estado NOT IN (4,5)
         WHERE u.id_rol = 2
         GROUP BY u.id_usuario
         ORDER BY total DESC"
    )->fetchAll();

    $porCategoria = $pdo->query(
        "SELECT c.nombre, COUNT(t.id_ticket) AS total
         FROM categorias c
         LEFT JOIN tickets t ON t.id_categoria = c.id_categoria
         GROUP BY c.id_categoria
         ORDER BY total DESC"
    )->fetchAll();

} elseif ($rol === 2) {
    // Técnico: sus tickets asignados
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN id_estado IN (2,3,6) THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN id_estado = 4 THEN 1 ELSE 0 END) AS resueltos,
            SUM(CASE WHEN id_prioridad = 4 AND id_estado NOT IN (4,5) THEN 1 ELSE 0 END) AS urgentes
         FROM tickets WHERE id_tecnico_asignado = :id"
    );
    $stmt->execute(['id' => $idUsuario]);
    $stats = $stmt->fetch();

} else {
    // Usuario final: sus propias solicitudes
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN id_estado IN (1,2,3,6) THEN 1 ELSE 0 END) AS abiertos,
            SUM(CASE WHEN id_estado = 4 THEN 1 ELSE 0 END) AS resueltos,
            SUM(CASE WHEN id_estado = 5 THEN 1 ELSE 0 END) AS cerrados
         FROM tickets WHERE id_usuario_creador = :id"
    );
    $stmt->execute(['id' => $idUsuario]);
    $stats = $stmt->fetch();
}

// Equipos listos para entrega segun el rol
if($rol ===3){
    $stmtEquipo =$pdo->prepare("SELECT COUNT(*) AS total FROM equipos_soporte WHERE id_usuario_entrega = :id AND estado = 'Listo para entrega'");
    $stmtEquipo->execute(['id' => $idUsuario]);
} else{
    $stmtEquipo = $pdo->query("SELECT COUNT(*) AS total FROM equipos_soporte WHERE estado = 'Listo para entrega'");
}
$equiposListos =(int)$stmtEquipo->fetch()['total'];

// Últimos tickets relevantes para el usuario en sesión
if ($rol === 1) {
    $ultimos = $pdo->query(
        "SELECT t.*, e.nombre AS estado_nombre, e.color AS estado_color, p.nombre AS prioridad_nombre
         FROM tickets t
         JOIN estados e ON e.id_estado = t.id_estado
         JOIN prioridades p ON p.id_prioridad = t.id_prioridad
         ORDER BY t.fecha_creacion DESC LIMIT 8"
    )->fetchAll();
} elseif ($rol === 2) {
    $stmt = $pdo->prepare(
        "SELECT t.*, e.nombre AS estado_nombre, e.color AS estado_color, p.nombre AS prioridad_nombre
         FROM tickets t
         JOIN estados e ON e.id_estado = t.id_estado
         JOIN prioridades p ON p.id_prioridad = t.id_prioridad
         WHERE t.id_tecnico_asignado = :id
         ORDER BY t.fecha_creacion DESC LIMIT 8"
    );
    $stmt->execute(['id' => $idUsuario]);
    $ultimos = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare(
        "SELECT t.*, e.nombre AS estado_nombre, e.color AS estado_color, p.nombre AS prioridad_nombre
         FROM tickets t
         JOIN estados e ON e.id_estado = t.id_estado
         JOIN prioridades p ON p.id_prioridad = t.id_prioridad
         WHERE t.id_usuario_creador = :id
         ORDER BY t.fecha_creacion DESC LIMIT 8"
    );
    $stmt->execute(['id' => $idUsuario]);
    $ultimos = $stmt->fetchAll();
}

$tituloPagina = 'Panel principal';
$subtituloPagina = 'Bienvenido(a), ' . $_SESSION['nombre_completo'] . ' - ' . $_SESSION['nombre_rol'];
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($rol === 3): ?>
<section class="banner-ayuda">
    <div>
        <strong>¿Tu equipo tiene un problema sencillo?</strong>
        <p>Antes de registrar una solicitud, revisa la guía rápida: puede resolverse en un par de minutos.</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/tickets/ayuda.php" class="btn btn-secundario">Ver guía rápida</a>
</section>
<?php endif; ?>

<section class="tarjetas-stats">
    <?php if ($rol === 1): ?>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['total'] ?></span><span>Total de tickets</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['abiertos'] ?></span><span>Abiertos</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['resueltos'] ?></span><span>Resueltos</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['cerrados'] ?></span><span>Cerrados</span></div>
        <div class="tarjeta tarjeta-alerta"><span class="tarjeta-num"><?= (int)$stats['urgentes'] ?></span><span>Urgentes activos</span></div>
    <?php elseif ($rol === 2): ?>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['total'] ?></span><span>Asignados a mí</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['pendientes'] ?></span><span>Pendientes</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['resueltos'] ?></span><span>Resueltos</span></div>
        <div class="tarjeta tarjeta-alerta"><span class="tarjeta-num"><?= (int)$stats['urgentes'] ?></span><span>Urgentes</span></div>
    <?php else: ?>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['total'] ?></span><span>Mis solicitudes</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['abiertos'] ?></span><span>Abiertas</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['resueltos'] ?></span><span>Resueltas</span></div>
        <div class="tarjeta"><span class="tarjeta-num"><?= (int)$stats['cerrados'] ?></span><span>Cerradas</span></div>
    <?php endif; ?>
    <div class = "tarjeta <?= $equiposListos > 0 ? 'tarjeta-alerta' : '' ?>"> 
        <span class="tarjeta-num"><?= $equiposListos ?></span>
        <span>Equipos listos para entrega</span>
    </div>
</section>

<?php if ($rol === 1): ?>
<section class="grid-2">
    <div class="panel">
        <h3>Carga de trabajo por técnico</h3>
        <table class="tabla">
            <thead><tr><th>Técnico</th><th>Tickets activos</th></tr></thead>
            <tbody>
            <?php foreach ($porTecnico as $t): ?>
                <tr><td><?= h($t['nombre_completo']) ?></td><td><?= (int)$t['total'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="panel">
        <h3>Tickets por categoría</h3>
        <table class="tabla">
            <thead><tr><th>Categoría</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($porCategoria as $c): ?>
                <tr><td><?= h($c['nombre']) ?></td><td><?= (int)$c['total'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h3>Últimos tickets</h3>
    <table class="tabla">
        <thead>
            <tr><th>Folio</th><th>Título</th><th>Prioridad</th><th>Estado</th><th>Fecha</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($ultimos as $t): ?>
            <tr>
                <td><?= h($t['folio']) ?></td>
                <td><?= h($t['titulo']) ?></td>
                <td><?= h($t['prioridad_nombre']) ?></td>
                <td><?= badgeEstado($t['estado_nombre'], $t['estado_color']) ?></td>
                <td><?= formatoFecha($t['fecha_creacion']) ?></td>
                <td><a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= (int)$t['id_ticket'] ?>">Ver</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($ultimos)): ?>
            <tr><td colspan="6">Sin registros por el momento.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
