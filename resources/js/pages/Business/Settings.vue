<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, CheckCircle2, MapPin, Save } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface Business {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    postal_code: string | null;
    logo_url: string | null;
    introduction: string | null;
}

const props = defineProps<{
    business: Business;
    features: { business_logo: boolean; can_manage_logo: boolean; business_introduction: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'تنظیمات کسب‌وکار',
                href: '/business/settings',
            },
        ],
    },
});

const form = useForm({
    name: props.business.name ?? '',
    phone: props.business.phone ?? '',
    email: props.business.email ?? '',
    address: props.business.address ?? '',
    city: props.business.city ?? '',
    postal_code: props.business.postal_code ?? '',
    introduction: props.business.introduction ?? '',
});

const logoForm = useForm<{ logo: File | null }>({ logo: null });

function submitLogo() {
    logoForm.post('/business/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => logoForm.reset(),
    });
}

function removeLogo() {
    router.delete('/business/logo', { preserveScroll: true });
}

function submit() {
    form.put('/business/settings', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="تنظیمات کسب‌وکار" />

    <main
        dir="rtl"
        class="min-h-full bg-background px-4 py-6 text-right sm:px-6 lg:px-8"
    >
        <div class="mx-auto w-full max-w-5xl space-y-8">
            <header class="space-y-2">
                <div class="flex items-center gap-2 text-sm font-medium text-primary">
                    <Building2 class="size-4" aria-hidden="true" />
                    مدیریت کسب‌وکار
                </div>

                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    تنظیمات کسب‌وکار
                </h1>

                <p class="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                    اطلاعات اصلی کسب‌وکار خود را مدیریت و به‌روزرسانی کنید.
                    این اطلاعات می‌توانند در بخش‌های مختلف پلتفرم نمایش داده شوند.
                </p>
            </header>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h2 class="font-semibold text-foreground">لوگوی کسب‌وکار</h2>
                    <p class="mt-1 text-xs text-muted-foreground">فایل لوگو به‌صورت خصوصی ذخیره می‌شود و فقط از مسیر امن منوی منتشرشده نمایش داده می‌شود.</p>
                </div>
                <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                    <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted">
                        <img v-if="business.logo_url" :src="business.logo_url" :alt="'لوگوی ' + business.name" class="size-full object-contain" width="96" height="96" />
                        <Building2 v-else class="size-9 text-muted-foreground" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1 space-y-3">
                        <form @submit.prevent="submitLogo" class="space-y-3">
                            <Label for="business-logo">انتخاب تصویر لوگو (JPG، PNG یا WebP، حداکثر ۲ مگابایت)</Label>
                            <Input id="business-logo" type="file" accept="image/jpeg,image/png,image/webp" class="h-auto min-h-11 py-2"  :disabled="!features.business_logo || !features.can_manage_logo || logoForm.processing" @input="logoForm.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                            <p v-if="logoForm.errors.logo" class="text-sm text-destructive" role="alert">{{ logoForm.errors.logo }}</p>
                            <p v-if="!features.business_logo" class="text-xs text-muted-foreground">بارگذاری لوگو در طرح فعلی فعال نیست. لوگوی قبلی در صورت وجود نمایش داده می‌شود.</p>
                            <div class="flex flex-wrap gap-2">
                                <Button type="submit"  :disabled="!features.business_logo || !features.can_manage_logo || !logoForm.logo || logoForm.processing">
                                    {{ logoForm.processing ? 'در حال بارگذاری...' : 'بارگذاری لوگو' }}
                                </Button>
                                <Button v-if="business.logo_url && features.can_manage_logo" type="button" variant="outline" :disabled="logoForm.processing" @click="removeLogo">حذف لوگو</Button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <div
                v-if="form.recentlySuccessful"
                role="status"
                class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-5 shrink-0" aria-hidden="true" />
                <span>تغییرات با موفقیت ذخیره شدند.</span>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Building2 class="size-5" aria-hidden="true" />
                        </div>

                        <div>
                            <h2 class="font-semibold text-foreground">
                                اطلاعات عمومی
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                نام و راه‌های ارتباطی کسب‌وکار
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="business-name">
                                نام کسب‌وکار
                                <span class="text-destructive">*</span>
                            </Label>

                            <Input
                                id="business-name"
                                v-model="form.name"
                                type="text"
                                required
                                autocomplete="organization"
                                placeholder="مثلاً کافه آفتاب"
                                class="h-11 text-right"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(form.errors.name)"
                                :aria-describedby="form.errors.name ? 'name-error' : undefined"
                            />

                            <p
                                v-if="form.errors.name"
                                id="name-error"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="business-phone">شماره تماس</Label>

                            <Input
                                id="business-phone"
                                v-model="form.phone"
                                type="tel"
                                autocomplete="tel"
                                dir="ltr"
                                placeholder="02112345678"
                                class="h-11 text-left"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(form.errors.phone)"
                            />

                            <p
                                v-if="form.errors.phone"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="business-email">ایمیل کسب‌وکار</Label>

                            <Input
                                id="business-email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                dir="ltr"
                                placeholder="info@example.com"
                                class="h-11 text-left"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(form.errors.email)"
                            />

                            <p
                                v-if="form.errors.email"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border px-5 py-4 sm:px-6">
                        <h2 class="font-semibold text-foreground">معرفی کسب‌وکار</h2>
                        <p class="mt-1 text-xs text-muted-foreground">متنی کوتاه درباره فضای کسب‌وکار و تجربه‌ای که به مشتری ارائه می‌دهید.</p>
                    </div>
                    <div class="space-y-3 p-5 sm:p-6">
                        <Label for="business-introduction">متن معرفی (حداکثر ۱۲۰۰ نویسه)</Label>
                        <textarea
                            id="business-introduction"
                            v-model="form.introduction"
                            rows="4"
                            maxlength="1200"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm leading-6 outline-none focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60"
                            :readonly="!features.business_introduction && Boolean(form.introduction)"
                            :disabled="!features.business_introduction && !form.introduction"
                            :aria-invalid="Boolean(form.errors.introduction)"
                        />
                        <p v-if="form.errors.introduction" class="text-sm text-destructive" role="alert">{{ form.errors.introduction }}</p>
                        <p v-if="!features.business_introduction" class="text-xs text-muted-foreground">معرفی کسب‌وکار در طرح فعلی قابل ویرایش نیست. متن قبلی حفظ می‌شود؛ می‌توانید آن را حذف کنید.</p>
                        <Button v-if="!features.business_introduction && form.introduction" type="button" variant="outline" size="sm" @click="form.introduction = ''">حذف معرفی قبلی</Button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="flex items-center gap-3 border-b border-border px-5 py-4 sm:px-6">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <MapPin class="size-5" aria-hidden="true" />
                        </div>

                        <div>
                            <h2 class="font-semibold text-foreground">
                                اطلاعات مکانی
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                آدرس و موقعیت مکانی کسب‌وکار
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="business-address">آدرس کامل</Label>

                            <textarea
                                id="business-address"
                                v-model="form.address"
                                rows="3"
                                placeholder="آدرس خیابان، کوچه، پلاک و واحد..."
                                :disabled="form.processing"
                                :aria-invalid="Boolean(form.errors.address)"
                                class="flex min-h-24 w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm text-right shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20"
                            />

                            <p
                                v-if="form.errors.address"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ form.errors.address }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="business-city">شهر</Label>

                            <Input
                                id="business-city"
                                v-model="form.city"
                                type="text"
                                autocomplete="address-level2"
                                placeholder="مثلاً تهران"
                                class="h-11 text-right"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(form.errors.city)"
                            />

                            <p
                                v-if="form.errors.city"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ form.errors.city }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="business-postal-code">کد پستی</Label>

                            <Input
                                id="business-postal-code"
                                v-model="form.postal_code"
                                type="text"
                                autocomplete="postal-code"
                                dir="ltr"
                                placeholder="1234567890"
                                class="h-11 text-left"
                                :disabled="form.processing"
                                :aria-invalid="Boolean(form.errors.postal_code)"
                            />

                            <p
                                v-if="form.errors.postal_code"
                                class="text-sm text-destructive"
                                role="alert"
                            >
                                {{ form.errors.postal_code }}
                            </p>
                        </div>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-5 text-muted-foreground">
                        پس از ذخیره، اطلاعات جدید کسب‌وکار ثبت خواهد شد.
                    </p>

                    <Button
                        type="submit"
                        class="h-11 w-full gap-2 sm:w-auto sm:min-w-40"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        <Save v-else class="size-4" aria-hidden="true" />

                        {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
                    </Button>
                </div>
            </form>
        </div>
    </main>
</template>
