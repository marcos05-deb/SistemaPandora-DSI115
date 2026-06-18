<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});

function getLabelText(label) {
    if (label.includes('&laquo;')) return 'Anterior';
    if (label.includes('&raquo;')) return 'Siguiente';
    return label;
}

function isPrevious(label) {
    return label.includes('&laquo;');
}

function isNext(label) {
    return label.includes('&raquo;');
}
</script>

<template>
    <div v-if="links.length > 3" class="px-4 py-3 border-t border-[var(--nord5)] flex justify-center bg-[var(--surface-subtle)]">
        <div class="flex flex-wrap items-center gap-1.5">
            <template v-for="(link, p) in links" :key="p">
                <div v-if="link.url === null" 
                    class="px-3 py-1.5 text-[12px] rounded-[8px] text-[var(--nord4)] border border-[var(--nord4)]/50 bg-transparent cursor-not-allowed flex items-center gap-1"
                >
                    <svg v-if="isPrevious(link.label)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span v-else-if="!isNext(link.label)">{{ getLabelText(link.label) }}</span>
                    <svg v-if="isNext(link.label)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <Link v-else
                    class="px-3 py-1.5 text-[12px] font-medium rounded-[8px] transition-all duration-200 border flex items-center gap-1"
                    :class="link.active 
                        ? 'bg-[var(--frost4)] text-white border-[var(--frost4)] shadow-sm' 
                        : 'border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--nord5)] hover:border-[var(--frost3)]'"
                    :href="link.url" 
                >
                    <svg v-if="isPrevious(link.label)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span v-else-if="!isNext(link.label)">{{ getLabelText(link.label) }}</span>
                    <svg v-if="isNext(link.label)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </Link>
            </template>
        </div>
    </div>
</template>
