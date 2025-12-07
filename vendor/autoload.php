<?php
/**
 * Autoloader completo y funcional para el proyecto TelegramBot
 * Generado automáticamente
 */

// Evitar carga múltiple
if (defined('TELEGRAM_BOT_AUTOLOADER_LOADED')) {
    return;
}
define('TELEGRAM_BOT_AUTOLOADER_LOADED', true);

// Obtener el directorio raíz del proyecto
$projectRoot = dirname(__DIR__);

// ====================================================================
// AUTOLOADER PRINCIPAL PARA TELEGRAMBOT
// ====================================================================
spl_autoload_register(function ($className) use ($projectRoot) {
    // Solo procesar clases del namespace TelegramBot
    if (strpos($className, 'TelegramBot\\') !== 0) {
        return false;
    }
    
    // Remover el namespace principal
    $relativePath = substr($className, strlen('TelegramBot\\'));
    
    // Convertir namespace a ruta de archivo
    $filePath = $projectRoot . '/telegram_bot/' . str_replace('\\', '/', $relativePath) . '.php';
    
    // Cargar el archivo si existe
    if (file_exists($filePath)) {
        require_once $filePath;
        return class_exists($className);
    }
    
    return false;
});

// ====================================================================
// AUTOLOADER PARA SHARED
// ====================================================================
spl_autoload_register(function ($className) use ($projectRoot) {
    // Solo procesar clases del namespace Shared
    if (strpos($className, 'Shared\\') !== 0) {
        return false;
    }
    
    // Remover el namespace principal
    $relativePath = substr($className, strlen('Shared\\'));
    
    // Convertir namespace a ruta de archivo
    $filePath = $projectRoot . '/shared/' . str_replace('\\', '/', $relativePath) . '.php';
    
    // Cargar el archivo si existe
    if (file_exists($filePath)) {
        require_once $filePath;
        return class_exists($className);
    }
    
    return false;
});

// ====================================================================
// AUTOLOADER PARA LONGMAN TELEGRAM BOT
// ====================================================================
spl_autoload_register(function ($className) use ($projectRoot) {
    // Solo procesar clases del namespace Longman\TelegramBot
    if (strpos($className, 'Longman\\TelegramBot\\') !== 0) {
        return false;
    }
    
    // Buscar en diferentes ubicaciones posibles
    $possiblePaths = [
        $projectRoot . '/vendor/longman/telegram-bot/src/',
        $projectRoot . '/vendor/longman/telegram-bot/src/Longman/TelegramBot/',
        $projectRoot . '/longman/telegram-bot/src/',
    ];
    
    $relativePath = substr($className, strlen('Longman\\TelegramBot\\'));
    $filePath = str_replace('\\', '/', $relativePath) . '.php';
    
    foreach ($possiblePaths as $basePath) {
        $fullPath = $basePath . $filePath;
        if (file_exists($fullPath)) {
            require_once $fullPath;
            return class_exists($className);
        }
    }
    
    return false;
});

// ====================================================================
// AUTOLOADER FALLBACK PARA INCLUDES DIRECTOS
// ====================================================================
spl_autoload_register(function ($className) use ($projectRoot) {
    // Lista de archivos que se pueden incluir directamente
    $directIncludes = [
        'TelegramBot\\Services\\TelegramAuth' => '/telegram_bot/services/TelegramAuth.php',
        'TelegramBot\\Services\\TelegramQuery' => '/telegram_bot/services/TelegramQuery.php',
        'TelegramBot\\Handlers\\CommandHandler' => '/telegram_bot/handlers/CommandHandler.php',
        'TelegramBot\\Handlers\\CallbackHandler' => '/telegram_bot/handlers/CallbackHandler.php',
        'TelegramBot\\Utils\\TelegramAPI' => '/telegram_bot/utils/TelegramAPI.php',
    ];
    
    if (isset($directIncludes[$className])) {
        $filePath = $projectRoot . $directIncludes[$className];
        if (file_exists($filePath)) {
            require_once $filePath;
            return class_exists($className);
        }
    }
    
    return false;
});

// ====================================================================
// FUNCIÓN DE VERIFICACIÓN
// ====================================================================
function verifyTelegramBotClasses() {
    $classes = [
        'TelegramBot\\Services\\TelegramAuth',
        'TelegramBot\\Services\\TelegramQuery',
        'TelegramBot\\Handlers\\CommandHandler',
        'TelegramBot\\Handlers\\CallbackHandler',
        'TelegramBot\\Utils\\TelegramAPI'
    ];
    
    $results = [];
    foreach ($classes as $class) {
        $results[$class] = class_exists($class);
    }
    
    return $results;
}

// Solo mostrar resultados si se solicita
if (defined('SHOW_VERIFICATION') && SHOW_VERIFICATION) {
    $results = verifyTelegramBotClasses();
    foreach ($results as $class => $loaded) {
        echo ($loaded ? '✅' : '❌') . ' ' . $class . "\n";
    }
}
