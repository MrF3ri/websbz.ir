<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Store } from '@lucide/vue';
import { store } from '@/actions/App/Modules/Business/Http/Controllers/BusinessOnboardingController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type PlanOption = {
    code: string;
    name: string;
    monthly_price: number;
};

const props = defineProps<{
    plans: PlanOption[];
}>();

defineOptions({
    layout: {
        title: 'کسب‌وکار خود را ثبت کنید',
        description: 'برای شروع، نام کسب‌وکار و طرح اشتراک موردنظر خود را انتخاب کنید.',
    },
});

const form = useForm({
    name: '',
    plan_code: props.plans[0]?.code ?? '',
});

const priceFormatter = new Intl.NumberFormat('fa-IR');

function formatPrice(price: number): string {
    return `${priceFormatter.format(price)} تومان در ماه`;
}

function formatPlanName(plan: PlanOption): string {
    const labels: Record<string, string> = {
        economic: 'اقتصادی',
        professional: 'حرفه‌ای',
        top: 'ویژه',
    };

    return labels[plan.code] ?? plan.name;
}

function submit() {
    form.submit(store());
}
</script>

<template>
    <Head title="ثبت کسب‌وکار" />

    <form
        dir="rtl"
        class="flex flex-col gap-6 text-right"
        @submit.prevent="submit"
    >
        <div class="flex items-start gap-3 rounded-xl border border-primary/10 bg-primary/5 p-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-background text-primary shadow-sm">
                <Store class="size-5" />
            </div>
            <div class="min-w-0">
                <h2 class="font-semibold text-foreground">اولین قدم برای شروع</h2>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    اطلاعات اولیه کسب‌وکارتان را ثبت کنید و یکی از طرح‌های فعال را برای شروع دوره آزمایشی انتخاب کنید.
                </p>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="business-name">نام کسب‌وکار</Label>
            <Input
                id="business-name"
                v-model="form.name"
                type="text"
                required
                autofocus
                autocomplete="organization"
                placeholder="مثلاً کافه آفتاب"
                class="h-11 text-right"
                :aria-invalid="Boolean(form.errors.name)"
                :aria-describedby="form.errors.name ? 'business-name-error' : 'business-name-hint'"
                :disabled="form.processing"
            />
            <p
                v-if="form.errors.name"
                id="business-name-error"
                class="text-sm leading-6 text-destructive"
                role="alert"
            >
                {{ form.errors.name }}
            </p>
            <p
                v-else
                id="business-name-hint"
                class="text-xs leading-5 text-muted-foreground"
            >
                نامی را وارد کنید که مشتریان کسب‌وکار شما را با آن می‌شناسند.
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="plan-code">طرح اشتراک</Label>
            <select
                id="plan-code"
                v-model="form.plan_code"
                required
                :disabled="form.processing || plans.length === 0"
                class="h-11 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                :aria-invalid="Boolean(form.errors.plan_code)"
                :aria-describedby="form.errors.plan_code ? 'plan-code-error' : 'plan-code-hint'"
            >
                <option
                    v-for="plan in plans"
                    :key="plan.code"
                    :value="plan.code"
                >
                    {{ formatPlanName(plan) }} — {{ formatPrice(plan.monthly_price) }}
                </option>
            </select>
            <p
                v-if="form.errors.plan_code"
                id="plan-code-error"
                class="text-sm leading-6 text-destructive"
                role="alert"
            >
                {{ form.errors.plan_code }}
            </p>
            <p
                v-else-if="plans.length > 0"
                id="plan-code-hint"
                class="text-xs leading-5 text-muted-foreground"
            >
                با انتخاب طرح، یک دوره آزمایشی ۷۲ ساعته برای کسب‌وکار شما فعال می‌شود.
            </p>
            <p
                v-else
                class="text-sm leading-6 text-destructive"
                role="alert"
            >
                در حال حاضر هیچ طرح فعالی برای ثبت‌نام وجود ندارد. لطفاً بعداً دوباره تلاش کنید.
            </p>
        </div>

        <Button
            type="submit"
            class="h-11 w-full"
            :disabled="form.processing || plans.length === 0 || !form.plan_code"
        >
            <Spinner v-if="form.processing" />
            {{ form.processing ? 'در حال ثبت...' : 'ثبت و شروع دوره آزمایشی' }}
        </Button>

        <p class="flex items-center justify-center gap-2 text-center text-xs text-muted-foreground">
            <Building2 class="size-4 shrink-0" />
            اطلاعات کسب‌وکار شما در فضای اختصاصی آن نگهداری می‌شود.
        </p>
    </form>
</template>
