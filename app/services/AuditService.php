<?php
// app/services/AuditService.php - Unified audit service wrapper

declare(strict_types=1);

namespace App\Services;

class AuditService {
    public static function log(string $action, string $table, $rowId = null, $oldData = null, $newData = null): void {
        AuditLog::log($action, $table, $rowId, $oldData, $newData);
    }

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
        AuditLog::record($concernId, $table, $rowId, $action, $oldData, $newData, $notes, $userId);
    }
}
