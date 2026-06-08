<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import FlashBanner from '@/Components/UI/FlashBanner.vue';
import { useAutoLogout } from '@/Composables/useAutoLogout';

defineProps({
    userLabel: { type: String, required: true },
    navigation: { type: Array, required: true },
});

const pendingModule = ref(null);
useAutoLogout();

function onNavClick(item, event) {
    if (item.href === '#') {
        event.preventDefault();
        pendingModule.value = item.label;
        setTimeout(() => { pendingModule.value = null; }, 4000);
    }
}
</script>

<template>
    <div class="min-h-screen bg-[var(--nord6)] flex flex-col font-sans">
        <header class="bg-[var(--chrome-topbar)] text-white shadow-md">
            <div class="flex items-center justify-between px-6 py-3.5">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-[var(--frost4)] flex items-center justify-center">
                        <span class="text-[13px] font-bold text-white">P</span>
                    </div>
                    <span class="text-[17px] font-bold tracking-tight text-white">PANDORA</span>
                    <span class="text-[12px] text-[var(--frost2)] hidden sm:inline">Sistema de Gestión Clínica</span>
                </div>
                <div class="flex items-center gap-3 text-[13px] text-[var(--chrome-text-muted)]">
                    <div class="w-7 h-7 rounded-full bg-[var(--frost4)] flex items-center justify-center text-white text-[11px] font-bold">
                        {{ (userLabel || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <span class="hidden sm:inline">{{ userLabel }}</span>
                </div>
            </div>
        </header>

        <div class="flex flex-1">
            <aside class="w-[240px] bg-white border-r border-[var(--nord4)] shadow-sm shrink-0">
                <nav class="py-4 px-3">
                    <Link
                        v-for="item in navigation"
                        :key="item.href + item.label"
                        :href="item.href"
                        :class="[
                            'block px-3 py-2.5 text-[13px] font-medium rounded-lg transition-all duration-200 mb-0.5',
                            item.active
                                ? 'bg-[var(--frost4)]/10 text-[var(--frost4)] border-l-[3px] border-[var(--frost4)]'
                                : 'text-[var(--nord3)] hover:bg-[var(--nord6)] hover:text-[var(--nord0)]',
                            item.href === '#' ? 'cursor-not-allowed opacity-60' : '',
                        ]"
                        @click="onNavClick(item, $event)"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <main class="flex-1 p-8 overflow-auto">
                <div
                    v-if="pendingModule"
                    class="mb-6 rounded-lg border border-[var(--frost4)]/20 bg-[var(--frost4)]/10 px-4 py-3 text-[13px] text-[var(--frost4)] animate-fade-in"
                >
                    El módulo «{{ pendingModule }}» estará disponible cuando se implemente el backend completo.
                </div>
                <FlashBanner />
                <div class="animate-fade-in">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
