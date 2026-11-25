<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BookOpen, Building2, ClipboardList, CreditCard, FolderGit2, Languages, LayoutGrid, Palette, Store } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu,
    SidebarMenuButton, SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import type { Entitlements, NavItem } from '@/types';

const page = usePage();
const canManageTranslations = computed(() => {
    const entitlements = page.props.entitlements as Entitlements | null;

    return Number(entitlements?.features.max_languages ?? 1) > 1;
});
const mainNavItems = computed<NavItem[]>(() => [
    { title: 'داشبورد', href: dashboard(), icon: LayoutGrid },
    { title: 'مدیریت منو', href: catalogIndex(), icon: ClipboardList },
    { title: 'قالب و ظاهر منو', href: '/catalog/menu/theme', icon: Palette },
    ...(canManageTranslations.value ? [{ title: 'ترجمه منو', href: '/catalog/menu/translations/en', icon: Languages }] : []),
    { title: 'مدیریت شعب', href: '/business/branches/manage', icon: Store },
    { title: 'اشتراک و پرداخت', href: '/billing', icon: CreditCard },
    { title: 'تنظیمات کسب‌وکار', href: '/business/settings', icon: Building2 },
]);
const footerNavItems: NavItem[] = [
    { title: 'مخزن کد', href: 'https://github.com/laravel/vue-starter-kit', icon: FolderGit2 },
    { title: 'مستندات', href: 'https://laravel.com/docs/starter-kits#vue', icon: BookOpen },
];
</script>

<template>
    <Sidebar side="right" collapsible="icon" variant="inset" dir="rtl">
        <SidebarHeader><SidebarMenu><SidebarMenuItem><SidebarMenuButton size="lg" as-child><Link :href="dashboard()"><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader>
        <SidebarContent><NavMain :items="mainNavItems" /></SidebarContent>
        <SidebarFooter><NavFooter :items="footerNavItems" /><NavUser /></SidebarFooter>
    </Sidebar>
</template>
