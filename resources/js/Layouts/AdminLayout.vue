<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useAutoLogout } from '@/Composables/useAutoLogout';

defineProps({
    navigation: { 
        type: Array, 
        default: () => [
            { label: 'Panel', href: '/admin/dashboard' },
            { label: 'Gestión de Personal', href: '/admin/users' },
        ]
    },
});

const page = usePage();
const { isDark, toggleTheme } = useTheme();
useAutoLogout();

const initials = computed(() => {
    const src = (page.props.auth?.user?.name || page.props.auth?.user?.email || 'U').trim();
    const parts = src.split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] || 'U') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
});

const sidebarOpen = ref(false);
const profileOpen = ref(false);
const profileRef = ref(null);
const showFlash = ref(true);
const flashExit = ref(false);
const flashProgress = ref(100);
let flashProgressInterval = null;

watch(sidebarOpen, (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
});

watch(() => page.props.flash?.message, (newMsg) => {
    if (newMsg) {
        showFlash.value = true;
        flashExit.value = false;
        flashProgress.value = 100;
        if (flashProgressInterval) clearInterval(flashProgressInterval);
        flashProgressInterval = setInterval(() => {
            flashProgress.value -= 2.5;
            if (flashProgress.value <= 0) clearInterval(flashProgressInterval);
        }, 100);
        setTimeout(() => { flashExit.value = true; }, 4000);
        setTimeout(() => { showFlash.value = false; }, 4500);
    }
}, { immediate: true });

watch(() => page.url, () => { sidebarOpen.value = false; profileOpen.value = false; });

function onDocClick(e) {
    if (profileRef.value && !profileRef.value.contains(e.target)) {
        profileOpen.value = false;
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        sidebarOpen.value = false;
        profileOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKeydown);
});
onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});

const sidebarLinks = [
    {
        label: 'Panel',
        href: '/admin/dashboard',
        icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        match: (url) => url === '/admin/dashboard'
    },
    {
        label: 'Personal del Sistema',
        href: '/admin/users',
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        match: (url) => url.startsWith('/admin/users')
    },
    {
        label: 'Auditoría de Pacientes',
        href: '/admin/pacientes',
        icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
        match: (url) => url.startsWith('/admin/pacientes')
    },
    {
        label: 'Calendario de Citas',
        href: '/admin/citas',
        icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        match: (url) => url.startsWith('/admin/citas')
    },
    {
        label: 'Organigrama',
        href: '/admin/organigrama',
        icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        match: (url) => url.startsWith('/admin/organigrama')
    },
];

if (page.props.can?.indicadores) {
    sidebarLinks.push({
        label: 'Indicadores',
        href: '/indicadores',
        icon: 'M4 19V5m0 14h16M8 15v-4m4 4V7m4 8V9',
        match: (url) => url.startsWith('/indicadores'),
    });
}
if (page.props.can?.reportes) {
    sidebarLinks.push({
        label: 'Reportes',
        href: '/reportes',
        icon: 'M4 5h16M4 10h16M4 15h10m-10 4h16',
        match: (url) => url.startsWith('/reportes'),
    });
}
if (page.props.can?.bitacora) {
    sidebarLinks.push({
        label: 'Bitácora de auditoría',
        href: '/admin/bitacora',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        match: (url) => url.startsWith('/admin/bitacora'),
    });
}
</script>

<template>
    <div class="min-h-screen bg-pd-app-bg text-pd-text-primary flex flex-col font-sans overflow-x-hidden">
        <!-- Toast Notification -->
        <div v-if="page.props.flash?.message && showFlash" class="fixed top-4 left-4 right-4 sm:left-auto z-[60] max-w-sm sm:ml-auto animate-slide-in-right pointer-events-none">
            <div :class="[
                'relative rounded-xl shadow-xl border flex flex-col overflow-hidden pointer-events-auto transition-all duration-300 bg-pd-surface',
                flashExit ? 'opacity-0 translate-x-4' : 'opacity-100',
                page.props.flash?.variant === 'success' ? 'border-pd-success/40' :
                page.props.flash?.variant === 'error' ? 'border-pd-danger/40' :
                page.props.flash?.variant === 'warning' ? 'border-pd-warning/50' :
                'border-pd-border'
            ]">
                <div class="flex items-center gap-2.5 px-4 py-3">
                    <div :class="[
                        'w-8 h-8 rounded-lg flex items-center justify-center shrink-0',
                        page.props.flash?.variant === 'success' ? 'bg-pd-success-soft text-pd-success' :
                        page.props.flash?.variant === 'error' ? 'bg-pd-danger-soft text-pd-danger' :
                        page.props.flash?.variant === 'warning' ? 'bg-pd-warning-soft text-pd-warning' :
                        'bg-pd-brand-soft text-pd-brand'
                    ]">
                        <svg v-if="page.props.flash?.variant === 'success'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        <svg v-else-if="page.props.flash?.variant === 'error'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <svg v-else-if="page.props.flash?.variant === 'warning'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <span class="text-[13px] font-medium text-pd-text-primary flex-1">{{ page.props.flash.message }}</span>
                    <button @click="showFlash = false" class="p-1 hover:bg-pd-surface-header rounded-lg transition-colors" aria-label="Cerrar mensaje">
                        <svg class="h-3.5 w-3.5 text-pd-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="h-0.5 w-full bg-pd-surface-header">
                    <div class="h-full transition-all duration-100 ease-linear"
                        :class="page.props.flash?.variant === 'success' ? 'bg-pd-success' : page.props.flash?.variant === 'error' ? 'bg-pd-danger' : page.props.flash?.variant === 'warning' ? 'bg-pd-warning' : 'bg-pd-brand'"
                        :style="{ width: flashProgress + '%' }">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Backdrop (mobile) -->
        <div v-if="sidebarOpen" class="fixed inset-0 bg-pd-sidebar/60 backdrop-blur-sm z-40 lg:hidden" @click="sidebarOpen = false" />

        <!-- Topbar -->
        <header class="bg-pd-surface border-b border-pd-border z-50 h-[72px] flex-shrink-0 relative">
            <div class="flex items-center justify-between px-4 lg:px-7 h-full">
                <div class="flex items-center gap-3">
                    <button type="button" class="lg:hidden p-2 -ml-1 text-pd-text-secondary hover:text-pd-text-primary rounded-lg hover:bg-pd-surface-header transition-colors" :aria-expanded="sidebarOpen" aria-controls="admin-sidebar" aria-label="Abrir menú" @click="sidebarOpen = !sidebarOpen">
                        <svg v-if="!sidebarOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-[10px] bg-pd-brand flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        </div>
                        <div class="leading-tight">
                            <div class="text-[20px] font-bold tracking-tight text-pd-text-primary">PANDORA</div>
                            <div class="hidden sm:block text-[11px] font-medium uppercase tracking-[0.08em] text-pd-text-secondary">Expediente clínico universitario</div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 lg:gap-3 text-[13px]">
                    <button @click="toggleTheme" class="p-2 text-pd-text-secondary hover:text-pd-text-primary hover:bg-pd-surface-header rounded-lg transition-colors" :title="isDark ? 'Modo Claro' : 'Modo Oscuro'" :aria-label="isDark ? 'Modo Claro' : 'Modo Oscuro'">
                        <svg v-if="isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>
                    <div class="hidden sm:block h-8 w-px bg-pd-border" />

                    <!-- Profile Dropdown -->
                    <div ref="profileRef" class="relative">
                        <button @click="profileOpen = !profileOpen" class="flex items-center gap-3 hover:bg-pd-surface-header rounded-xl px-2 py-1.5 transition-colors text-left" :aria-expanded="profileOpen">
                            <div class="w-10 h-10 rounded-full bg-pd-brand-soft text-pd-brand flex items-center justify-center text-[13px] font-bold shrink-0">
                                {{ initials }}
                            </div>
                            <div class="hidden md:block leading-tight">
                                <div class="text-[14px] font-semibold text-pd-text-primary">{{ page.props.auth?.user?.name || page.props.auth?.user?.email }}</div>
                                <div class="text-[12px] text-pd-text-secondary">{{ page.props.auth?.user?.role_label || 'Usuario' }}</div>
                            </div>
                            <svg class="hidden md:block h-4 w-4 text-pd-text-secondary transition-transform duration-200" :class="profileOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95 -translate-y-1" enter-to-class="opacity-100 scale-100 translate-y-0" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                        <div v-if="profileOpen" class="absolute right-0 mt-2 w-64 bg-pd-surface rounded-xl shadow-xl border border-pd-border overflow-hidden z-50">
                            <div class="px-4 py-3 border-b border-pd-border">
                                <p class="text-[14px] font-semibold text-pd-text-primary">{{ page.props.auth?.user?.name }}</p>
                                <p class="text-[12px] text-pd-text-secondary">{{ page.props.auth?.user?.email }}</p>
                                <span class="inline-block mt-1.5 text-[10px] font-medium px-2 py-0.5 rounded-full bg-pd-brand-soft text-pd-brand">
                                    {{ page.props.auth?.user?.role_label || 'Usuario' }}
                                </span>
                            </div>
                            <div class="p-1.5">
                                <Link href="/logout" method="post" as="button" class="flex items-center gap-2.5 w-full px-3 py-2 text-[13px] text-pd-danger hover:bg-pd-danger-soft rounded-lg transition-colors font-medium">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                    Cerrar Sesión
                                </Link>
                            </div>
                        </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <aside
                id="admin-sidebar"
                class="w-[min(264px,85vw)] bg-pd-sidebar shrink-0 z-10 flex flex-col transition-transform duration-300 lg:w-[264px] lg:translate-x-0 lg:z-10 lg:relative"
                :class="sidebarOpen
                    ? 'fixed inset-y-0 left-0 translate-x-0 z-[45] pt-[72px] lg:pt-0 shadow-xl'
                    : 'fixed inset-y-0 left-0 -translate-x-full lg:translate-x-0'"
            >
                <nav class="flex-1 pt-7 px-4 space-y-2 overflow-y-auto" aria-label="Administración">
                    <div class="pb-3 text-[11px] font-bold text-pd-sidebar-text/80 uppercase tracking-[0.14em]">
                        Administración
                    </div>
                    <Link
                        v-for="link in sidebarLinks"
                        :key="link.href"
                        :href="link.href"
                        class="group relative flex items-center min-h-[46px] px-3 py-2.5 text-[14px] font-medium rounded-lg transition-colors duration-200"
                        :class="link.match($page.url)
                            ? 'bg-pd-brand text-white'
                            : 'text-pd-sidebar-text hover:bg-pd-sidebar-hover hover:text-white'"
                        :aria-current="link.match($page.url) ? 'page' : undefined"
                    >
                        <span v-if="link.match($page.url)"
                            class="absolute left-[10px] top-1/2 -translate-y-1/2 w-[3px] h-5 rounded-full bg-pd-brand-mint">
                        </span>
                        <svg class="mr-3 h-5 w-5 flex-shrink-0" :class="link.match($page.url) ? 'ml-3' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="link.icon" />
                        </svg>
                        {{ link.label }}
                    </Link>
                </nav>
                <div class="p-4">
                    <div class="px-3.5 py-3 rounded-xl bg-pd-sidebar-hover border border-white/10">
                        <div class="text-[11px] text-pd-sidebar-text/80 uppercase tracking-[0.1em] font-bold">Área autorizada</div>
                        <div class="text-[14px] font-semibold text-white mt-1.5 truncate">Institucional</div>
                        <div class="text-[12px] text-pd-sidebar-text/80 mt-1">Sesión protegida</div>
                    </div>
                </div>
            </aside>

            <main class="flex-1 p-4 lg:p-8 overflow-y-auto overflow-x-hidden relative min-w-0">
                <div class="max-w-6xl mx-auto animate-fade-in">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
