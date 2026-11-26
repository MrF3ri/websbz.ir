<?php

namespace Tests\Feature\Review;

use App\Models\User;
use App\Modules\Branch\Models\Branch;
use App\Modules\Business\Models\Business;
use App\Modules\Menu\Enums\MenuStatus;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\Product;
use App\Modules\Review\Enums\ReviewStatus;
use App\Modules\Review\Models\ProductReview;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use InteractsWithCatalog;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_top_plan_guest_can_submit_a_review_pending_moderation(): void
    {
        [, $business, $menu, $product, $branch] = $this->publishedProduct('top');

        $response = $this->post($this->reviewUrl($business, $menu, $product, $branch), [
            'rating' => 5,
            'display_name' => 'A customer',
            'body' => 'Excellent coffee.',
        ]);

        $response->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertCookie('product_review_visitor');

        $review = ProductReview::query()->firstOrFail();
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($item) => $item->getName() === 'product_review_visitor');

        $this->assertNotNull($cookie);
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $review->visitor_key_hash);
        $this->assertSame(5, $review->rating);
        $this->assertSame('A customer', $review->display_name);
        $this->assertSame('Excellent coffee.', $review->body);
    }

    public function test_non_top_plan_cannot_submit_product_reviews(): void
    {
        [, $business, $menu, $product, $branch] = $this->publishedProduct('professional');

        $this->post($this->reviewUrl($business, $menu, $product, $branch), [
            'rating' => 5,
        ])->assertNotFound();

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_guest_cannot_review_a_draft_or_unavailable_product(): void
    {
        [, $business, $menu] = $this->createCatalogTenant('top');
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, status: ProductStatus::Draft);
        $branch = $business->primaryBranch()->firstOrFail();
        $menu->update(['status' => MenuStatus::Published]);

        $this->post($this->reviewUrl($business, $menu, $product, $branch), [
            'rating' => 4,
        ])->assertNotFound();

        $product->update(['status' => ProductStatus::Published, 'is_available' => false]);

        $this->post($this->reviewUrl($business, $menu, $product, $branch), [
            'rating' => 4,
        ])->assertNotFound();

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_guest_cannot_review_a_product_hidden_by_branch_settings(): void
    {
        [, $business, $menu, $product, $branch] = $this->publishedProduct('top');

        $branch->productSettings()->create([
            'business_id' => $business->getKey(),
            'menu_id' => $menu->getKey(),
            'product_id' => $product->getKey(),
            'is_visible' => false,
            'is_available' => true,
        ]);

        $this->post($this->reviewUrl($business, $menu, $product, $branch), [
            'rating' => 4,
        ])->assertNotFound();

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        [, $business, $menu, $product, $branch] = $this->publishedProduct('top');

        $this->post($this->reviewUrl($business, $menu, $product, $branch), [
            'rating' => 6,
        ])->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_same_browser_cannot_submit_duplicate_review_for_same_product(): void
    {
        $this->withoutMiddleware(EncryptCookies::class);

        [, $business, $menu, $product, $branch] = $this->publishedProduct('top');
        $url = $this->reviewUrl($business, $menu, $product, $branch);
        $visitorToken = str_repeat('a', 64);

        $this->withCookie('product_review_visitor', $visitorToken)
            ->post($url, ['rating' => 4])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->withCookie('product_review_visitor', $visitorToken)
            ->post($url, ['rating' => 3])
            ->assertSessionHasErrors('review');

        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->getKey(),
            'visitor_key_hash' => hash('sha256', $visitorToken),
        ]);
    }

    public function test_public_menu_displays_only_approved_reviews_and_aggregates(): void
    {
        [, $business, $menu, $product, $branch] = $this->publishedProduct('top');

        ProductReview::query()->create([
            'business_id' => $business->getKey(),
            'menu_id' => $menu->getKey(),
            'product_id' => $product->getKey(),
            'rating' => 5,
            'body' => 'Approved review',
            'status' => ReviewStatus::Approved,
            'visitor_key_hash' => hash('sha256', 'approved-reviewer'),
        ]);

        ProductReview::query()->create([
            'business_id' => $business->getKey(),
            'menu_id' => $menu->getKey(),
            'product_id' => $product->getKey(),
            'rating' => 1,
            'body' => 'Pending review',
            'status' => ReviewStatus::Pending,
            'visitor_key_hash' => hash('sha256', 'pending-reviewer'),
        ]);

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.product_reviews_enabled', true)
                ->where('categories.0.products.0.review_count', 1)
                ->where('categories.0.products.0.average_rating', 5)
                ->has('categories.0.products.0.reviews', 1)
                ->where('categories.0.products.0.reviews.0.body', 'Approved review'));
    }

    public function test_business_owner_can_approve_a_pending_review_and_publish_it(): void
    {
        [$user, $business, $menu, $product, $branch] = $this->publishedProduct('top');
        $review = $this->createReview($business, $menu, $product);

        $this->actingAs($user)
            ->get(route('catalog.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Reviews')
                ->has('reviews.data', 1)
                ->where('reviews.data.0.public_id', $review->public_id));

        $this->actingAs($user)
            ->post(route('catalog.reviews.approve', $review->public_id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->getKey(),
            'status' => ReviewStatus::Approved->value,
            'moderated_by' => $user->getKey(),
        ]);

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.review_count', 1)
                ->where('categories.0.products.0.reviews.0.body', 'A useful review'));
    }

    public function test_business_owner_can_reject_a_review_without_public_display(): void
    {
        [$user, $business, $menu, $product, $branch] = $this->publishedProduct('top');
        $review = $this->createReview($business, $menu, $product);

        $this->actingAs($user)
            ->post(route('catalog.reviews.reject', $review->public_id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->getKey(),
            'status' => ReviewStatus::Rejected->value,
        ]);

        $this->get(route('public.menu.branch.show', [$business->slug, $menu->slug, $branch->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.products.0.review_count', 0)
                ->has('categories.0.products.0.reviews', 0));
    }

    public function test_review_moderation_is_tenant_scoped(): void
    {
        [$userA] = $this->createCatalogTenant('top');
        [, $businessB, $menuB, $productB] = $this->publishedProduct('top');
        $review = $this->createReview($businessB, $menuB, $productB);

        $this->actingAs($userA)
            ->post(route('catalog.reviews.approve', $review->public_id))
            ->assertNotFound();

        $this->assertDatabaseHas('product_reviews', [
            'id' => $review->getKey(),
            'status' => ReviewStatus::Pending->value,
        ]);
    }

    /**
     * @return array{0: User, 1: Business, 2: Menu, 3: Product, 4: Branch}
     */
    private function publishedProduct(string $plan): array
    {
        [$user, $business, $menu] = $this->createCatalogTenant($plan);
        $category = $this->createCatalogCategory($menu);
        $product = $this->createCatalogProduct($menu, $category, status: ProductStatus::Published);
        $menu->update(['status' => MenuStatus::Published]);
        $branch = $business->primaryBranch()->firstOrFail();

        return [$user, $business, $menu, $product, $branch];
    }

    private function reviewUrl(Business $business, Menu $menu, Product $product, Branch $branch): string
    {
        return route('public.menu.reviews.store', [
            $business->slug,
            $menu->slug,
            $branch->slug,
            $product->public_id,
        ]);
    }

    private function createReview(Business $business, Menu $menu, Product $product): ProductReview
    {
        return ProductReview::query()->create([
            'business_id' => $business->getKey(),
            'menu_id' => $menu->getKey(),
            'product_id' => $product->getKey(),
            'rating' => 4,
            'display_name' => 'Customer',
            'body' => 'A useful review',
            'status' => ReviewStatus::Pending,
            'visitor_key_hash' => hash('sha256', 'reviewer-'.$product->getKey()),
        ]);
    }
}
