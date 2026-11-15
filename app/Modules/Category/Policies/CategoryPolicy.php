<?php

namespace App\Modules\Category\Policies;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Category\Models\Category;
use App\Modules\Catalog\Services\CatalogAuthorization;
use App\Modules\Tenancy\Services\CurrentBusiness;

class CategoryPolicy
{
    private readonly CatalogAuthorization $authorization;

    private readonly CurrentBusiness $currentBusiness;

    public function __construct(
        CatalogAuthorization $authorization,
        CurrentBusiness $currentBusiness,
    ) {
        $this->authorization = $authorization;
        $this->currentBusiness = $currentBusiness;
    }

    public function viewAny(User $user): bool
    {
        $business = $this->currentBusiness->get();

        return $business instanceof Business && $this->authorization->allows($user, $business);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Category $category): bool
    {
        $business = $category->menu?->business;

        return $business instanceof Business && $this->authorization->allows($user, $business);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->view($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->view($user, $category);
    }
}
