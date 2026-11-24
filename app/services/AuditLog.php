<?php
// app/services/AuditLog.php

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;

class AuditLog {
    /**
     * Standard audit logger
     */
    public static function log(string $action, string $table, $rowId = null, $oldData = null, $newData = null): void {
        try {
            self::write($action, $table, $rowId, $oldData, $newData);
        } catch (\Throwable $e) {
            // Silently handle audit log errors to avoid blocking primary business operations
            error_log("AuditLog error: " . $e->getMessage());
        }
    }

    public static function logOrThrow(string $action, string $table, $rowId = null, $oldData = null, $newData = null): void {
        self::write($action, $table, $rowId, $oldData, $newData);
    }

    private static function write(string $action, string $table, $rowId, $oldData, $newData): void {
        $cid = Auth::concernId();
        $uid = Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $oldJson = $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO audit_log (concern_id, user_id, action, table_name, row_id, old_json, new_json, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        DB::query($sql, [$cid, $uid, $action, $table, $rowId, $oldJson, $newJson, $ip]);
    }

    /**
     * Overloaded / explicit audit recorder helper
     */
    public static function record(
        ?int $concernId = null,
        string $table = '',
        $rowId = null,
        string $action = 'CREATE',
        $oldData = null,
        $newData = null,
        ?string $notes = null,
        ?int $userId = null
    ): void {
        try {
            $cid = $concernId ?? Auth::concernId();
            $uid = $userId ?? Auth::id();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $oldJson = $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null;
            $newJson = $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null;

            $sql = "INSERT INTO audit_log (concern_id, user_id, action, table_name, row_id, old_json, new_json, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            DB::query($sql, [$cid, $uid, $action, $table, $rowId, $oldJson, $newJson, $ip]);
        } catch (\Throwable $e) {
            error_log("AuditLog record error: " . $e->getMessage());
        }
    }
}
