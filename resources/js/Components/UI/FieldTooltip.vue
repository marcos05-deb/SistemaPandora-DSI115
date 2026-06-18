<script setup>
import { ref } from 'vue';

const props = defineProps({
    text: { type: String, required: true },
});

const show = ref(false);
</script>

<template>
    <span class="relative inline-flex items-center cursor-help" @mouseenter="show = true" @mouseleave="show = false" @click="show = !show">
        <svg class="h-[14px] w-[14px] text-[var(--nord3)] hover:text-[var(--frost3)] transition-colors shrink-0 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95 translate-y-1" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="show" class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 px-3 py-2 bg-[var(--chrome-topbar)] text-[var(--chrome-text)] text-[11px] leading-relaxed rounded-lg shadow-xl z-50 pointer-events-none">
            {{ text }}
            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-px border-4 border-transparent border-t-[var(--chrome-topbar)]" />
        </div>
        </Transition>
    </span>
</template>
