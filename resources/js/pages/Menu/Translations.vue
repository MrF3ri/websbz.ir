<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

type LocaleOption = {
    code: string;
    name: string;
    native_name: string;
    direction: 'rtl' | 'ltr';
};

const props = defineProps<{
    locale: LocaleOption;
    locales: LocaleOption[];
    menu: { source_title: string; translation_title: string };
    categories: { public_id: string; source_name: string; translation_name: string }[];
    products: {
        public_id: string;
        source_name: string;
        source_description: string | null;
        translation_name: string;
        translation_description: string;
    }[];
}>();

const form = useForm({
    menu_title: props.menu.translation_title,
    categories: props.categories.map((category) => ({
        public_id: category.public_id,
        name: category.translation_name,
    })),
    products: props.products.map((product) => ({
        public_id: product.public_id,
        name: product.translation_name,
        description: product.translation_description,
    })),
});

const totalFields = computed(() => 1 + form.categories.length + form.products.length);
const completedFields = computed(() => (
    Number(form.menu_title.trim().length > 0)
    + form.categories.filter((category) => category.name.trim().length > 0).length
    + form.products.filter((product) => product.name.trim().length > 0).length
));
const completionPercent = computed(() => totalFields.value === 0
    ? 100
    : Math.round((completedFields.value / totalFields.value) * 100));

function changeLocale(locale: string): void {
    if (locale !== props.locale.code) {
        router.get(`/catalog/menu/translations/${locale}`);
    }
}

function changeLocaleFromEvent(event: Event): void {
    if (event.target instanceof HTMLSelectElement) {
        changeLocale(event.target.value);
    }
}

function save(): void {
    form.put(`/catalog/menu/translations/${props.locale.code}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`ترجمه منو — ${locale.native_name}`" />
    <AppLayout>
        <main dir="rtl" class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
            <header class="space-y-2">
                <h1 class="text-2xl font-bold">مدیریت ترجمه منو</h1>
                <p class="text-sm text-muted-foreground">
                    متن اصلی منو را به زبان انتخاب‌شده ترجمه کنید. قیمت، موجودی و تصاویر بدون تغییر باقی می‌مانند.
                </p>
            </header>

            <section class="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-border bg-card p-5">
                <div class="min-w-48 flex-1 space-y-2">
                    <label for="translation-locale" class="text-sm font-medium">زبان ترجمه</label>
                    <select
                        id="translation-locale"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        :value="locale.code"
                        @change="changeLocaleFromEvent"
                    >
                        <option v-for="option in locales" :key="option.code" :value="option.code">
                            {{ option.native_name }} ({{ option.code }})
                        </option>
                    </select>
                </div>
                <div class="min-w-48 flex-1 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span>تکمیل ترجمه</span>
                        <span class="tabular-nums">{{ completedFields }} از {{ totalFields }}</span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label="درصد تکمیل ترجمه"
                        :aria-valuenow="completionPercent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        class="h-2 overflow-hidden rounded-full bg-muted"
                    >
                        <div class="h-full rounded-full bg-primary transition-[width]" :style="{ width: completionPercent + '%' }" />
                    </div>
                </div>
            </section>

            <form class="space-y-6" @submit.prevent="save">
                <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 class="font-semibold">عنوان منو</h2>
                        <p class="mt-1 text-sm text-muted-foreground">متن اصلی: {{ menu.source_title }}</p>
                    </div>
                    <label for="menu-title" class="block space-y-2">
                        <span class="text-sm font-medium">عنوان ترجمه‌شده</span>
                        <input
                            id="menu-title"
                            v-model="form.menu_title"
                            :dir="locale.direction"
                            maxlength="160"
                            required
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        />
                    </label>
                    <p v-if="form.errors.menu_title" class="text-sm text-destructive">{{ form.errors.menu_title }}</p>
                </section>

                <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 class="font-semibold">دسته‌بندی‌ها</h2>
                        <p class="mt-1 text-sm text-muted-foreground">نام هر دسته‌بندی را ترجمه کنید.</p>
                    </div>
                    <p v-if="categories.length === 0" class="text-sm text-muted-foreground">هنوز دسته‌بندی‌ای ثبت نشده است.</p>
                    <div v-for="(category, index) in categories" :key="category.public_id" class="grid gap-2 sm:grid-cols-2">
                        <div class="rounded-md bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">متن اصلی</p>
                            <p class="mt-1 font-medium">{{ category.source_name }}</p>
                        </div>
                        <label :for="`category-${category.public_id}`" class="space-y-1">
                            <span class="text-sm font-medium">ترجمه دسته‌بندی</span>
                            <input
                                :id="`category-${category.public_id}`"
                                v-model="form.categories[index].name"
                                :dir="locale.direction"
                                maxlength="120"
                                required
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </label>
                    </div>
                    <p v-if="form.errors.categories" class="text-sm text-destructive">{{ form.errors.categories }}</p>
                </section>

                <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div>
                        <h2 class="font-semibold">محصولات</h2>
                        <p class="mt-1 text-sm text-muted-foreground">نام و توضیحات محصولات را ترجمه کنید.</p>
                    </div>
                    <p v-if="products.length === 0" class="text-sm text-muted-foreground">هنوز محصولی ثبت نشده است.</p>
                    <article v-for="(product, index) in products" :key="product.public_id" class="space-y-3 rounded-lg border border-border p-4">
                        <div>
                            <p class="font-medium">{{ product.source_name }}</p>
                            <p v-if="product.source_description" class="mt-1 text-sm text-muted-foreground">{{ product.source_description }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label :for="`product-name-${product.public_id}`" class="space-y-1">
                                <span class="text-sm font-medium">نام ترجمه‌شده</span>
                                <input
                                    :id="`product-name-${product.public_id}`"
                                    v-model="form.products[index].name"
                                    :dir="locale.direction"
                                    maxlength="180"
                                    required
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                />
                            </label>
                            <label :for="`product-description-${product.public_id}`" class="space-y-1">
                                <span class="text-sm font-medium">توضیحات ترجمه‌شده</span>
                                <textarea
                                    :id="`product-description-${product.public_id}`"
                                    v-model="form.products[index].description"
                                    :dir="locale.direction"
                                    rows="2"
                                    maxlength="5000"
                                    class="w-full rounded-md border border-input bg-background p-3 text-sm"
                                />
                            </label>
                        </div>
                    </article>
                    <p v-if="form.errors.products" class="text-sm text-destructive">{{ form.errors.products }}</p>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">ذخیره همه ترجمه‌ها به‌صورت یک تراکنش انجام می‌شود.</p>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'در حال ذخیره…' : 'ذخیره ترجمه‌ها' }}
                    </Button>
                </div>
            </form>
        </main>
    </AppLayout>
</template>
