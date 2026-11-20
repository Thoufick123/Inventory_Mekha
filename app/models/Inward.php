<?php
namespace App\Models;
use App\Core\Model;

class Inward extends Model {
    protected string $table = 'inwards';
    protected bool $tenantScoped = true;

    /**
     * Generate sequential Goods Receipt Note (GRN) number e.g. GRN-20261120-0001
     */
    public static function generateGrnNumber(?int $concernId = null): string {
        $cid = $concernId ?: \App\Core\Auth::concernId() ?: 1;
        $datePrefix = date('Ymd');
        $prefix = "GRN-{$datePrefix}-";
        
        $last = \App\Core\DB::fetchValue(
            "SELECT grn_number FROM inwards WHERE concern_id = ? AND grn_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$cid, "{$prefix}%"]
        );

        $nextSeq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq = (int)end($parts);
            $nextSeq = $seq + 1;
        }

        return $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
