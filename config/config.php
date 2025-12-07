<?php
/**
 * Configuración centralizada de base de datos.
 *
 * Busca las credenciales en variables de entorno o en config/db_credentials.php.
 * Mantiene compatibilidad con instalaciones antiguas usando instalacion/basededatos.php si existe.
 */

declare(strict_types=1);

/**
 * Cargar las credenciales de la base de datos.
 *
 * @return array{host:string,user:string,password:string,database:string}
 */
function load_db_config(): array
{
    static $cachedConfig = null;

    if ($cachedConfig !== null) {
        return $cachedConfig;
    }

    // 1) Variables de entorno
    $envHost = getenv('DB_HOST');
    if (!empty($envHost)) {
        $cachedConfig = [
            'host' => $envHost,
            'user' => getenv('DB_USER') ?: '',
            'password' => getenv('DB_PASSWORD') ?: '',
            'database' => getenv('DB_NAME') ?: '',
        ];
        return $cachedConfig;
    }

    // 2) Archivo de credenciales moderno
    $credentialsFile = __DIR__ . '/db_credentials.php';
    if (file_exists($credentialsFile)) {
        require $credentialsFile;
        $cachedConfig = [
            'host' => $db_host ?? '',
            'user' => $db_user ?? '',
            'password' => $db_password ?? '',
            'database' => $db_name ?? '',
        ];
        return $cachedConfig;
    }

    // 3) Fallback heredado
    $legacyFile = dirname(__DIR__) . '/instalacion/basededatos.php';
    if (file_exists($legacyFile)) {
        require $legacyFile;
        $cachedConfig = [
            'host' => $db_host ?? '',
            'user' => $db_user ?? '',
            'password' => $db_password ?? '',
            'database' => $db_name ?? '',
        ];
        return $cachedConfig;
    }

    throw new RuntimeException(
        'No se encontraron credenciales de base de datos. Define las variables de entorno DB_HOST/DB_USER/DB_PASSWORD/DB_NAME o crea config/db_credentials.php.'
    );
}

/**
 * Crear una conexión mysqli con charset configurado.
 */
function get_db_connection(): mysqli
{
    $config = load_db_config();
    $conn = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
    $conn->set_charset('utf8mb4');

    if ($conn->connect_error) {
        throw new RuntimeException('Error de conexión a la base de datos: ' . $conn->connect_error);
    }

    return $conn;
}

// Exponer variables globales para compatibilidad con código existente
try {
    $dbConfig = load_db_config();
    $db_host = $dbConfig['host'];
    $db_user = $dbConfig['user'];
    $db_password = $dbConfig['password'];
    $db_name = $dbConfig['database'];
} catch (RuntimeException $e) {
    // Si faltan credenciales dejamos las variables vacías; el flujo superior decidirá cómo proceder
    $db_host = $db_user = $db_password = $db_name = '';
}
