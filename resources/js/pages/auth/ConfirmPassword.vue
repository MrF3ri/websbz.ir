<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
/* @chisel-passkeys */
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'تأیید مجدد رمز عبور',
        description: 'این بخش امن است. برای ادامه، رمز عبور حساب کاربری خود را دوباره وارد کنید.',
    },
});
</script>

<template>
    <Head title="تأیید رمز عبور" />

    <!-- @chisel-passkeys -->
    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="تأیید با Passkey"
        loading-label="در حال تأیید..."
        separator="یا با رمز عبور تأیید کنید"
    />
    <!-- @end-chisel-passkeys -->

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        dir="rtl"
        class="text-right"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="password">رمز عبور</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    :disabled="processing"
                />
                <InputError :message="errors.password" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'در حال بررسی...' : 'تأیید رمز عبور' }}
            </Button>
        </div>
    </Form>
</template>
