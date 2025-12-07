<?php
/**
 * Funciones de personalización por Admin.
 */
function getAdminConfigForUser(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare("SELECT created_by_admin_id, role FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Admins y superadmins usan configuración global
    if (in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) {
        return null;
    }

    // Configuración del admin creador
    $adminId = $user['created_by_admin_id'] ?? null;
    if ($adminId) {
        $stmt = $conn->prepare(
            "SELECT site_title, logo_url, web_url, telegram_url, whatsapp_url, welcome_message
             FROM admin_configurations
             WHERE admin_id = ?"
        );
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $config = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $config ?: null;
    }

    return null;
}
