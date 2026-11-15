<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { useJalaliDate } from '@/composables/useJalaliDate';

type Payment = {
    id: number;
    business_name: string;
    plan_name: string;
    amount: number;
    bank_tracking_number: string | null;
    submitted_at: string | null;
    receipt_url: string;
};

const props = defineProps<{
    payments: {
        data: Payment[];
        current_page: number;
        last_page: number;
        total: number;
    };
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

function formatToman(amount: number): string {
    return `${numberFormatter.format(amount)} تومان`;
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
    <Head title="بررسی پرداخت‌ها" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6">
        <div class="mx-auto max-w-5xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold">بررسی پرداخت‌ها</h1>
                <p class="mt-2 text-sm text-muted-foreground">رسیدهای در انتظار بررسی مدیر پلتفرم.</p>
            </header>

            <div v-if="payments.data.length === 0" class="rounded-xl border border-border bg-card px-6 py-12 text-center text-sm text-muted-foreground">
                پرداختی در انتظار بررسی نیست.
            </div>

            <section v-else class="space-y-4">
                <article v-for="payment in payments.data" :key="payment.id" class="rounded-xl border border-border bg-card p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <h2 class="font-semibold">{{ payment.business_name }}</h2>
                            <p class="text-sm text-muted-foreground">{{ payment.plan_name }} · {{ formatToman(payment.amount) }}</p>
                            <p class="text-xs text-muted-foreground">شماره پیگیری: <span dir="ltr" class="font-mono">{{ payment.bank_tracking_number }}</span></p>
                            <p class="text-xs text-muted-foreground">زمان ثبت: {{ formatDateTime(payment.submitted_at) }}</p>
                        </div>
                        <a :href="payment.receipt_url" target="_blank" rel="noopener noreferrer" class="text-sm text-primary underline underline-offset-4">
                            مشاهده رسید خصوصی
                        </a>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button type="button" @click="approve(payment)">تأیید پرداخت</Button>
                        <Button type="button" variant="destructive" @click="reject(payment)">رد پرداخت</Button>
                    </div>
                </article>
            </section>
        </div>
    </main>
</template>
