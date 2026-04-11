<?php

namespace App\Concerns;

trait MessageValidationRules
{
    public function messageRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
