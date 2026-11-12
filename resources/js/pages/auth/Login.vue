<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'ورود به حساب کاربری',
        description: 'برای ورود، آدرس ایمیل و رمز عبور خود را وارد کنید.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="ورود" />

    <div
        v-if="status"
        dir="rtl"
        class="mb-5 rounded-lg border border-green-500/20 bg-green-500/10 px-4 py-3 text-right text-sm leading-6 text-green-700 dark:text-green-400"
        role="status"
    >
        {{ status }}
    </div>

    <!-- @chisel-passkeys -->
    <PasskeyVerify />
    <!-- @end-chisel-passkeys -->

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        dir="rtl"
        class="flex flex-col gap-6 text-right"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">آدرس ایمیل</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="name@example.com"
                    dir="ltr"
                    class="text-left"
                    :disabled="processing"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Label for="password">رمز عبور</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm text-primary underline underline-offset-4"
                        :tabindex="5"
                    >
                        رمز عبور خود را فراموش کرده‌اید؟
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="رمز عبور خود را وارد کنید"
                    :disabled="processing"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Label for="remember" class="flex cursor-pointer items-center gap-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        :disabled="processing"
                    />
                    <span>مرا به خاطر بسپار</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-1 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'در حال ورود...' : 'ورود به حساب کاربری' }}
            </Button>
        </div>

        <!-- @chisel-registration -->
        <p class="text-center text-sm leading-6 text-muted-foreground">
            حساب کاربری ندارید؟
            <TextLink
                :href="register()"
                :tabindex="6"
                class="font-medium text-primary underline underline-offset-4"
            >
                ثبت‌نام
            </TextLink>
        </p>
        <!-- @end-chisel-registration -->
    </Form>
</template>
