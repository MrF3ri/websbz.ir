<?php

use App\Modules\Business\Models\Business;
use App\Modules\Category\Actions\CreateCategoryAction;
use App\Modules\Category\Models\Category;
use App\Modules\Menu\Models\Menu;
use App\Modules\Product\Actions\CreateProductAction;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment('testing')) {
    fwrite(STDERR, "This worker is only available in the testing environment.\n");
    exit(2);
}

[
    $resource,
    $businessId,
    $menuId,
    $categoryId,
    $name,
    $signalDirectory,
    $workerId,
    $holdAfterLock,
    $failAfterLock,
] = array_pad(array_slice($argv, 1), 9, null);

$businessId = (int) $businessId;
$menuId = (int) $menuId;
$categoryId = (int) $categoryId;
$holdAfterLock = $holdAfterLock === '1';
$failAfterLock = $failAfterLock === '1';

if (! in_array($resource, ['product', 'category'], true) || ! is_dir((string) $signalDirectory)) {
    fwrite(STDERR, "Invalid worker arguments.\n");
    exit(2);
}

DB::connection()->selectOne(
    "select set_config('application_name', ?, false)",
    ['catalog-worker-'.$workerId],
);

file_put_contents($signalDirectory.'/'.$workerId.'.started', 'started');

$menuLockObserved = false;

DB::listen(function (QueryExecuted $query) use (
    &$menuLockObserved,
    $signalDirectory,
    $workerId,
    $holdAfterLock,
    $failAfterLock,
): void {
    $sql = strtolower($query->sql);

    if (
        $menuLockObserved
        || ! str_contains($sql, 'for update')
        || ! preg_match('/\bfrom\s+["`]?menus["`]?/', $sql)
    ) {
        return;
    }

    $menuLockObserved = true;
    file_put_contents($signalDirectory.'/'.$workerId.'.locked', 'locked');

    if ($holdAfterLock) {
        $gate = fopen($signalDirectory.'/gate', 'c+');

        if ($gate === false) {
            throw new \\RuntimeException('Unable to open the concurrency gate.');
        }

        try {
            if (! flock($gate, LOCK_EX)) {
                throw new RuntimeException('Unable to wait on the concurrency gate.');
            }

            flock($gate, LOCK_UN);
        } finally {
            fclose($gate);
        }
    }

    if ($failAfterLock) {
        throw new RuntimeException('Intentional failure after acquiring the menu lock.');
    }
});

$result = ['status' => 'error', 'message' => 'Worker did not execute.'];

try {
    $business = Business::query()->findOrFail($businessId);
    $menu = Menu::query()
        ->whereKey($menuId)
        ->where('business_id', $businessId)
        ->firstOrFail();

    if ($resource === 'category') {
        app(CreateCategoryAction::class)->execute($business, $menu, (string) $name);
    } else {
        $category = Category::query()
            ->whereKey($categoryId)
            ->where('menu_id', $menu->getKey())
            ->firstOrFail();

        app(CreateProductAction::class)->execute($business, $menu, $category, [
            'name' => (string) $name,
            'base_price' => 100000,
            'status' => 'draft',
        ]);
    }

    $result = ['status' => 'success'];
} catch (AccessDeniedHttpException $exception) {
    $result = ['status' => 'forbidden', 'message' => $exception->getMessage()];
} catch (\\Throwable $exception) {
    $result = [
        'status' => 'error',
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ];
}

file_put_contents(
    $signalDirectory.'/'.$workerId.'.result',
    json_encode($result, JSON_THROW_ON_ERROR),
);

exit($result['status'] === 'error' ? 1 : 0);
