<?php
/**
 * Funciones básicas de autenticación para proteger las páginas internas.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Valida que exista sesión iniciada. Si no, redirige a la ruta indicada.
 */
function check_session(bool $require_login = true, string $redirect = 'index.php', bool $refresh_activity = true): bool
{
    $loggedIn = isset($_SESSION['user_id']);

    if ($require_login && !$loggedIn) {
        header("Location: {$redirect}");
        exit();
    }

    if ($refresh_activity) {
        $_SESSION['last_activity'] = time();
    }

    return $loggedIn;
}
