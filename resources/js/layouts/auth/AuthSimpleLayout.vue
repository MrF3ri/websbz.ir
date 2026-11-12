<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main
        dir="rtl"
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-muted/40 px-4 py-10 sm:px-6 lg:px-8"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-primary/10 via-primary/5 to-transparent"
        />

        <div class="relative z-10 w-full max-w-md">
            <div class="mb-7 flex flex-col items-center gap-3 text-center">
                <Link
                    :href="home()"
                    class="flex size-14 items-center justify-center rounded-2xl border border-border/70 bg-background shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    aria-label="صفحه اصلی"
                >
                    <AppLogoIcon class="size-9 fill-current text-foreground" />
                </Link>

                <div class="space-y-2">
                    <p class="text-xs font-semibold tracking-[0.18em] text-primary">
                        WEB SBZ
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        {{ title }}
                    </h1>
                    <p class="mx-auto max-w-sm text-sm leading-7 text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>

            <section
                class="rounded-2xl border border-border/70 bg-card p-5 shadow-xl shadow-black/[0.03] sm:p-7"
            >
                <slot />
            </section>

            <p class="mt-6 text-center text-xs text-muted-foreground">
                © {{ new Date().getFullYear() }} وب‌سبز. تمامی حقوق محفوظ است.
            </p>
        </div>
    </main>
</template>
