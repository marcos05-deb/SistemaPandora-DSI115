<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
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

const sidebarOpen = ref(false);
const profileOpen = ref(false);
const profileRef = ref(null);
const showFlash = ref(true);
const flashExit = ref(false);

watch(() => page.props.flash?.message, (newMsg) => {
    if (newMsg) {
        showFlash.value = true;
        flashExit.value = false;
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

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));

const allLinks = [
    {
        label: 'Panel Clínico',
        href: '/dashboard',
        icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
        match: (url) => url === '/dashboard',
        roles: null,
    },
    {
        label: 'Pacientes',
        href: '/pacientes',
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        match: (url) => url.startsWith('/pacientes') && !url.startsWith('/busqueda-segura'),
        roles: ['psychosocial_referent', 'specialist'],
    },
    {
        label: 'Búsqueda UUID',
        href: '/busqueda-segura',
        icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
        match: (url) => url.startsWith('/busqueda-segura'),
        roles: ['specialist', 'area_coordinator'],
    },
];

const userRoles = computed(() => page.props.auth?.user?.roles || []);
const sidebarLinks = computed(() => allLinks.filter(link => !link.roles || link.roles.some(r => userRoles.value.includes(r))));
</script>

<template>
    <div class="min-h-screen bg-[var(--nord6)] flex flex-col font-sans">
        <!-- Toast Notification -->
        <div v-if="page.props.flash?.message && showFlash" class="fixed top-4 right-4 z-[60] max-w-sm animate-slide-in-right pointer-events-none">
            <div :class="[
                'px-4 py-3 rounded-xl shadow-lg border flex items-center gap-2.5 transition-all duration-300 pointer-events-auto',
                flashExit ? 'opacity-0 translate-x-4' : 'opacity-100',
                page.props.flash?.variant === 'success' ? 'bg-[var(--aurora-green)] text-white border-[var(--aurora-green)]' : 
                page.props.flash?.variant === 'error' ? 'bg-[var(--aurora-red)] text-white border-[var(--aurora-red)]' :
                'bg-[var(--chrome-topbar)] text-white border-[var(--chrome-border)]'
            ]">
                <svg v-if="page.props.flash?.variant === 'success'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <svg v-else-if="page.props.flash?.variant === 'error'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <svg v-else class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span class="text-[13px] font-medium">{{ page.props.flash.message }}</span>
                <button @click="showFlash = false" class="ml-auto p-0.5 hover:bg-white/20 rounded transition-colors pointer-events-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <!-- Sidebar Backdrop (mobile) -->
        <div v-if="sidebarOpen" class="fixed inset-0 bg-[var(--nord0)]/60 backdrop-blur-sm z-30 lg:hidden" @click="sidebarOpen = false" />

        <!-- Topbar -->
        <header class="bg-[var(--chrome-topbar)] shadow-md z-20 h-[56px] flex-shrink-0">
            <div class="flex items-center justify-between px-4 lg:px-6 h-full">
                <div class="flex items-center gap-3">
                    <!-- Hamburger (mobile) -->
                    <button class="lg:hidden p-1.5 -ml-1 text-[var(--chrome-text-muted)] hover:text-white rounded-lg hover:bg-[var(--chrome-topbar-hover)] transition-colors" @click="sidebarOpen = !sidebarOpen">
                        <svg v-if="!sidebarOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="w-8 h-8 rounded-lg bg-[var(--frost4)] flex items-center justify-center shrink-0">
                        <span class="text-[13px] font-bold text-white">P</span>
                    </div>
                    <div>
                        <span class="text-[16px] lg:text-[17px] font-bold tracking-tight text-white">PANDORA</span>
                        <span class="text-[10px] lg:text-[11px] font-normal text-[var(--frost2)] ml-2 align-middle hidden sm:inline">Clínico</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 lg:gap-3 text-[13px] text-[var(--chrome-text-muted)]">
                    <button @click="toggleTheme" class="p-2 hover:text-white hover:bg-[var(--chrome-topbar-hover)] rounded-lg transition-colors" :title="isDark ? 'Modo Claro' : 'Modo Oscuro'">
                        <svg v-if="isDark" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                        <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                    </button>
                    <div class="hidden sm:block h-5 w-px bg-[var(--chrome-border)]" />

                    <!-- Profile Dropdown -->
                    <div ref="profileRef" class="relative">
                        <button @click="profileOpen = !profileOpen" class="flex items-center gap-2 hover:bg-[var(--chrome-topbar-hover)] rounded-lg px-2 py-1.5 transition-colors">
                            <div class="w-7 h-7 rounded-full bg-[var(--frost4)] flex items-center justify-center text-white text-[11px] font-bold shrink-0">
                                {{ (page.props.auth?.user?.name || 'U').charAt(0).toUpperCase() }}
                            </div>
                            <span class="hidden md:inline text-[var(--chrome-text)]">{{ page.props.auth?.user?.name || page.props.auth?.user?.email }}</span>
                            <svg class="hidden md:block h-3 w-3 transition-transform duration-200" :class="profileOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95 -translate-y-1" enter-to-class="opacity-100 scale-100 translate-y-0" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                        <div v-if="profileOpen" class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-[var(--nord4)] overflow-hidden z-50">
                            <div class="px-4 py-3 border-b border-[var(--nord4)]">
                                <p class="text-[14px] font-semibold text-[var(--nord0)]">{{ page.props.auth?.user?.name }}</p>
                                <p class="text-[12px] text-[var(--nord3)]">{{ page.props.auth?.user?.email }}</p>
                                <span class="inline-block mt-1.5 text-[10px] font-medium px-2 py-0.5 rounded-full bg-[var(--frost4)]/10 text-[var(--frost4)]">
                                    {{ page.props.auth?.user?.roles?.[0]?.nombre || 'Usuario' }}
                                </span>
                            </div>
                            <div class="px-4 py-3 border-b border-[var(--nord4)] bg-[var(--nord6)]/50">
                                <div class="text-[10px] text-[var(--nord3)] uppercase tracking-[0.1em] font-semibold">Área</div>
                                <div class="text-[12px] text-[var(--nord0)] mt-0.5">{{ page.props.auth?.user?.area || 'General' }}</div>
                            </div>
                            <div class="p-1.5">
                                <Link href="/logout" method="post" as="button" class="flex items-center gap-2.5 w-full px-3 py-2 text-[13px] text-[var(--aurora-red)] hover:bg-[var(--aurora-red)]/5 rounded-lg transition-colors font-medium">
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
            <!-- Sidebar -->
            <aside class="w-[240px] bg-[var(--chrome-sidebar)] shadow-xl shrink-0 z-10 flex flex-col border-r border-[var(--chrome-border)] transition-transform duration-300 lg:translate-x-0" :class="sidebarOpen ? 'fixed inset-y-0 left-0 translate-x-0' : 'fixed inset-y-0 left-0 -translate-x-full lg:relative lg:translate-x-0'">
                <nav class="flex-1 py-4 px-3 space-y-1 mt-4 lg:mt-0">
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
                        <svg class="mr-3 h-[18px] w-[18px] flex-shrink-0" :class="link.match($page.url) ? 'text-[var(--frost2)]' : 'text-[var(--chrome-text-muted)] group-hover:text-white'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

            <main class="flex-1 p-4 lg:p-8 overflow-y-auto relative">
                <div class="max-w-6xl mx-auto animate-fade-in">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
