<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Material extends Model
{
    /** @use HasFactory<\Database\Factories\MaterialFactory> */
    use HasFactory;

    public const PURPOSE_BOARD = 'board';
    public const PURPOSE_LEG = 'leg';
    public const PURPOSE_HANDLE = 'handle';
    public const PURPOSE_OTHER = 'other';

    public const PURPOSES = [
        self::PURPOSE_BOARD,
        self::PURPOSE_LEG,
        self::PURPOSE_HANDLE,
        self::PURPOSE_OTHER,
    ];

    /**
     * Supported stock units for each material purpose.
     */
    public const PURPOSE_UNITS = [
        self::PURPOSE_BOARD => ['m2'],
        self::PURPOSE_LEG => ['ea'],
        self::PURPOSE_HANDLE => ['ea'],
        self::PURPOSE_OTHER => ['m2', 'm', 'ea', 'kg', 'g', 'L', 'mL'],
    ];

    protected $fillable = [
        'name',
        'sku',
        'material_group',
        'purpose',
        'stock_unit',
        'quantity_on_hand',
        'reorder_level',
        'visual_key',
        'color_hex',
        'texture_path',
        'is_active',
    ];

    protected $appends = [
        'texture_url',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'string',
            'reorder_level' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->orderBy('created_at', 'desc');
    }

    public function productionConsumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_level === null) {
            return false;
        }

        return bccomp($this->quantity_on_hand, $this->reorder_level, 6) <= 0;
    }

    /**
     * Public URL for the texture image, if one is stored.
     */
    protected function textureUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->texture_path
                ? Storage::disk('public')->url($this->texture_path)
                : null
        );
    }
}
