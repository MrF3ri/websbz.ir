<?php

namespace App\\Modules\\Review\\Http\\Controllers;

use App\\Enums\\BusinessStatus;
use App\\Modules\\Business\\Models\\Business;
use App\\Modules\\Menu\\Enums\\MenuStatus;
use App\\Modules\\Review\\Enums\\ReviewStatus;
use App\\Modules\\Review\\Http\\Requests\\StoreProductReviewRequest;
use App\\Modules\\Review\\Models\\ProductReview;
use App\\Modules\\Product\\Enums\\ProductStatus;
use App\\Modules\\Subscription\\Services\\EntitlementService;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Validation\\ValidationException;

class ProductReviewController
{
    private const VISITOR_COOKIE = 'product_review_visitor';

    public function store(
        StoreProductReviewRequest $request,
        string $businessSlug,
        string $menuSlug,
        string $branchSlug,
        string $productPublicId,
    ): RedirectResponse {
        $business = Business::query()
            ->where('slug', $businessSlug)
            ->where('status', BusinessStatus::Active)
            ->firstOrFail();

        $menu = $business->menu()
            ->where('slug', $menuSlug)
            ->where('status', MenuStatus::Published)
            ->firstOrFail();

        $menu->business->branches()
            ->where('slug', $branchSlug)
            ->where('status', 'active')
            ->firstOrFail();

        $product = $menu->products()
            ->where('public_id', $productPublicId)
            ->where('status', ProductStatus::Published)
            ->where('is_available', true)
            ->firstOrFail();

        abort_unless((new EntitlementService($business))->hasFeature('product_reviews'), 404);

        $visitorKey = $request->cookie(self::VISITOR_COOKIE);
        if (! is_string($visitorKey) || preg_match('/^[a-f0-9]{64}$/', $visitorKey) !== 1) {
            $visitorKey = bin2hex(random_bytes(32));
        }

        $visitorKeyHash = hash('sha256', $visitorKey);
        $alreadyReviewed = ProductReview::query()
            ->where('product_id', $product->getKey())
            ->where('visitor_key_hash', $visitorKeyHash)
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'review' => 'از این مرورگر قبلاً برای این محصول نظر ثبت شده است.',
            ]);
        }

        DB::transaction(function () use ($business, $menu, $product, $request, $visitorKeyHash): void {
            ProductReview::query()->create([
                'business_id' => $business->getKey(),
                'menu_id' => $menu->getKey(),
                'product_id' => $product->getKey(),
                'rating' => $request->integer('rating'),
                'display_name' => $request->validated('display_name'),
                'body' => $request->validated('body'),
                'status' => ReviewStatus::Pending,
                'visitor_key_hash' => $visitorKeyHash,
            ]);
        });

        $cookie = cookie(
            self::VISITOR_COOKIE,
            $visitorKey,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );

        return back()
            ->with('success', 'نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.')
            ->withCookie($cookie);
    }
}
