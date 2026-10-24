<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $material = $this->route('material');
        $quantityRules = $material instanceof Material && $material->stock_unit === 'ea'
            ? ['required', 'integer']
            : ['required', 'numeric', 'decimal:0,6'];

        return [
            'quantity' => $quantityRules,
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
