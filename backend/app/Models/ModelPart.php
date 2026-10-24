<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelPart extends Model
{
    /** @use HasFactory<\Database\Factories\ModelPartFactory> */
    use HasFactory;

    protected $fillable = [
        'furniture_model_id',
        'code',
        'name',
        'component_type',
        'material_group',
        'compatible_purposes',
        'consumption_unit',
        'formula_key',
        'formula_parameters',
        'is_configurable',
        'sort_order',
        'material_id',
        'length_mm',
        'width_mm',
        'height_mm',
        'pos_x_mm',
        'pos_y_mm',
        'pos_z_mm',
        'rot_x_deg',
        'rot_y_deg',
        'rot_z_deg',
        'shape',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'compatible_purposes' => 'array',
            'formula_parameters' => 'array',
            'is_configurable' => 'boolean',
            'sort_order' => 'integer',
            'material_id' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'pos_x_mm' => 'integer',
            'pos_y_mm' => 'integer',
            'pos_z_mm' => 'integer',
            'rot_x_deg' => 'integer',
            'rot_y_deg' => 'integer',
            'rot_z_deg' => 'integer',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function furnitureModel(): BelongsTo
    {
        return $this->belongsTo(FurnitureModel::class);
    }

    public function productionConsumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }
}
