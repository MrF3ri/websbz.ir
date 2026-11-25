<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type PublicProduct = {
    public_id: string;
    name: string;
    label: 'new' | 'popular' | 'recommended' | 'chef_special' | null;
    images: { url: string; srcset: string; sizes: string; width: number; height: number }[];
    description: string | null;
    price: number;
    discount_price: number | null;
};

type PublicCategory = {
    name: string;
    products: PublicProduct[];
};

defineProps<{
    business: {
        name: string;
        slug: string;
        logo_url: string | null;
        introduction: string | null;
    };
    menu: {
        title: string;
        slug: string;
        locale: string;
        theme: { template: string; primary_color: string; font: string; mode: string; density: string; card_style: string; heading_size: string };
        image_slider_enabled: boolean;
        advertising_banner: { title: string | null; message: string | null; cta_label: string | null; cta_url: string | null } | null;
    };
    categories: PublicCategory[];
}>();

const tomanFormatter = new Intl.NumberFormat('fa-IR', {
    maximumFractionDigits: 0,
});

const productLabelTitles = { new: 'جدید', popular: 'پرفروش', recommended: 'پیشنهاد ویژه', chef_special: 'پیشنهاد سرآشپز' } as const;

const densityClasses: Record<string, string> = {
    compact: 'space-y-6',
    comfortable: 'space-y-8',
    spacious: 'space-y-10',
};
const categoryDensityClasses: Record<string, string> = {
    compact: 'space-y-2',
    comfortable: 'space-y-3',
    spacious: 'space-y-5',
};
const productPaddingClasses: Record<string, string> = {
    compact: 'px-4 py-3',
    comfortable: 'px-5 py-4',
    spacious: 'px-6 py-6',
};
const headingSizeClasses: Record<string, string> = {
    normal: 'text-3xl sm:text-4xl',
    large: 'text-4xl sm:text-5xl',
    display: 'text-5xl sm:text-6xl',
};
const categoryHeadingClasses: Record<string, string> = {
    normal: 'text-xl',
    large: 'text-2xl',
    display: 'text-3xl',
};
const productBorderClasses: Record<string, string> = {
    classic: 'border border-stone-200 dark:border-stone-800',
    minimal: 'border-0',
    editorial: 'border-y border-stone-300 dark:border-stone-700',
    modern: 'border border-stone-200 dark:border-stone-700',
    cafe: 'border border-amber-200 dark:border-amber-900',
    contrast: 'border-2 border-stone-900 dark:border-white',
};
const templateDefaultCardClasses: Record<string, string> = {
    classic: 'rounded-2xl shadow-sm',
    minimal: 'rounded-lg shadow-none',
    editorial: 'rounded-none shadow-none',
    modern: 'rounded-3xl shadow-lg',
    cafe: 'rounded-2xl shadow-sm',
    contrast: 'rounded-md shadow-md',
};
const cardStyleClasses: Record<string, string> = {
    flat: 'rounded-none shadow-none',
    elevated: 'rounded-3xl shadow-lg',
};
const imageSliderRefs = new Map<string, HTMLDivElement>();

function setImageSliderRef(publicId: string, element: unknown): void {
    if (element instanceof HTMLDivElement) {
        imageSliderRefs.set(publicId, element);
    } else {
        imageSliderRefs.delete(publicId);
    }
}

function scrollProductImages(publicId: string, direction: -1 | 1): void {
    const element = imageSliderRefs.get(publicId);
    if (!element) return;

    element.scrollBy({
        left: direction * Math.max(220, element.clientWidth * 0.75),
        behavior: 'smooth',
    });
}

function formatPrice(value: number): string {
    return tomanFormatter.format(value) + ' تومان';
}
</script>

<template>
    <Head :title="business.name + ' | ' + menu.title">
        <meta
            head-key="description"
            name="description"
            :content="'منوی ' + business.name + ' - ' + menu.title"
        />
        <meta head-key="robots" name="robots" content="index,follow" />
    </Head>

    <div :class="menu.theme.mode === 'dark' ? 'dark' : ''">
    <main id="public-menu-main" aria-labelledby="public-menu-title" dir="rtl" lang="fa" :data-template="menu.theme.template" :style="{ '--menu-accent': menu.theme.primary_color }"  :class="['min-h-screen bg-stone-50 px-4 py-8 text-stone-900 dark:bg-stone-950 dark:text-stone-100 sm:py-12', menu.theme.font === 'serif' ? 'font-serif' : menu.theme.font === 'rounded' ? 'font-sans tracking-wide' : 'font-sans']">
        <div class="mx-auto w-full max-w-3xl" :class="densityClasses[menu.theme.density] ?? densityClasses.comfortable">
            <header class="border border-stone-200 bg-white px-6 py-8 text-center shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:px-10"  :class="{ classic: 'rounded-3xl shadow-sm', minimal: 'rounded-xl border-0 shadow-none', editorial: 'rounded-none border-x-0 border-t-0 shadow-none', modern: 'rounded-2xl border-2 border-stone-100 shadow-lg dark:border-stone-700', cafe: 'rounded-[2rem] border-amber-200 dark:border-amber-900', contrast: 'rounded-md border-2 border-stone-900 shadow-md dark:border-white' }[menu.theme.template] ?? 'rounded-3xl'">
                <img v-if="business.logo_url" :src="business.logo_url" :alt="'لوگوی ' + business.name" class="mx-auto mb-4 size-20 rounded-xl object-contain" width="80" height="80" />
                <p class="text-sm font-medium text-stone-500 dark:text-stone-400">
                    {{ business.name }}
                </p>
                <p v-if="business.introduction" class="mx-auto mt-3 max-w-2xl text-sm leading-7 text-stone-600 dark:text-stone-300">{{ business.introduction }}</p>
                <h1 id="public-menu-title" class="mt-2 font-bold tracking-tight" :class="headingSizeClasses[menu.theme.heading_size] ?? headingSizeClasses.normal" style="color: var(--menu-accent)">
                    {{ menu.title }}
                </h1>
                <p class="mt-3 text-sm text-stone-500 dark:text-stone-400">
                    منوی رسمی {{ business.name }}
                </p>
            </header>

            <section v-if="menu.advertising_banner" aria-label="بنر تبلیغاتی" class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-5 text-amber-950 shadow-sm dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100 sm:px-7">
                <h2 v-if="menu.advertising_banner.title" class="text-lg font-bold">{{ menu.advertising_banner.title }}</h2>
                <p v-if="menu.advertising_banner.message" class="mt-2 whitespace-pre-line text-sm leading-7">{{ menu.advertising_banner.message }}</p>
                <a
                    v-if="menu.advertising_banner.cta_label && menu.advertising_banner.cta_url"
                    :href="menu.advertising_banner.cta_url"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="mt-4 inline-flex items-center rounded-lg bg-amber-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 dark:bg-amber-200 dark:text-amber-950 dark:hover:bg-amber-100"
                >
                    {{ menu.advertising_banner.cta_label }}
                </a>
            </section>

            <div v-if="categories.length === 0" role="status" aria-live="polite" class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center text-sm text-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-400">
                در حال حاضر محصولی برای نمایش وجود ندارد.
            </div>

            <div v-else class="space-y-8">
                <section
                    v-for="(category, index) in categories"
                    :key="category.name + '-' + index"
                    :aria-label="'دسته‌بندی ' + category.name"
                    :class="categoryDensityClasses[menu.theme.density] ?? categoryDensityClasses.comfortable"
                >
                    <div class="flex items-center gap-3">
                        <h2 class="font-bold" :class="categoryHeadingClasses[menu.theme.heading_size] ?? categoryHeadingClasses.normal" style="color: var(--menu-accent)">{{ category.name }}</h2>
                        <div class="h-px flex-1 bg-stone-200 dark:bg-stone-800" />
                    </div>

                    <div class="overflow-hidden bg-white dark:bg-stone-900" :class="[productBorderClasses[menu.theme.template] ?? productBorderClasses.classic, (menu.theme.card_style === 'standard' ? templateDefaultCardClasses[menu.theme.template] : cardStyleClasses[menu.theme.card_style]) ?? templateDefaultCardClasses[menu.theme.template] ?? templateDefaultCardClasses.classic]">
                        <article
                            v-for="(product, productIndex) in category.products"
                            :key="product.public_id"
                            :class="[productPaddingClasses[menu.theme.density] ?? productPaddingClasses.comfortable, productIndex > 0 ? 'border-t border-stone-100 dark:border-stone-800' : '']"
                        >
                            <div v-if="menu.image_slider_enabled && product.images.length > 1" class="mb-4">
                                <div class="mb-2 flex justify-end gap-2" dir="rtl">
                                    <button type="button" class="rounded-md border border-stone-200 px-3 py-1 text-xs text-stone-600 transition hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--menu-accent)] dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800" :aria-label="'تصاویر قبلی ' + product.name" @click="scrollProductImages(product.public_id, -1)">قبلی</button>
                                    <button type="button" class="rounded-md border border-stone-200 px-3 py-1 text-xs text-stone-600 transition hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--menu-accent)] dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800" :aria-label="'تصاویر بعدی ' + product.name" @click="scrollProductImages(product.public_id, 1)">بعدی</button>
                                </div>
                                <div :ref="(element) => setImageSliderRef(product.public_id, element)" class="flex snap-x snap-mandatory gap-2 overflow-x-auto scroll-smooth pb-2" dir="ltr" tabindex="0" role="region" aria-roledescription="گالری تصاویر" :aria-label="'گالری تصاویر ' + product.name">
                                    <div v-for="(image, imageIndex) in product.images" :key="image.url" class="w-[82%] shrink-0 snap-center sm:w-[62%]">
                                        <img :src="image.url" :srcset="image.srcset" :sizes="image.sizes" :alt="'تصویر ' + product.name + '، شماره ' + (imageIndex + 1)" :width="image.width" :height="image.height" loading="lazy" decoding="async" class="aspect-[4/3] w-full rounded-xl object-cover" />
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="product.images.length" class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <img
                                    v-for="(image, imageIndex) in product.images"
                                    :key="image.url"
                                    :src="image.url"
                                    :srcset="image.srcset"
                                    :sizes="image.sizes"
                                    :alt="'تصویر ' + product.name + '، شماره ' + (imageIndex + 1)"
                                    :width="image.width"
                                    :height="image.height"
                                    loading="lazy"
                                    decoding="async"
                                    class="aspect-[4/3] w-full rounded-xl object-cover"
                                />
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold">{{ product.name }}</h3>
                                        <span v-if="product.label" class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="{ new: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200', popular: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200', recommended: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200', chef_special: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200' }[product.label]">{{ productLabelTitles[product.label] }}</span>
                                    </div>
                                    <p v-if="product.description" class="mt-1 whitespace-pre-line text-sm leading-6 text-stone-500 dark:text-stone-400">
                                        {{ product.description }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-left">
                                    <p v-if="product.discount_price !== null" class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ formatPrice(product.discount_price) }}
                                    </p>
                                    <p :class="product.discount_price !== null ? 'mt-1 text-xs text-stone-400 line-through' : 'text-sm font-bold'">
                                        {{ formatPrice(product.price) }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <footer class="pt-2 text-center text-xs text-stone-400">
                منوی دیجیتال
            </footer>
        </div>
    </main>
    </div>
</template>
