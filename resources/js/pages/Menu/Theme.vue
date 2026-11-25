<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';

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
                <h2 class="font-semibold">انتخاب قالب</h2>
                <p class="mt-1 text-sm text-muted-foreground">قالب‌های قابل انتخاب بر اساس اشتراک فعال نمایش داده می‌شوند.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="(template, key) in templates"
                        :key="key"
                        class="cursor-pointer rounded-lg border p-4 transition-colors"
                        :class="form.template === key ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50'"
                    >
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
