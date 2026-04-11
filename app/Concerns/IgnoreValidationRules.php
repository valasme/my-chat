<?php

namespace App\Concerns;

trait IgnoreValidationRules
{
    public function ignoreRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'duration' => ['required', 'string', 'in:1h,8h,24h,3d,7d,custom'],
            'expires_at' => ['nullable', 'required_if:duration,custom', 'date', 'after:now'],
        ];
    }
}
