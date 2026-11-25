<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type PublicProduct = {
    name: string;
    images: { url: string; width: number; height: number }[];
    description: string | null;
    price: number;
    discount_price: number | null;
};

type PublicCategory = {
    name: string;
    products: PublicProduct[];
};

const props = defineProps<{
    business: {
        name: string;
        slug: string;
    };
    menu: {
        title: string;
        slug: string;
        locale: string;
    };
    categories: PublicCategory[];
}>();

const tomanFormatter = new Intl.NumberFormat('fa-IR', {
    maximumFractionDigits: 0,
});

function formatPrice(value: number): string {
    return `${tomanFormatter.format(value)} تومان`;
}
</script>

<template>
    <Head :title="`${business.name} | ${menu.title}`">
        <meta
            head-key="description"
            name="description"
            :content="`منوی ${business.name} - ${menu.title}`"
        />
        <meta head-key="robots" name="robots" content="index,follow" />
    </Head>

    <main dir="rtl" lang="fa" class="min-h-screen bg-stone-50 px-4 py-8 text-stone-900 dark:bg-stone-950 dark:text-stone-100 sm:py-12">
        <div class="mx-auto w-full max-w-3xl space-y-8">
            <header class="rounded-3xl border border-stone-200 bg-white px-6 py-8 text-center shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:px-10">
                <p class="text-sm font-medium text-stone-500 dark:text-stone-400">
                    {{ business.name }}
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ menu.title }}
                </h1>
                <p class="mt-3 text-sm text-stone-500 dark:text-stone-400">
                    منوی رسمی {{ business.name }}
                </p>
            </header>

            <div v-if="categories.length === 0" class="rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center text-sm text-stone-500 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-400">
                در حال حاضر محصولی برای نمایش وجود ندارد.
            </div>

            <div v-else class="space-y-8">
                <section
                    v-for="(category, index) in categories"
                    :key="`${category.name}-${index}`"
                    class="space-y-3"
                >
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold">{{ category.name }}</h2>
                        <div class="h-px flex-1 bg-stone-200 dark:bg-stone-800" />
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm dark:border-stone-800 dark:bg-stone-900">
                        <article
                            v-for="(product, productIndex) in category.products"
                            :key="`${product.name}-${productIndex}`"
                            class="flex items-start justify-between gap-4 px-5 py-4"
                            :class="{ 'border-t border-stone-100 dark:border-stone-800': productIndex > 0 }"
                        >
                            <img
                                v-if="product.images[0]"
                                :src="product.images[0].url"
                                :alt="`تصویر ${product.name}`"
                                :width="product.images[0].width"
                                :height="product.images[0].height"
                                loading="lazy"
                                class="size-20 shrink-0 rounded-xl object-cover sm:size-24"
                            />
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold">{{ product.name }}</h3>
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
                        </article>
                    </div>
                </section>
            </div>

            <footer class="pt-2 text-center text-xs text-stone-400">
                منوی دیجیتال
            </footer>
        </div>
    </main>
</template>
