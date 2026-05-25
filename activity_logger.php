<?php
/**
 * logActivity — writes a row to activity_logs.
 * Safe to call from anywhere. Silently fails if the table doesn't exist yet.
 *
 * @param mysqli      $conn
 * @param int|null    $userId
 * @param string      $userName
 * @param string      $userRole   admin|seller|customer|unknown
 * @param string      $actionType e.g. LOGIN_SUCCESS, PRODUCT_ADDED, ORDER_PLACED …
 * @param string      $description
 * @param string      $status     Success|Failed
 */
function logActivity(
    mysqli $conn,
    ?int   $userId,
    string $userName,
    string $userRole,
    string $actionType,
    string $description,
    string $status = 'Success'
): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    try {
        $stmt = $conn->prepare(
            "INSERT INTO activity_logs
                (user_id, user_name, user_role, action_type, description, ip_address, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param(
                'issssss',
                $userId, $userName, $userRole, $actionType, $description, $ip, $status
            );
            $stmt->execute();
        }
    } catch (Throwable $e) {
        // Silently ignore — logging should never break the main flow
        error_log('logActivity error: ' . $e->getMessage());
    }
}
?>
