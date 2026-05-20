<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import FlashBanner from '@/Components/UI/FlashBanner.vue';

defineProps({
    userLabel: { type: String, required: true },
    navigation: { type: Array, required: true },
});

const pendingModule = ref(null);

function onNavClick(item, event) {
    if (item.href === '#') {
        event.preventDefault();
        pendingModule.value = item.label;
        setTimeout(() => {
            pendingModule.value = null;
        }, 4000);
    }
}
</script>

<template>
    <div class="min-h-screen bg-pandora-surface flex flex-col">
        <header class="bg-pandora-navy text-white shadow-md">
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <span class="text-xl font-bold tracking-wider">PANDORA</span>
                    <span class="text-sm text-blue-200 ml-3 hidden sm:inline">
                        Sistema de Gestión Clínica
                    </span>
                </div>
                <div class="text-sm text-blue-100">{{ userLabel }}</div>
            </div>
        </header>

        <div class="flex flex-1">
            <aside class="w-56 bg-white border-r border-gray-200 shrink-0">
                <nav class="py-4">
                    <Link
                        v-for="item in navigation"
                        :key="item.href + item.label"
                        :href="item.href"
                        :class="[
                            'block px-5 py-2.5 text-sm transition-colors',
                            item.active
                                ? 'bg-blue-50 text-pandora-navy font-semibold border-r-2 border-blue-600'
                                : 'text-gray-600 hover:bg-gray-50 hover:text-pandora-navy',
                            item.href === '#' ? 'cursor-not-allowed opacity-70' : '',
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
                    class="mb-6 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
                >
                    El módulo «{{ pendingModule }}» estará disponible cuando se implemente el backend completo.
                </div>
                <FlashBanner />
                <slot />
            </main>
        </div>
    </div>
</template>
