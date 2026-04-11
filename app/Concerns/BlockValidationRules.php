<?php

namespace App\Concerns;

trait BlockValidationRules
{
    public function blockRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
