<?php

namespace App\Policies;

use App\Models\Trash;
use App\Models\User;

class TrashPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Trash $trash): bool
    {
        return $trash->user_id === $user->id;
    }

    public function forceDelete(User $user, Trash $trash): bool
    {
        return $trash->user_id === $user->id;
    }
}
