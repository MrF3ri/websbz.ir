<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'تأیید آدرس ایمیل',
        description: 'برای فعال‌سازی حساب کاربری، لینک ارسال‌شده به ایمیل خود را باز کنید.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="تأیید آدرس ایمیل" />

    <div
        v-if="status === 'verification-link-sent'"
        dir="rtl"
        class="mb-5 rounded-lg border border-green-500/20 bg-green-500/10 px-4 py-3 text-right text-sm leading-6 text-green-700 dark:text-green-400"
        role="status"
    >
        لینک تأیید جدیدی به آدرس ایمیل شما ارسال شد. لطفاً صندوق ورودی خود را بررسی کنید.
    </div>

    <div
        dir="rtl"
        class="mb-6 rounded-lg bg-muted/60 px-4 py-4 text-right text-sm leading-7 text-muted-foreground"
    >
        <p>
            برای ادامه استفاده از امکانات پلتفرم، ابتدا آدرس ایمیل خود را تأیید کنید.
            اگر ایمیل را دریافت نکرده‌اید، می‌توانید دوباره درخواست ارسال لینک بدهید.
        </p>
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-5 text-center"
        v-slot="{ processing }"
    >
        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
        >
            <Spinner v-if="processing" />
            {{ processing ? 'در حال ارسال...' : 'ارسال مجدد ایمیل تأیید' }}
        </Button>

        <TextLink
            :href="logout()"
            as="button"
            class="mx-auto block text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
        >
            خروج از حساب کاربری
        </TextLink>
    </Form>
</template>
