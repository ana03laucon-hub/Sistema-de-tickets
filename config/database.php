<?php

/**
 * Configuración de conexión a la base de datos.
 * Las credenciales se obtienen desde el archivo .env
 * para evitar almacenarlas directamente en el código.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

/**
 * Cargar variables del archivo .env
 */
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'sistema_tickets');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

/**
 * Devuelve una conexión PDO reutilizable.
 */
function conectarDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {

        $dsn = 'mysql:host=' . DB_HOST .
               ';dbname=' . DB_NAME .
               ';charset=' . DB_CHARSET;

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {

            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                $opciones
            );

        } catch (PDOException $e) {

            // El detalle del error no se muestra al usuario.
            error_log(
                'Error de conexión a la base de datos: ' .
                $e->getMessage()
            );

            die(
                'No fue posible conectar con la base de datos. ' .
                'Contacte al administrador del sistema.'
            );
        }
    }

    return $pdo;
}