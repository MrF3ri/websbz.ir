<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
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
    form.post(route('onboarding.store'));
}
</script>

<template>
    <Head title="ثبت کسب‌وکار" />

    <form
        dir="rtl"
        class="flex flex-col gap-6 text-right"
        @submit.prevent="submit"
    >
        <div class="grid gap-2">
            <Label for="business-name">نام کسب‌وکار</Label>
            <Input
                id="business-name"
                v-model="form.name"
                type="text"
                required
                autofocus
                autocomplete="organization"
                placeholder="مثلاً کافه یا رستوران شما"
                class="text-right"
                :aria-invalid="Boolean(form.errors.name)"
                :aria-describedby="form.errors.name ? 'business-name-error' : undefined"
                :disabled="form.processing"
            />
            <p
                v-if="form.errors.name"
                id="business-name-error"
                class="text-sm text-destructive"
                role="alert"
            >
                {{ form.errors.name }}
            </p>
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="form.processing"
        >
            <Spinner v-if="form.processing" />
            {{ form.processing ? 'در حال ثبت...' : 'ثبت و ورود به داشبورد' }}
        </Button>
    </form>
</template>
