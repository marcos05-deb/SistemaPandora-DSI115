<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav class="flex items-center mb-3" aria-label="Breadcrumb">
        <ol class="inline-flex items-center gap-0.5 text-[12px] bg-[var(--surface-subtle)] border border-[var(--nord4)] rounded-full px-3 py-1.5">
            <template v-for="(item, i) in items" :key="i">
                <!-- Separador -->
                <li v-if="i > 0" class="flex items-center">
                    <svg class="h-3 w-3 text-[var(--nord4)] mx-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                    </svg>
                </li>
                <!-- Ícono home en el primer item -->
                <li v-if="i === 0" class="flex items-center">
                    <svg class="h-3.5 w-3.5 text-[var(--nord3)] mr-1 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </li>
                <!-- Link o texto -->
                <li class="flex items-center">
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        class="text-[var(--nord3)] hover:text-[var(--nord10)] transition-colors truncate font-medium"
                    >
                        {{ item.label }}
                    </Link>
                    <span v-else class="text-[var(--nord0)] font-semibold truncate">{{ item.label }}</span>
                </li>
            </template>
        </ol>
    </nav>
</template>
