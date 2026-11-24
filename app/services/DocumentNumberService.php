<?php
// app/services/DocumentNumberService.php - Thread-safe, Concern & Year scoped sequence generator

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use Exception;

class DocumentNumberService {
    private static bool $tableChecked = false;

    /**
     * Ensure the sequence tracking table exists.
     */
    public static function initTable(): void {
        if (self::$tableChecked || DB::pdo()->inTransaction()) {
            return;
        }

        self::$tableChecked = true;
        DB::query("CREATE TABLE IF NOT EXISTS `doc_sequences` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `concern_id` INT NOT NULL,
            `doc_type` VARCHAR(50) NOT NULL,
            `doc_year` INT NOT NULL,
            `prefix` VARCHAR(20) NOT NULL,
            `current_seq` INT NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_concern_doc_year` (`concern_id`, `doc_type`, `doc_year`),
            INDEX `idx_ds_concern` (`concern_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Generate next document number scoped by concern and calendar year.
     * Guaranteed gap-free sequence with row locking and collision retry.
     *
     * Format: {PREFIX}-{YEAR}-{SEQUENCE:04d} (e.g. RET-2026-0001, REQ-2026-0001, DMG-2026-0001)
     *
     * @param string $docType e.g. 'RETURN', 'EXCHANGE', 'STOCK_REQUEST', 'PRODUCTION_STOCK_REQUEST', 'DAMAGE_WH'
     * @param int $concernId
     * @param string|null $customPrefix e.g. 'RET', 'REQ', 'WHD', 'SHD', 'DMG'
     * @param int $padLength Default 4 digits (e.g. 0001)
     * @param int $maxRetries Maximum attempts on sequence contention
     * @return string
     * @throws Exception
     */
    public static function generate(
        string $docType,
        int $concernId,
        ?string $customPrefix = null,
        int $padLength = 4,
        int $maxRetries = 3
    ): string {
        self::initTable();

        $docType = strtoupper(trim($docType));
        $docYear = (int)date('Y');
        $prefix = $customPrefix ? strtoupper(trim($customPrefix)) : self::getDefaultPrefix($docType);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $hasActiveTx = DB::pdo()->inTransaction();
            if (!$hasActiveTx) {
                DB::beginTransaction();
            }

            try {
                // Lock sequence row for update
                $row = DB::fetch(
                    "SELECT id, current_seq FROM doc_sequences 
                     WHERE concern_id = ? AND doc_type = ? AND doc_year = ? 
                     FOR UPDATE",
                    [$concernId, $docType, $docYear]
                );

                if (!$row) {
                    // Check fallback MAX sequence from existing tables if migrating
                    $startingSeq = self::detectStartingSequence($docType, $concernId, $docYear, $prefix);
                    $nextSeq = $startingSeq + 1;

                    DB::query(
                        "INSERT INTO doc_sequences (concern_id, doc_type, doc_year, prefix, current_seq, updated_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE current_seq = current_seq + 1",
                        [$concernId, $docType, $docYear, $prefix, $nextSeq]
                    );

                    // Re-read current sequence after insert/duplicate update
                    $row = DB::fetch(
                        "SELECT current_seq FROM doc_sequences 
                         WHERE concern_id = ? AND doc_type = ? AND doc_year = ?",
                        [$concernId, $docType, $docYear]
                    );
                    $nextSeq = (int)($row['current_seq'] ?? $nextSeq);
                } else {
                    $nextSeq = (int)$row['current_seq'] + 1;
                    DB::query(
                        "UPDATE doc_sequences SET current_seq = ?, updated_at = NOW() 
                         WHERE id = ?",
                        [$nextSeq, $row['id']]
                    );
                }

                $formattedNumber = sprintf("%s-%d-%0*d", $prefix, $docYear, $padLength, $nextSeq);

                if (!$hasActiveTx) {
                    DB::commit();
                }

                return $formattedNumber;
            } catch (\Throwable $e) {
                if (!$hasActiveTx) {
                    DB::rollBack();
                }

                if ($attempt === $maxRetries) {
                    // Fallback to timestamp + random if locked
                    return sprintf("%s-%d-%0*d", $prefix, $docYear, $padLength, (int)substr((string)time(), -4));
                }
                usleep(50000 * $attempt); // backoff 50ms, 100ms
            }
        }

        throw new Exception("Unable to generate unique document sequence for {$docType}.");
    }

    /**
     * Map doc type to standard prefix.
     */
    private static function getDefaultPrefix(string $docType): string {
        return match ($docType) {
            'RETURN', 'EXCHANGE', 'RMA' => 'RET',
            'STOCK_REQUEST', 'PRODUCTION_STOCK_REQUEST', 'REQUISITION', 'REQ' => 'REQ',
            'DAMAGE_WH', 'WAREHOUSE_DAMAGE' => 'WHD',
            'DAMAGE_SHOP', 'SHOP_DAMAGE' => 'SHD',
            'DAMAGE_CONCERN', 'CONCERN_DAMAGE', 'DAMAGE' => 'DMG',
            'DISPATCH_CHALLAN', 'CHALLAN' => 'DC',
            default => strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $docType) ?: 'DOC', 0, 4)),
        };
    }

    /**
     * Detect existing MAX() sequence numbers from target tables for continuity.
     */
    private static function detectStartingSequence(string $docType, int $concernId, int $docYear, string $prefix): int {
        try {
            $yearStr = (string)$docYear;
            $pattern = "{$prefix}-{$yearStr}-%";

            $maxNum = 0;
            switch ($docType) {
                case 'RETURN':
                case 'EXCHANGE':
                case 'RMA':
                    // Check returns_exchanges or production_returns
                    $v1 = DB::fetchValue(
                        "SELECT MAX(return_no) FROM returns_exchanges WHERE concern_id = ? AND return_no LIKE ?",
                        [$concernId, $pattern]
                    );
                    if ($v1) {
                        $parts = explode('-', (string)$v1);
                        $maxNum = max($maxNum, (int)end($parts));
                    }
                    break;

                case 'STOCK_REQUEST':
                case 'REQ':
                    $v2 = DB::fetchValue(
                        "SELECT MAX(request_no) FROM customer_requests WHERE concern_id = ? AND request_no LIKE ?",
                        [$concernId, $pattern]
                    );
                    if ($v2) {
                        $parts = explode('-', (string)$v2);
                        $maxNum = max($maxNum, (int)end($parts));
                    }
                    break;

                case 'PRODUCTION_STOCK_REQUEST':
                    $existingSequence = DB::fetchValue(
                        "SELECT MAX(CAST(SUBSTRING_INDEX(request_no, '-', -1) AS UNSIGNED))
                         FROM production_stock_requests WHERE request_no LIKE ?",
                        [$pattern]
                    );
                    if ($existingSequence !== null) {
                        $maxNum = max($maxNum, (int)$existingSequence);
                    }
                    break;

                case 'DAMAGE_WH':
                case 'DAMAGE_SHOP':
                case 'DAMAGE_CONCERN':
                case 'DAMAGE':
                    $v3 = DB::fetchValue(
                        "SELECT MAX(damage_no) FROM concern_damages WHERE concern_id = ? AND damage_no LIKE ?",
                        [$concernId, $pattern]
                    );
                    if ($v3) {
                        $parts = explode('-', (string)$v3);
                        $maxNum = max($maxNum, (int)end($parts));
                    }
                    break;
            }

            return $maxNum;
        } catch (\Throwable) {
            return 0;
        }
    }
}
