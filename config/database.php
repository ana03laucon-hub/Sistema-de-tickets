<?php
/**
 * Configuración de conexión a la base de datos.
 * Ajusta estos valores según los datos que te proporcione
 * el administrador del servidor del H. Ayuntamiento.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_tickets');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Devuelve una conexión PDO reutilizable (patrón singleton simple).
 */
function conectarDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
        } catch (PDOException $e) {
            // En producción no debe mostrarse el detalle del error al usuario final.
            error_log('Error de conexión a la base de datos: ' . $e->getMessage());
            die('No fue posible conectar con la base de datos. Contacte al administrador del sistema.');
        }
    }

    return $pdo;
}
