<?php
/**
 * Configuración general de la aplicación.
 */

date_default_timezone_set('America/Mexico_City');

/* ============================================================
 * MANEJO GLOBAL DE ERRORES
 * Requerimiento no funcional: "El sistema deberá presentar
 * mensajes claros al usuario cuando ocurra un error o una
 * operación no válida", sin exponer detalles técnicos internos.
 * Los detalles reales se registran en el log del servidor.
 * ============================================================ */
ini_set('display_errors', '0');
error_reporting(E_ALL);

function mostrarPaginaError(string $mensaje = 'Ocurrió un error inesperado. Intente de nuevo más tarde.'): void
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Error · Sistema de Soporte Técnico</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f4f5f7;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
    echo '.caja{background:#fff;padding:2rem;border-radius:8px;max-width:420px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.1)}';
    echo 'h1{color:#7A1F2B;font-size:1.2rem}a{color:#7A1F2B}</style></head><body>';
    echo '<div class="caja"><h1>Algo salió mal</h1><p>' . htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="' . (defined('BASE_URL') ? BASE_URL : '') . '/index.php">Volver al inicio</a></p></div></body></html>';
}

set_exception_handler(function (Throwable $e) {
    error_log('Excepción no controlada: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    mostrarPaginaError();
});

set_error_handler(function ($nivel, $mensaje, $archivo, $linea) {
    error_log("Error PHP [$nivel]: $mensaje en $archivo:$linea");
    if (in_array($nivel, [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        mostrarPaginaError();
        exit;
    }
    return true; // Continúa la ejecución para warnings/notices no críticos.
});

// Nombre del sistema, usado en títulos y correos.
define('NOMBRE_SISTEMA', 'Sistema de Soporte Técnico - H. Ayuntamiento de Tehuacán');

// Ruta base del proyecto (ajustar si se instala en un subdirectorio, ej. "/soporte").
define('BASE_URL', '/sistema-tickets');

// Carpeta donde se guardan los archivos adjuntos de los tickets.
define('RUTA_UPLOADS', __DIR__ . '/../uploads/');
define('URL_UPLOADS', BASE_URL . '/uploads/');

// Extensiones y tamaño permitido para adjuntos (5 MB).
define('EXTENSIONES_PERMITIDAS', ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx', 'txt']);
define('TAMANO_MAXIMO_ARCHIVO', 5 * 1024 * 1024);

// Configuración de sesión segura antes de iniciarla.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/funciones.php';
