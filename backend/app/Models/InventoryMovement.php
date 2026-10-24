<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryMovementFactory> */
    use HasFactory;

    public const TYPE_OPENING = 'opening';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const TYPE_PRODUCTION = 'production';

    protected $fillable = [
        'material_id',
        'production_order_id',
        'movement_type',
        'quantity_delta',
        'balance_after',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'string',
            'balance_after' => 'string',
            'created_at' => 'datetime',
        ];
    }

    public $timestamps = false;

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }
}
