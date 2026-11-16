<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useJalaliDate } from '@/composables/useJalaliDate';

type Member = {
    name: string;
    email: string;
    role: string;
    joined_at: string | null;
};

type Payment = {
    id: number;
    plan: string;
    amount: number;
    status: string;
    bank_tracking_number: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    paid_at: string | null;
    reviewer_name: string | null;
    rejection_reason: string | null;
    receipt_url: string | null;
};

type SubscriptionEvent = {
    id: number;
    type: string;
    plan: string;
    created_at: string | null;
};

const props = defineProps<{
    business: {
        public_id: string;
        name: string;
        slug: string;
        status: string;
        phone: string | null;
        email: string | null;
        address: string | null;
        city: string | null;
        postal_code: string | null;
        created_at: string | null;
    };
    owner: Member | null;
    members: Member[];
    subscription: {
        status: string;
        plan: string;
        ends_at: string | null;
        trial_ends_at: string | null;
    } | null;
    payments: Payment[];
    events: SubscriptionEvent[];
}>();

const { formatDateTime } = useJalaliDate();
const numberFormatter = new Intl.NumberFormat('fa-IR');

const businessStatusLabels: Record<string, string> = {
    active: 'فعال',
    suspended: 'تعلیق‌شده',
};

const subscriptionStatusLabels: Record<string, string> = {
    trialing: 'آزمایشی',
    active: 'فعال',
    past_due: 'سررسیدگذشته',
    canceled: 'لغوشده',
    expired: 'منقضی',
};

const paymentStatusLabels: Record<string, string> = {
    pending: 'در انتظار',
    successful: 'موفق',
    failed: 'ناموفق',
    pending_review: 'در انتظار بررسی',
    approved: 'تأییدشده',
    rejected: 'ردشده',
};

const roleLabels: Record<string, string> = {
    owner: 'مالک',
    admin: 'مدیر',
    manager: 'سرپرست',
    editor: 'ویرایشگر',
};

const eventLabels: Record<string, string> = {
    created: 'ایجاد اشتراک',
    renewed: 'تمدید اشتراک',
    upgraded: 'ارتقای طرح',
    downgraded: 'کاهش طرح',
    canceled: 'لغو اشتراک',
    expired: 'انقضای اشتراک',
    trial_started: 'شروع دوره آزمایشی',
    trial_ended: 'پایان دوره آزمایشی',
    payment_submitted: 'ثبت پرداخت',
    payment_approved: 'تأیید پرداخت',
    payment_rejected: 'رد پرداخت',
};

function paymentStatusClass(status: string): string {
    if (status === 'approved' || status === 'successful') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
    }

    if (status === 'pending_review' || status === 'pending') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
    }

    return 'bg-destructive/10 text-destructive';
}
</script>

<template>
    <Head :title="`جزئیات کسب‌وکار: ${business.name}`" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="space-y-2">
                <a href="/admin/businesses" class="text-sm text-primary underline underline-offset-4">بازگشت به فهرست کسب‌وکارها</a>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ business.name }}</h1>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium">{{ businessStatusLabels[business.status] ?? business.status }}</span>
                </div>
                <p dir="ltr" class="text-sm text-muted-foreground">{{ business.slug }}</p>
            </header>

            <section class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-xl border border-border bg-card p-5 lg:col-span-2">
                    <h2 class="font-semibold">اطلاعات کسب‌وکار</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-muted-foreground">تلفن</dt>
                            <dd class="mt-1 text-sm">{{ business.phone || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">ایمیل</dt>
                            <dd class="mt-1 break-all text-sm">{{ business.email || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">شهر</dt>
                            <dd class="mt-1 text-sm">{{ business.city || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">کد پستی</dt>
                            <dd class="mt-1 text-sm">{{ business.postal_code || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-muted-foreground">نشانی</dt>
                            <dd class="mt-1 text-sm">{{ business.address || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">تاریخ ثبت</dt>
                            <dd class="mt-1 text-sm">{{ formatDateTime(business.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">شناسه عمومی</dt>
                            <dd dir="ltr" class="mt-1 break-all text-left text-xs text-muted-foreground">{{ business.public_id }}</dd>
                        </div>
                    </dl>
                </article>

                <article class="rounded-xl border border-border bg-card p-5">
                    <h2 class="font-semibold">مالک کسب‌وکار</h2>
                    <template v-if="owner">
                        <p class="mt-4 font-medium">{{ owner.name }}</p>
                        <p dir="ltr" class="mt-1 break-all text-left text-sm text-muted-foreground">{{ owner.email }}</p>
                    </template>
                    <p v-else class="mt-4 text-sm text-muted-foreground">مالک مشخصی ثبت نشده است.</p>
                    <div class="mt-5 border-t border-border pt-4">
                        <p class="text-xs text-muted-foreground">وضعیت اشتراک</p>
                        <template v-if="subscription">
                            <p class="mt-1 font-semibold">{{ subscription.plan }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">{{ subscriptionStatusLabels[subscription.status] ?? subscription.status }}</p>
                            <p class="mt-3 text-xs text-muted-foreground">پایان دوره: {{ formatDateTime(subscription.ends_at) }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">پایان آزمایشی: {{ formatDateTime(subscription.trial_ends_at) }}</p>
                        </template>
                        <p v-else class="mt-1 text-sm text-muted-foreground">اشتراکی ثبت نشده است.</p>
                    </div>
                </article>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="border-b border-border px-5 py-4">
                    <h2 class="font-semibold">اعضای کسب‌وکار</h2>
                    <p class="mt-1 text-xs text-muted-foreground">{{ numberFormatter.format(members.length) }} عضو</p>
                </div>
                <div v-if="members.length === 0" class="px-5 py-8 text-sm text-muted-foreground">عضوی ثبت نشده است.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="bg-muted/40 text-right text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3 font-medium">نام</th>
                                <th class="px-5 py-3 font-medium">ایمیل</th>
                                <th class="px-5 py-3 font-medium">نقش</th>
                                <th class="px-5 py-3 font-medium">تاریخ عضویت</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="member in members" :key="member.email">
                                <td class="px-5 py-3 font-medium">{{ member.name }}</td>
                                <td dir="ltr" class="px-5 py-3 text-left text-muted-foreground">{{ member.email }}</td>
                                <td class="px-5 py-3">{{ roleLabels[member.role] ?? member.role }}</td>
                                <td class="px-5 py-3 text-muted-foreground">{{ formatDateTime(member.joined_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-5 py-4">
                    <div>
                        <h2 class="font-semibold">تاریخچه پرداخت</h2>
                        <p class="mt-1 text-xs text-muted-foreground">۱۰ پرداخت اخیر این کسب‌وکار</p>
                    </div>
                    <a href="/admin/payments" class="text-sm text-primary underline underline-offset-4">همه پرداخت‌ها</a>
                </div>
                <div v-if="payments.length === 0" class="px-5 py-8 text-sm text-muted-foreground">پرداختی ثبت نشده است.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-muted/40 text-right text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3 font-medium">طرح</th>
                                <th class="px-5 py-3 font-medium">مبلغ</th>
                                <th class="px-5 py-3 font-medium">وضعیت</th>
                                <th class="px-5 py-3 font-medium">شماره پیگیری</th>
                                <th class="px-5 py-3 font-medium">ثبت / بررسی</th>
                                <th class="px-5 py-3 font-medium">رسید</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="payment in payments" :key="payment.id">
                                <td class="px-5 py-3">{{ payment.plan }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ numberFormatter.format(payment.amount) }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="paymentStatusClass(payment.status)">
                                        {{ paymentStatusLabels[payment.status] ?? payment.status }}
                                    </span>
                                    <p v-if="payment.rejection_reason" class="mt-1 max-w-xs text-xs text-destructive">{{ payment.rejection_reason }}</p>
                                </td>
                                <td dir="ltr" class="px-5 py-3 text-left text-muted-foreground">{{ payment.bank_tracking_number || '—' }}</td>
                                <td class="px-5 py-3 text-xs text-muted-foreground">
                                    <p>{{ formatDateTime(payment.submitted_at) }}</p>
                                    <p v-if="payment.reviewer_name" class="mt-1">بررسی توسط {{ payment.reviewer_name }}</p>
                                    <p v-else-if="payment.reviewed_at" class="mt-1">{{ formatDateTime(payment.reviewed_at) }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <a v-if="payment.receipt_url" :href="payment.receipt_url" class="text-primary underline underline-offset-4">مشاهده رسید</a>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="font-semibold">رویدادهای اشتراک</h2>
                <p class="mt-1 text-xs text-muted-foreground">۱۰ رویداد اخیر؛ بدون نمایش فراداده داخلی</p>
                <div v-if="events.length === 0" class="py-6 text-sm text-muted-foreground">رویدادی ثبت نشده است.</div>
                <ol v-else class="mt-5 space-y-4">
                    <li v-for="event in events" :key="event.id" class="flex items-start justify-between gap-4 border-r-2 border-border pr-4">
                        <div>
                            <p class="text-sm font-medium">{{ eventLabels[event.type] ?? event.type }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ event.plan }}</p>
                        </div>
                        <time class="shrink-0 text-xs text-muted-foreground">{{ formatDateTime(event.created_at) }}</time>
                    </li>
                </ol>
            </section>
        </div>
    </main>
</template>
