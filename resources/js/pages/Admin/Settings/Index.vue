<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    cardDetails: {
        bank_name: string;
        card_number: string;
        card_holder: string;
    };
}>();

const form = useForm({
    bank_name: props.cardDetails.bank_name,
    card_number: props.cardDetails.card_number,
    card_holder: props.cardDetails.card_holder,
});

function save(): void {
    form.put('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="تنظیمات پلتفرم" />

    <main dir="rtl" class="min-h-screen bg-background px-4 py-8 text-right sm:px-6">
        <div class="mx-auto max-w-2xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold">تنظیمات پلتفرم</h1>
                <p class="mt-2 text-sm text-muted-foreground">اطلاعات کارت مقصد پرداخت دستی اشتراک‌ها را مدیریت کنید.</p>
                <a href="/admin/payments" class="mt-2 inline-block text-sm text-primary underline underline-offset-4">صف بررسی پرداخت‌ها</a>
            </header>

            <form class="grid gap-4 rounded-xl border border-border bg-card p-5" @submit.prevent="save">
                <div class="grid gap-2">
                    <label for="bank-name" class="text-sm font-medium">نام بانک</label>
                    <input id="bank-name" v-model="form.bank_name" required maxlength="100" class="h-10 rounded-md border border-input bg-background px-3 text-sm" />
                    <p v-if="form.errors.bank_name" class="text-sm text-destructive">{{ form.errors.bank_name }}</p>
                </div>
                <div class="grid gap-2">
                    <label for="card-number" class="text-sm font-medium">شماره کارت ۱۶ رقمی</label>
                    <input id="card-number" v-model="form.card_number" required inputmode="numeric" maxlength="16" dir="ltr" class="h-10 rounded-md border border-input bg-background px-3 text-left font-mono text-sm" />
                    <p v-if="form.errors.card_number" class="text-sm text-destructive">{{ form.errors.card_number }}</p>
                </div>
                <div class="grid gap-2">
                    <label for="card-holder" class="text-sm font-medium">نام صاحب کارت</label>
                    <input id="card-holder" v-model="form.card_holder" required maxlength="120" class="h-10 rounded-md border border-input bg-background px-3 text-sm" />
                    <p v-if="form.errors.card_holder" class="text-sm text-destructive">{{ form.errors.card_holder }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'در حال ذخیره...' : 'ذخیره اطلاعات کارت' }}
                </Button>
            </form>
        </div>
    </main>
</template>
