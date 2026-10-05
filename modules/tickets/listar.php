<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$rol = rolActual();
$idUsuario = $_SESSION['id_usuario'];

$estados = $pdo->query('SELECT * FROM estados ORDER BY id_estado')->fetchAll();
$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
$tecnicos = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE id_rol = 2 AND activo = 1 ORDER BY nombre_completo")->fetchAll();

// ------------------------------------------------------------
// Filtros (todos opcionales, vía GET)
// ------------------------------------------------------------
$filtroEstado = (int)($_GET['estado'] ?? 0);
$filtroCategoria = (int)($_GET['categoria'] ?? 0);
$filtroTecnico = (int)($_GET['tecnico'] ?? 0);
$busqueda = trim($_GET['q'] ?? '');

$condiciones = [];
$parametros = [];

// Restricción según rol: cada quien ve lo que le corresponde
if ($rol === 2) {
    $condiciones[] = 't.id_tecnico_asignado = :id_usuario';
    $parametros['id_usuario'] = $idUsuario;
} elseif ($rol === 3) {
    $condiciones[] = 't.id_usuario_creador = :id_usuario';
    $parametros['id_usuario'] = $idUsuario;
}

if ($filtroEstado > 0) {
    $condiciones[] = 't.id_estado = :estado';
    $parametros['estado'] = $filtroEstado;
}
if ($filtroCategoria > 0) {
    $condiciones[] = 't.id_categoria = :categoria';
    $parametros['categoria'] = $filtroCategoria;
}
if ($filtroTecnico > 0 && $rol === 1) {
    $condiciones[] = 't.id_tecnico_asignado = :tecnico';
    $parametros['tecnico'] = $filtroTecnico;
}
if ($busqueda !== '') {
    $condiciones[] = '(t.folio LIKE :busqueda OR t.titulo LIKE :busqueda)';
    $parametros['busqueda'] = '%' . $busqueda . '%';
}

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// ------------------------------------------------------------
// Paginación (5 resultados por página)
// ------------------------------------------------------------
$porPagina = 5;
$paginaActualNum = max(1, (int)($_GET['pagina'] ?? 1));

$sqlTotal = "SELECT COUNT(*) AS total FROM tickets t $where";
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($parametros);
$totalTickets = (int)$stmtTotal->fetch()['total'];
$totalPaginas = max(1, (int)ceil($totalTickets / $porPagina));
$paginaActualNum = min($paginaActualNum, $totalPaginas);
$offset = ($paginaActualNum - 1) * $porPagina;

$sql = "SELECT t.*, e.nombre AS estado_nombre, e.color AS estado_color,
               p.nombre AS prioridad_nombre, p.color AS prioridad_color,
               c.nombre AS categoria_nombre,
               ut.nombre_completo AS tecnico_nombre,
               uc.nombre_completo AS creador_nombre
        FROM tickets t
        JOIN estados e ON e.id_estado = t.id_estado
        JOIN prioridades p ON p.id_prioridad = t.id_prioridad
        JOIN categorias c ON c.id_categoria = t.id_categoria
        JOIN usuarios uc ON uc.id_usuario = t.id_usuario_creador
        LEFT JOIN usuarios ut ON ut.id_usuario = t.id_tecnico_asignado
        $where
        ORDER BY t.fecha_creacion DESC
        LIMIT $porPagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$tickets = $stmt->fetchAll();

// Construye la URL de una página de resultados conservando los filtros actuales.
function urlPagina(int $pagina): string
{
    $params = $_GET;
    $params['pagina'] = $pagina;
    return '?' . http_build_query($params);
}

$tituloPagina = 'Tickets de soporte';
require_once __DIR__ . '/../../includes/header.php';
?>

<form method="GET" action="" class="formulario-filtros">
    <input type="text" name="q" placeholder="Buscar por folio o título" value="<?= h($busqueda) ?>">

    <select name="estado">
        <option value="0">Estado</option>
        <?php foreach ($estados as $e): ?>
            <option value="<?= (int)$e['id_estado'] ?>" <?= $filtroEstado === (int)$e['id_estado'] ? 'selected' : '' ?>>
                <?= h($e['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="categoria">
        <option value="0">Categoría</option>
        <?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>" <?= $filtroCategoria === (int)$c['id_categoria'] ? 'selected' : '' ?>>
                <?= h($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($rol === 1): ?>
        <select name="tecnico">
            <option value="0">Técnico</option>
            <?php foreach ($tecnicos as $t): ?>
                <option value="<?= (int)$t['id_usuario'] ?>" <?= $filtroTecnico === (int)$t['id_usuario'] ? 'selected' : '' ?>>
                    <?= h($t['nombre_completo']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>

    <button type="submit" class="btn btn-secundario">Filtrar</button>
    <a href="<?= BASE_URL ?>/modules/tickets/listar.php" class="btn btn-enlace">Limpiar</a>
</form>

<table class="tabla">
    <thead>
        <tr>
            <th>Folio</th><th>Título</th><th>Categoría</th><th>Prioridad</th>
            <th>Estado</th>
            <?php if ($rol !== 3): ?><th>Solicitante</th><?php endif; ?>
            <?php if ($rol === 1): ?><th>Técnico</th><?php endif; ?>
            <th>Fecha</th><th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
        <tr>
            <td><?= h($t['folio']) ?></td>
            <td><?= h($t['titulo']) ?></td>
            <td><?= h($t['categoria_nombre']) ?></td>
            <td><span class="badge" style="color:<?= h($t['prioridad_color']) ?>"><?= h($t['prioridad_nombre']) ?></span></td>
            <td><?= badgeEstado($t['estado_nombre'], $t['estado_color']) ?></td>
            <?php if ($rol !== 3): ?><td><?= h($t['creador_nombre']) ?></td><?php endif; ?>
            <?php if ($rol === 1): ?><td><?= h($t['tecnico_nombre'] ?? 'Sin asignar') ?></td><?php endif; ?>
            <td><?= formatoFecha($t['fecha_creacion']) ?></td>
            <td><a href="<?= BASE_URL ?>/modules/tickets/detalle.php?id=<?= (int)$t['id_ticket'] ?>">Ver</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($tickets)): ?>
        <tr><td colspan="9">No se encontraron tickets con los filtros seleccionados.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($totalTickets > 0): ?>
<nav class="paginacion" aria-label="Paginación de tickets">
    <span class="paginacion-info">
        Mostrando de <?= $offset + 1 ?> a <?= min($offset + $porPagina, $totalTickets) ?> de <?= $totalTickets ?> tickets
    </span>
    <a href="<?= h(urlPagina($paginaActualNum - 1)) ?>" class="<?= $paginaActualNum <= 1 ? 'deshabilitado' : '' ?>">&lt;</a>
    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
        <?php if ($p === $paginaActualNum): ?>
            <span class="pagina-actual"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= h(urlPagina($p)) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <a href="<?= h(urlPagina($paginaActualNum + 1)) ?>" class="<?= $paginaActualNum >= $totalPaginas ? 'deshabilitado' : '' ?>">&gt;</a>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
