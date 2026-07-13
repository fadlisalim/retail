<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:191'],
            'customer_phone' => ['required', 'string', 'max:30'],

            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],

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
        ];
    }
}
