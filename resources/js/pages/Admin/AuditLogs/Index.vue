<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useJalaliDate } from '@/composables/useJalaliDate';

type Snapshot = Record<string, string | number | boolean | null>;

type AuditLog = {
    id: number;
    action: string;
    target_type: string | null;
    target_id: string | null;
    actor_name: string;
    actor_email: string | null;
    before: Snapshot | null;
    after: Snapshot | null;
    created_at: string | null;
};

defineProps<{
    logs: {
        data: AuditLog[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { action: string; search: string };
    actions: string[];
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function fieldLabel(key: string): string {
    return ({
        bank_name: 'نام بانک',
        card_number_last4: 'چهار رقم پایانی کارت',
        card_holder: 'صاحب کارت',
        payment_status: 'وضعیت پرداخت',
        payment_amount: 'مبلغ پرداخت',
        target_plan_id: 'شناسه طرح درخواستی',
        subscription_status: 'وضعیت اشتراک',
        subscription_plan_id: 'شناسه طرح اشتراک',
        current_period_starts_at: 'شروع دوره اشتراک',
        current_period_ends_at: 'پایان دوره اشتراک',
    } as Record<string, string>)[key] ?? key;
}
</script>

<template>
    <Head title="گزارش ممیزی مدیریت" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <a href="/admin" class="text-sm text-primary underline underline-offset-4">بازگشت به داشبورد مدیریت</a>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">گزارش ممیزی مدیریت</h1>
                <p class="text-sm text-muted-foreground">ثبت غیرقابل‌ویرایش از تغییرات حساس مدیریتی؛ اطلاعات محرمانه کارت در این گزارش ذخیره نمی‌شود.</p>
            </header>

            <form method="get" action="/admin/audit-logs" class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_16rem_auto]">
                <label>
                    <span class="mb-1.5 block text-sm font-medium">جست‌وجو</span>
                    <input type="search" name="search" :value="filters.search" maxlength="100" placeholder="عامل، عملیات یا موضوع" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" />
                </label>
                <label>
                    <span class="mb-1.5 block text-sm font-medium">نوع عملیات</span>
                    <select name="action" :value="filters.action" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">همه عملیات</option>
                        <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="h-10 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground">اعمال فیلتر</button>
                    <a href="/admin/audit-logs" class="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm">پاک‌کردن</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <h2 class="font-semibold">سوابق ثبت‌شده</h2>
                    <span class="text-sm tabular-nums text-muted-foreground">{{ numberFormatter.format(logs.total) }} مورد</span>
                </div>
                <div v-if="logs.data.length === 0" class="px-6 py-14 text-center text-sm text-muted-foreground">گزارشی برای نمایش وجود ندارد.</div>
                <ol v-else class="divide-y divide-border">
                    <li v-for="log in logs.data" :key="log.id" class="space-y-4 px-5 py-5">
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                            <div class="space-y-1">
                                <p class="font-semibold">{{ log.action }}</p>
                                <p class="text-sm text-muted-foreground">{{ log.actor_name }}<span v-if="log.actor_email"> · {{ log.actor_email }}</span></p>
                                <p class="text-xs text-muted-foreground">{{ log.target_type }} / {{ log.target_id }}</p>
                            </div>
                            <time v-if="log.created_at" class="shrink-0 text-xs text-muted-foreground">{{ formatDateTime(log.created_at) }}</time>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-lg border border-border p-3">
                                <h3 class="mb-2 text-xs font-semibold text-muted-foreground">مقادیر قبل</h3>
                                <p v-if="!log.before || Object.keys(log.before).length === 0" class="text-sm text-muted-foreground">بدون مقدار قبلی</p>
                                <dl v-else class="space-y-2">
                                    <div v-for="(value, key) in log.before" :key="key" class="flex justify-between gap-3 text-sm">
                                        <dt class="text-muted-foreground">{{ fieldLabel(key) }}</dt>
                                        <dd class="break-all font-medium">{{ value ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="rounded-lg border border-border p-3">
                                <h3 class="mb-2 text-xs font-semibold text-muted-foreground">مقادیر بعد</h3>
                                <p v-if="!log.after || Object.keys(log.after).length === 0" class="text-sm text-muted-foreground">بدون مقدار جدید</p>
                                <dl v-else class="space-y-2">
                                    <div v-for="(value, key) in log.after" :key="key" class="flex justify-between gap-3 text-sm">
                                        <dt class="text-muted-foreground">{{ fieldLabel(key) }}</dt>
                                        <dd class="break-all font-medium">{{ value ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </li>
                </ol>
                <footer v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-border px-5 py-4">
                    <a v-if="logs.prev_page_url" :href="logs.prev_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه قبل</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه قبل</span>
                    <span class="text-sm tabular-nums text-muted-foreground">صفحه {{ numberFormatter.format(logs.current_page) }} از {{ numberFormatter.format(logs.last_page) }}</span>
                    <a v-if="logs.next_page_url" :href="logs.next_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه بعد</a>
                    <span v-else class="text-sm text-muted-foreground">صفحه بعد</span>
                </footer>
            </section>
        </div>
    </main>
</template>
