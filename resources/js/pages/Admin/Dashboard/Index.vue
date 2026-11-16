<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Building2, CreditCard, Wallet } from '@lucide/vue';
import { useJalaliDate } from '@/composables/useJalaliDate';

type HealthCheck = {
    key: string;
    label: string;
    status: 'ok' | 'warning';
    message: string;
    value: string | null;
};

type Payment = {
    id: number;
    business_name: string;
    plan_name: string;
    amount: number;
    status: string;
    created_at: string | null;
};

const props = defineProps<{
    metrics: {
        businesses: number;
        active_subscriptions: number;
        pending_payments: number;
        approved_payments_total: number;
        approved_payments_this_month: number;
    };
    recentPayments: Payment[];
    configurationHealth: HealthCheck[];
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function formatToman(amount: number): string {
    return `${numberFormatter.format(amount)} تومان`;
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        pending: 'در انتظار',
        successful: 'موفق',
        failed: 'ناموفق',
        pending_review: 'در انتظار بررسی',
        approved: 'تأییدشده',
        rejected: 'ردشده',
    };

    return labels[status] ?? status;
}

function statusClass(status: string): string {
    if (status === 'approved' || status === 'successful') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }

    if (status === 'rejected' || status === 'failed') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
}
</script>

<template>
    <Head title="داشبورد مدیریت پلتفرم" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-muted-foreground">مدیریت کل سامانه</p>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">داشبورد ادمین اصلی</h1>
                    <p class="text-sm text-muted-foreground">نمای کلی کسب‌وکارها، اشتراک‌ها و وضعیت مالی پلتفرم</p>
                </div>
                <nav aria-label="ناوبری مدیریت" class="flex flex-wrap gap-2 text-sm">
                    <a href="/admin/businesses" class="rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted">کسب‌وکارها</a>
                    <a href="/admin/subscriptions" class="rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted">اشتراک‌ها</a>
                    <a href="/admin/events" class="rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted">رویدادها</a>
                    <a href="/admin/audit-logs" class="rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted">گزارش ممیزی</a>
                    <a href="/admin/payments" class="rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted">
                        بررسی پرداخت‌ها
                        <span v-if="metrics.pending_payments > 0" class="mr-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-amber-700">{{ numberFormatter.format(metrics.pending_payments) }}</span>
                    </a>
                    <a href="/admin/settings" class="rounded-lg border border-border px-4 py-2 font-medium hover:bg-muted">تنظیمات پلتفرم</a>
                </nav>
            </header>

            <section aria-label="شاخص‌های کلیدی" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">کل کسب‌وکارها</p>
                        <Building2 class="size-5 text-primary" aria-hidden="true" />
                    </div>
                    <p class="mt-4 text-3xl font-bold tabular-nums">{{ numberFormatter.format(metrics.businesses) }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">تمام کسب‌وکارهای ثبت‌شده</p>
                </article>
                <article class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">اشتراک‌های فعال</p>
                        <Activity class="size-5 text-primary" aria-hidden="true" />
                    </div>
                    <p class="mt-4 text-3xl font-bold tabular-nums">{{ numberFormatter.format(metrics.active_subscriptions) }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">اشتراک فعال و دوره آزمایشی</p>
                </article>
                <article class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">پرداخت‌های در انتظار</p>
                        <CreditCard class="size-5 text-primary" aria-hidden="true" />
                    </div>
                    <p class="mt-4 text-3xl font-bold tabular-nums">{{ numberFormatter.format(metrics.pending_payments) }}</p>
                    <p class="mt-2 text-xs text-muted-foreground"><a href="/admin/payments" class="text-primary underline underline-offset-4">رفتن به صف بررسی</a></p>
                </article>
                <article class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">مجموع پرداخت تأییدشده</p>
                        <Wallet class="size-5 text-primary" aria-hidden="true" />
                    </div>
                    <p class="mt-4 text-2xl font-bold tabular-nums">{{ formatToman(metrics.approved_payments_total) }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">این ماه: {{ formatToman(metrics.approved_payments_this_month) }}</p>
                </article>
            </section>

            <section aria-label="سلامت پیکربندی پلتفرم" class="space-y-4">
                <div>
                    <h2 class="font-semibold">سلامت پیکربندی پلتفرم</h2>
                    <p class="mt-1 text-sm text-muted-foreground">وضعیت تنظیمات پایه پرداخت، طرح‌ها، ذخیره‌سازی خصوصی و اتصال صف پردازش</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="check in configurationHealth" :key="check.key" class="rounded-xl border border-border bg-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold">{{ check.label }}</h3>
                            <span class="shrink-0 text-xs font-medium" :class="check.status === 'ok' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'">
                                {{ check.status === 'ok' ? 'مناسب' : 'نیازمند بررسی' }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ check.message }}</p>
                        <p v-if="check.value !== null" class="mt-2 text-xs font-medium tabular-nums">{{ check.value }}</p>
                    </article>
                </div>
                <p class="text-xs leading-6 text-muted-foreground">این بخش فقط وضعیت پیکربندی را بررسی می‌کند؛ روشن بودن worker، سلامت اتصال‌های بیرونی و تحویل ایمیل در این صفحه پایش نمی‌شود. هیچ مقدار محرمانه‌ای نمایش داده نمی‌شود.</p>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="font-semibold">آخرین پرداخت‌ها</h2>
                        <p class="mt-1 text-sm text-muted-foreground">هشت پرداخت اخیر ثبت‌شده در سامانه</p>
                    </div>
                    <a href="/admin/payments" class="text-sm font-medium text-primary underline underline-offset-4">مشاهده صف پرداخت‌ها</a>
                </div>

                <div v-if="recentPayments.length === 0" class="px-6 py-14 text-center text-sm text-muted-foreground">
                    هنوز پرداختی ثبت نشده است.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-muted/40 text-right text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3 font-medium">کسب‌وکار</th>
                                <th class="px-5 py-3 font-medium">طرح</th>
                                <th class="px-5 py-3 font-medium">مبلغ</th>
                                <th class="px-5 py-3 font-medium">وضعیت</th>
                                <th class="px-5 py-3 font-medium">تاریخ ثبت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="payment in recentPayments" :key="payment.id">
                                <td class="px-5 py-4 font-medium">{{ payment.business_name }}</td>
                                <td class="px-5 py-4">{{ payment.plan_name }}</td>
                                <td class="px-5 py-4 tabular-nums">{{ formatToman(payment.amount) }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(payment.status)">
                                        {{ statusLabel(payment.status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-muted-foreground">{{ formatDateTime(payment.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="text-xs leading-6 text-muted-foreground">
                این نمای اولیه بر اساس داده‌های واقعی پایگاه داده ساخته شده است. گزارش‌های روند رشد، فیلترهای پیشرفته، مدیریت کسب‌وکارها و رویدادهای ممیزی در برش‌های بعدی داشبورد اضافه خواهند شد.
            </p>
        </div>
    </main>
</template>
