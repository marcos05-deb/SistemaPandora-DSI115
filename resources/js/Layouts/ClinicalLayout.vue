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
        setTimeout(() => {
            showFlash.value = false;
        }, 5000);
    }
}, { immediate: true });
</script>

<template>
    <div class="min-h-screen bg-[var(--nord6)] flex flex-col font-sans">
        <!-- Topbar -->
        <header class="bg-[#2E3440] shadow-md z-20 h-[44px]">
            <div class="flex items-center justify-between px-6 h-full">
                <div class="flex items-center gap-2">
                    <span class="text-[16px] font-bold tracking-tight text-[#ECEFF4]">PANDORA <span class="font-normal text-[var(--frost2)] ml-1">Clínico</span></span>
                </div>
                <div class="flex items-center gap-4 text-[12px] text-[#D8DEE9]">
                    <button @click="toggleTheme" class="p-1.5 hover:text-white hover:bg-[#434C5E] rounded transition-colors" :title="isDark ? 'Modo Claro' : 'Modo Oscuro'">
                        <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>
                    <span>{{ page.props.auth?.user?.name || page.props.auth?.user?.email }}</span>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="px-2 py-1 bg-transparent hover:bg-[#434C5E] text-[#D8DEE9] rounded transition-colors border border-[#4C566A]"
                    >
                        Cerrar Sesión
                    </Link>
                </div>
            </div>
        </header>

        <!-- Layout Body -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-[180px] bg-[#3B4252] shadow-xl shrink-0 z-10 flex flex-col">
                <nav class="flex-1 py-4">
                    <Link
                        href="/dashboard"
                        class="group flex items-center px-4 py-2.5 text-[14px] font-medium rounded-[8px] transition-all duration-200"
                        :class="$page.url === '/dashboard' ? 'bg-[#4C566A] text-white shadow-sm' : 'text-[#D8DEE9] hover:bg-[#434C5E] hover:text-white'"
                    >
                        <svg class="mr-3 h-5 w-5 flex-shrink-0 transition-colors" :class="$page.url === '/dashboard' ? 'text-white' : 'text-[#D8DEE9] group-hover:text-[var(--nord11)]'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        Dashboard
                    </Link>

                    <Link
                        href="/pacientes"
                        class="group flex items-center px-4 py-2.5 text-[14px] font-medium rounded-[8px] transition-all duration-200"
                        :class="$page.url.startsWith('/pacientes') && !$page.url.startsWith('/busqueda-segura') ? 'bg-[#4C566A] text-white shadow-sm' : 'text-[#D8DEE9] hover:bg-[#434C5E] hover:text-white'"
                    >
                        <svg class="mr-3 h-5 w-5 flex-shrink-0 transition-colors" :class="$page.url.startsWith('/pacientes') && !$page.url.startsWith('/busqueda-segura') ? 'text-white' : 'text-[#D8DEE9] group-hover:text-[var(--nord10)]'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Pacientes
                    </Link>

                    <Link
                        href="/busqueda-segura"
                        class="group flex items-center px-4 py-2.5 text-[14px] font-medium rounded-[8px] transition-all duration-200"
                        :class="$page.url.startsWith('/busqueda-segura') ? 'bg-[#4C566A] text-white shadow-sm' : 'text-[#D8DEE9] hover:bg-[#434C5E] hover:text-white'"
                    >
                        <svg class="mr-3 h-5 w-5 flex-shrink-0 transition-colors" :class="$page.url.startsWith('/busqueda-segura') ? 'text-white' : 'text-[#D8DEE9] group-hover:text-[var(--nord12)]'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Búsqueda UUID
                    </Link>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 p-8 overflow-y-auto relative">
                <!-- Flash messages -->
                <div v-if="page.props.flash?.message && showFlash" class="max-w-6xl mx-auto mb-6">
                    <div :class="[
                        'px-4 py-3 rounded-lg shadow-sm border-l-4',
                        page.props.flash?.variant === 'success' ? 'bg-green-50 border-green-500 text-green-800' : 
                        page.props.flash?.variant === 'error' ? 'bg-red-50 border-red-500 text-red-800' :
                        'bg-blue-50 border-blue-500 text-blue-800'
                    ]">
                        {{ page.props.flash.message }}
                    </div>
                </div>

                <div class="max-w-6xl mx-auto">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
