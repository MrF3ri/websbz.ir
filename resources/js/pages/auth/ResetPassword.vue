<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'تنظیم رمز عبور جدید',
        description: 'برای حساب کاربری خود یک رمز عبور جدید و امن انتخاب کنید.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="تنظیم رمز عبور جدید" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        dir="rtl"
        class="text-right"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">آدرس ایمیل</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    v-model="inputEmail"
                    dir="ltr"
                    class="text-left"
                    readonly
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">رمز عبور جدید</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    placeholder="رمز عبور جدید را وارد کنید"
                    :passwordrules="passwordRules"
                    :disabled="processing"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">تکرار رمز عبور جدید</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="رمز عبور جدید را دوباره وارد کنید"
                    :passwordrules="passwordRules"
                    :disabled="processing"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'در حال ذخیره...' : 'ذخیره رمز عبور جدید' }}
            </Button>
        </div>
    </Form>
</template>
