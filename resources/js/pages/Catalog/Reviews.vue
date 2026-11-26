<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Review = {
    public_id: string;
    product_name: string;
    rating: number;
    display_name: string | null;
    body: string | null;
    status: 'pending' | 'approved' | 'rejected';
    created_at: string | null;
};

type Props = {
    reviews: {
        data: Review[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

const props = defineProps<Props>();
const processingId = ref<string | null>(null);

function moderate(review: Review, action: 'approve' | 'reject'): void {
    processingId.value = review.public_id;
    router.post(route('catalog.reviews.' + action, review.public_id), {}, {
        preserveScroll: true,
        onFinish: () => {
            processingId.value = null;
        },
    });
}

const statusLabels = {
    pending: 'در انتظار بررسی',
    approved: 'تأییدشده',
    rejected: 'ردشده',
} as const;
</script>

<template>
    <Head title="مدیریت نظرات محصولات" />

    <main dir="rtl" class="min-h-full bg-background px-4 py-6 text-right sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <header class="space-y-2">
                <p class="text-sm font-medium text-primary">مدیریت کسب‌وکار</p>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">نظرات و امتیازهای محصولات</h1>
                <p class="text-sm leading-6 text-muted-foreground">نظرها ابتدا در انتظار بررسی هستند و فقط پس از تأیید در منوی عمومی نمایش داده می‌شوند.</p>
                <a :href="route('catalog.index')" class="inline-flex text-sm text-primary underline underline-offset-4">بازگشت به مدیریت منو</a>
            </header>

            <section class="overflow-hidden rounded-xl border border-border bg-card">
                <div v-if="reviews.data.length === 0" class="px-6 py-14 text-center text-sm text-muted-foreground">
                    هنوز نظری ثبت نشده است.
                </div>
                <div v-else class="divide-y divide-border">
                    <article v-for="review in reviews.data" :key="review.public_id" class="space-y-3 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="font-semibold text-foreground">{{ review.product_name }}</h2>
                                <p class="mt-1 text-xs text-muted-foreground">{{ review.display_name || 'مهمان' }} · {{ review.created_at ? new Date(review.created_at).toLocaleString('fa-IR') : '' }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-amber-600">{{ '★'.repeat(review.rating) }} <span class="text-xs">{{ review.rating }}/5</span></span>
                                <span class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground">{{ statusLabels[review.status] }}</span>
                            </div>
                        </div>
                        <p v-if="review.body" class="whitespace-pre-line text-sm leading-7 text-foreground">{{ review.body }}</p>
                        <div v-if="review.status === 'pending'" class="flex flex-wrap gap-2">
                            <button type="button" class="rounded-md bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground disabled:opacity-50" :disabled="processingId === review.public_id" @click="moderate(review, 'approve')">تأیید و انتشار</button>
                            <button type="button" class="rounded-md border border-destructive px-4 py-2 text-xs font-semibold text-destructive disabled:opacity-50" :disabled="processingId === review.public_id" @click="moderate(review, 'reject')">رد نظر</button>
                        </div>
                    </article>
                </div>
                <nav v-if="reviews.last_page > 1" aria-label="صفحه‌بندی" class="flex flex-wrap gap-2 border-t border-border p-4">
                    <a v-for="link in reviews.links" :key="link.label" :href="link.url ?? undefined" class="rounded-md border border-border px-3 py-2 text-xs" :class="link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" v-html="link.label" />
                </nav>
            </section>
        </div>
    </main>
</template>
