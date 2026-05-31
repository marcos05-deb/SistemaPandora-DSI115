<script setup>
import { ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    navigation: { 
        type: Array, 
        default: () => [
            { label: 'Dashboard', href: '/admin/dashboard' },
            { label: 'Gestión de Personal', href: '/admin/users' },
        ]
    },
});

const page = usePage();

const showFlash = ref(true);

// Watch for flash messages and hide them after 5 seconds
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
    <!-- Background with Oracle aesthetic -->
    <div 
        class="min-h-screen bg-[#F0EBE1] bg-cover bg-center bg-fixed flex flex-col font-sans"
        style="background-image: url('/images/background.png')"
    >
        <!-- Topbar -->
        <header class="bg-[#1A1816] text-white shadow-md z-20">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center gap-4">
                    <span class="text-xl font-semibold tracking-tight">PANDORA <span class="font-light text-gray-400">Admin</span></span>
                </div>
                <div class="flex items-center gap-4 text-sm text-gray-300">
                    <span>{{ page.props.auth?.user?.name || page.props.auth?.user?.email }}</span>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="px-3 py-1.5 bg-[#2F2B28] hover:bg-gray-700 text-white rounded-lg transition-colors border border-gray-600"
                    >
                        Cerrar Sesión
                    </Link>
                </div>
            </div>
        </header>

        <!-- Layout Body -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-64 bg-white/95 backdrop-blur-sm shadow-xl shrink-0 z-10 flex flex-col">
                <nav class="flex-1 py-6 space-y-1">
                    <Link
                        v-for="item in navigation"
                        :key="item.href + item.label"
                        :href="item.href"
                        :class="[
                            'block px-6 py-3 text-[15px] font-medium transition-colors border-l-4',
                            $page.url.startsWith(item.href) && (item.href !== '/admin/dashboard' || $page.url === '/admin/dashboard')
                                ? 'bg-gray-100/80 text-[#1A1816] border-[#1A1816]'
                                : 'text-gray-600 border-transparent hover:bg-gray-50 hover:text-[#1A1816]'
                        ]"
                    >
                        {{ item.label }}
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
