<?php

/**
 * Store Contact Form Request
 *
 * Validates the incoming request when a user adds a new contact.
 * Only an email address is required — the controller handles all
 * business logic (user lookup, self-add prevention, duplicates).
 *
 * Validation rules:
 *   - email: required, valid format, max 255 chars.
 *
 * @see \App\Http\Controllers\ContactController::store()
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by ContactPolicy via Gate::authorize()
     * in the controller, so this always returns true.
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
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * Get custom user-friendly error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter an email address to search for.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'The email address must not exceed 255 characters.',
        ];
    }
}
