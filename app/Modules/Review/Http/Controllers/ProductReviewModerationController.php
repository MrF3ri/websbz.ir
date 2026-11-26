<?php

namespace App\\Modules\\Review\\Http\\Controllers;

use App\\Modules\\Business\\Models\\Business;
use App\\Modules\\Product\\Models\\Product;
use App\\Modules\\PublicMenu\\Services\\PublicMenuCache;
use App\\Modules\\Review\\Enums\\ReviewStatus;
use App\\Modules\\Review\\Models\\ProductReview;
use App\\Modules\\Subscription\\Services\\EntitlementService;
use App\\Modules\\Tenancy\\Services\\CurrentBusiness;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Support\\Facades\\DB;
use Inertia\\Inertia;
use Inertia\\Response;

class ProductReviewModerationController
{
    public function index(CurrentBusiness $currentBusiness): Response
    {
        $business = $this->business($currentBusiness);
        abort_unless((new EntitlementService($business))->hasFeature('product_reviews'), 404);

        $reviews = ProductReview::query()
            ->where('business_id', $business->getKey())
            ->with('product:id,public_id,name')
            ->latest('id')
            ->paginate(20)
            ->through(fn (ProductReview $review): array => [
                'public_id' => $review->public_id,
                'product_name' => $review->product->name,
                'rating' => $review->rating,
                'display_name' => $review->display_name,
                'body' => $review->body,
                'status' => $review->status->value,
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Catalog/Reviews', [
            'reviews' => $reviews,
        ]);
    }

    public function approve(
        string $reviewPublicId,
        CurrentBusiness $currentBusiness,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        return $this->moderate($reviewPublicId, ReviewStatus::Approved, $currentBusiness, $publicMenuCache);
    }

    public function reject(
        string $reviewPublicId,
        CurrentBusiness $currentBusiness,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        return $this->moderate($reviewPublicId, ReviewStatus::Rejected, $currentBusiness, $publicMenuCache);
    }

    private function moderate(
        string $reviewPublicId,
        ReviewStatus $status,
        CurrentBusiness $currentBusiness,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness);
        abort_unless((new EntitlementService($business))->hasFeature('product_reviews'), 404);

        $review = ProductReview::query()
            ->where('business_id', $business->getKey())
            ->where('public_id', $reviewPublicId)
            ->firstOrFail();

        if ($review->status !== ReviewStatus::Pending) {
            return back()->withErrors(['review' => 'این نظر قبلاً بررسی شده است.']);
        }

        DB::transaction(function () use ($review, $status): void {
            $lockedReview = ProductReview::query()
                ->whereKey($review->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReview->status !== ReviewStatus::Pending) {
                return;
            }

            $lockedReview->update([
                'status' => $status,
                'moderated_by' => auth()->id(),
                'moderated_at' => now(),
            ]);
        });

        $menu = $review->menu;
        if ($menu !== null) {
            $publicMenuCache->forget($menu);
        }

        return back()->with('success', $status === ReviewStatus::Approved
            ? 'نظر تأیید و در منوی عمومی نمایش داده شد.'
            : 'نظر رد شد.');
    }

    private function business(CurrentBusiness $currentBusiness): Business
    {
        $business = $currentBusiness->get();
        abort_unless($business instanceof Business, 404);

        return $business;
    }
}
