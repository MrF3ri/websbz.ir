<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Actions\ReviewManualPaymentAction;
use App\Modules\Subscription\Actions\SubmitManualPaymentAction;
use App\Modules\Subscription\Enums\PaymentStatus;
use App\Modules\Subscription\Enums\SubscriptionEventType;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Payment;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithBusinesses;
use Tests\TestCase;

class SubscriptionPaymentPostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Payment row-lock concurrency requires PostgreSQL.');
        }
    }

    public function test_concurrent_approvals_finalize_payment_and_renew_subscription_only_once(): void
    {
        $owner = User::factory()->create();
        $business = $this->createBusinessFor($owner);
        $subscription = $this->giveBusinessTrialSubscription($business, 'economic');
        $trialEndsAt = $subscription->trial_ends_at;
        self::assertNotNull($trialEndsAt);

        $targetPlan = Plan::query()->where('code', 'professional')->firstOrFail();
        $payment = app(SubmitManualPaymentAction::class)->execute(
            $subscription,
            $targetPlan,
            'BANK-CONCURRENT-APPROVAL-001',
            'payment-receipts/1/concurrent-receipt.pdf',
        );

        $admin = User::factory()->create();
        $admin->forceFill(['is_platform_admin' => true])->save();

        [$first, $second] = $this->runConcurrentApprovals($payment, $admin);

        $this->assertSame(PaymentStatus::Approved->value, $first['status']);
        $this->assertSame(PaymentStatus::Approved->value, $second['status']);
        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        $this->assertSame($targetPlan->getKey(), $subscription->fresh()->plan_id);
        $this->assertSame(
            $trialEndsAt->copy()->addMonth()->timestamp,
            $subscription->fresh()->current_period_ends_at?->timestamp,
        );
        $this->assertSame(
            1,
            $subscription->events()->where('event_type', SubscriptionEventType::PaymentApproved->value)->count(),
        );
        $this->assertSame(
            1,
            $subscription->events()->where('event_type', SubscriptionEventType::Upgraded->value)->count(),
        );
    }

    /**
     * @return array{0: array{status: string}, 1: array{status: string}}
     */
    private function runConcurrentApprovals(Payment $payment, User $admin): array
    {
        $directory = $this->makeSignalDirectory();
        $gate = $this->lockGate($directory);
        $first = null;
        $second = null;

        try {
            $first = $this->startWorker($payment, $admin, $directory, 'first', true);
            $this->waitForFile($directory.'/first.locked');

            $second = $this->startWorker($payment, $admin, $directory, 'second', false);
            $this->waitForDatabaseLockWait('second');
            $this->releaseGate($gate);

            return [
                $this->awaitWorker($first, $directory, 'first'),
                $this->awaitWorker($second, $directory, 'second'),
            ];
        } finally {
            $this->releaseGate($gate);
            $this->stopWorker($first);
            $this->stopWorker($second);
            $this->removeSignalDirectory($directory);
        }
    }

    /**
     * @return array{process: resource, stderr: string}
     */
    private function startWorker(
        Payment $payment,
        User $admin,
        string $directory,
        string $workerId,
        bool $holdAfterLock,
    ): array {
        $command = [
            PHP_BINARY,
            base_path('scripts/subscription-payment-concurrency-worker.stub'),
            (string) $payment->getKey(),
            (string) $admin->getKey(),
            $directory,
            $workerId,
            $holdAfterLock ? '1' : '0',
        ];

        $database = config('database.connections.pgsql');
        $environment = array_merge(getenv() ?: [], [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $directory.'/'.$workerId.'.stdout', 'a'],
            2 => ['file', $directory.'/'.$workerId.'.stderr', 'a'],
        ];

        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, base_path(), $environment);

        if (! is_resource($process)) {
            $this->fail("Unable to start PostgreSQL payment worker [{$workerId}].");
        }

        fclose($pipes[0]);

        return [
            'process' => $process,
            'stderr' => $directory.'/'.$workerId.'.stderr',
        ];
    }

    /**
     * @param  array{process: resource, stderr: string}  $worker
     * @return array{status: string}
     */
    private function awaitWorker(array $worker, string $directory, string $workerId): array
    {
        $resultPath = $directory.'/'.$workerId.'.result';

        for ($attempt = 0; $attempt < 1500; $attempt++) {
            if (is_file($resultPath)) {
                $result = json_decode((string) file_get_contents($resultPath), true, 512, JSON_THROW_ON_ERROR);
                $exitCode = proc_close($worker['process']);

                if (! is_array($result) || ! is_string($result['status'] ?? null)) {
                    $this->fail("Worker [{$workerId}] returned an invalid result.");
                }

                if ($result['status'] === 'error' && $exitCode === 0) {
                    $this->fail("Worker [{$workerId}] returned an error with a successful exit code.");
                }

                return ['status' => $result['status']];
            }

            $status = proc_get_status($worker['process']);

            if (! $status['running']) {
                $stderr = is_file($worker['stderr'])
                    ? (string) file_get_contents($worker['stderr'])
                    : '';

                proc_close($worker['process']);

                $this->fail("Worker [{$workerId}] exited before writing its result. {$stderr}");
            }

            usleep(10_000);
        }

        $this->fail("Timed out waiting for payment worker [{$workerId}].");

        return ['status' => 'error'];
    }

    private function waitForDatabaseLockWait(string $workerId): void
    {
        for ($attempt = 0; $attempt < 1500; $attempt++) {
            $result = DB::selectOne(
                <<<'SQL'
                SELECT CASE WHEN EXISTS (
                    SELECT 1
                    FROM pg_stat_activity
                    WHERE application_name = ?
                      AND wait_event_type = 'Lock'
                ) THEN 1 ELSE 0 END AS waiting
                SQL,
                ['subscription-worker-'.$workerId],
            );

            if ((int) $result->waiting === 1) {
                return;
            }

            usleep(10_000);
        }

        $this->fail("Worker [{$workerId}] never entered a PostgreSQL payment lock wait.");
    }

    private function waitForFile(string $path): void
    {
        for ($attempt = 0; $attempt < 1500; $attempt++) {
            if (is_file($path)) {
                return;
            }

            usleep(10_000);
        }

        $this->fail("Timed out waiting for payment concurrency signal [{$path}].");
    }

    private function makeSignalDirectory(): string
    {
        $directory = sys_get_temp_dir().'/subscription-pgsql-'.Str::uuid();
        mkdir($directory, 0700, true);
        touch($directory.'/gate');

        return $directory;
    }

    /**
     * @return resource
     */
    private function lockGate(string $directory)
    {
        $gate = fopen($directory.'/gate', 'c+');

        if ($gate === false || ! flock($gate, LOCK_EX | LOCK_NB)) {
            $this->fail('Unable to acquire the PostgreSQL payment concurrency gate.');
        }

        return $gate;
    }

    /**
     * @param  resource|null  $gate
     */
    private function releaseGate($gate): void
    {
        if (is_resource($gate)) {
            flock($gate, LOCK_UN);
            fclose($gate);
        }
    }

    /**
     * @param  array{process: resource, stderr: string}|null  $worker
     */
    private function stopWorker(?array $worker): void
    {
        if ($worker === null || ! is_resource($worker['process'])) {
            return;
        }

        $status = proc_get_status($worker['process']);

        if ($status['running']) {
            proc_terminate($worker['process']);
        }

        proc_close($worker['process']);
    }

    private function removeSignalDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}
