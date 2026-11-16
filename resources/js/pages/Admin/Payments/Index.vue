<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useJalaliDate } from '@/composables/useJalaliDate';

type Payment = {
    id: number;
    business_name: string;
    plan_name: string;
    amount: number;
    status: string;
    bank_tracking_number: string | null;
    submitted_at: string | null;
    paid_at: string | null;
    receipt_url: string | null;
};

const props = defineProps<{
    payments: {
        data: Payment[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        status: string;
        search: string;
    };
    statuses: Array<{ value: string; label: string }>;
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function formatToman(amount: number): string {
    return `${numberFormatter.format(amount)} تومان`;
}

function statusLabel(status: string): string {
    return props.statuses.find((item) => item.value === status)?.label ?? status;
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

function approve(payment: Payment): void {
    if (!window.confirm(`پرداخت ${payment.bank_tracking_number ?? payment.id} تأیید شود؟`)) {
        return;
    }

    router.post(`/admin/payments/${payment.id}/approve`, {}, { preserveScroll: true });
}

function reject(payment: Payment): void {
    const reason = window.prompt('علت رد پرداخت را وارد کنید:');

    if (!reason || reason.trim().length < 5) {
        return;
    }

    router.post(`/admin/payments/${payment.id}/reject`, { reason: reason.trim() }, { preserveScroll: true });
}
</script>

<template>
    <Head title="مدیریت پرداخت‌ها" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6">
        <div class="mx-auto max-w-6xl space-y-6">
            <header>
                <a href="/admin" class="mb-3 inline-block text-sm text-primary underline underline-offset-4">بازگشت به داشبورد مدیریت</a>
                <h1 class="text-2xl font-bold">مدیریت پرداخت‌ها</h1>
                <p class="mt-2 text-sm text-muted-foreground">بررسی پرداخت‌های دستی و مشاهده سوابق پرداخت کسب‌وکارها.</p>
                <a href="/admin/settings" class="mt-2 inline-block text-sm text-primary underline underline-offset-4">تنظیمات کارت بانکی</a>
            </header>

            <form method="get" action="/admin/payments" class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row">
                <label class="min-w-0 flex-1">
                    <span class="mb-1.5 block text-sm font-medium">جست‌وجو</span>
                    <input type="search" name="search" :value="filters.search" maxlength="100" placeholder="نام کسب‌وکار یا شماره پیگیری" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" />
                </label>
                <label class="sm:w-56">
                    <span class="mb-1.5 block text-sm font-medium">وضعیت پرداخت</span>
                    <select name="status" :value="filters.status" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">همه وضعیت‌ها</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="h-10 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground">اعمال فیلتر</button>
                    <a href="/admin/payments?status=pending_review" class="inline-flex h-10 items-center rounded-md border border-border px-4 text-sm">در انتظار بررسی</a>
                </div>
            </form>

            <div v-if="payments.data.length === 0" class="rounded-xl border border-border bg-card px-6 py-12 text-center text-sm text-muted-foreground">
                پرداختی با این فیلترها پیدا نشد.
            </div>

            <section v-else class="space-y-4">
                <article v-for="payment in payments.data" :key="payment.id" class="rounded-xl border border-border bg-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold">{{ payment.business_name }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(payment.status)">{{ statusLabel(payment.status) }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ payment.plan_name }} · {{ formatToman(payment.amount) }}</p>
                            <p class="text-xs text-muted-foreground">شماره پیگیری: <span dir="ltr" class="font-mono">{{ payment.bank_tracking_number ?? '—' }}</span></p>
                            <p class="text-xs text-muted-foreground">زمان ثبت: {{ formatDateTime(payment.submitted_at) }}</p>
                            <p v-if="payment.paid_at" class="text-xs text-muted-foreground">زمان تأیید/پرداخت: {{ formatDateTime(payment.paid_at) }}</p>
                        </div>
                        <a v-if="payment.receipt_url" :href="payment.receipt_url" target="_blank" rel="noopener noreferrer" class="text-sm text-primary underline underline-offset-4">
                            مشاهده رسید خصوصی
                        </a>
                    </div>
                    <div v-if="payment.status === 'pending_review'" class="mt-4 flex flex-wrap gap-2">
                        <Button type="button" @click="approve(payment)">تأیید پرداخت</Button>
                        <Button type="button" variant="destructive" @click="reject(payment)">رد پرداخت</Button>
                    </div>
                </article>
            </section>

            <footer v-if="payments.last_page > 1" class="flex items-center justify-between rounded-xl border border-border bg-card px-5 py-4">
                <a v-if="payments.prev_page_url" :href="payments.prev_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه قبل</a>
                <span v-else class="text-sm text-muted-foreground">صفحه قبل</span>
                <span class="text-sm tabular-nums text-muted-foreground">صفحه {{ numberFormatter.format(payments.current_page) }} از {{ numberFormatter.format(payments.last_page) }} · {{ numberFormatter.format(payments.total) }} پرداخت</span>
                <a v-if="payments.next_page_url" :href="payments.next_page_url" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted">صفحه بعد</a>
                <span v-else class="text-sm text-muted-foreground">صفحه بعد</span>
            </footer>
        </div>
    </main>
</template>
