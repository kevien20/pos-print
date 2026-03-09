<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.product_id.required' => 'Each item must have a product.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.quantity.min' => 'Each item quantity must be at least 1.',
        ];
    }
}
