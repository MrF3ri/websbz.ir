<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'کد بازیابی',
            description: 'برای تأیید دسترسی به حساب، یکی از کدهای بازیابی اضطراری خود را وارد کنید.',
            buttonText: 'ورود با کد تأییدکننده',
        };
    }

    return {
        title: 'تأیید هویت دومرحله‌ای',
        description: 'کد شش‌رقمی نمایش‌داده‌شده در برنامه احراز هویت خود را وارد کنید.',
        buttonText: 'ورود با کد بازیابی',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="تأیید هویت دومرحله‌ای" />

    <div dir="rtl" class="space-y-6 text-right">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="space-y-5"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div class="flex flex-col items-center justify-center gap-3">
                    <InputOTP
                        id="otp"
                        v-model="code"
                        :maxlength="6"
                        :disabled="processing"
                        autofocus
                        dir="ltr"
                    >
                        <InputOTPGroup>
                            <InputOTPSlot
                                v-for="index in 6"
                                :key="index"
                                :index="index - 1"
                            />
                        </InputOTPGroup>
                    </InputOTP>
                    <InputError :message="errors.code" />
                </div>

                <Button type="submit" class="w-full" :disabled="processing">
                    {{ processing ? 'در حال بررسی...' : 'تأیید و ورود' }}
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    یا
                    <button
                        type="button"
                        class="font-medium text-primary underline underline-offset-4"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="space-y-5"
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    placeholder="کد بازیابی را وارد کنید"
                    v-focus
                    required
                    :disabled="processing"
                    dir="ltr"
                    class="text-left"
                />
                <InputError :message="errors.recovery_code" />

                <Button type="submit" class="w-full" :disabled="processing">
                    {{ processing ? 'در حال بررسی...' : 'تأیید و ورود' }}
                </Button>

                <p class="text-center text-sm text-muted-foreground">
                    یا
                    <button
                        type="button"
                        class="font-medium text-primary underline underline-offset-4"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>
    </div>
</template>
