<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FurnitureModel extends Model
{
    /** @use HasFactory<\Database\Factories\FurnitureModelFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'model_asset_path',
        'is_active',
        'is_custom',
        'default_length_mm',
        'min_length_mm',
        'max_length_mm',
        'default_width_mm',
        'min_width_mm',
        'max_width_mm',
        'default_height_mm',
        'min_height_mm',
        'max_height_mm',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'default_length_mm' => 'integer',
            'min_length_mm' => 'integer',
            'max_length_mm' => 'integer',
            'default_width_mm' => 'integer',
            'min_width_mm' => 'integer',
            'max_width_mm' => 'integer',
            'default_height_mm' => 'integer',
            'min_height_mm' => 'integer',
            'max_height_mm' => 'integer',
        ];
    }

    public function modelParts(): HasMany
    {
        return $this->hasMany(ModelPart::class)->orderBy('sort_order');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
