<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { ref } from 'vue';

type Template = { name: string; description: string; layout: string };

const props = defineProps<{
    menu: { theme: { template: string; primary_color: string; font: string; mode: string } };
    templates: Record<string, Template>;
    features: { custom_colors: boolean; font_selection: boolean; dark_mode: boolean };
}>();

const form = useForm({
    template: props.menu.theme.template,
    primary_color: props.menu.theme.primary_color,
    font: props.menu.theme.font,
    mode: props.menu.theme.mode,
});

const previewMode = ref<'mobile' | 'desktop'>('mobile');

const previewClasses: Record<string, string> = {
    classic: 'rounded-xl border border-stone-200 bg-white shadow-sm',
    minimal: 'rounded-lg border-0 bg-white shadow-none',
    editorial: 'rounded-none border-y border-stone-300 bg-white',
    modern: 'rounded-2xl border-2 border-stone-200 bg-white shadow-lg',
    cafe: 'rounded-[1.5rem] border border-amber-200 bg-amber-50',
    contrast: 'rounded-md border-2 border-stone-900 bg-white shadow-md',
};

function save(): void {
    form.put('/catalog/menu/theme', { preserveScroll: true });
}
</script>

<template>
    <Head title="سفارشی‌سازی ظاهر منو" />
    <AppLayout>
        <main dir="rtl" class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
            <header class="space-y-2">
                <h1 class="text-2xl font-bold">قالب و ظاهر منو</h1>
                <p class="text-sm text-muted-foreground">قالب، رنگ، قلم و حالت نمایش منوی عمومی را تنظیم کنید.</p>
            </header>

            <section class="rounded-xl border border-border bg-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">انتخاب قالب</h2>
                        <p class="mt-1 text-sm text-muted-foreground">پیش‌نمایش‌های زیر در چیدمان واکنش‌گرا نمایش داده می‌شوند.</p>
                    </div>
                    <div class="flex items-center gap-1 rounded-lg border border-border p-1" aria-label="اندازه پیش‌نمایش">
                        <button type="button" class="rounded-md px-3 py-1.5 text-xs" :class="previewMode === 'mobile' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="previewMode = 'mobile'">موبایل</button>
                        <button type="button" class="rounded-md px-3 py-1.5 text-xs" :class="previewMode === 'desktop' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="previewMode = 'desktop'">دسکتاپ</button>
                    </div>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">قالب‌های قابل انتخاب بر اساس اشتراک فعال نمایش داده می‌شوند.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="(template, key) in templates"
                        :key="key"
                        class="cursor-pointer rounded-lg border p-4 transition-colors"
                        :class="form.template === key ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                    >
                        <span class="mx-auto mb-3 block overflow-hidden rounded-lg border border-border bg-background p-2 transition-[width]" :class="previewMode === 'mobile' ? 'w-full max-w-40' : 'w-full max-w-full'">
                            <span class="mb-2 flex items-center justify-between gap-2">
                                <span class="size-5 rounded-full bg-primary/20" />
                                <span class="h-2 w-14 rounded bg-muted-foreground/30" />
                            </span>
                            <span class="mb-2 block h-2 w-2/3 rounded" :style="{ backgroundColor: form.primary_color }" />
                            <span class="grid grid-cols-2 gap-2">
                                <span v-for="sample in 2" :key="sample" class="space-y-1 rounded-md p-2" :class="previewClasses[key] ?? previewClasses.classic">
                                    <span class="block h-8 rounded bg-stone-200" />
                                    <span class="block h-1.5 w-3/4 rounded bg-stone-400/60" />
                                    <span class="block h-1.5 w-1/2 rounded" :style="{ backgroundColor: form.primary_color }" />
                                </span>
                            </span>
                        </span>
                        <span class="flex items-center gap-2 font-medium">
                            <input v-model="form.template" type="radio" name="template" :value="key" />
                            {{ template.name }}
                        </span>
                        <span class="mt-2 block text-sm text-muted-foreground">{{ template.description }}</span>
                    </label>
                </div>
                <p v-if="form.errors.template" class="mt-2 text-sm text-destructive" role="alert">{{ form.errors.template }}</p>
            </section>

            <section class="grid gap-5 rounded-xl border border-border bg-card p-5 sm:grid-cols-2">
                <div class="space-y-2">
                    <label for="theme-color" class="text-sm font-medium">رنگ اصلی</label>
                    <div class="flex items-center gap-3">
                        <input
                            id="theme-color"
                            v-model="form.primary_color"
                            type="color"
                            class="size-10 cursor-pointer rounded border border-input"
                            :disabled="!features.custom_colors"
                        />
                        <span class="text-sm text-muted-foreground">{{ form.primary_color }}</span>
                    </div>
                    <p v-if="!features.custom_colors" class="text-xs text-muted-foreground">تغییر رنگ در طرح فعلی فعال نیست.</p>
                </div>

                <div class="space-y-2">
                    <label for="theme-font" class="text-sm font-medium">قلم منو</label>
                    <select id="theme-font" v-model="form.font" class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" :disabled="!features.font_selection">
                        <option value="system">استاندارد</option>
                        <option value="serif">سریف</option>
                        <option value="rounded">گرد</option>
                    </select>
                    <p v-if="!features.font_selection" class="text-xs text-muted-foreground">انتخاب قلم در طرح فعلی فعال نیست.</p>
                </div>

                <div class="space-y-2 sm:col-span-2">
                    <span class="text-sm font-medium">حالت نمایش</span>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.mode" type="radio" value="light" /> روشن</label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.mode" type="radio" value="dark" :disabled="!features.dark_mode" /> تاریک</label>
                    </div>
                    <p v-if="!features.dark_mode" class="text-xs text-muted-foreground">حالت تاریک در طرح فعلی فعال نیست.</p>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <Button type="button" :disabled="form.processing" @click="save">
                    {{ form.processing ? 'در حال ذخیره...' : 'ذخیره تنظیمات' }}
                </Button>
                <p v-if="form.errors.primary_color || form.errors.font || form.errors.mode" class="text-sm text-destructive" role="alert">
                    مقادیر واردشده معتبر نیستند.
                </p>
            </div>
        </main>
    </AppLayout>
</template>
