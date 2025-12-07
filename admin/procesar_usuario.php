<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

// Verificar si el administrador está logueado
check_session(true, '../index.php');

// Crear una conexión a la base de datos
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4"); // Establecer UTF-8 para la conexión

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

function ensure_created_by_admin_column(mysqli $conn): void {
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'created_by_admin_id'");
    if ($column_check && $column_check->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN created_by_admin_id INT NULL AFTER role, ADD INDEX idx_created_by_admin (created_by_admin_id)");
    }
    if ($column_check instanceof mysqli_result) {
        $column_check->close();
    }
}

ensure_created_by_admin_column($conn);

$current_user_role = $_SESSION['user_role'] ?? 'user';
$current_user_id = $_SESSION['user_id'] ?? null;

// Verificar qué acción se va a realizar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            createUser($conn);
            break;
        case 'update':
            updateUser($conn);
            break;
        case 'delete':
            deleteUser($conn);
            break;
        default:
            $_SESSION['message'] = 'Acción no válida.';
            header('Location: /admin/admin.php');
            exit();
    }
}

// Función para crear un nuevo usuario
function createUser($conn) {
    global $current_user_role, $current_user_id;

    $username = trim($_POST['username']);
    $telegram_id = trim($_POST['telegram_id'] ?? '');
    $password = $_POST['password'];
    $status = isset($_POST['status']) ? 1 : 0;
    $created_by_admin_id = ($current_user_role === 'admin') ? $current_user_id : null;

    if ($current_user_role === 'superadmin') {
        $role_input = $_POST['role'] ?? '';
        if (!in_array($role_input, ['admin', 'user'], true)) {
            $_SESSION['message'] = 'Debes seleccionar un rol válido para el nuevo usuario.';
            header('Location: /admin/admin.php');
            exit();
        }
        $role = $role_input;
    } else {
        $role = 'user';
    }
    
    // Validar datos
    if (empty($username) || empty($password)) {
        $_SESSION['message'] = 'El nombre de usuario y la contraseña son obligatorios.';
        header('Location: /admin/admin.php');
        exit();
    }
    
    // Verificar si el usuario ya existe
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['message'] = 'El nombre de usuario ya existe.';
        header('Location: /admin/admin.php');
        exit();
    }
    $check_stmt->close();
    
    // Cifrar contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar nuevo usuario
    $stmt = $conn->prepare("INSERT INTO users (username, password, telegram_id, status, role, created_by_admin_id) VALUES (?, ?, ?, ?, ?, ?)");
    $telegram_param = $telegram_id !== '' ? $telegram_id : null;
    $stmt->bind_param("sssisi", $username, $hashed_password, $telegram_param, $status, $role, $created_by_admin_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Usuario creado con éxito.';
    } else {
        $_SESSION['message'] = 'Error al crear el usuario: ' . $stmt->error;
    }
    
    $stmt->close();
    header('Location: /admin/admin.php');
    exit();
}

// Función para actualizar un usuario existente
function updateUser($conn) {
    global $current_user_role, $current_user_id;

    $user_id = (int) $_POST['user_id'];
    $username = trim($_POST['username']);
    $telegram_id = trim($_POST['telegram_id'] ?? '');
    $password = $_POST['password'];
    $status = isset($_POST['status']) ? 1 : 0;
    $telegram_param = $telegram_id !== '' ? $telegram_id : null;

    $user_stmt = $conn->prepare("SELECT id, role, created_by_admin_id FROM users WHERE id = ?");
    $user_stmt->bind_param('i', $user_id);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user_info) {
        $_SESSION['message'] = 'Usuario no encontrado.';
        header('Location: /admin/admin.php');
        exit();
    }

    if ($current_user_role === 'admin' && ($user_info['role'] !== 'user' || (int)$user_info['created_by_admin_id'] !== (int)$current_user_id)) {
        $_SESSION['message'] = 'No tienes permisos para modificar este usuario.';
        header('Location: /admin/admin.php');
        exit();
    }

    if ($current_user_role === 'superadmin' && !empty($user_info['created_by_admin_id']) && $user_info['role'] === 'user') {
        $_SESSION['message'] = 'Los usuarios creados por un Admin solo pueden ser gestionados por ese Admin.';
        header('Location: /admin/admin.php');
        exit();
    }

    
    // Validar datos
    if (empty($username) || empty($user_id)) {
        $_SESSION['message'] = 'Datos incompletos para actualizar el usuario.';
        header('Location: /admin/admin.php');
        exit();
    }
    
    // Verificar si el nombre de usuario ya existe para otro usuario
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_stmt->bind_param("si", $username, $user_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['message'] = 'El nombre de usuario ya está en uso por otro usuario.';
        header('Location: /admin/admin.php');
        exit();
    }
    $check_stmt->close();
    
    // Actualizar usuario
    if (empty($password)) {
        // Si no se proporciona contraseña, actualizar otros campos
    $stmt = $conn->prepare("UPDATE users SET username = ?, telegram_id = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssii", $username, $telegram_param, $status, $user_id);
    } else {
        // Si se proporciona contraseña, actualizar todos los campos
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, telegram_id = ?, password = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssii", $username, $telegram_param, $hashed_password, $status, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Usuario actualizado con éxito.';
    } else {
        $_SESSION['message'] = 'Error al actualizar el usuario: ' . $stmt->error;
    }
    
    $stmt->close();
    header('Location: /admin/admin.php');
    exit();
}

// Función para eliminar un usuario
function deleteUser($conn) {
    global $current_user_role, $current_user_id;

    $user_id = (int) $_POST['user_id'];

    $user_stmt = $conn->prepare("SELECT id, role, created_by_admin_id FROM users WHERE id = ?");
    $user_stmt->bind_param('i', $user_id);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if (!$user_info) {
        $_SESSION['message'] = 'Usuario no encontrado.';
        header('Location: /admin/admin.php');
        exit();
    }

    if ($current_user_role === 'admin' && ($user_info['role'] !== 'user' || (int)$user_info['created_by_admin_id'] !== (int)$current_user_id)) {
        $_SESSION['message'] = 'No tienes permisos para eliminar este usuario.';
        header('Location: /admin/admin.php');
        exit();
    }

    if ($current_user_role === 'superadmin' && !empty($user_info['created_by_admin_id']) && $user_info['role'] === 'user') {
        $_SESSION['message'] = 'Los usuarios creados por un Admin solo pueden ser gestionados por ese Admin.';
        header('Location: /admin/admin.php');
        exit();
    }
    
    // Actualizar los logs para establecer user_id a NULL
    $update_logs_stmt = $conn->prepare("UPDATE logs SET user_id = NULL WHERE user_id = ?");
    $update_logs_stmt->bind_param("i", $user_id);
    $update_logs_stmt->execute();
    $update_logs_stmt->close();
    
    // Eliminar el usuario
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Usuario eliminado con éxito.';
    } else {
        $_SESSION['message'] = 'Error al eliminar el usuario: ' . $stmt->error;
    }
    
    $stmt->close();
    header('Location: /admin/admin.php');
    exit();
}

$conn->close();
?> 
