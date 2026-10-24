<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionConsumption extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionConsumptionFactory> */
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'model_part_id',
        'material_id',
        'quantity_consumed',
        'stock_unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity_consumed' => 'string',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function modelPart(): BelongsTo
    {
        return $this->belongsTo(ModelPart::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
