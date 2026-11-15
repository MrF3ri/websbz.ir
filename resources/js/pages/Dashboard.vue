<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Activity, Eye, Users } from '@lucide/vue';
import { dashboard } from '@/routes';
import { useJalaliDate } from '@/composables/useJalaliDate';
import type { Entitlements } from '@/types/entitlements';

type DashboardPageProps = {
    entitlements: Entitlements | null;
};

const page = usePage<DashboardPageProps>();
const { formatDateTime } = useJalaliDate();

const hasTrial = computed(() => {
    const entitlements = page.props.entitlements;

    return entitlements?.status === 'trialing' && Boolean(entitlements.trial_ends_at);
});

const trialIsActive = computed(() => {
    const trialEndsAt = page.props.entitlements?.trial_ends_at;

    return hasTrial.value && trialEndsAt !== null && trialEndsAt !== undefined
        && Number.isFinite(Date.parse(trialEndsAt))
        && Date.parse(trialEndsAt) > Date.now();
});

const trialEndsAt = computed(() => {
    const value = page.props.entitlements?.trial_ends_at;

    return hasTrial.value && value ? formatDateTime(value) : null;
});

const currentPlanLabel = computed(() => {
    const labels: Record<string, string> = {
        economic: 'اقتصادی',
        professional: 'حرفه‌ای',
        top: 'ویژه',
    };

    const planCode = page.props.entitlements?.plan_code;

    return planCode ? labels[planCode] ?? planCode : '';
});

const stats = [
    {
        title: 'تعداد اعضا',
        value: '۰',
        description: 'اعضای فعال کسب‌وکار',
        icon: Users,
    },
    {
        title: 'بازدید منو',
        value: '۰',
        description: 'بازدید در ۳۰ روز گذشته',
        icon: Eye,
    },
    {
        title: 'فعالیت‌های اخیر',
        value: '۰',
        description: 'رویدادهای ثبت‌شده',
        icon: Activity,
    },
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'داشبورد',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="داشبورد" />

    <main class="min-h-full bg-background px-4 py-6 sm:px-6 lg:px-8" dir="rtl">
        <div class="mx-auto w-full max-w-7xl space-y-8">
            <header class="space-y-2">
                <p class="text-sm font-medium text-muted-foreground">
                    نمای کلی
                </p>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    خلاصه وضعیت کسب‌وکار
                </h1>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
                    در این بخش می‌توانید نمایی کلی از وضعیت و فعالیت‌های کسب‌وکار خود داشته باشید.
                </p>
            </header>

            <section
                v-if="trialEndsAt"
                aria-labelledby="trial-status-heading"
                class="rounded-xl border border-primary/20 bg-primary/5 p-5"
            >
                <h2 id="trial-status-heading" class="font-semibold text-foreground">
                    <template v-if="trialIsActive">
                        دوره آزمایشی طرح {{ currentPlanLabel }} فعال است
                    </template>
                    <template v-else>
                        دوره آزمایشی طرح {{ currentPlanLabel }} به پایان رسیده است
                    </template>
                </h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    زمان پایان دوره آزمایشی:
                    <time :datetime="page.props.entitlements?.trial_ends_at ?? undefined" class="font-medium text-foreground">
                        {{ trialEndsAt }}
                    </time>
                </p>
            </section>

            <section aria-labelledby="dashboard-stats-heading" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 id="dashboard-stats-heading" class="text-base font-semibold text-foreground">
                        شاخص‌های کلیدی
                    </h2>
                    <span class="text-xs text-muted-foreground">نمای آزمایشی</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="stat in stats"
                        :key="stat.title"
                        class="rounded-xl border border-border bg-card p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-3">
                                <p class="text-sm font-medium text-muted-foreground">
                                    {{ stat.title }}
                                </p>
                                <p class="text-3xl font-bold tabular-nums text-foreground">
                                    {{ stat.value }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-primary/10 p-2.5 text-primary">
                                <component :is="stat.icon" class="size-5" aria-hidden="true" />
                            </div>
                        </div>
                        <p class="mt-4 text-xs text-muted-foreground">
                            {{ stat.description }}
                        </p>
                    </article>
                </div>
            </section>

            <section
                aria-labelledby="recent-activity-heading"
                class="rounded-xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h2 id="recent-activity-heading" class="font-semibold text-foreground">
                        فعالیت‌های اخیر
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        آخرین رویدادهای ثبت‌شده در کسب‌وکار
                    </p>
                </div>

                <div class="flex min-h-64 flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="mb-4 rounded-full bg-muted p-4 text-muted-foreground">
                        <Activity class="size-7" aria-hidden="true" />
                    </div>
                    <p class="font-medium text-foreground">
                        هنوز فعالیتی ثبت نشده است.
                    </p>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground">
                        پس از ثبت رویدادها و تعاملات، خلاصه‌ای از آن‌ها در این قسمت نمایش داده می‌شود.
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
