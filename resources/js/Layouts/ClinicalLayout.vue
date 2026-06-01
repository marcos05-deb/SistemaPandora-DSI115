<script setup>
import { ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

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
        <header class="bg-[var(--nord0)] shadow-md z-20 h-[44px]">
            <div class="flex items-center justify-between px-6 h-full">
                <div class="flex items-center gap-2">
                    <span class="text-[16px] font-bold tracking-tight text-[var(--nord6)]">PANDORA <span class="font-normal text-[var(--frost2)] ml-1">Clínico</span></span>
                </div>
                <div class="flex items-center gap-4 text-[12px] text-[var(--nord4)]">
                    <span>{{ page.props.auth?.user?.name || page.props.auth?.user?.email }}</span>
                    <Link 
                        href="/logout" 
                        method="post" 
                        as="button" 
                        class="px-2 py-1 bg-transparent hover:bg-[var(--nord2)] text-[var(--nord4)] rounded transition-colors border border-[var(--nord3)]"
                    >
                        Cerrar Sesión
                    </Link>
                </div>
            </div>
        </header>

        <!-- Layout Body -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-[180px] bg-[var(--nord1)] shadow-xl shrink-0 z-10 flex flex-col">
                <nav class="flex-1 py-4">
                    <Link
                        v-for="item in navigation"
                        :key="item.href + item.label"
                        :href="item.href"
                        :class="[
                            'block px-6 py-2.5 text-[13px] transition-colors border-l-2',
                            $page.url.startsWith(item.href) && (item.href !== '/dashboard' || $page.url === '/dashboard')
                                ? 'bg-[rgba(136,192,208,0.07)] text-[var(--frost2)] border-[var(--frost2)] font-medium'
                                : 'text-[var(--nord4)] border-transparent hover:bg-[var(--nord2)]'
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
