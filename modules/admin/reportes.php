<?php
require_once __DIR__ . '/../../config/config.php';
requerirRol([1]);

$pdo = conectarDB();

$fechaInicio = $_GET['inicio'] ?? date('Y-m-01');
$fechaFin = $_GET['fin'] ?? date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT t.folio, t.titulo, c.nombre AS categoria, p.nombre AS prioridad, e.nombre AS estado,
            uc.nombre_completo AS solicitante, COALESCE(ut.nombre_completo, 'Sin asignar') AS tecnico,
            t.fecha_creacion, t.fecha_resolucion, t.calificacion
     FROM tickets t
     JOIN categorias c ON c.id_categoria = t.id_categoria
     JOIN prioridades p ON p.id_prioridad = t.id_prioridad
     JOIN estados e ON e.id_estado = t.id_estado
     JOIN usuarios uc ON uc.id_usuario = t.id_usuario_creador
     LEFT JOIN usuarios ut ON ut.id_usuario = t.id_tecnico_asignado
     WHERE DATE(t.fecha_creacion) BETWEEN :inicio AND :fin
     ORDER BY t.fecha_creacion DESC"
);
$stmt->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
$filas = $stmt->fetchAll();

// Exportación a CSV
if (isset($_GET['exportar'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_tickets_' . $fechaInicio . '_a_' . $fechaFin . '.csv"');
    $salida = fopen('php://output', 'w');
    fputcsv($salida, ['Folio', 'Título', 'Categoría', 'Prioridad', 'Estado', 'Solicitante', 'Técnico', 'Creado', 'Resuelto', 'Calificación']);
    foreach ($filas as $f) {
        fputcsv($salida, [
            $f['folio'], $f['titulo'], $f['categoria'], $f['prioridad'], $f['estado'],
            $f['solicitante'], $f['tecnico'], $f['fecha_creacion'], $f['fecha_resolucion'], $f['calificacion'],
        ]);
    }
    fclose($salida);
    exit;
}

// Promedio de tiempo de resolución (en horas) del periodo
$stmtProm = $pdo->prepare(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_resolucion)) AS promedio
     FROM tickets
     WHERE fecha_resolucion IS NOT NULL AND DATE(fecha_creacion) BETWEEN :inicio AND :fin"
);
$stmtProm->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
$promedioHoras = $stmtProm->fetch()['promedio'];

$tituloPagina = 'Reportes y Estadísticas';
require_once __DIR__ . '/../../includes/header.php';
?>

<form method="GET" class="formulario-filtros">
    <label>Del: <input type="date" name="inicio" value="<?= h($fechaInicio) ?>"></label>
    <label>Al: <input type="date" name="fin" value="<?= h($fechaFin) ?>"></label>
    <button type="submit" class="btn btn-secundario">Consultar</button>
    <a class="btn btn-primario" href="?inicio=<?= h($fechaInicio) ?>&fin=<?= h($fechaFin) ?>&exportar=1">Exportar CSV</a>
</form>

<p><strong>Tiempo promedio de resolución:</strong>
    <?= $promedioHoras !== null ? round($promedioHoras, 1) . ' horas' : 'Sin datos suficientes' ?>
</p>
<p><strong>Total de tickets en el periodo:</strong> <?= count($filas) ?></p>

<table class="tabla">
    <thead>
        <tr>
            <th>Folio</th><th>Título</th><th>Categoría</th><th>Prioridad</th><th>Estado</th>
            <th>Solicitante</th><th>Técnico</th><th>Creado</th><th>Resuelto</th><th>Calif.</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <tr>
            <td><?= h($f['folio']) ?></td>
            <td><?= h($f['titulo']) ?></td>
            <td><?= h($f['categoria']) ?></td>
            <td><?= h($f['prioridad']) ?></td>
            <td><?= h($f['estado']) ?></td>
            <td><?= h($f['solicitante']) ?></td>
            <td><?= h($f['tecnico']) ?></td>
            <td><?= formatoFecha($f['fecha_creacion']) ?></td>
            <td><?= formatoFecha($f['fecha_resolucion']) ?></td>
            <td><?= $f['calificacion'] ? str_repeat('★', (int)$f['calificacion']) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($filas)): ?>
        <tr><td colspan="10">No hay tickets en el periodo seleccionado.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
