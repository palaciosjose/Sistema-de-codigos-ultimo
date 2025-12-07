<?php
session_start();
require_once '../instalacion/basededatos.php';
require_once '../security/auth.php';

check_session(true, '../index.php');

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
$conn->set_charset("utf8mb4");

$action = $_REQUEST['action'] ?? null;

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

function can_manage_user(mysqli $conn, string $current_role, ?int $current_id, int $target_user_id) {
    $stmt = $conn->prepare("SELECT id, role, created_by_admin_id FROM users WHERE id = ?");
    $stmt->bind_param('i', $target_user_id);
    $stmt->execute();
    $user_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user_row) {
        return false;
    }

    if ($current_role === 'superadmin') {
        if (!empty($user_row['created_by_admin_id']) && $user_row['role'] === 'user') {
            return false;
        }
        return $user_row;
    }

    if ($current_role === 'admin' && $user_row['role'] === 'user' && (int)$user_row['created_by_admin_id'] === (int)$current_id) {
        return $user_row;
    }

    return false;
}

function get_admin_allowed_subjects(mysqli $conn, string $current_role, ?int $current_id): ?array {
    if ($current_role !== 'admin' || !$current_id) {
        return null;
    }

    $allowed = [];
    $stmt = $conn->prepare("SELECT platform_id, subject_keyword FROM user_platform_subjects WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $current_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $allowed[$row['platform_id']][] = $row['subject_keyword'];
        }
        $stmt->close();
    }
    return $allowed;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'get_assignments':
            header('Content-Type: application/json');
            $users = [];
            $platforms = [];
            $assignments = [];
            $allowed_subjects = get_admin_allowed_subjects($conn, $current_user_role, $current_user_id);

            if ($current_user_role === 'superadmin') {
                $res = $conn->query("SELECT id, username, role FROM users WHERE role != 'superadmin' ORDER BY username ASC");
            } else {
                $res = $conn->prepare("SELECT id, username, role FROM users WHERE role = 'user' AND created_by_admin_id = ? ORDER BY username ASC");
                if ($res) {
                    $res->bind_param('i', $current_user_id);
                    $res->execute();
                    $res = $res->get_result();
                }
            }

            $manageable_user_ids = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $users[$row['id']] = ['id' => $row['id'], 'username' => $row['username'], 'role' => $row['role']];
                    $manageable_user_ids[] = (int)$row['id'];
                }
                if ($res instanceof mysqli_result) {
                    $res->close();
                }
            }

            $platforms_result = $conn->query("SELECT id, name FROM platforms ORDER BY sort_order ASC");
            if ($platforms_result) {
                while ($row = $platforms_result->fetch_assoc()) {
                    $platforms[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'subjects' => []];
                }
                $platforms_result->close();
            }

            if (!empty($platforms)) {
                $ids = implode(',', array_keys($platforms));
                $subjects_result = $conn->query("SELECT platform_id, subject FROM platform_subjects WHERE platform_id IN ($ids) ORDER BY subject ASC");
                if ($subjects_result) {
                    while ($row = $subjects_result->fetch_assoc()) {
                        if (is_array($allowed_subjects)) {
                            if (empty($allowed_subjects[$row['platform_id']]) || !in_array($row['subject'], $allowed_subjects[$row['platform_id']], true)) {
                                continue;
                            }
                        }
                        $platforms[$row['platform_id']]['subjects'][] = $row['subject'];
                    }
                    $subjects_result->close();
                }
            }

            if (!empty($manageable_user_ids)) {
                $placeholders = implode(',', array_fill(0, count($manageable_user_ids), '?'));
                $assignment_stmt = $conn->prepare("SELECT user_id, platform_id, subject_keyword FROM user_platform_subjects WHERE user_id IN ($placeholders)");
                if ($assignment_stmt) {
                    $types = str_repeat('i', count($manageable_user_ids));
                    $assignment_stmt->bind_param($types, ...$manageable_user_ids);
                    $assignment_stmt->execute();
                    $res = $assignment_stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $assignments[$row['user_id']][$row['platform_id']][] = $row['subject_keyword'];
                    }
                    $assignment_stmt->close();
                }
            }

            echo json_encode([
                'success' => true,
                'users' => array_values($users),
                'platforms' => array_values(array_filter($platforms, function ($platform) {
                    return !empty($platform['subjects']);
                })),
                'assignments' => $assignments
            ]);
            exit();

        case 'get_user_assignments':
            header('Content-Type: application/json');
            $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
            if (!$user_id) {
                echo json_encode(['success' => false, 'error' => 'ID de usuario no válido']);
                exit();
            }

            $manageable_user = can_manage_user($conn, $current_user_role, $current_user_id, $user_id);
            if (!$manageable_user) {
                if ($current_user_role !== 'superadmin') {
                    echo json_encode(['success' => false, 'error' => 'No tienes permisos para administrar este usuario']);
                    exit();
                }
                $user_exists_stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                $user_exists_stmt->bind_param('i', $user_id);
                $user_exists_stmt->execute();
                $user_exists = $user_exists_stmt->get_result()->num_rows > 0;
                $user_exists_stmt->close();
                if (!$user_exists) {
                    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                    exit();
                }
            }

            $allowed_subjects = get_admin_allowed_subjects($conn, $current_user_role, $current_user_id);

            // 1. Obtener todas las plataformas
            $platforms = [];
            $res_platforms = $conn->query("SELECT id, name FROM platforms ORDER BY sort_order ASC");
            if ($res_platforms) {
                while ($row = $res_platforms->fetch_assoc()) {
                    $platforms[$row['id']] = ['id' => $row['id'], 'name' => $row['name'], 'subjects' => []];
                }
            }

            // 2. Obtener todos los asuntos para esas plataformas
            if (!empty($platforms)) {
                $platform_ids_str = implode(',', array_keys($platforms));
                $res_subjects = $conn->query("SELECT platform_id, subject FROM platform_subjects WHERE platform_id IN ($platform_ids_str) ORDER BY subject ASC");
                if ($res_subjects) {
                    while ($row = $res_subjects->fetch_assoc()) {
                        if (isset($platforms[$row['platform_id']])) {
                            if (is_array($allowed_subjects)) {
                                if (empty($allowed_subjects[$row['platform_id']]) || !in_array($row['subject'], $allowed_subjects[$row['platform_id']], true)) {
                                    continue;
                                }
                            }
                            $platforms[$row['platform_id']]['subjects'][] = $row['subject'];
                        }
                    }
                }
            }

            // 3. Obtener solo las asignaciones para ESTE usuario
            $user_assignments = [];
            $stmt = $conn->prepare("SELECT platform_id, subject_keyword FROM user_platform_subjects WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $user_assignments[$row['platform_id']][] = $row['subject_keyword'];
                }
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'platforms' => array_values($platforms),
                'assignments' => [$user_id => $user_assignments]
            ]);
            exit();
            
        default:
            // No hacer nada si la acción no coincide
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_assignment') {
    header('Content-Type: application/json');
    global $current_user_role, $current_user_id;
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = intval($data['user_id'] ?? 0);
    $platform_id = intval($data['platform_id'] ?? 0);
    $subjects = $data['subjects'] ?? [];
    $resp = ['success' => false];
    if ($user_id && $platform_id && is_array($subjects)) {
        $manageable_user = can_manage_user($conn, $current_user_role, $current_user_id, $user_id);
        if (!$manageable_user) {
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para administrar este usuario']);
            exit();
        }

        $target_role = $manageable_user['role'] ?? 'user';
        $is_superadmin_editing_admin = ($current_user_role === 'superadmin' && $target_role === 'admin');

        $previous_subjects = [];
        if ($is_superadmin_editing_admin) {
            $previous_stmt = $conn->prepare("SELECT subject_keyword FROM user_platform_subjects WHERE user_id = ? AND platform_id = ?");
            if ($previous_stmt) {
                $previous_stmt->bind_param('ii', $user_id, $platform_id);
                if ($previous_stmt->execute()) {
                    $res_prev = $previous_stmt->get_result();
                    while ($row = $res_prev->fetch_assoc()) {
                        $previous_subjects[] = $row['subject_keyword'];
                    }
                }
                $previous_stmt->close();
            }
        }

        $allowed_subjects = get_admin_allowed_subjects($conn, $current_user_role, $current_user_id);
        if (is_array($allowed_subjects)) {
            $subjects = array_values(array_filter($subjects, function ($subject) use ($allowed_subjects, $platform_id) {
                return !empty($allowed_subjects[$platform_id]) && in_array($subject, $allowed_subjects[$platform_id], true);
            }));
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM user_platform_subjects WHERE user_id=? AND platform_id=?");
            $stmt->bind_param('ii', $user_id, $platform_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($subjects)) {
                $stmt = $conn->prepare("INSERT INTO user_platform_subjects (user_id, platform_id, subject_keyword) VALUES (?, ?, ?)");
                foreach ($subjects as $subj) {
                    $stmt->bind_param('iis', $user_id, $platform_id, $subj);
                    $stmt->execute();
                }
                $stmt->close();
            }

            if ($is_superadmin_editing_admin && !empty($previous_subjects)) {
                $removed_subjects = array_values(array_diff($previous_subjects, $subjects));
                if (!empty($removed_subjects)) {
                    $placeholders = implode(',', array_fill(0, count($removed_subjects), '?'));
                    $cleanup_sql = "DELETE FROM user_platform_subjects WHERE user_id IN (SELECT id FROM users WHERE created_by_admin_id = ?) AND platform_id = ? AND subject_keyword IN ($placeholders)";
                    $cleanup_stmt = $conn->prepare($cleanup_sql);
                    if (!$cleanup_stmt) {
                        throw new Exception('Error al preparar limpieza en cascada de asuntos: ' . $conn->error);
                    }

                    $types = 'ii' . str_repeat('s', count($removed_subjects));
                    $params = array_merge([$user_id, $platform_id], $removed_subjects);
                    $cleanup_stmt->bind_param($types, ...$params);
                    if (!$cleanup_stmt->execute()) {
                        throw new Exception('Error al limpiar asuntos de usuarios dependientes: ' . $cleanup_stmt->error);
                    }
                    $cleanup_stmt->close();
                }
            }
            $conn->commit();
            $resp['success'] = true;
        } catch (Exception $e) {
            $conn->rollback();
            $resp['error'] = $e->getMessage();
        }
    } else {
        $resp['error'] = 'Datos inválidos';
    }
    echo json_encode($resp);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
