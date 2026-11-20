<?php
// app/models/ProductionUnit.php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ProductionUnit extends Model {
    protected string $table = 'production_units';
    protected bool $tenantScoped = true;
}
