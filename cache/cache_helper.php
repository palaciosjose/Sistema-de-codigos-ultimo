<?php
/**
 * Utilidades simples de caché en memoria para configuraciones y catálogos.
 */
class SimpleCache
{
    private static ?array $settingsCache = null;
    private static ?array $platformSubjectsCache = null;
    private static ?array $enabledServersCache = null;

    public static function clear(): void
    {
        self::$settingsCache = null;
        self::$platformSubjectsCache = null;
        self::$enabledServersCache = null;
    }

    public static function get_settings(mysqli $conn): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        $settings = [];
        $queryVariants = [
            "SELECT name AS setting_key, value AS setting_value FROM settings",
            "SELECT setting_key, setting_value FROM settings"
        ];

        foreach ($queryVariants as $query) {
            $result = $conn->query($query);
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                $result->free();
                break;
            }
        }

        self::$settingsCache = $settings;
        return $settings;
    }

    public static function get_platform_subjects(mysqli $conn): array
    {
        if (self::$platformSubjectsCache !== null) {
            return self::$platformSubjectsCache;
        }

        $platforms = [];
        $result = $conn->query(
            "SELECT platform_name, subject_keyword FROM platform_subjects"
        );

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $platform = $row['platform_name'];
                $platforms[$platform][] = $row['subject_keyword'];
            }
            $result->free();
        }

        self::$platformSubjectsCache = $platforms;
        return $platforms;
    }

    public static function get_enabled_servers(mysqli $conn): array
    {
        if (self::$enabledServersCache !== null) {
            return self::$enabledServersCache;
        }

        $servers = [];
        $result = $conn->query(
            "SELECT server_name, host, username, password, port, folder, ssl, status FROM email_servers WHERE status = 1"
        );

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $servers[] = [
                    'server_name' => $row['server_name'],
                    'host' => $row['host'],
                    'username' => $row['username'],
                    'password' => $row['password'],
                    'port' => (int)($row['port'] ?? 993),
                    'folder' => $row['folder'] ?? 'INBOX',
                    'ssl' => (bool)($row['ssl'] ?? true),
                    'status' => (int)($row['status'] ?? 0),
                ];
            }
            $result->free();
        }

        self::$enabledServersCache = $servers;
        return $servers;
    }
}
