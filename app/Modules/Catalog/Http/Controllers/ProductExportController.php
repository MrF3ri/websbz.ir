<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Catalog\Services\CatalogAuthorization;
use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Subscription\Services\EntitlementService;
use App\Modules\Tenancy\Services\CurrentBusiness;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportController
{
    public function download(
        CurrentBusiness $currentBusiness,
        CatalogAuthorization $authorization,
    ): StreamedResponse {
        $business = $currentBusiness->get();
        $user = auth()->user();

        abort_unless($business instanceof Business, 404);
        abort_unless($user instanceof User && $authorization->allows($user, $business), 403);
        abort_unless((new EntitlementService($business))->hasFeature('excel_export'), 404);

        $menu = $business->menu()->firstOrFail();
        $products = $menu->products()
            ->with('category:id,name')
            ->orderBy('id');

        $filename = 'products-'.$business->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($products): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                'شناسه محصول',
                'دسته‌بندی',
                'نام محصول',
                'توضیحات',
                'قیمت پایه (تومان)',
                'قیمت تخفیف (تومان)',
                'وضعیت انتشار',
                'وضعیت موجودی',
                'برچسب',
                'تاریخ ایجاد',
            ], ',', '"', '');

            $products->chunkById(500, function ($batch) use ($stream): void {
                foreach ($batch as $product) {
                    $row = [
                        $product->public_id,
                        $product->category?->name,
                        $product->name,
                        $product->description,
                        $product->base_price,
                        $product->discount_price,
                        $product->status === ProductStatus::Published ? 'منتشرشده' : 'پیش‌نویس',
                        $product->is_available ? 'موجود' : 'ناموجود',
                        $product->label?->value,
                        $product->created_at?->toIso8601String(),
                    ];

                    fputcsv(
                        $stream,
                        array_map(fn (mixed $value): string => $this->safeCell($value), $row),
                        ',',
                        '"',
                        '',
                    );
                }
            });

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $cell = (string) ($value ?? '');

        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $cell) === 1) {
            return "'".$cell;
        }

        return $cell;
    }
}
