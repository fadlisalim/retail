<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:191'],
            'customer_phone' => ['required', 'string', 'max:30'],

            // Shipping address must be one saved to the logged-in user's account.
            'address_id' => ['required', 'integer', Rule::exists('customer_addresses', 'id')->where('user_id', $this->user()?->id)],

            'shipping_provider' => ['required', 'string', 'max:40'],
            'shipping_service' => ['required', 'string', 'max:40'],
            'payment_method' => ['required', 'string', 'max:40'],

            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'agree_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'agree_terms.accepted' => 'Anda harus menyetujui syarat dan ketentuan.',
            'address_id.required' => 'Pilih alamat pengiriman terlebih dahulu.',
            'address_id.exists' => 'Alamat pengiriman tidak valid.',
        ];
    }
}
