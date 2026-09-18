<?php
/**
 * Configuracion y conexion a la base de datos
 * TodoCamisetas - Examen Transversal Final | Desarrollo Backend IF201IINF
 *
 * Se utiliza PDO y no mysqli por tres razones documentadas en el informe:
 *   1. Permite sentencias preparadas con parametros nombrados, lo que
 *      elimina el riesgo de inyeccion SQL.
 *   2. Soporta transacciones de forma nativa (beginTransaction/commit/rollBack),
 *      necesarias para las operaciones CRUD del indicador 3.2.
 *   3. Es agnostico del motor: migrar de MySQL a otro gestor solo requiere
 *      cambiar el DSN de esta linea, sin tocar los modelos.
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'todocamisetas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'TodoCamisetas API');
define('APP_VERSION', '1.0.0');

/**
 * Devuelve una unica instancia de conexion PDO (patron singleton).
 *
 * Mantener una sola conexion por peticion evita abrir sockets
 * innecesarios contra MySQL cuando un mismo request consulta varias
 * tablas (por ejemplo, una camiseta junto a sus tallas y su cliente).
 *
 * @return PDO
 * @throws PDOException si la conexion falla (capturada en el front controller)
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $opciones = [
            // Lanza excepciones ante cualquier error SQL en lugar de fallar en silencio.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Devuelve arreglos asociativos, evitando indices numericos duplicados.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Usa sentencias preparadas reales del motor, no emuladas por PHP.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
    }

    return $pdo;
}
