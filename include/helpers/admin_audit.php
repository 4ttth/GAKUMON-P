<?php
// Robust audit logger used by ALL endpoints.
// Accepts either: (conn, adminId, action, "details string")
// or:             (conn, adminId, action, "target_type", target_id, [extra payload])

if (!function_exists('logAdminAction')) {
    function logAdminAction(mysqli $connection, int $adminId, string $action,
                           $detailsOrType = null, $maybeId = null, array $extra = []): bool
    {
        try {
            $ip  = $_SERVER['REMOTE_ADDR']    ?? null;
            $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $targetType = null;
            $targetId   = null;
            $details    = null;

            if (is_string($detailsOrType) && $maybeId === null) {
                // Style A: free-form details string
                $details = $detailsOrType;
            } else {
                // Style B: target_type + target_id (+ optional extra JSON)
                $targetType = is_string($detailsOrType) ? $detailsOrType : null;
                $targetId   = is_scalar($maybeId) ? (int)$maybeId : null;
                $details    = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;
            }

            $sql = "INSERT INTO tbl_admin_audit_logs
                    (admin_id, action, target_type, target_id, details, ip_address, user_agent, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $connection->prepare($sql);
            if (!$stmt) {
                error_log("logAdminAction prepare failed: ".$connection->error);
                return false;
            }
            $stmt->bind_param(
                'ississs',
                $adminId,
                $action,
                $targetType,
                $targetId,
                $details,
                $ip,
                $ua
            );
            $ok = $stmt->execute();
            if (!$ok) {
                error_log("logAdminAction execute failed: ".$stmt->error);
            }
            $stmt->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            error_log("logAdminAction exception: ".$e->getMessage());
            return false;
        }
    }
}
