<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'furniture_model_id',
        'length_mm',
        'width_mm',
        'height_mm',
        'quantity',
        'configuration_snapshot',
        'status',
        'idempotency_key',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'quantity' => 'integer',
            'configuration_snapshot' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function furnitureModel(): BelongsTo
    {
        return $this->belongsTo(FurnitureModel::class);
    }

    public function productionConsumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
