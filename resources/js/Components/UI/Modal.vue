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
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-[var(--nord0)]/60 backdrop-blur-sm"
                    @click="emit('close')"
                />

                <!-- Panel con animación propia -->
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        :class="[
                            'relative w-full bg-[var(--surface)] rounded-2xl shadow-2xl border border-[var(--nord4)] overflow-hidden',
                            maxWidth,
                        ]"
                    >
                        <!-- Header con gradiente -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-[var(--nord4)]"
                            style="background: linear-gradient(135deg, var(--surface-header) 0%, var(--surface) 100%);">
                            <h3 class="text-[15px] font-bold text-[var(--nord0)] tracking-tight">{{ title }}</h3>
                            <button
                                type="button"
                                class="w-8 h-8 flex items-center justify-center rounded-full text-[var(--nord3)] hover:bg-[var(--nord5)] hover:text-[var(--nord0)] transition-all duration-150 hover:scale-110"
                                aria-label="Cerrar"
                                @click="emit('close')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="px-6 py-5">
                            <slot />
                        </div>
                        <div v-if="$slots.footer" class="px-6 py-4 border-t border-[var(--nord4)] flex justify-end gap-3 bg-[var(--surface-subtle)]/50">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
