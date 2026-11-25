<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    CheckCircle2,
    Package,
    Pencil,
    Plus,
    Search,
    Tags,
    Trash2,
    X,
} from '@lucide/vue';
import { store as storeCategory, update as updateCategory, destroy as destroyCategory, reorder as reorderCategories } from '@/actions/App/Modules/Category/Http/Controllers/CategoryController';
import { store as storeProduct, update as updateProduct, destroy as destroyProduct, reorder as reorderProducts } from '@/actions/App/Modules/Product/Http/Controllers/ProductController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as catalogIndex } from '@/routes/catalog';
import type { Entitlements } from '@/types/entitlements';
import { createQrSvg } from '@/lib/qrCode';

type Category = {
    public_id: string;
    name: string;
    sort_order: number;
    is_active: boolean;
    products_count: number;
};

type ProductImage = { public_id: string; url: string; width: number; height: number };

type Product = {
    public_id: string;
    images: ProductImage[];
    category_public_id: string;
    category_name: string;
    name: string;
    description: string | null;
    base_price: number;
    discount_price: number | null;
    is_available: boolean;
    status: 'draft' | 'published';
    sort_order: number;
};

type ProductPaginator = {
    data: Product[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    menu: {
        public_id: string;
        slug: string;
        title: string;
        status: 'draft' | 'published';
        public_url: string;
    };
    categories: Category[];
    products: ProductPaginator;
    usage: {
        categories: number;
        products: number;
    };
    filters: {
        search: string;
        category: string;
        status: string;
    };
};

type SharedProps = {
    entitlements: Entitlements | null;
};

const props = defineProps<Props>();
const page = usePage<SharedProps>();

const successMessage = ref('');
const serverError = ref('');
const selectedCategory = ref<Category | null>(null);
const selectedProduct = ref<Product | null>(null);
const publishingMenu = ref(false);

const categoryForm = useForm({
    name: '',
    is_active: true,
});

const productForm = useForm({
    category_public_id: props.categories[0]?.public_id ?? '',
    name: '',
    description: '',
    base_price: 0,
    discount_price: null as number | null,
    is_available: true,
    status: 'draft' as 'draft' | 'published',
});

const filters = ref({
    search: props.filters.search,
    category: props.filters.category,
    status: props.filters.status,
});

const tomanFormatter = new Intl.NumberFormat('fa-IR', {
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('fa-IR');

const entitlements = computed(() => page.props.entitlements);
const canDiscount = computed(() => entitlements.value?.features.product_discount === true);
const canToggleAvailability = computed(() => entitlements.value?.features.product_availability_toggle === true);
const imageLimit = computed(() => entitlements.value?.features.max_product_images ?? 0);
const canUploadImages = computed(() => imageLimit.value === -1 || imageLimit.value > 0);

const qrSvg = computed(() => (
    props.menu.status === 'published' ? createQrSvg(props.menu.public_url) : null
));

const qrDataUrl = computed(() => (
    qrSvg.value
        ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(qrSvg.value)}`
        : ''
));

const categoryLimitHint = computed(() => limitHint(
    'max_categories',
    props.usage.categories,
));

const productLimitHint = computed(() => limitHint(
    'max_products',
    props.usage.products,
));

function limitHint(feature: string, used: number): string {
    const limit = entitlements.value?.features[feature];

    if (typeof limit !== 'number') {
        return `${numberFormatter.format(used)} مورد ثبت شده`;
    }

    if (limit === -1) {
        return `${numberFormatter.format(used)} مورد · نامحدود`;
    }

    return `${numberFormatter.format(used)} از ${numberFormatter.format(limit)}`;
}

function formatToman(value: number | null): string {
    return value === null ? '—' : `${tomanFormatter.format(value)} تومان`;
}

function submitCategory(): void {
    serverError.value = '';
    successMessage.value = '';

    const action = selectedCategory.value
        ? updateCategory(selectedCategory.value.public_id)
        : storeCategory();

    categoryForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = selectedCategory.value
                ? 'دسته‌بندی ویرایش شد.'
                : 'دسته‌بندی ایجاد شد.';
            selectedCategory.value = null;
            categoryForm.reset();
            categoryForm.is_active = true;
        },
    });
}

function editCategory(category: Category): void {
    selectedCategory.value = category;
    categoryForm.name = category.name;
    categoryForm.is_active = category.is_active;
}

function cancelCategoryEdit(): void {
    selectedCategory.value = null;
    categoryForm.reset();
    categoryForm.is_active = true;
    categoryForm.clearErrors();
}

function deleteCategory(category: Category): void {
    if (!window.confirm(`دسته‌بندی «${category.name}» حذف شود؟ دسته‌بندی دارای محصول قابل حذف نیست.`)) {
        return;
    }

    serverError.value = '';
    router.delete(destroyCategory(category.public_id).url, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'دسته‌بندی حذف شد.';
        },
        onError: (errors) => {
            serverError.value = errors.category ?? 'حذف دسته‌بندی انجام نشد.';
        },
    });
}

function moveCategory(index: number, direction: -1 | 1): void {
    const neighbor = props.categories[index + direction];
    const current = props.categories[index];

    if (!neighbor || !current) {
        return;
    }

    router.put(reorderCategories().url, {
        category_public_ids: [neighbor.public_id, current.public_id],
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'ترتیب دسته‌بندی‌ها ذخیره شد.';
        },
    });
}

function submitProduct(): void {
    serverError.value = '';
    successMessage.value = '';

    const action = selectedProduct.value
        ? updateProduct(selectedProduct.value.public_id)
        : storeProduct();

    productForm.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = selectedProduct.value
                ? 'محصول ویرایش شد.'
                : 'محصول ایجاد شد.';
            selectedProduct.value = null;
            productForm.reset();
            productForm.category_public_id = props.categories[0]?.public_id ?? '';
            productForm.base_price = 0;
            productForm.discount_price = null;
            productForm.is_available = true;
            productForm.status = 'draft';
        },
    });
}

function editProduct(product: Product): void {
    selectedProduct.value = product;
    productForm.category_public_id = product.category_public_id;
    productForm.name = product.name;
    productForm.description = product.description ?? '';
    productForm.base_price = product.base_price;
    productForm.discount_price = product.discount_price;
    productForm.is_available = product.is_available;
    productForm.status = product.status;
}

function cancelProductEdit(): void {
    selectedProduct.value = null;
    productForm.reset();
    productForm.category_public_id = props.categories[0]?.public_id ?? '';
    productForm.base_price = 0;
    productForm.discount_price = null;
    productForm.is_available = true;
    productForm.status = 'draft';
    productForm.clearErrors();
}

function uploadProductImage(product: Product, event: Event): void {
    const input = event.target;
    if (!(input instanceof HTMLInputElement)) return;
    const file = input.files?.[0];
    if (!file) return;

    serverError.value = '';
    router.post(`/catalog/products/${product.public_id}/images`, { image: file }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { successMessage.value = 'تصویر محصول بارگذاری شد.'; },
        onError: (errors) => { serverError.value = errors.image ?? 'بارگذاری تصویر انجام نشد.'; },
        onFinish: () => { input.value = ''; },
    });
}

function deleteProductImage(product: Product, image: ProductImage): void {
    if (!window.confirm('این تصویر از محصول حذف شود؟')) return;

    router.delete(`/catalog/products/${product.public_id}/images/${image.public_id}`, {
        preserveScroll: true,
        onSuccess: () => { successMessage.value = 'تصویر محصول حذف شد.'; },
        onError: () => { serverError.value = 'حذف تصویر انجام نشد.'; },
    });
}

function deleteProduct(product: Product): void {
    if (!window.confirm(`محصول «${product.name}» حذف شود؟`)) {
        return;
    }

    router.delete(destroyProduct(product.public_id).url, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'محصول حذف شد.';
        },
    });
}

function moveProduct(product: Product, direction: -1 | 1): void {
    const index = props.products.data.findIndex((item) => item.public_id === product.public_id);
    const neighbor = props.products.data[index + direction];

    if (!neighbor || neighbor.category_public_id !== product.category_public_id) {
        return;
    }

    router.put(reorderProducts(product.category_public_id).url, {
        product_public_ids: [neighbor.public_id, product.public_id],
    }, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'ترتیب محصولات ذخیره شد.';
        },
    });
}

function publishMenu(): void {
    serverError.value = '';
    publishingMenu.value = true;

    router.put('/catalog/menu/publish', {}, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'منوی کسب‌وکار منتشر شد.';
        },
        onError: (errors) => {
            serverError.value = errors.menu ?? 'انتشار منو انجام نشد.';
        },
        onFinish: () => {
            publishingMenu.value = false;
        },
    });
}

function unpublishMenu(): void {
    serverError.value = '';
    publishingMenu.value = true;

    router.put('/catalog/menu/unpublish', {}, {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'انتشار منو متوقف شد.';
        },
        onFinish: () => {
            publishingMenu.value = false;
        },
    });
}

function applyFilters(): void {
    router.get(catalogIndex().url, {
        search: filters.value.search || undefined,
        category: filters.value.category || undefined,
        status: filters.value.status || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function goToPage(pageNumber: number): void {
    router.get(catalogIndex().url, {
        ...filters.value,
        page: pageNumber,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="مدیریت منو" />

    <main dir="rtl" class="min-h-full bg-background px-4 py-6 text-right sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl space-y-7">
            <header class="space-y-2">
                <p class="text-sm font-medium text-primary">{{ menu.title }} · مدیریت کسب‌وکار</p>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    مدیریت منو و محصولات
                </h1>
                <p class="text-sm leading-6 text-muted-foreground">
                    دسته‌بندی‌ها و محصولات منوی اصلی کسب‌وکار خود را مدیریت کنید.
                </p>
            </header>

            <section class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-foreground">وضعیت منوی عمومی</span>
                        <span
                            class="rounded-full px-2 py-1 text-xs"
                            :class="menu.status === 'published' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                        >
                            {{ menu.status === 'published' ? 'منتشرشده' : 'پیش‌نویس' }}
                        </span>
                    </div>
                    <p class="text-xs leading-5 text-muted-foreground">
                        {{ menu.status === 'published' ? 'مشتریان می‌توانند منوی منتشرشده را بدون ورود مشاهده کنند.' : 'برای نمایش منو به مشتریان، آن را منتشر کنید. حداقل یک محصول موجود و منتشرشده لازم است.' }}
                    </p>
                    <a
                        v-if="menu.status === 'published'"
                        :href="menu.public_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-primary underline underline-offset-4"
                    >
                        مشاهده منوی عمومی
                    </a>
                </div>
                <Button
                    v-if="menu.status === 'draft'"
                    type="button"
                    :disabled="publishingMenu"
                    @click="publishMenu"
                >
                    {{ publishingMenu ? 'در حال انتشار...' : 'انتشار منو' }}
                </Button>
                <Button
                    v-else
                    type="button"
                    variant="outline"
                    :disabled="publishingMenu"
                    @click="unpublishMenu"
                >
                    {{ publishingMenu ? 'در حال ذخیره...' : 'توقف انتشار' }}
                </Button>
            </section>

            <div
                v-if="successMessage"
                role="status"
                class="flex items-center gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-4 shrink-0" />
                {{ successMessage }}
                <button type="button" class="mr-auto" aria-label="بستن پیام" @click="successMessage = ''">
                    <X class="size-4" />
                </button>
            </div>

            <div
                v-if="serverError"
                role="alert"
                class="rounded-lg border border-destructive/20 bg-destructive/5 px-4 py-3 text-sm text-destructive"
            >
                {{ serverError }}
            </div>

            <section
                v-if="qrDataUrl"
                class="flex flex-col items-center gap-4 rounded-xl border border-border bg-card p-5 shadow-sm sm:flex-row sm:justify-between"
            >
                <div class="space-y-2 text-center sm:text-right">
                    <h2 class="font-semibold text-foreground">QR منوی عمومی</h2>
                    <p class="max-w-md text-sm leading-6 text-muted-foreground">
                        مشتریان با اسکن این کد مستقیماً منوی منتشرشده را باز می‌کنند. کد در همین مرورگر ساخته می‌شود و نشانی منو به سرویس خارجی ارسال نمی‌شود.
                    </p>
                    <a
                        :href="qrDataUrl"
                        :download="`menu-${menu.slug}-qr.svg`"
                        class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
                    >
                        دانلود QR با کیفیت برداری (SVG)
                    </a>
                </div>
                <img
                    :src="qrDataUrl"
                    alt="کد QR منوی عمومی"
                    width="192"
                    height="192"
                    class="size-48 rounded-lg border border-border bg-white p-2"
                />
            </section>

            <section class="grid gap-4 sm:grid-cols-2">
                <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Tags class="size-4" />
                            دسته‌بندی‌ها
                        </div>
                        <span class="text-xs text-muted-foreground">{{ categoryLimitHint }}</span>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-foreground">
                        {{ numberFormatter.format(categories.length) }}
                    </p>
                </article>

                <article class="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                            <Package class="size-4" />
                            محصولات
                        </div>
                        <span class="text-xs text-muted-foreground">{{ productLimitHint }}</span>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums text-foreground">
                        {{ numberFormatter.format(products.total) }}
                    </p>
                </article>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                <article class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border px-5 py-4">
                        <h2 class="font-semibold text-foreground">
                            {{ selectedCategory ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی' }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            هر دسته‌بندی به منوی اصلی همین کسب‌وکار تعلق دارد.
                        </p>
                    </div>

                    <form class="space-y-4 p-5" @submit.prevent="submitCategory">
                        <div class="grid gap-2">
                            <Label for="category-name">نام دسته‌بندی</Label>
                            <Input
                                id="category-name"
                                v-model="categoryForm.name"
                                required
                                maxlength="120"
                                placeholder="مثلاً نوشیدنی‌های گرم"
                                :disabled="categoryForm.processing"
                                :aria-invalid="Boolean(categoryForm.errors.name)"
                            />
                            <p v-if="categoryForm.errors.name" class="text-sm text-destructive" role="alert">
                                {{ categoryForm.errors.name }}
                            </p>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input
                                v-model="categoryForm.is_active"
                                type="checkbox"
                                class="size-4 rounded border-input accent-primary"
                                :disabled="categoryForm.processing"
                            />
                            دسته‌بندی فعال باشد
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="categoryForm.processing">
                                <Plus v-if="!selectedCategory" class="size-4" />
                                {{ categoryForm.processing ? 'در حال ذخیره...' : selectedCategory ? 'ذخیره تغییرات' : 'افزودن دسته‌بندی' }}
                            </Button>
                            <Button
                                v-if="selectedCategory"
                                type="button"
                                variant="outline"
                                @click="cancelCategoryEdit"
                            >
                                انصراف
                            </Button>
                        </div>
                    </form>

                    <div class="border-t border-border px-5 py-4">
                        <h3 class="mb-3 text-sm font-semibold text-foreground">دسته‌بندی‌های ثبت‌شده</h3>

                        <p v-if="categories.length === 0" class="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                            هنوز دسته‌بندی‌ای ایجاد نشده است.
                        </p>

                        <ul v-else class="space-y-2">
                            <li
                                v-for="(category, index) in categories"
                                :key="category.public_id"
                                class="flex items-center gap-2 rounded-lg border border-border p-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-foreground">{{ category.name }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ numberFormatter.format(category.products_count) }} محصول
                                        ·
                                        {{ category.is_active ? 'فعال' : 'غیرفعال' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        :disabled="index === 0"
                                        aria-label="انتقال دسته‌بندی به بالا"
                                        @click="moveCategory(index, -1)"
                                    >
                                        <ChevronUp class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        :disabled="index === categories.length - 1"
                                        aria-label="انتقال دسته‌بندی به پایین"
                                        @click="moveCategory(index, 1)"
                                    >
                                        <ChevronDown class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        aria-label="ویرایش دسته‌بندی"
                                        @click="editCategory(category)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon-sm"
                                        variant="ghost"
                                        aria-label="حذف دسته‌بندی"
                                        @click="deleteCategory(category)"
                                    >
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border px-5 py-4">
                        <h2 class="font-semibold text-foreground">
                            {{ selectedProduct ? 'ویرایش محصول' : 'افزودن محصول' }}
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            قیمت‌ها به تومان و به‌صورت عدد صحیح ذخیره می‌شوند.
                        </p>
                    </div>

                    <form class="grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="submitProduct">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="product-name">نام محصول</Label>
                            <Input
                                id="product-name"
                                v-model="productForm.name"
                                required
                                maxlength="180"
                                placeholder="مثلاً قهوه اسپرسو"
                                :disabled="productForm.processing"
                                :aria-invalid="Boolean(productForm.errors.name)"
                            />
                            <p v-if="productForm.errors.name" class="text-sm text-destructive" role="alert">
                                {{ productForm.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="product-category">دسته‌بندی</Label>
                            <select
                                id="product-category"
                                v-model="productForm.category_public_id"
                                required
                                :disabled="productForm.processing || categories.length === 0"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            >
                                <option value="" disabled>انتخاب دسته‌بندی</option>
                                <option v-for="category in categories" :key="category.public_id" :value="category.public_id">
                                    {{ category.name }}
                                </option>
                            </select>
                            <p v-if="productForm.errors.category_public_id" class="text-sm text-destructive" role="alert">
                                {{ productForm.errors.category_public_id }}
                            </p>
                            <p v-if="categories.length === 0" class="text-xs text-destructive">
                                ابتدا یک دسته‌بندی ایجاد کنید.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="product-price">قیمت پایه (تومان)</Label>
                            <Input
                                id="product-price"
                                v-model.number="productForm.base_price"
                                type="number"
                                min="0"
                                step="1"
                                required
                                dir="ltr"
                                class="text-left"
                                :disabled="productForm.processing"
                            />
                            <p v-if="productForm.errors.base_price" class="text-sm text-destructive" role="alert">
                                {{ productForm.errors.base_price }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="product-discount">قیمت با تخفیف (تومان)</Label>
                            <Input
                                id="product-discount"
                                :model-value="productForm.discount_price ?? ''"
                                @update:model-value="(value) => { productForm.discount_price = value === '' ? null : Number(value) }"
                                type="number"
                                min="0"
                                step="1"
                                dir="ltr"
                                class="text-left"
                                :disabled="productForm.processing || (!canDiscount && productForm.discount_price === null)"
                            />
                            <p v-if="!canDiscount" class="text-xs text-muted-foreground">
                                ثبت تخفیف در طرح فعلی فعال نیست.
                            </p>
                            <p v-if="productForm.errors.discount_price" class="text-sm text-destructive" role="alert">
                                {{ productForm.errors.discount_price }}
                            </p>
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="product-description">توضیحات</Label>
                            <textarea
                                id="product-description"
                                v-model="productForm.description"
                                rows="3"
                                maxlength="5000"
                                class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="توضیح کوتاهی درباره محصول..."
                                :disabled="productForm.processing"
                            />
                            <p v-if="productForm.errors.description" class="text-sm text-destructive" role="alert">
                                {{ productForm.errors.description }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="product-status">وضعیت انتشار</Label>
                            <select
                                id="product-status"
                                v-model="productForm.status"
                                class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                :disabled="productForm.processing"
                            >
                                <option value="draft">پیش‌نویس</option>
                                <option value="published">منتشرشده</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-2 self-end pb-2 text-sm text-foreground">
                            <input
                                v-model="productForm.is_available"
                                type="checkbox"
                                class="size-4 rounded border-input accent-primary"
                                :disabled="productForm.processing || (!canToggleAvailability && productForm.is_available)"
                            />
                            محصول موجود است
                        </label>
                        <p v-if="!canToggleAvailability" class="text-xs text-muted-foreground sm:col-span-2">
                            تغییر وضعیت موجودی در طرح فعلی فعال نیست.
                        </p>

                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <Button
                                type="submit"
                                :disabled="productForm.processing || categories.length === 0"
                            >
                                <Plus v-if="!selectedProduct" class="size-4" />
                                {{ productForm.processing ? 'در حال ذخیره...' : selectedProduct ? 'ذخیره تغییرات' : 'افزودن محصول' }}
                            </Button>
                            <Button
                                v-if="selectedProduct"
                                type="button"
                                variant="outline"
                                @click="cancelProductEdit"
                            >
                                انصراف
                            </Button>
                        </div>
                    </form>
                </article>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex flex-col gap-4 border-b border-border px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="font-semibold text-foreground">فهرست محصولات</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            جست‌وجو، فیلتر، ویرایش و مرتب‌سازی محصولات
                        </p>
                    </div>

                    <form class="grid gap-2 sm:grid-cols-3" @submit.prevent="applyFilters">
                        <div class="relative">
                            <Search class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="filters.search"
                                class="pr-9"
                                placeholder="جست‌وجوی نام محصول"
                                aria-label="جست‌وجوی محصول"
                            />
                        </div>
                        <select
                            v-model="filters.category"
                            aria-label="فیلتر دسته‌بندی"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">همه دسته‌بندی‌ها</option>
                            <option v-for="category in categories" :key="category.public_id" :value="category.public_id">
                                {{ category.name }}
                            </option>
                        </select>
                        <div class="flex gap-2">
                            <select
                                v-model="filters.status"
                                aria-label="فیلتر وضعیت انتشار"
                                class="h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">همه وضعیت‌ها</option>
                                <option value="draft">پیش‌نویس</option>
                                <option value="published">منتشرشده</option>
                            </select>
                            <Button type="submit" variant="outline" size="icon" aria-label="اعمال فیلترها">
                                <Search class="size-4" />
                            </Button>
                        </div>
                    </form>
                </div>

                <div v-if="products.data.length === 0" class="flex min-h-52 flex-col items-center justify-center px-6 py-10 text-center">
                    <div class="mb-3 rounded-full bg-muted p-3 text-muted-foreground">
                        <Package class="size-6" />
                    </div>
                    <p class="font-medium text-foreground">محصولی برای نمایش وجود ندارد.</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        محصول جدیدی ثبت کنید یا فیلترهای جست‌وجو را تغییر دهید.
                    </p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 text-right font-medium">محصول</th>
                                <th class="px-4 py-3 text-right font-medium">دسته‌بندی</th>
                                <th class="px-4 py-3 text-right font-medium">قیمت</th>
                                <th class="px-4 py-3 text-right font-medium">وضعیت</th>
                                <th class="px-4 py-3 text-right font-medium">موجودی</th>
                                <th class="px-4 py-3 text-left font-medium">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="(product, index) in products.data" :key="product.public_id">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-foreground">{{ product.name }}</p>
                                    <p v-if="product.description" class="mt-1 max-w-xs truncate text-xs text-muted-foreground">
                                        {{ product.description }}
                                    </p>
                                    <div v-if="product.images.length" class="mt-2 flex items-center gap-2">
                                        <div v-for="image in product.images" :key="image.public_id" class="relative">
                                            <img :src="image.url" :alt="`تصویر ${product.name}`" width="48" height="48" class="size-12 rounded-md object-cover" />
                                            <button type="button" class="absolute -left-1 -top-1 rounded-full bg-destructive p-0.5 text-white" :aria-label="`حذف تصویر ${product.name}`" @click="deleteProductImage(product, image)">
                                                <X class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <label v-if="canUploadImages && (imageLimit === -1 || product.images.length < imageLimit)" class="mt-2 inline-flex cursor-pointer items-center text-xs text-primary underline underline-offset-4">
                                        افزودن تصویر
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="uploadProductImage(product, $event)" />
                                    </label>
                                    <p v-if="imageLimit === 0" class="mt-2 text-xs text-muted-foreground">تصویر محصول در طرح فعلی فعال نیست.</p>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ product.category_name }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium tabular-nums text-foreground">{{ formatToman(product.base_price) }}</p>
                                    <p v-if="product.discount_price !== null" class="mt-1 text-xs tabular-nums text-emerald-600">
                                        {{ formatToman(product.discount_price) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs" :class="product.status === 'published' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-muted text-muted-foreground'">
                                        {{ product.status === 'published' ? 'منتشرشده' : 'پیش‌نویس' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ product.is_available ? 'موجود' : 'ناموجود' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            :disabled="index === 0 || products.data[index - 1]?.category_public_id !== product.category_public_id"
                                            aria-label="انتقال محصول به بالا"
                                            @click="moveProduct(product, -1)"
                                        >
                                            <ChevronUp class="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            :disabled="index === products.data.length - 1 || products.data[index + 1]?.category_public_id !== product.category_public_id"
                                            aria-label="انتقال محصول به پایین"
                                            @click="moveProduct(product, 1)"
                                        >
                                            <ChevronDown class="size-4" />
                                        </Button>
                                        <Button type="button" size="icon-sm" variant="ghost" aria-label="ویرایش محصول" @click="editProduct(product)">
                                            <Pencil class="size-4" />
                                        </Button>
                                        <Button type="button" size="icon-sm" variant="ghost" aria-label="حذف محصول" @click="deleteProduct(product)">
                                            <Trash2 class="size-4 text-destructive" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="products.last_page > 1" class="flex items-center justify-between border-t border-border px-5 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="products.current_page <= 1"
                        @click="goToPage(products.current_page - 1)"
                    >
                        قبلی
                    </Button>
                    <span class="text-xs tabular-nums text-muted-foreground">
                        صفحه {{ numberFormatter.format(products.current_page) }}
                        از {{ numberFormatter.format(products.last_page) }}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="products.current_page >= products.last_page"
                        @click="goToPage(products.current_page + 1)"
                    >
                        بعدی
                    </Button>
                </div>
            </section>
        </div>
    </main>
</template>
