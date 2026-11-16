<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useJalaliDate } from '@/composables/useJalaliDate';

type BusinessRow = {
    public_id: string;
    name: string;
    slug: string;
    status: string;
    members_count: number;
    created_at: string | null;
    subscription: {
        status: string;
        plan: string;
        ends_at: string | null;
    } | null;
};

const props = defineProps<{
    businesses: {
        data: BusinessRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        search: string;
        status: string;
    };
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function businessStatus(status: string): string {
    return status === 'active' ? 'فعال' : 'تعلیق‌شده';
}

function subscriptionStatus(status: string): string {
    const labels: Record<string, string> = {
        trialing: 'آزمایشی',
        active: 'فعال',
        past_due: 'سررسیدگذشته',
        canceled: 'لغوشده',
        expired: 'منقضی',
    };

    return labels[status] ?? status;
}

function statusClass(status: string): string {
    return status === 'active'
        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'bg-destructive/10 text-destructive';
}
</script>

<template>
    <Head title="مدیریت کسب‌وکارها" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <a href="/admin" class="text-sm text-primary underline underline-offset-4">بازگشت به داشبورد مدیریت</a>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">کسب‌وکارها</h1>
                <p class="text-sm text-muted-foreground">جست‌وجو و مشاهده وضعیت کسب‌وکارها و آخرین اشتراک هرکدام</p>
            </header>

            <form method="get" action="/admin/businesses" class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row">
                <label class="min-w-0 flex-1">
                    <span class="mb-1.5 block text-sm font-medium">جست‌وجو</span>
                    <input
                        type="search"
                        name="search"
                        :value="filters.search"
                        maxlength="100"
                        placeholder="نام یا شناسه آدرس کسب‌وکار"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    />
                </label>
                <label class="sm:w-52">
                    <span class="mb-1.5 block text-sm font-medium">وضعیت</span>
                    <select name="status" :value="filters.status" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="active">فعال</option>
                        <option value="suspended">تعلیق‌شده</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="h-10 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground">اعمال فیلتر</button>
                    <a href="/admin/businesses" class="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm">پاک‌کردن</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4">
                    <h2 class="font-semibold">فهرست کسب‌وکارها</h2>
                    <p class="text-sm text-muted-foreground">{{ numberFormatter.format(businesses.total) }} مورد</p>
                </div>

                <div v-if="businesses.data.length === 0" class="px-6 py-14 text-center text-sm text-muted-foreground">
                    کسب‌وکاری با این فیلترها پیدا نشد.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-muted/40 text-right text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3 font-medium">کسب‌وکار</th>
                                <th class="px-5 py-3 font-medium">وضعیت</th>
                                <th class="px-5 py-3 font-medium">اعضا</th>
                                <th class="px-5 py-3 font-medium">آخرین اشتراک</th>
                                <th class="px-5 py-3 font-medium">پایان دوره</th>
                                <th class="px-5 py-3 font-medium">تاریخ ثبت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="business in businesses.data" :key="business.public_id">
                                <td class="px-5 py-4">
                                    <a :href="`/admin/businesses/${business.public_id}`" class="font-semibold text-primary underline-offset-4 hover:underline">{{ business.name }}</a>
                                    <p dir="ltr" class="mt-1 text-xs text-muted-foreground">{{ business.slug }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(business.status)">
                                        {{ businessStatus(business.status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 tabular-nums">{{ numberFormatter.format(business.members_count) }}</td>
                                <td class="px-5 py-4">
                                    <template v-if="business.subscription">
                                        <p class="font-medium">{{ business.subscription.plan }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ subscriptionStatus(business.subscription.status) }}</p>
                                    </template>
                                    <span v-else class="text-muted-foreground">بدون اشتراک</span>
                                </td>
                                <td class="px-5 py-4 text-muted-foreground">{{ business.subscription ? formatDateTime(business.subscription.ends_at) : '—' }}</td>
                                <td class="px-5 py-4 text-muted-foreground">{{ formatDateTime(business.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer v-if="businesses.last_page > 1" class="flex items-center justify-between border-t border-border px-5 py-4">
                    <a
                        v-if="businesses.prev_page_url"
                        :href="businesses.prev_page_url"
                        class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted"
                    >صفحه قبل</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه قبل</span>
                    <span class="text-sm tabular-nums text-muted-foreground">صفحه {{ numberFormatter.format(businesses.current_page) }} از {{ numberFormatter.format(businesses.last_page) }}</span>
                    <a
                        v-if="businesses.next_page_url"
                        :href="businesses.next_page_url"
                        class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted"
                    >صفحه بعد</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه بعد</span>
                </footer>
            </section>
        </div>
    </main>
</template>
