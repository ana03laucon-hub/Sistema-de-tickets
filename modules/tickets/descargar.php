<?php
/**
 * Sirve un archivo adjunto (de un ticket o de un comentario) solo si el
 * usuario en sesión tiene permiso para ver el ticket al que pertenece.
 * Esto evita que un archivo sea accesible por cualquiera que adivine o
 * comparta la URL directa, cumpliendo con el requerimiento de que la
 * información quede disponible únicamente para usuarios autorizados.
 */
require_once __DIR__ . '/../../config/config.php';
requerirLogin();

$pdo = conectarDB();
$archivo = basename($_GET['archivo'] ?? ''); // basename evita traversal de directorios (../)
$idUsuario = $_SESSION['id_usuario'];
$rol = rolActual();

if ($archivo === '') {
    http_response_code(400);
    die('Archivo no especificado.');
}

// Buscar a qué ticket pertenece el archivo, ya sea adjunto principal o de un comentario.
$stmt = $pdo->prepare('SELECT id_ticket FROM tickets WHERE archivo_adjunto = :archivo');
$stmt->execute(['archivo' => $archivo]);
$ticket = $stmt->fetch();

if (!$ticket) {
    $stmt = $pdo->prepare('SELECT id_ticket FROM comentarios WHERE archivo_adjunto = :archivo');
    $stmt->execute(['archivo' => $archivo]);
    $ticket = $stmt->fetch();
}

if (!$ticket) {
    http_response_code(404);
    die('Archivo no encontrado.');
}

if (!usuarioPuedeVerTicket($pdo, (int)$ticket['id_ticket'], $idUsuario, $rol)) {
    http_response_code(403);
    die('No tiene permiso para acceder a este archivo.');
}

$rutaArchivo = RUTA_UPLOADS . $archivo;
if (!is_file($rutaArchivo)) {
    http_response_code(404);
    die('El archivo ya no está disponible.');
}

$mimeTypes = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'pdf' => 'application/pdf', 'txt' => 'text/plain',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$extension = strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION));
$mime = $mimeTypes[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($rutaArchivo));
header('X-Content-Type-Options: nosniff');
readfile($rutaArchivo);
exit;
