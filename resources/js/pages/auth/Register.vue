<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{ passwordRules: string }>();

defineOptions({
    layout: {
        title: 'ایجاد حساب کاربری',
        description: 'برای ساخت حساب کاربری، اطلاعات زیر را وارد کنید.',
    },
});
</script>

<template>
    <Head title="ثبت‌نام" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        dir="rtl"
        class="flex flex-col gap-6 text-right"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name">نام و نام خانوادگی</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    placeholder="نام و نام خانوادگی خود را وارد کنید"
                    class="text-right"
                    :disabled="processing"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">آدرس ایمیل</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    placeholder="name@example.com"
                    dir="ltr"
                    class="text-left"
                    :disabled="processing"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">رمز عبور</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    placeholder="رمز عبور خود را وارد کنید"
                    :passwordrules="passwordRules"
                    :disabled="processing"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">تکرار رمز عبور</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    placeholder="رمز عبور را دوباره وارد کنید"
                    :passwordrules="passwordRules"
                    :disabled="processing"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-1 w-full"
                :tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'در حال ثبت‌نام...' : 'ثبت‌نام' }}
            </Button>
        </div>

        <p class="text-center text-sm leading-6 text-muted-foreground">
            قبلاً ثبت‌نام کرده‌اید؟
            <TextLink
                :href="login()"
                class="font-medium text-primary underline underline-offset-4"
                :tabindex="6"
            >
                ورود به حساب کاربری
            </TextLink>
        </p>
    </Form>
</template>
