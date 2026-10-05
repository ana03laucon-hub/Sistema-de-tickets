<?php
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$rol = rolActual();
$idUsuario = (int)$_SESSION['id_usuario'];
$idEquipo = (int)($_GET['id'] ?? 0);

if ($idEquipo <= 0) {
    http_response_code(400);
    exit('Equipo no válido.');
}

$stmt = $pdo->prepare(
    "SELECT e.*,
            u.nombre_completo AS usuario_nombre,
            d.nombre AS departamento_nombre,
            tr.nombre_completo AS tecnico_recepciona_nombre,
            te.nombre_completo AS tecnico_entrega_nombre,
            t.folio AS ticket_folio
     FROM equipos_soporte e
     INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_entrega
     LEFT JOIN departamentos d ON d.id_departamento = e.id_departamento
     INNER JOIN usuarios tr ON tr.id_usuario = e.id_tecnico_recepciona
     LEFT JOIN usuarios te ON te.id_usuario = e.id_tecnico_entrega
     LEFT JOIN tickets t ON t.id_ticket = e.id_ticket
     WHERE e.id_equipo = :id
     LIMIT 1"
);
$stmt->execute(['id' => $idEquipo]);
$equipo = $stmt->fetch();

if (!$equipo) {
    http_response_code(404);
    exit('El equipo no existe.');
}

// Administradores y técnicos pueden generar comprobantes de cualquier equipo.
// El usuario solo puede consultar los comprobantes de sus propios equipos.
if ($rol === 3 && (int)$equipo['id_usuario_entrega'] !== $idUsuario) {
    http_response_code(403);
    exit('No tiene permisos para consultar este comprobante.');
}
if (!in_array($rol, [1, 2, 3], true)) {
    http_response_code(403);
    exit('No tiene permisos para generar este comprobante.');
}

require_once __DIR__ . '/../../includes/pdf_comprobante.php';

$equipoNombre = $equipo['tipo_equipo'];
if ($equipo['tipo_equipo'] === 'Otro' && !empty($equipo['otro_equipo'])) {
    $equipoNombre .= ': ' . $equipo['otro_equipo'];
}

$datos = [
    'folio' => $equipo['folio'],
    'fecha_ingreso' => formatoFecha($equipo['fecha_ingreso']),
    'usuario' => $equipo['usuario_nombre'] ?? '—',
    'departamento' => $equipo['departamento_nombre'] ?? '—',
    'equipo' => $equipoNombre,
    'cables' => $equipo['condicion_cables'] ?: 'No especificado',
    'ticket' => $equipo['ticket_folio'] ?: 'No relacionado con ticket',
    'descripcion' => $equipo['descripcion'] ?: 'Sin descripción.',
    'recepciona' => $equipo['tecnico_recepciona_nombre'] ?? '—',
];

$pdf = (new ComprobanteIngresoPDF())->generar($datos);
$nombreArchivo = 'comprobante-ingreso-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $equipo['folio']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $pdf;
exit;
