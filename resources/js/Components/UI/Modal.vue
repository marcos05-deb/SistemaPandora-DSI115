<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);

function onBackdropClick() {
    emit('close');
}
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
                class="absolute inset-0 bg-gray-900/50"
                @click="onBackdropClick"
            />
            <div
                :class="[
                    'relative w-full bg-white rounded-lg shadow-xl border border-gray-200',
                    maxWidth,
                ]"
            >
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none"
                        aria-label="Cerrar"
                        @click="emit('close')"
                    >
                        ×
                    </button>
                </div>
                <div class="px-6 py-5">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
