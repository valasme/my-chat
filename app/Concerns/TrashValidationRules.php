<?php

namespace App\Concerns;

trait TrashValidationRules
{
    public function trashRules(): array
    {
        return [
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'duration' => ['required_without:is_quick_delete', 'nullable', 'string', 'in:7d,14d,30d,60d,custom'],
            'expires_at' => ['nullable', 'required_if:duration,custom', 'date', 'after:now'],
            'is_quick_delete' => ['nullable', 'boolean'],
        ];
    }
}
