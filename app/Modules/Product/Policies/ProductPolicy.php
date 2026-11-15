<?php

namespace App\Modules\Product\Policies;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Catalog\Services\CatalogAuthorization;
use App\Modules\Product\Models\Product;
use App\Modules\Tenancy\Services\CurrentBusiness;

class ProductPolicy
{
    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CurrentBusiness $currentBusiness,
    ) {}

    public function viewAny(User $user): bool
    {
        $business = $this->currentBusiness->get();

        return $business instanceof Business && $this->authorization->allows($user, $business);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->authorization->allows($user, $product->menu->business);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}
