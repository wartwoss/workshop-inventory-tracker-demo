<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
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
        return [
            'furniture_model_slug' => ['required', 'string', 'exists:furniture_models,slug'],
            'length_mm' => ['required', 'integer', 'min:1'],
            'width_mm' => ['required', 'integer', 'min:1'],
            'height_mm' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.code' => ['required', 'string'],
            'parts.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'parts.*.length_mm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'parts.*.width_mm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'parts.*.height_mm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'parts.*.pos_x_mm' => ['sometimes', 'nullable', 'integer', 'min:-5000', 'max:5000'],
            'parts.*.pos_y_mm' => ['sometimes', 'nullable', 'integer', 'min:-5000', 'max:5000'],
            'parts.*.pos_z_mm' => ['sometimes', 'nullable', 'integer', 'min:-5000', 'max:5000'],
            'parts.*.rot_x_deg' => ['sometimes', 'nullable', 'integer', 'min:-360', 'max:360'],
            'parts.*.rot_y_deg' => ['sometimes', 'nullable', 'integer', 'min:-360', 'max:360'],
            'parts.*.rot_z_deg' => ['sometimes', 'nullable', 'integer', 'min:-360', 'max:360'],
            'parts.*.shape' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }
}
