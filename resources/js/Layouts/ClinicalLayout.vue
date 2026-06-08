<script setup>
import { ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useAutoLogout } from '@/Composables/useAutoLogout';

defineProps({
    navigation: { 
        type: Array, 
        default: () => [
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
        ]
    },
});

const page = usePage();
const { isDark, toggleTheme } = useTheme();
useAutoLogout();

const showFlash = ref(true);

watch(() => page.props.flash?.message, (newMsg) => {
    if (newMsg) {
        showFlash.value = true;
        setTimeout(() => { showFlash.value = false; }, 5000);
    }
}, { immediate: true });

const sidebarLinks = [
    {
        label: 'Panel Clínico',
        href: '/dashboard',
        icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        match: (url) => url === '/dashboard'
    },
    {
        label: 'Pacientes',
        href: '/pacientes',
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        match: (url) => url.startsWith('/pacientes') && !url.startsWith('/busqueda-segura')
    },
    {
        label: 'Búsqueda UUID',
        href: '/busqueda-segura',
        icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        match: (url) => url.startsWith('/busqueda-segura')
    },
];
</script>

<template>
    <div class="min-h-screen bg-[var(--nord6)] flex flex-col font-sans">
        <!-- Topbar -->
        <header class="bg-[var(--chrome-topbar)] shadow-md z-20 h-[56px] flex-shrink-0">
            <div class="flex items-center justify-between px-6 h-full">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-[var(--frost4)] flex items-center justify-center">
                        <span class="text-[13px] font-bold text-white">P</span>
                    </div>
                    <div>
                        <span class="text-[17px] font-bold tracking-tight text-white">
                            PANDORA
                        </span>
                        <span class="text-[11px] font-normal text-[var(--frost2)] ml-2 align-middle">
                            Clínico
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-[13px] text-[var(--chrome-text-muted)]">
                    <button 
                        @click="toggleTheme" 
                        class="p-2 hover:text-white hover:bg-[var(--chrome-topbar-hover)] rounded-lg transition-colors" 
                        :title="isDark ? 'Modo Claro' : 'Modo Oscuro'"
                    >
                        <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>
                    <div class="h-5 w-px bg-[var(--chrome-border)]"></div>
                    <div class="flex items-center gap-2 text-[13px]">
                        <div class="w-7 h-7 rounded-full bg-[var(--frost4)] flex items-center justify-center text-white text-[11px] font-bold">
                            {{ (page.props.auth?.user?.name || 'U').charAt(0).toUpperCase() }}
                        </div>
                        <span class="hidden sm:inline">{{ page.props.auth?.user?.name || page.props.auth?.user?.email }}</span>
                    </div>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="px-3 py-1.5 bg-transparent hover:bg-[var(--chrome-topbar-hover)] text-[var(--chrome-text-muted)] rounded-lg transition-colors border border-[var(--chrome-border)] text-[12px] font-medium"
                    >
                        Cerrar Sesión
                    </Link>
                </div>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <aside class="w-[240px] bg-[var(--chrome-sidebar)] shadow-xl shrink-0 z-10 flex flex-col border-r border-[var(--chrome-border)]">
                <nav class="flex-1 py-4 px-3 space-y-1">
                    <div class="px-3 pb-2 mb-2 text-[10px] font-semibold text-[var(--chrome-text-muted)] uppercase tracking-[0.12em] border-b border-[var(--chrome-border)]">
                        Navegación
                    </div>
                    <Link
                        v-for="link in sidebarLinks"
                        :key="link.href"
                        :href="link.href"
                        class="group flex items-center px-3 py-2.5 text-[14px] font-medium rounded-lg transition-all duration-200"
                        :class="link.match($page.url) 
                            ? 'bg-[var(--frost4)]/15 text-[var(--frost2)] shadow-sm border border-[var(--frost4)]/20' 
                            : 'text-[var(--chrome-text-muted)] hover:bg-[var(--chrome-sidebar-hover)] hover:text-white'"
                    >
                        <svg 
                            class="mr-3 h-[18px] w-[18px] flex-shrink-0" 
                            :class="link.match($page.url) ? 'text-[var(--frost2)]' : 'text-[var(--chrome-text-muted)] group-hover:text-white'"
                            xmlns="http://www.w3.org/2000/svg" 
                            fill="none" 
                            viewBox="0 0 24 24" 
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="link.icon" />
                        </svg>
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="p-3 border-t border-[var(--chrome-border)]">
                    <div class="px-3 py-2 rounded-lg bg-[var(--chrome-sidebar-hover)]/30">
                        <div class="text-[10px] text-[var(--chrome-text-muted)] uppercase tracking-[0.1em] font-semibold">Área</div>
                        <div class="text-[12px] text-[var(--chrome-text)] mt-0.5 truncate">{{ page.props.auth?.user?.area || 'General' }}</div>
                    </div>
                </div>
            </aside>

            <main class="flex-1 p-8 overflow-y-auto relative">
                <div v-if="page.props.flash?.message && showFlash" class="max-w-6xl mx-auto mb-6 animate-fade-in">
                    <div :class="[
                        'px-4 py-3 rounded-lg shadow-sm border-l-4 flex items-center gap-2.5',
                        page.props.flash?.variant === 'success' ? 'bg-[var(--aurora-green)]/10 border-[var(--aurora-green)] text-[var(--aurora-green)]' : 
                        page.props.flash?.variant === 'error' ? 'bg-[var(--aurora-red)]/10 border-[var(--aurora-red)] text-[var(--aurora-red)]' :
                        'bg-[var(--frost4)]/10 border-[var(--frost4)] text-[var(--frost4)]'
                    ]">
                        <svg v-if="page.props.flash?.variant === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <svg v-else-if="page.props.flash?.variant === 'error'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="text-[13px]">{{ page.props.flash.message }}</span>
                    </div>
                </div>

                <div class="max-w-6xl mx-auto animate-fade-in">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
