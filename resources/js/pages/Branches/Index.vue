<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, Clock3, MapPin, Plus, QrCode, Save, Store, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { createQrSvg } from '@/lib/qrCode';

type Weekday = 'sat' | 'sun' | 'mon' | 'tue' | 'wed' | 'thu' | 'fri';
type Period = { open: string; close: string };
type OpeningHours = Partial<Record<Weekday, Period[]>>;
type Branch = {
    public_id: string;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    is_primary: boolean;
    phone: string | null;
    email: string | null;
    address: string | null;
    latitude: string | number | null;
    longitude: string | number | null;
    timezone: string;
    opening_hours: OpeningHours | null;
    public_url: string | null;
};
type DayState = { closed: boolean; first: Period; secondEnabled: boolean; second: Period };

const props = defineProps<{ branches: Branch[]; canManage: boolean }>();
const days: { key: Weekday; label: string }[] = [
    { key: 'sat', label: 'شنبه' }, { key: 'sun', label: 'یکشنبه' },
    { key: 'mon', label: 'دوشنبه' }, { key: 'tue', label: 'سه‌شنبه' },
    { key: 'wed', label: 'چهارشنبه' }, { key: 'thu', label: 'پنجشنبه' },
    { key: 'fri', label: 'جمعه' },
];
const selected = ref<Branch | null>(null);
const showForm = ref(false);
const hasSchedule = ref(false);
const successMessage = ref('');
const serverError = ref('');
const numberFormatter = new Intl.NumberFormat('fa-IR');

function blankDay(): DayState {
    return {
        closed: false,
        first: { open: '09:00', close: '22:00' },
        secondEnabled: false,
        second: { open: '17:00', close: '22:00' },
    };
}
function blankSchedule(): Record<Weekday, DayState> {
    return Object.fromEntries(days.map((day) => [day.key, blankDay()])) as Record<Weekday, DayState>;
}
const schedule = reactive(blankSchedule());
const form = useForm({
    name: '', slug: '', phone: '', email: '', address: '',
    latitude: '', longitude: '', timezone: 'Asia/Tehran',
    opening_hours: null as OpeningHours | null,
});

const formTitle = computed(() => selected.value ? 'ویرایش شعبه' : 'افزودن شعبه');

function openingHoursError(path: string): string | undefined {
    const error = Object.entries(form.errors).find(([key]) => key === path)?.[1];

    return typeof error === 'string' ? error : undefined;
}

function hydrateSchedule(hours: OpeningHours | null): void {
    const fresh = blankSchedule();
    hasSchedule.value = hours !== null;
    for (const day of days) {
        const periods = hours?.[day.key] ?? [];
        fresh[day.key].closed = periods.length === 0;
        if (periods[0]) fresh[day.key].first = { ...periods[0] };
        fresh[day.key].secondEnabled = Boolean(periods[1]);
        if (periods[1]) fresh[day.key].second = { ...periods[1] };
    }
    Object.assign(schedule, fresh);
}
function fillForm(branch: Branch | null): void {
    selected.value = branch;
    form.clearErrors();
    form.name = branch?.name ?? '';
    form.slug = branch?.slug ?? '';
    form.phone = branch?.phone ?? '';
    form.email = branch?.email ?? '';
    form.address = branch?.address ?? '';
    form.latitude = branch?.latitude?.toString() ?? '';
    form.longitude = branch?.longitude?.toString() ?? '';
    form.timezone = branch?.timezone ?? 'Asia/Tehran';
    hydrateSchedule(branch?.opening_hours ?? null);
    showForm.value = true;
}
function serializeSchedule(): OpeningHours | null {
    if (!hasSchedule.value) return null;
    const result: OpeningHours = {};
    for (const day of days) {
        const state = schedule[day.key];
        result[day.key] = state.closed ? [] : [
            { ...state.first },
            ...(state.secondEnabled ? [{ ...state.second }] : []),
        ];
    }
    return result;
}
function submit(): void {
    successMessage.value = '';
    serverError.value = '';
    form.opening_hours = serializeSchedule();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = selected.value ? 'اطلاعات شعبه به‌روزرسانی شد.' : 'شعبه جدید ایجاد شد.';
            showForm.value = false;
            selected.value = null;
            form.reset();
            hydrateSchedule(null);
        },
        onError: () => {
            serverError.value = 'لطفاً خطاهای فرم را بررسی کنید.';
        },
    };
    if (selected.value) {
        form.put(`/business/branches/${selected.value.public_id}`, options);
    } else {
        form.post('/business/branches', options);
    }
}
function setStatus(branch: Branch): void {
    successMessage.value = '';
    serverError.value = '';
    const action = branch.status === 'active' ? 'deactivate' : 'activate';
    router.post(`/business/branches/${branch.public_id}/${action}`, {}, {
        preserveScroll: true,
        onSuccess: () => { successMessage.value = action === 'activate' ? 'شعبه فعال شد.' : 'شعبه غیرفعال شد.'; },
        onError: (errors) => { serverError.value = errors.branch ?? 'تغییر وضعیت شعبه انجام نشد.'; },
    });
}
function closeForm(): void {
    showForm.value = false;
    selected.value = null;
    form.reset();
    form.clearErrors();
    hydrateSchedule(null);
}
function daySummary(branch: Branch): string {
    if (!branch.opening_hours) return 'ساعت کاری ثبت نشده';
    const openDays = Object.values(branch.opening_hours).filter((periods) => periods && periods.length > 0).length;
    return openDays === 0 ? 'تمام روزها تعطیل' : `${numberFormatter.format(openDays)} روز دارای ساعت کاری`;
}

const branchQrDataUrls = computed<Record<string, string>>(() => {
    const urls: Record<string, string> = {};

    for (const branch of props.branches) {
        if (!branch.public_url) continue;

        const svg = createQrSvg(branch.public_url);
        urls[branch.public_id] = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    return urls;
});

function branchQrDataUrl(branch: Branch): string {
    return branchQrDataUrls.value[branch.public_id] ?? '';
}
</script>

<template>
    <Head title="مدیریت شعب" />
    <main dir="rtl" class="min-h-full bg-background px-4 py-6 text-right sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-7">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm font-medium text-primary"><Store class="size-4" /> مدیریت شعب</div>
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">شعبه‌های کسب‌وکار</h1>
                    <p class="max-w-2xl text-sm leading-6 text-muted-foreground">اطلاعات، وضعیت و ساعت کاری هر شعبه را مستقل مدیریت کنید. تغییرات هر شعبه روی شعبه‌های دیگر اثر نمی‌گذارد.</p>
                </div>
                <Button v-if="canManage" class="h-11 gap-2" @click="fillForm(null)"><Plus class="size-4" /> افزودن شعبه</Button>
            </header>

            <div v-if="successMessage" role="status" class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">{{ successMessage }}</div>
            <div v-if="serverError" role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">{{ serverError }}</div>

            <section v-if="showForm && canManage" class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b border-border px-5 py-4">
                    <div><h2 class="font-semibold">{{ formTitle }}</h2><p class="mt-1 text-xs text-muted-foreground">اطلاعات شعبه و تنظیمات منوی همان شعبه</p></div>
                    <Button variant="ghost" size="icon" aria-label="بستن فرم" @click="closeForm"><X class="size-4" /></Button>
                </div>
                <form class="space-y-6 p-5 sm:p-6" @submit.prevent="submit">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2"><Label for="branch-name">نام شعبه *</Label><Input id="branch-name" v-model="form.name" required maxlength="160" placeholder="مثلاً شعبه مرکزی" :disabled="form.processing" /><p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p></div>
                        <div class="grid gap-2"><Label for="branch-slug">شناسه نشانی (انگلیسی) *</Label><Input id="branch-slug" v-model="form.slug" required maxlength="80" dir="ltr" placeholder="central" :disabled="form.processing || selected !== null" /><p v-if="selected" class="text-xs text-muted-foreground">شناسه نشانی پس از ایجاد ثابت می‌ماند تا QRهای چاپ‌شده معتبر بمانند.</p><p v-if="form.errors.slug" class="text-sm text-destructive">{{ form.errors.slug }}</p></div>
                        <div class="grid gap-2"><Label for="branch-phone">تلفن</Label><Input id="branch-phone" v-model="form.phone" type="tel" dir="ltr" placeholder="+982112345678" :disabled="form.processing" /><p v-if="form.errors.phone" class="text-sm text-destructive">{{ form.errors.phone }}</p></div>
                        <div class="grid gap-2"><Label for="branch-email">ایمیل</Label><Input id="branch-email" v-model="form.email" type="email" dir="ltr" placeholder="branch@example.com" :disabled="form.processing" /><p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p></div>
                        <div class="grid gap-2 sm:col-span-2"><Label for="branch-address">نشانی</Label><textarea id="branch-address" v-model="form.address" rows="2" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" :disabled="form.processing" /><p v-if="form.errors.address" class="text-sm text-destructive">{{ form.errors.address }}</p></div>
                        <div class="grid gap-2"><Label for="branch-latitude">عرض جغرافیایی</Label><Input id="branch-latitude" v-model="form.latitude" dir="ltr" inputmode="decimal" placeholder="35.6892" :disabled="form.processing" /><p v-if="form.errors.latitude" class="text-sm text-destructive">{{ form.errors.latitude }}</p></div>
                        <div class="grid gap-2"><Label for="branch-longitude">طول جغرافیایی</Label><Input id="branch-longitude" v-model="form.longitude" dir="ltr" inputmode="decimal" placeholder="51.3890" :disabled="form.processing" /><p v-if="form.errors.longitude" class="text-sm text-destructive">{{ form.errors.longitude }}</p></div>
                        <div class="grid gap-2 sm:col-span-2"><Label for="branch-timezone">منطقه زمانی (IANA)</Label><Input id="branch-timezone" v-model="form.timezone" dir="ltr" placeholder="Asia/Tehran" :disabled="form.processing" /><p class="text-xs text-muted-foreground">شناسه منطقه زمانی شعبه؛ مثلاً Asia/Tehran یا Europe/Istanbul</p><p v-if="form.errors.timezone" class="text-sm text-destructive">{{ form.errors.timezone }}</p></div>
                    </div>

                    <section class="space-y-4 rounded-lg border border-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2"><Clock3 class="size-5 text-primary" /><div><h3 class="font-semibold">ساعت کاری هفتگی</h3><p class="text-xs text-muted-foreground">اختیاری؛ برای هر روز حداکثر دو بازه تعریف کنید.</p></div></div>
                            <label class="flex items-center gap-2 text-sm"><input v-model="hasSchedule" type="checkbox" class="size-4 accent-primary" /> ثبت ساعت کاری</label>
                        </div>
                        <div v-if="hasSchedule" class="space-y-3">
                            <div v-for="day in days" :key="day.key" class="grid gap-3 border-b border-border pb-3 last:border-0 sm:grid-cols-[90px_90px_1fr] sm:items-center">
                                <span class="text-sm font-medium">{{ day.label }}</span>
                                <label class="flex items-center gap-2 text-sm text-muted-foreground"><input v-model="schedule[day.key].closed" type="checkbox" class="size-4 accent-primary" /> تعطیل</label>
                                <div v-if="!schedule[day.key].closed" class="grid gap-2 sm:grid-cols-2">
                                    <div class="flex items-center gap-2"><span class="w-14 shrink-0 text-xs text-muted-foreground">بازه اول</span><input v-model="schedule[day.key].first.open" type="time" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm" /><span>تا</span><input v-model="schedule[day.key].first.close" type="time" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm" /></div>
                                    <div class="flex items-center gap-2"><label class="flex shrink-0 items-center gap-1 text-xs text-muted-foreground"><input v-model="schedule[day.key].secondEnabled" type="checkbox" class="size-4 accent-primary" /> بازه دوم</label><template v-if="schedule[day.key].secondEnabled"><input v-model="schedule[day.key].second.open" type="time" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm" /><span>تا</span><input v-model="schedule[day.key].second.close" type="time" class="h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm" /></template></div>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">در این روز تعطیل است</span>
                                <div v-if="openingHoursError(`opening_hours.${day.key}.0.close`)" class="text-xs text-destructive sm:col-start-3">{{ openingHoursError(`opening_hours.${day.key}.0.close`) }}</div>
                                <div v-if="openingHoursError(`opening_hours.${day.key}.1.open`)" class="text-xs text-destructive sm:col-start-3">{{ openingHoursError(`opening_hours.${day.key}.1.open`) }}</div>
                            </div>
                        </div>
                        <p v-if="form.errors.opening_hours" class="text-sm text-destructive">{{ form.errors.opening_hours }}</p>
                    </section>

                    <div class="flex flex-col-reverse gap-3 border-t border-border pt-5 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" :disabled="form.processing" @click="closeForm">انصراف</Button>
                        <Button type="submit" class="gap-2" :disabled="form.processing"><Spinner v-if="form.processing" /><Save v-else class="size-4" /> ذخیره شعبه</Button>
                    </div>
                </form>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <article v-for="branch in branches" :key="branch.public_id" class="rounded-xl border border-border bg-card p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3"><div class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"><Building2 class="size-5" /></div><div><h2 class="font-semibold">{{ branch.name }}</h2><p dir="ltr" class="mt-1 text-xs text-muted-foreground">{{ branch.slug }}</p></div></div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="branch.status === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'">{{ branch.status === 'active' ? 'فعال' : 'غیرفعال' }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs"><span v-if="branch.is_primary" class="rounded-md bg-primary/10 px-2 py-1 text-primary">شعبه اصلی</span><span class="rounded-md bg-muted px-2 py-1 text-muted-foreground">{{ daySummary(branch) }}</span></div>
                    <p v-if="branch.address" class="mt-3 flex items-start gap-2 text-sm text-muted-foreground"><MapPin class="mt-0.5 size-4 shrink-0" />{{ branch.address }}</p>
                    <p v-if="branch.phone" dir="ltr" class="mt-2 text-left text-sm text-muted-foreground">{{ branch.phone }}</p>
                    <div v-if="branch.public_url" class="mt-4 flex flex-col items-center gap-3 rounded-lg border border-border bg-muted/20 p-3 sm:flex-row sm:items-center">
                        <img :src="branchQrDataUrl(branch)" :alt="'QR منوی شعبه ' + branch.name" width="112" height="112" class="size-28 rounded-md border border-border bg-white p-1" />
                        <div class="min-w-0 flex-1 space-y-2 text-center sm:text-right">
                            <div class="flex items-center justify-center gap-2 text-sm font-medium sm:justify-start"><QrCode class="size-4 text-primary" /> منوی عمومی این شعبه</div>
                            <a :href="branch.public_url" target="_blank" rel="noopener noreferrer" class="text-sm text-primary underline underline-offset-4">مشاهده منوی شعبه</a>
                            <div>
                                <a :href="branchQrDataUrl(branch)" :download="'menu-' + branch.slug + '-qr.svg'" class="inline-flex items-center rounded-md border border-border px-3 py-1.5 text-xs font-medium hover:bg-accent">دانلود QR (SVG)</a>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 rounded-lg bg-muted/30 px-3 py-2 text-xs leading-5 text-muted-foreground">QR این شعبه پس از فعال بودن شعبه و انتشار منوی عمومی در دسترس خواهد بود.</p>
                    <div v-if="canManage" class="mt-5 flex flex-wrap gap-2 border-t border-border pt-4">
                        <Button size="sm" variant="outline" @click="fillForm(branch)">ویرایش اطلاعات</Button>
                        <Button v-if="!branch.is_primary" size="sm" :variant="branch.status === 'active' ? 'destructive' : 'secondary'" @click="setStatus(branch)">{{ branch.status === 'active' ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}</Button>
                    </div>
                </article>
            </section>
            <div v-if="branches.length === 0" class="rounded-xl border border-dashed border-border px-6 py-12 text-center text-sm text-muted-foreground">هنوز شعبه‌ای ثبت نشده است.</div>
        </div>
    </main>
</template>
