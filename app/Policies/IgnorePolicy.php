<?php

namespace App\Policies;

use App\Models\Ignore;
use App\Models\User;

class IgnorePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Ignore $ignore): bool
    {
        return $ignore->ignorer_id === $user->id;
    }
}
