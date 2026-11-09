<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'بازیابی رمز عبور',
        description: 'آدرس ایمیل حساب کاربری خود را وارد کنید تا لینک بازیابی برایتان ارسال شود.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="بازیابی رمز عبور" />

    <div
        v-if="status"
        dir="rtl"
        class="mb-5 rounded-lg border border-green-500/20 bg-green-500/10 px-4 py-3 text-right text-sm leading-6 text-green-700 dark:text-green-400"
        role="status"
    >
        {{ status }}
    </div>

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        dir="rtl"
        class="space-y-6 text-right"
    >
        <div class="grid gap-2">
            <Label for="email">آدرس ایمیل</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                v-focus
                placeholder="name@example.com"
                dir="ltr"
                class="text-left"
                :disabled="processing"
            />
            <InputError :message="errors.email" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'در حال ارسال...' : 'ارسال لینک بازیابی رمز عبور' }}
        </Button>
    </Form>

    <div class="mt-6 text-center text-sm text-muted-foreground">
        رمز عبور را به خاطر آوردید؟
        <TextLink :href="login()" class="font-medium text-primary underline underline-offset-4">
            بازگشت به ورود
        </TextLink>
    </div>
</template>
