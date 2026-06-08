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
    <nav class="flex items-center gap-1.5 text-[12px] text-[var(--nord3)] mb-2">
        <template v-for="(item, i) in items" :key="i">
            <svg v-if="i > 0" class="h-3 w-3 text-[var(--nord4)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <Link
                v-if="item.href"
                :href="item.href"
                class="hover:text-[var(--nord10)] transition-colors truncate"
            >
                {{ item.label }}
            </Link>
            <span v-else class="text-[var(--nord0)] font-medium truncate">{{ item.label }}</span>
        </template>
    </nav>
</template>
