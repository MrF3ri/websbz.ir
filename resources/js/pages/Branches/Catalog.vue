<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, Save, Store } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type CategorySetting = { public_id: string; name: string; is_active: boolean; is_visible: boolean; sort_order: number };
type ProductSetting = {
    public_id: string; category_name: string; name: string; description: string | null;
    base_price: number; discount_price: number | null; name_override: string | null;
    description_override_enabled: boolean; description_override: string | null;
    base_price_override: number | null; discount_override_mode: 'inherit' | 'set' | 'clear';
    discount_price_override: number | null; is_visible: boolean; is_available: boolean;
    track_inventory: boolean; stock_quantity: number | null; sort_order: number;
};
const props = defineProps<{
    branch: { public_id: string; name: string; is_primary: boolean };
    categories: CategorySetting[];
    products: ProductSetting[];
    canManage: boolean;
}>();
const form = useForm({
    categories: props.categories.map((item) => ({
        public_id: item.public_id, is_visible: item.is_visible, sort_order: item.sort_order,
    })),
    products: props.products.map((item) => ({
        public_id: item.public_id,
        name_override: item.name_override ?? '',
        description_override_enabled: item.description_override_enabled,
        description_override: item.description_override ?? '',
        base_price_override: item.base_price_override === null ? '' : String(item.base_price_override),
        discount_override_mode: item.discount_override_mode,
        discount_price_override: item.discount_price_override === null ? '' : String(item.discount_price_override),
        is_visible: item.is_visible,
        is_available: item.is_available,
        track_inventory: item.track_inventory,
        stock_quantity: item.stock_quantity === null ? '' : String(item.stock_quantity),
        sort_order: item.sort_order,
    })),
});
function submit(): void {
    form.transform((data) => ({
        categories: data.categories,
        products: data.products.map((item) => ({
            ...item,
            name_override: item.name_override.trim() === '' ? null : item.name_override,
            description_override: item.description_override_enabled ? item.description_override : null,
            base_price_override: item.base_price_override === '' ? null : Number(item.base_price_override),
            discount_price_override: item.discount_price_override === '' ? null : Number(item.discount_price_override),
            stock_quantity: item.stock_quantity === '' ? null : Number(item.stock_quantity),
        })),
    })).put(`/business/branches/${props.branch.public_id}/catalog`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`تنظیم منوی ${branch.name}`" />
    <main dir="rtl" class="min-h-full bg-background px-4 py-6 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-2">
                    <a href="/business/branches/manage" class="inline-flex items-center gap-2 text-sm text-primary hover:underline"><ArrowRight class="size-4" /> بازگشت به شعب</a>
                    <div class="flex items-center gap-2 text-sm text-primary"><Store class="size-4" /> تنظیمات منوی شعبه</div>
                    <h1 class="text-2xl font-bold">منوی {{ branch.name }}</h1>
                    <p class="text-sm text-muted-foreground">تنظیمات این صفحه فقط روی همین شعبه اعمال می‌شود؛ اطلاعات اصلی کاتالوگ تغییر نمی‌کند.</p>
                </div>
                <Button v-if="canManage" :disabled="form.processing" class="gap-2" @click="submit"><Spinner v-if="form.processing" /><Save v-else class="size-4" /> ذخیره تنظیمات</Button>
            </header>

            <div v-if="form.errors.catalog || form.errors.products" role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{{ form.errors.catalog || form.errors.products }}</div>
            <section class="space-y-3 rounded-xl border border-border bg-card p-5">
                <div><h2 class="font-semibold">دسته‌بندی‌ها</h2><p class="mt-1 text-xs text-muted-foreground">نمایش و ترتیب دسته‌بندی‌ها در این شعبه</p></div>
                <div v-for="(category, index) in categories" :key="category.public_id" class="grid gap-3 border-t border-border pt-3 sm:grid-cols-[1fr_120px_140px] sm:items-center">
                    <div><p class="font-medium">{{ category.name }}</p><p v-if="!category.is_active" class="text-xs text-muted-foreground">غیرفعال در کاتالوگ اصلی</p></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.categories[index].is_visible" type="checkbox" class="size-4 accent-primary" /> نمایش در شعبه</label>
                    <label class="flex items-center gap-2 text-sm">ترتیب <input v-model.number="form.categories[index].sort_order" type="number" min="0" class="h-9 w-20 rounded-md border border-input bg-background px-2" /></label>
                </div>
                <p v-if="categories.length === 0" class="text-sm text-muted-foreground">هنوز دسته‌بندی‌ای ثبت نشده است.</p>
            </section>

            <section class="space-y-4">
                <div><h2 class="font-semibold">محصولات</h2><p class="mt-1 text-xs text-muted-foreground">مقادیر خالی برای نام و قیمت پایه یعنی «ارث‌بری از کاتالوگ اصلی». برای توضیحات، گزینهٔ Override امکان خالی‌کردن عمدی توضیح را هم می‌دهد.</p></div>
                <article v-for="(product, index) in products" :key="product.public_id" class="space-y-4 rounded-xl border border-border bg-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div><h3 class="font-semibold">{{ product.name }}</h3><p class="text-xs text-muted-foreground">{{ product.category_name }} · قیمت پایه اصلی: {{ product.base_price.toLocaleString('fa-IR') }} تومان</p></div>
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.products[index].is_visible" type="checkbox" class="size-4 accent-primary" /> نمایش محصول</label>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm">نام اختصاصی (خالی = ارث‌بری)<input v-model="form.products[index].name_override" maxlength="160" class="h-10 rounded-md border border-input bg-background px-3" /></label>
                        <label class="grid gap-1 text-sm">قیمت پایه اختصاصی (خالی = ارث‌بری)<input v-model="form.products[index].base_price_override" type="number" min="0" class="h-10 rounded-md border border-input bg-background px-3" /></label>
                        <label class="grid gap-1 text-sm">ترتیب نمایش<input v-model.number="form.products[index].sort_order" type="number" min="0" class="h-10 rounded-md border border-input bg-background px-3" /></label>
                    </div>
                    <div class="space-y-2 rounded-lg bg-muted/30 p-3">
                        <label class="flex items-center gap-2 text-sm font-medium"><input v-model="form.products[index].description_override_enabled" type="checkbox" class="size-4 accent-primary" /> استفاده از توضیحات اختصاصی (حتی اگر خالی باشد)</label>
                        <textarea v-model="form.products[index].description_override" :disabled="!form.products[index].description_override_enabled" rows="2" maxlength="5000" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50" placeholder="توضیحات اختصاصی این شعبه" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="grid gap-1 text-sm">رفتار تخفیف
                            <select v-model="form.products[index].discount_override_mode" class="h-10 rounded-md border border-input bg-background px-3">
                                <option value="inherit">ارث‌بری از کاتالوگ اصلی</option><option value="set">تخفیف اختصاصی</option><option value="clear">حذف تخفیف در این شعبه</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm">قیمت تخفیف اختصاصی
                            <input v-model="form.products[index].discount_price_override" type="number" min="0" :disabled="form.products[index].discount_override_mode !== 'set'" class="h-10 rounded-md border border-input bg-background px-3 disabled:opacity-50" />
                        </label>
                        <label class="flex items-center gap-2 self-end text-sm"><input v-model="form.products[index].is_available" type="checkbox" class="size-4 accent-primary" /> قابل سفارش/دسترس</label>
                    </div>
                    <div class="flex flex-wrap items-center gap-5 border-t border-border pt-3">
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.products[index].track_inventory" type="checkbox" class="size-4 accent-primary" /> شمارش موجودی</label>
                        <label class="flex items-center gap-2 text-sm">تعداد موجودی <input v-model="form.products[index].stock_quantity" type="number" min="0" :disabled="!form.products[index].track_inventory" class="h-9 w-24 rounded-md border border-input bg-background px-2 disabled:opacity-50" /></label>
                    </div>
                </article>
                <p v-if="products.length === 0" class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">هنوز محصولی در کاتالوگ ثبت نشده است.</p>
            </section>
            <div v-if="canManage" class="flex justify-end"><Button :disabled="form.processing" class="gap-2" @click="submit"><Spinner v-if="form.processing" /><Save v-else class="size-4" /> ذخیره تنظیمات منو</Button></div>
        </div>
    </main>
</template>
