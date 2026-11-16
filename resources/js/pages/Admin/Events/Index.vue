<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useJalaliDate } from '@/composables/useJalaliDate';

type EventRow = {
    id: number;
    type: string;
    business_public_id: string;
    business_name: string;
    business_slug: string;
    plan_name: string;
    subscription_id: number;
    created_at: string;
};

const props = defineProps<{
    events: {
        data: EventRow[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        event_type: string;
        search: string;
    };
    eventTypes: Array<{ value: string; label: string }>;
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function eventLabel(type: string): string {
    return props.eventTypes.find((eventType) => eventType.value === type)?.label ?? type;
}

function eventClass(type: string): string {
    if (type === 'payment_approved' || type === 'renewed' || type === 'created' || type === 'trial_started') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }

    if (type === 'payment_rejected' || type === 'expired' || type === 'canceled' || type === 'trial_ended') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="رویدادهای عملیاتی" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <a href="/admin" class="text-sm text-primary underline underline-offset-4">بازگشت به داشبورد مدیریت</a>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">رویدادهای عملیاتی</h1>
                <p class="text-sm text-muted-foreground">تاریخچه تغییرات چرخه اشتراک و پرداخت‌های دستی در سراسر پلتفرم</p>
            </header>

            <form method="get" action="/admin/events" class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_16rem_auto]">
                <label>
                    <span class="mb-1.5 block text-sm font-medium">جست‌وجوی کسب‌وکار یا طرح</span>
                    <input type="search" name="search" :value="filters.search" maxlength="100" placeholder="نام کسب‌وکار، نشانی یا طرح" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" />
                </label>
                <label>
                    <span class="mb-1.5 block text-sm font-medium">نوع رویداد</span>
                    <select name="event_type" :value="filters.event_type" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">همه رویدادها</option>
                        <option v-for="eventType in eventTypes" :key="eventType.value" :value="eventType.value">{{ eventType.label }}</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="h-10 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground">اعمال فیلتر</button>
                    <a href="/admin/events" class="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm">پاک‌کردن</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4">
                    <div>
                        <h2 class="font-semibold">جریان رویدادها</h2>
                        <p class="mt-1 text-xs text-muted-foreground">رویدادها به ترتیب زمان ثبت، جدید به قدیم</p>
                    </div>
                    <p class="text-sm tabular-nums text-muted-foreground">{{ numberFormatter.format(events.total) }} رویداد</p>
                </div>

                <div v-if="events.data.length === 0" class="px-6 py-14 text-center text-sm text-muted-foreground">
                    رویدادی با این فیلترها پیدا نشد.
                </div>
                <ol v-else class="divide-y divide-border">
                    <li v-for="event in events.data" :key="event.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="eventClass(event.type)">
                                    {{ eventLabel(event.type) }}
                                </span>
                                <span class="text-xs text-muted-foreground">رویداد #{{ numberFormatter.format(event.id) }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <a :href="'/admin/businesses/' + event.business_public_id" class="font-semibold text-primary underline-offset-4 hover:underline">
                                    {{ event.business_name }}
                                </a>
                                <span class="text-muted-foreground">·</span>
                                <span class="text-muted-foreground">{{ event.plan_name }}</span>
                                <span class="text-xs text-muted-foreground">· اشتراک #{{ numberFormatter.format(event.subscription_id) }}</span>
                            </div>
                            <p dir="ltr" class="text-xs text-muted-foreground">{{ event.business_slug }}</p>
                        </div>
                        <time class="shrink-0 text-xs text-muted-foreground">{{ formatDateTime(event.created_at) }}</time>
                    </li>
                </ol>

                <footer v-if="events.last_page > 1" class="flex items-center justify-between border-t border-border px-5 py-4">
                    <a v-if="events.prev_page_url" :href="events.prev_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه قبل</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه قبل</span>
                    <span class="text-sm tabular-nums text-muted-foreground">صفحه {{ numberFormatter.format(events.current_page) }} از {{ numberFormatter.format(events.last_page) }}</span>
                    <a v-if="events.next_page_url" :href="events.next_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه بعد</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه بعد</span>
                </footer>
            </section>

            <p class="text-xs leading-6 text-muted-foreground">
                این نسخه فقط رویدادهایی را نشان می‌دهد که در حال حاضر در چرخه اشتراک و پرداخت ثبت می‌شوند. فراداده خام رویدادها، مسیر فایل رسید و اطلاعات خصوصی ذخیره‌سازی به مرورگر ارسال نمی‌شوند.
            </p>
        </div>
    </main>
</template>
