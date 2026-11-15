<?php

namespace App\Modules\Catalog\Services;

use App\Enums\BusinessRole;
use App\Models\User;
use App\Modules\Business\Models\Business;

class CatalogAuthorization
{
    public function allows(User $user, Business $business): bool
    {
        return $business->members()
            ->where('user_id', $user->getKey())
            ->whereIn('role', [
                BusinessRole::Owner->value,
                BusinessRole::Admin->value,
                BusinessRole::Manager->value,
                BusinessRole::Editor->value,
            ])
            ->exists();
    }
}
