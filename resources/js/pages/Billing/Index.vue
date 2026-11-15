<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useJalaliDate } from '@/composables/useJalaliDate';

type Plan = {
    id: number;
    code: string;
    name: string;
    monthly_price: number;
};

type Payment = {
    id: number;
    amount: number;
    status: string;
    plan_name: string;
    bank_tracking_number: string | null;
    submitted_at: string | null;
    rejection_reason: string | null;
    receipt_url: string | null;
};

const props = defineProps<{
    subscription: {
        status: string;
        plan: {
            id: number;
            name: string;
            monthly_price: number;
        };
        trial_ends_at: string | null;
        current_period_ends_at: string | null;
    };
    plans: Plan[];
    cardDetails: {
        bank_name: string;
        card_number: string;
        card_holder: string;
    };
    hasCardDetails: boolean;
    hasPendingReview: boolean;
    payments: Payment[];
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

const form = useForm({
    plan_id: props.subscription.plan.id,
    bank_tracking_number: '',
    receipt: null as File | null,
});

const paymentError = computed(() => (form.errors as Record<string, string>).payment ?? '');

function formatToman(amount: number): string {
    return `${numberFormatter.format(amount)} تومان`;
}

function paymentStatusLabel(status: string): string {
    return ({
        pending_review: 'در انتظار بررسی',
        approved: 'تأییدشده',
        rejected: 'ردشده',
        pending: 'در انتظار پرداخت',
        successful: 'موفق',
        failed: 'ناموفق',
    } as Record<string, string>)[status] ?? status;
}

function chooseReceipt(event: Event): void {
    const target = event.target;

    if (target instanceof HTMLInputElement) {
        form.receipt = target.files?.[0] ?? null;
    }
}

function submitPayment(): void {
    form.post('/billing/payments', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('bank_tracking_number', 'receipt');
        },
    });
}
</script>

<template>
    <Head title="اشتراک و پرداخت" />

    <main dir="rtl" class="min-h-full bg-background px-4 py-6 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-foreground">اشتراک و پرداخت</h1>
                <p class="mt-2 text-sm text-muted-foreground">وضعیت اشتراک و درخواست تمدید یا تغییر طرح را مدیریت کنید.</p>
            </header>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold text-foreground">اشتراک فعلی</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-muted-foreground">طرح</p>
                        <p class="mt-1 font-semibold">{{ subscription.plan.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">وضعیت</p>
                        <p class="mt-1 font-semibold">{{ subscription.status === 'trialing' ? 'دوره آزمایشی' : subscription.status === 'active' ? 'فعال' : subscription.status }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">{{ subscription.status === 'trialing' ? 'پایان دوره آزمایشی' : 'پایان دوره فعلی' }}</p>
                        <p class="mt-1 font-semibold">{{ formatDateTime(subscription.status === 'trialing' ? subscription.trial_ends_at : subscription.current_period_ends_at) || '—' }}</p>
                    </div>
                </div>
            </section>

            <section v-if="hasCardDetails" class="rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold text-foreground">اطلاعات کارت بانکی پلتفرم</h2>
                <p class="mt-1 text-sm text-muted-foreground">مبلغ طرح انتخابی را به کارت زیر واریز کنید، سپس شماره پیگیری و تصویر رسید را ثبت کنید.</p>
                <div class="mt-4 grid gap-3 rounded-lg bg-muted/40 p-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-muted-foreground">بانک</p>
                        <p class="mt-1 font-medium">{{ cardDetails.bank_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">شماره کارت</p>
                        <p class="mt-1 font-mono font-semibold" dir="ltr">{{ cardDetails.card_number.replace(/(\d{4})(?=\d)/g, '$1 ') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">به نام</p>
                        <p class="mt-1 font-medium">{{ cardDetails.card_holder }}</p>
                    </div>
                </div>
            </section>
            <section v-else class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm text-amber-800 dark:text-amber-300">
                اطلاعات کارت بانکی پلتفرم هنوز ثبت نشده است؛ ثبت پرداخت فعلاً امکان‌پذیر نیست.
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold text-foreground">ثبت رسید پرداخت</h2>
                <p class="mt-1 text-sm text-muted-foreground">پس از ثبت، پرداخت توسط مدیر بررسی می‌شود. اشتراک فقط پس از تأیید تمدید خواهد شد.</p>

                <div v-if="props.hasPendingReview" class="mt-4 rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                    یک پرداخت در انتظار بررسی دارید. تا اعلام نتیجه، درخواست جدید ثبت نکنید.
                </div>

                <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="submitPayment">
                    <div class="grid gap-2 sm:col-span-2">
                        <label for="plan-id" class="text-sm font-medium">طرح موردنظر</label>
                        <select id="plan-id" v-model.number="form.plan_id" class="h-10 rounded-md border border-input bg-background px-3 text-sm" :disabled="form.processing">
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }} — {{ formatToman(plan.monthly_price) }}
                            </option>
                        </select>
                        <p v-if="form.errors.plan_id" class="text-sm text-destructive">{{ form.errors.plan_id }}</p>
                    </div>

                    <div class="grid gap-2">
                        <label for="bank-tracking-number" class="text-sm font-medium">شماره پیگیری بانکی</label>
                        <input id="bank-tracking-number" v-model="form.bank_tracking_number" required maxlength="100" dir="ltr" class="h-10 rounded-md border border-input bg-background px-3 text-left text-sm" />
                        <p v-if="form.errors.bank_tracking_number" class="text-sm text-destructive">{{ form.errors.bank_tracking_number }}</p>
                    </div>

                    <div class="grid gap-2">
                        <label for="receipt" class="text-sm font-medium">تصویر یا PDF رسید (حداکثر ۵ مگابایت)</label>
                        <input id="receipt" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required class="block w-full text-sm text-muted-foreground file:ml-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-2" @change="chooseReceipt" />
                        <p v-if="form.errors.receipt" class="text-sm text-destructive">{{ form.errors.receipt }}</p>
                    </div>

                    <p v-if="paymentError" class="text-sm text-destructive sm:col-span-2">{{ paymentError }}</p>

                    <div class="sm:col-span-2">
                        <Button type="submit" :disabled="form.processing || !hasCardDetails || props.hasPendingReview">
                            {{ form.processing ? 'در حال ثبت...' : 'ثبت رسید برای بررسی' }}
                        </Button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-4">
                    <h2 class="font-semibold">سوابق پرداخت</h2>
                </div>
                <div v-if="payments.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    هنوز پرداختی ثبت نشده است.
                </div>
                <div v-else class="divide-y divide-border">
                    <article v-for="payment in payments" :key="payment.id" class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium">{{ payment.plan_name }} · {{ formatToman(payment.amount) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ payment.bank_tracking_number ? `پیگیری: ${payment.bank_tracking_number}` : 'بدون شماره پیگیری' }}
                                <span v-if="payment.submitted_at"> · {{ formatDateTime(payment.submitted_at) }}</span>
                            </p>
                            <p v-if="payment.rejection_reason" class="mt-1 text-sm text-destructive">{{ payment.rejection_reason }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a v-if="payment.receipt_url" :href="payment.receipt_url" class="text-sm font-medium text-primary underline underline-offset-4">دانلود رسید</a>
                            <span class="w-fit rounded-full bg-muted px-3 py-1 text-xs">{{ paymentStatusLabel(payment.status) }}</span>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
