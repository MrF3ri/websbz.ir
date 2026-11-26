<?php

namespace App\Modules\Review\Http\Controllers;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Catalog\Services\CatalogAuthorization;
use App\Modules\PublicMenu\Services\PublicMenuCache;
use App\Modules\Review\Enums\ReviewStatus;
use App\Modules\Review\Models\ProductReview;
use App\Modules\Subscription\Services\EntitlementService;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductReviewModerationController
{
    public function index(CurrentBusiness $currentBusiness, CatalogAuthorization $authorization): Response
    {
        $business = $this->business($currentBusiness, $authorization);
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
        CatalogAuthorization $authorization,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        return $this->moderate($reviewPublicId, ReviewStatus::Approved, $currentBusiness, $authorization, $publicMenuCache);
    }

    public function reject(
        string $reviewPublicId,
        CurrentBusiness $currentBusiness,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        return $this->moderate($reviewPublicId, ReviewStatus::Rejected, $currentBusiness, $authorization, $publicMenuCache);
    }

    private function moderate(
        string $reviewPublicId,
        ReviewStatus $status,
        CurrentBusiness $currentBusiness,
        CatalogAuthorization $authorization,
        PublicMenuCache $publicMenuCache,
    ): RedirectResponse {
        $business = $this->business($currentBusiness, $authorization);
        abort_unless((new EntitlementService($business))->hasFeature('product_reviews'), 404);

        $review = ProductReview::query()
            ->where('business_id', $business->getKey())
            ->where('public_id', $reviewPublicId)
            ->firstOrFail();

        if ($review->status !== ReviewStatus::Pending) {
            return back()->withErrors(['review' => 'این نظر قبلاً بررسی شده است.']);
        }

        $moderated = DB::transaction(function () use ($review, $status): bool {
            $lockedReview = ProductReview::query()
                ->whereKey($review->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReview->status !== ReviewStatus::Pending) {
                return false;
            }

            $lockedReview->update([
                'status' => $status,
                'moderated_by' => auth()->id(),
                'moderated_at' => now(),
            ]);

            return true;
        });

        if (! $moderated) {
            return back()->withErrors(['review' => 'این نظر قبلاً بررسی شده است.']);
        }

        $menu = $review->menu;
        if ($menu !== null) {
            $publicMenuCache->forget($menu);
        }

        return back()->with('success', $status === ReviewStatus::Approved
            ? 'نظر تأیید و در منوی عمومی نمایش داده شد.'
            : 'نظر رد شد.');
    }

    private function business(CurrentBusiness $currentBusiness, CatalogAuthorization $authorization): Business
    {
        $business = $currentBusiness->get();
        $user = auth()->user();
        abort_unless($business instanceof Business, 404);
        abort_unless($user instanceof User && $authorization->allows($user, $business), 403);

        return $business;
    }
}
