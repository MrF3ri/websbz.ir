<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

type Review = {
    rating: number;
    display_name: string | null;
    body: string | null;
    created_at: string | null;
};

const props = defineProps<{
    product: {
        public_id: string;
        review_count: number;
        average_rating: number | null;
        reviews: Review[];
    };
    businessSlug: string;
    menuSlug: string;
    branchSlug: string;
    locale: 'fa' | 'en' | 'ar';
}>();

const copy = {
    fa: {
        reviews: 'نظر مشتریان',
        count: 'نظر',
        add: 'ثبت امتیاز و نظر',
        rating: 'امتیاز شما',
        name: 'نام نمایشی (اختیاری)',
        body: 'نظر شما (اختیاری)',
        submit: 'ارسال برای بررسی',
        pending: 'نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.',
        error: 'ارسال نظر انجام نشد.',
        guest: 'مهمان',
        noReviews: 'هنوز نظری ثبت نشده است.',
        ratingRequired: 'لطفاً امتیاز خود را انتخاب کنید.',
    },
    en: {
        reviews: 'Customer reviews',
        count: 'reviews',
        add: 'Rate this product',
        rating: 'Your rating',
        name: 'Display name (optional)',
        body: 'Your review (optional)',
        submit: 'Submit for moderation',
        pending: 'Your review was submitted and will appear after moderation.',
        error: 'The review could not be submitted.',
        guest: 'Guest',
        noReviews: 'No reviews yet.',
        ratingRequired: 'Please choose a rating.',
    },
    ar: {
        reviews: 'آراء العملاء',
        count: 'تقييمات',
        add: 'قيّم هذا المنتج',
        rating: 'تقييمك',
        name: 'الاسم الظاهر (اختياري)',
        body: 'رأيك (اختياري)',
        submit: 'إرسال للمراجعة',
        pending: 'تم إرسال رأيك وسيظهر بعد المراجعة.',
        error: 'تعذر إرسال التقييم.',
        guest: 'زائر',
        noReviews: 'لا توجد تقييمات بعد.',
        ratingRequired: 'يرجى اختيار تقييم.',
    },
} as const;

const labels = copy[props.locale] ?? copy.fa;
const form = useForm({
    rating: 0,
    display_name: '',
    body: '',
});
const submitted = ref(false);

function submit(): void {
    if (form.rating < 1 || form.rating > 5) {
        form.setError('rating', labels.ratingRequired);
        return;
    }

    form.post(route('public.menu.reviews.store', [
        props.businessSlug,
        props.menuSlug,
        props.branchSlug,
        props.product.public_id,
    ]), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset('body', 'display_name');
        },
    });
}
</script>

<template>
    <section class="mt-5 border-t border-stone-100 pt-4 dark:border-stone-800" :dir="locale === 'en' ? 'ltr' : 'rtl'">
        <div class="flex flex-wrap items-center gap-2 text-xs text-stone-500 dark:text-stone-400">
            <span class="font-semibold">{{ labels.reviews }}</span>
            <span v-if="product.average_rating !== null" class="font-semibold text-amber-600 dark:text-amber-400">
                ★ {{ product.average_rating.toFixed(1) }}
            </span>
            <span>({{ product.review_count }} {{ labels.count }})</span>
        </div>

        <div v-if="product.reviews.length" class="mt-3 space-y-3">
            <article v-for="(review, index) in product.reviews" :key="index" class="rounded-lg bg-stone-50 px-3 py-2 dark:bg-stone-800/60">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-xs font-semibold text-stone-700 dark:text-stone-200">{{ review.display_name || labels.guest }}</span>
                    <span class="text-xs text-amber-600 dark:text-amber-400" :aria-label="String(review.rating) + '/5'">{{ '★'.repeat(review.rating) }}</span>
                </div>
                <p v-if="review.body" class="mt-1 whitespace-pre-line text-sm leading-6 text-stone-600 dark:text-stone-300">{{ review.body }}</p>
            </article>
        </div>
        <p v-else class="mt-2 text-xs text-stone-400">{{ labels.noReviews }}</p>

        <details class="mt-3">
            <summary class="cursor-pointer text-xs font-semibold text-[var(--menu-accent)] underline underline-offset-4">{{ labels.add }}</summary>
            <form class="mt-3 space-y-3 rounded-lg border border-stone-200 p-3 dark:border-stone-700" @submit.prevent="submit">
                <fieldset>
                    <legend class="mb-2 text-xs font-medium text-stone-600 dark:text-stone-300">{{ labels.rating }}</legend>
                    <div class="flex gap-1" role="group" :aria-label="labels.rating">
                        <button
                            v-for="rating in [1, 2, 3, 4, 5]"
                            :key="rating"
                            type="button"
                            class="rounded px-1 text-2xl leading-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--menu-accent)]"
                            :class="rating <= form.rating ? 'text-amber-500' : 'text-stone-300 dark:text-stone-600'"
                            :aria-label="String(rating)"
                            :aria-pressed="form.rating === rating"
                            @click="form.rating = rating; form.clearErrors('rating')"
                        >★</button>
                    </div>
                    <p v-if="form.errors.rating" class="mt-1 text-xs text-red-600" role="alert">{{ form.errors.rating }}</p>
                </fieldset>
                <label class="block space-y-1">
                    <span class="text-xs text-stone-600 dark:text-stone-300">{{ labels.name }}</span>
                    <input v-model="form.display_name" maxlength="80" autocomplete="nickname" class="h-10 w-full rounded-md border border-stone-200 bg-transparent px-3 text-sm dark:border-stone-700" />
                    <span v-if="form.errors.display_name" class="text-xs text-red-600">{{ form.errors.display_name }}</span>
                </label>
                <label class="block space-y-1">
                    <span class="text-xs text-stone-600 dark:text-stone-300">{{ labels.body }}</span>
                    <textarea v-model="form.body" maxlength="1500" rows="3" class="w-full rounded-md border border-stone-200 bg-transparent px-3 py-2 text-sm dark:border-stone-700" />
                    <span v-if="form.errors.body" class="text-xs text-red-600">{{ form.errors.body }}</span>
                </label>
                <p v-if="form.errors.review" class="text-xs text-red-600" role="alert">{{ form.errors.review }}</p>
                <p v-if="submitted" class="text-xs text-emerald-700 dark:text-emerald-400" role="status">{{ labels.pending }}</p>
                <button type="submit" :disabled="form.processing" class="rounded-md bg-[var(--menu-accent)] px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? '…' : labels.submit }}
                </button>
            </form>
        </details>
    </section>
</template>
