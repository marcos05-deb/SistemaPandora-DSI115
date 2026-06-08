<script setup>
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);

watch(() => props.show, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <div
                class="absolute inset-0 bg-[var(--nord0)]/50 backdrop-blur-sm"
                @click="emit('close')"
            />
            <div
                :class="[
                    'relative w-full bg-white rounded-xl shadow-2xl border border-[var(--nord4)] animate-fade-in-up',
                    maxWidth,
                ]"
            >
                <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--nord4)]">
                    <h3 class="text-[15px] font-semibold text-[var(--nord0)]">{{ title }}</h3>
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded-full text-[var(--nord3)] hover:bg-[var(--nord6)] hover:text-[var(--nord0)] transition-colors text-lg leading-none"
                        aria-label="Cerrar"
                        @click="emit('close')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-5">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="px-6 py-4 border-t border-[var(--nord4)] flex justify-end gap-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
