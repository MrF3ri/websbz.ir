<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useJalaliDate } from '@/composables/useJalaliDate';

type SubscriptionRow = {
    id: number;
    business_name: string;
    business_slug: string;
    plan_name: string;
    plan_code: string;
    status: string;
    trial_ends_at: string | null;
    period_starts_at: string | null;
    period_ends_at: string | null;
};

const props = defineProps<{
    subscriptions: {
        data: SubscriptionRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        status: string;
        plan: string;
        search: string;
    };
    plans: Array<{ code: string; name: string }>;
    statuses: Array<{ value: string; label: string }>;
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function statusLabel(status: string): string {
    return props.statuses.find((item) => item.value === status)?.label ?? status;
}

function statusClass(status: string): string {
    if (status === 'active' || status === 'trialing') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }

    if (status === 'past_due') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="مدیریت اشتراک‌ها" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <a href="/admin" class="text-sm text-primary underline underline-offset-4">بازگشت به داشبورد مدیریت</a>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">اشتراک‌ها</h1>
                <p class="text-sm text-muted-foreground">وضعیت قرارداد، طرح و بازه زمانی اشتراک کسب‌وکارها</p>
            </header>

            <form method="get" action="/admin/subscriptions" class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 xl:grid-cols-4">
                <label>
                    <span class="mb-1.5 block text-sm font-medium">جست‌وجوی کسب‌وکار</span>
                    <input type="search" name="search" :value="filters.search" maxlength="100" placeholder="نام یا شناسه آدرس" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" />
                </label>
                <label>
                    <span class="mb-1.5 block text-sm font-medium">طرح</span>
                    <select name="plan" :value="filters.plan" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">همه طرح‌ها</option>
                        <option v-for="plan in plans" :key="plan.code" :value="plan.code">{{ plan.name }}</option>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-sm font-medium">وضعیت اشتراک</span>
                    <select name="status" :value="filters.status" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">همه وضعیت‌ها</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="h-10 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground">اعمال فیلتر</button>
                    <a href="/admin/subscriptions" class="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm">پاک‌کردن</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4">
                    <h2 class="font-semibold">فهرست اشتراک‌ها</h2>
                    <p class="text-sm text-muted-foreground">{{ numberFormatter.format(subscriptions.total) }} مورد</p>
                </div>

                <div v-if="subscriptions.data.length === 0" class="px-6 py-14 text-center text-sm text-muted-foreground">
                    اشتراکی با این فیلترها پیدا نشد.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead class="bg-muted/40 text-right text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3 font-medium">کسب‌وکار</th>
                                <th class="px-5 py-3 font-medium">طرح</th>
                                <th class="px-5 py-3 font-medium">وضعیت</th>
                                <th class="px-5 py-3 font-medium">شروع دوره</th>
                                <th class="px-5 py-3 font-medium">پایان دوره</th>
                                <th class="px-5 py-3 font-medium">پایان آزمایشی</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="subscription in subscriptions.data" :key="subscription.id">
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ subscription.business_name }}</p>
                                    <p dir="ltr" class="mt-1 text-xs text-muted-foreground">{{ subscription.business_slug }}</p>
                                </td>
                                <td class="px-5 py-4">{{ subscription.plan_name }}</td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(subscription.status)">
                                        {{ statusLabel(subscription.status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-muted-foreground">{{ formatDateTime(subscription.period_starts_at) }}</td>
                                <td class="px-5 py-4 text-muted-foreground">{{ formatDateTime(subscription.period_ends_at) }}</td>
                                <td class="px-5 py-4 text-muted-foreground">{{ formatDateTime(subscription.trial_ends_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer v-if="subscriptions.last_page > 1" class="flex items-center justify-between border-t border-border px-5 py-4">
                    <a v-if="subscriptions.prev_page_url" :href="subscriptions.prev_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه قبل</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه قبل</span>
                    <span class="text-sm tabular-nums text-muted-foreground">صفحه {{ numberFormatter.format(subscriptions.current_page) }} از {{ numberFormatter.format(subscriptions.last_page) }}</span>
                    <a v-if="subscriptions.next_page_url" :href="subscriptions.next_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه بعد</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه بعد</span>
                </footer>
            </section>
        </div>
    </main>
</template>
