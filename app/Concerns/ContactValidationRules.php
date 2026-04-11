<?php

namespace App\Concerns;

use App\Models\Contact;
use Illuminate\Contracts\Validation\Rule;

trait ContactValidationRules
{
    /**
     * Get the validation rules for a new contact request.
     *
     * @return array<string, array<int, Rule|string>>
     */
    protected function contactRequestRules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'exists:users,email',
            ],
        ];
    }

    /**
     * Get the validation rules for accepting/declining a contact.
     *
     * @return array<string, array<int, Rule|string>>
     */
    protected function contactUpdateRules(): array
    {
        return [
            'action' => ['required', 'string', 'in:accept,decline'],
        ];
    }
}
