<?php
// app/services/NumberSeries.php - Atomic document series generator

namespace App\Services;

use App\Core\DB;

class NumberSeries {
    /**
     * Generate next document sequence number for a concern and doc_type
     */
    public static function getNext(int $concernId, string $docType): string {
        $docType = strtoupper(trim($docType));

        $hasTx = DB::pdo()->inTransaction();
        if (!$hasTx) {
            DB::beginTransaction();
        }

        try {
            $row = DB::fetch(
                "SELECT prefix, next_no FROM doc_series WHERE concern_id = ? AND doc_type = ? FOR UPDATE",
                [$concernId, $docType]
            );

            if ($docType === 'INVOICE') {
                $concernCode = DB::fetchValue("SELECT code FROM concerns WHERE id = ?", [$concernId]);
                $prefix = rtrim(strtoupper(trim((string)$concernCode)), '-');
                $prefix = ($prefix !== '' ? $prefix : 'INV') . '-';
                $nextNo = $row ? max(1, (int)$row['next_no']) : 1;

                if ($row) {
                    DB::query(
                        "UPDATE doc_series SET prefix = ?, next_no = ? WHERE concern_id = ? AND doc_type = ?",
                        [$prefix, $nextNo + 1, $concernId, $docType]
                    );
                } else {
                    DB::query(
                        "INSERT INTO doc_series (concern_id, doc_type, prefix, next_no) VALUES (?, ?, ?, ?)",
                        [$concernId, $docType, $prefix, $nextNo + 1]
                    );
                }

                $code = sprintf("%s%02d", $prefix, $nextNo);
            } elseif (!$row) {
                // Initialize default
                $prefix = strtoupper(substr($docType, 0, 3)) . '-';
                $nextNo = 1001;
                DB::query(
                    "INSERT INTO doc_series (concern_id, doc_type, prefix, next_no) VALUES (?, ?, ?, ?)",
                    [$concernId, $docType, $prefix, $nextNo + 1]
                );
                $code = sprintf("%s%04d", $prefix, $nextNo);
            } else {
                $code = sprintf("%s%04d", $row['prefix'], $row['next_no']);
                DB::query(
                    "UPDATE doc_series SET next_no = next_no + 1 WHERE concern_id = ? AND doc_type = ?",
                    [$concernId, $docType]
                );
            }

            if (!$hasTx) {
                DB::commit();
            }

            return $code;
        } catch (\Throwable $e) {
            if (!$hasTx) {
                DB::rollBack();
            }
            throw $e;
        }
    }
}
