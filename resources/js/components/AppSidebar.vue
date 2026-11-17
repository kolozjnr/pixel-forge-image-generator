<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FolderGit2, ImagePlus, Images, LayoutGrid, Wallet } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: dashboard(),
        icon: LayoutGrid,
    },
    { title: 'Create image', href: '/generations/create', icon: ImagePlus },
    { title: 'My generations', href: '/generations', icon: Images },
    { title: 'Credits & balance', href: '/credits', icon: Wallet },
];

const page = usePage<{ auth: { user: { is_admin: boolean } | null } }>();
if (page.props.auth.user?.is_admin) {
    mainNavItems.push({ title: 'Admin reports', href: '/admin/reports', icon: LayoutGrid });
}

const footerNavItems: NavItem[] = [
    { title: 'Settings', href: '/settings/profile', icon: FolderGit2 },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
