<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, Store } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'کسب‌وکار خود را ثبت کنید',
        description: 'برای شروع استفاده از پلتفرم، نام کافه یا رستوران خود را وارد کنید.',
    },
});

const form = useForm({
    name: '',
});

function submit() {
    form.post('/onboarding');
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
                    اطلاعات اولیه کسب‌وکارتان را ثبت کنید. بعد از ثبت، وارد فضای مدیریت اختصاصی خود می‌شوید.
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

        <Button
            type="submit"
            class="h-11 w-full"
            :disabled="form.processing"
        >
            <Spinner v-if="form.processing" />
            {{ form.processing ? 'در حال ثبت...' : 'ثبت و ورود به داشبورد' }}
        </Button>

        <p class="flex items-center justify-center gap-2 text-center text-xs text-muted-foreground">
            <Building2 class="size-4 shrink-0" />
            اطلاعات کسب‌وکار شما در فضای اختصاصی آن نگهداری می‌شود.
        </p>
    </form>
</template>
