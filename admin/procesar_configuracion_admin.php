<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../funciones.php';
require_once '../security/auth.php';

check_session(true, '../index.php');

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    $_SESSION['admin_config_error'] = 'Solo los administradores pueden actualizar su personalización.';
    header('Location: admin.php?tab=admin-config');
    exit();
}

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    $_SESSION['admin_config_error'] = 'Error al conectar con la base de datos: ' . $conn->connect_error;
    header('Location: admin.php?tab=admin-config');
    exit();
}

$admin_id = $_SESSION['user_id'] ?? null;
if (!$admin_id) {
    $_SESSION['admin_config_error'] = 'No se pudo identificar al administrador actual.';
    header('Location: admin.php?tab=admin-config');
    exit();
}

$site_title = trim($_POST['site_title'] ?? '');
$web_url = trim($_POST['web_url'] ?? '');
$telegram_url = trim($_POST['telegram_url'] ?? '');
$whatsapp_url = trim($_POST['whatsapp_url'] ?? '');
$welcome_message = trim($_POST['welcome_message'] ?? '');

$current_logo = null;
$current_stmt = $conn->prepare('SELECT logo_url FROM admin_configurations WHERE admin_id = ? LIMIT 1');
if ($current_stmt) {
    $current_stmt->bind_param('i', $admin_id);
    $current_stmt->execute();
    $current_stmt->bind_result($current_logo);
    $current_stmt->fetch();
    $current_stmt->close();
}

$upload_dir = '../images/admin_logos/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$logo_file_name = $current_logo;
if (isset($_FILES['admin_logo']) && $_FILES['admin_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file_error = $_FILES['admin_logo']['error'];
    if ($file_error === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['admin_logo']['tmp_name'];
        $file_size = $_FILES['admin_logo']['size'];
        $mime_type = mime_content_type($tmp_name);

        if (!in_array($mime_type, ['image/png', 'image/jpeg'], true)) {
            $_SESSION['admin_config_error'] = 'El logo debe ser una imagen PNG o JPG.';
            header('Location: admin.php?tab=admin-config');
            exit();
        }

        if ($file_size > 2 * 1024 * 1024) {
            $_SESSION['admin_config_error'] = 'El logo no puede superar los 2MB.';
            header('Location: admin.php?tab=admin-config');
            exit();
        }

        $extension = $mime_type === 'image/png' ? '.png' : '.jpg';
        $new_logo_name = uniqid('admin_logo_', true) . $extension;

        if (!move_uploaded_file($tmp_name, $upload_dir . $new_logo_name)) {
            $_SESSION['admin_config_error'] = 'No se pudo subir el logo. Intenta nuevamente.';
            header('Location: admin.php?tab=admin-config');
            exit();
        }

        if ($logo_file_name && file_exists($upload_dir . $logo_file_name)) {
            @unlink($upload_dir . $logo_file_name);
        }

        $logo_file_name = $new_logo_name;
    } else {
        $_SESSION['admin_config_error'] = 'Error al subir el logo (código ' . $file_error . ').';
        header('Location: admin.php?tab=admin-config');
        exit();
    }
}

$site_title = $site_title ?: null;
$web_url = $web_url ?: null;
$telegram_url = $telegram_url ?: null;
$whatsapp_url = $whatsapp_url ?: null;
$welcome_message = $welcome_message ?: null;

$save_stmt = $conn->prepare(
    'INSERT INTO admin_configurations (admin_id, site_title, logo_url, web_url, telegram_url, whatsapp_url, welcome_message) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?) '
    . 'ON DUPLICATE KEY UPDATE site_title = VALUES(site_title), logo_url = VALUES(logo_url), web_url = VALUES(web_url), '
    . 'telegram_url = VALUES(telegram_url), whatsapp_url = VALUES(whatsapp_url), welcome_message = VALUES(welcome_message)'
);

if ($save_stmt) {
    $save_stmt->bind_param('issssss', $admin_id, $site_title, $logo_file_name, $web_url, $telegram_url, $whatsapp_url, $welcome_message);
    if ($save_stmt->execute()) {
        $_SESSION['admin_config_message'] = 'Configuración personalizada guardada correctamente.';
    } else {
        $_SESSION['admin_config_error'] = 'No se pudo guardar la configuración: ' . $save_stmt->error;
    }
    $save_stmt->close();
} else {
    $_SESSION['admin_config_error'] = 'Error al preparar la consulta: ' . $conn->error;
}

$conn->close();

header('Location: admin.php?tab=admin-config');
exit();
