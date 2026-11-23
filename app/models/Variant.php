<?php
// app/models/Variant.php
namespace App\Models;
use App\Core\Model;
use App\Core\DB;
use App\Core\Auth;

class Variant extends Model {
    protected string $table = 'variants';
    protected bool $tenantScoped = true;

    public function allWithDetails(): array {
        $cid = Auth::concernId();
        $sql = "SELECT v.*, 
                       c.name AS category_name, 
                       s.name AS subcategory_name, 
                       b.name AS brand_name,
                       con.name AS concern_name,
                       con.code AS concern_code,
                       COALESCE(stk.total_qty, 0) AS total_stock
                FROM variants v
                LEFT JOIN concerns con ON v.concern_id = con.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories s ON v.subcategory_id = s.id
                LEFT JOIN brands b ON v.brand_id = b.id
                LEFT JOIN (
                    SELECT variant_id, SUM(qty) AS total_qty 
                    FROM stocks 
                    WHERE location NOT LIKE '%:DAMAGED%'
                      AND location NOT LIKE '%:QUARANTINE%'
                      AND location NOT LIKE '%:RESERVED%'
                      AND location NOT LIKE '%:IN_TRANSIT%'
                    GROUP BY variant_id
                ) stk ON v.id = stk.variant_id";
        
        $params = [];
        if ($cid !== null) {
            $sql .= " WHERE v.concern_id = ?";
            $params[] = $cid;
        }

        $sql .= " ORDER BY v.id DESC";
        return DB::fetchAll($sql, $params);
    }

    public function findWithDetails(int $id): ?array {
        $cid = Auth::concernId();
        $sql = "SELECT v.*, 
                       c.name AS category_name, 
                       s.name AS subcategory_name, 
                       b.name AS brand_name,
                       con.name AS concern_name,
                       con.code AS concern_code,
                       COALESCE(stk.total_qty, 0) AS total_stock
                FROM variants v
                LEFT JOIN concerns con ON v.concern_id = con.id
                LEFT JOIN categories c ON v.category_id = c.id
                LEFT JOIN subcategories s ON v.subcategory_id = s.id
                LEFT JOIN brands b ON v.brand_id = b.id
                LEFT JOIN (
                    SELECT variant_id, SUM(qty) AS total_qty 
                    FROM stocks 
                    WHERE location NOT LIKE '%:DAMAGED%'
                      AND location NOT LIKE '%:QUARANTINE%'
                      AND location NOT LIKE '%:RESERVED%'
                      AND location NOT LIKE '%:IN_TRANSIT%'
                    GROUP BY variant_id
                ) stk ON v.id = stk.variant_id
                WHERE v.id = ?";
        
        $params = [$id];
        if ($cid !== null) {
            $sql .= " AND v.concern_id = ?";
            $params[] = $cid;
        }

        $sql .= " LIMIT 1";
        return DB::fetch($sql, $params);
    }
}
