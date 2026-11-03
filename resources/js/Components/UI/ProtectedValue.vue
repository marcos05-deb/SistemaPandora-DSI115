<script setup>
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';

// Only suppression metadata belongs here; never pass a protected value.
const props = defineProps({
    suppression: { type: String, default: '' },
    minimum: { type: Number, default: 5 },
    compact: { type: Boolean, default: false },
});
const descriptionId = useId();
const trigger = ref(null);
const open = ref(false);
const position = ref({});
let closeTimer;
const explanation = computed(() => props.suppression === 'primaria'
    ? `Este valor se oculta porque no alcanza el umbral mínimo de ${props.minimum} registros.`
    : props.suppression === 'complementaria'
        ? 'Este valor se protege para evitar que se deduzcan otros conteos protegidos.'
        : `Este valor se protege por el umbral mínimo de ${props.minimum} registros o para evitar que se deduzcan otros conteos protegidos.`);

function keepOpen() { clearTimeout(closeTimer); }
function show() {
    keepOpen();
    const bounds = trigger.value.getBoundingClientRect();
    const width = Math.min(288, window.innerWidth - 32);
    position.value = {
        width: `${width}px`,
        left: `${Math.max(16, Math.min(bounds.left, window.innerWidth - width - 16))}px`,
        top: `${bounds.bottom + 8}px`,
    };
    if (bounds.bottom + 112 > window.innerHeight) {
        position.value.top = 'auto';
        position.value.bottom = `${window.innerHeight - bounds.top + 8}px`;
    }
    open.value = true;
}
function close() { keepOpen(); open.value = false; }
function leave() {
    if (document.activeElement !== trigger.value) closeTimer = setTimeout(close, 150);
}
function handleScroll() {
    // Keyboard focus can scroll a clipped table cell into view after focus fires.
    if (document.activeElement === trigger.value) show();
    else close();
}
function outside(event) {
    if (!trigger.value?.contains(event.target)) close();
}
function dismiss(event) {
    if (event.key === 'Escape') close();
}
watch(open, (isOpen, _previous, onCleanup) => {
    if (!isOpen) return;
    window.addEventListener('pointerdown', outside);
    window.addEventListener('keydown', dismiss);
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', close);
    onCleanup(() => {
        window.removeEventListener('pointerdown', outside);
        window.removeEventListener('keydown', dismiss);
        window.removeEventListener('scroll', handleScroll, true);
        window.removeEventListener('resize', close);
    });
});
onBeforeUnmount(keepOpen);
</script>

<template>
    <span class="relative inline-flex max-w-full align-middle">
        <button
            ref="trigger"
            type="button"
            :aria-describedby="descriptionId"
            class="inline-flex min-h-11 shrink-0 cursor-help items-center justify-center rounded-lg border border-pd-border bg-pd-surface-subtle px-2.5 py-1 text-xs font-medium leading-5 tracking-normal text-pd-text-secondary sm:min-h-8 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand"
            :class="compact ? 'max-w-20 flex-col gap-0.5 whitespace-normal' : 'gap-1.5 whitespace-nowrap'"
            @mouseenter="show" @mouseleave="leave" @focus="show" @blur="close" @click="show"
        >
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <rect x="5" y="10" width="14" height="11" rx="2" />
                <path stroke-linecap="round" d="M8 10V7a4 4 0 018 0v3M12 14v3" />
            </svg>
            <span>Dato protegido</span>
        </button>
        <span :id="descriptionId" class="sr-only">{{ explanation }}</span>
        <Teleport to="body">
            <span v-if="open" aria-hidden="true" :style="position" class="fixed z-[100] rounded-lg border border-pd-border bg-pd-surface px-3 py-2 text-left text-xs font-normal leading-relaxed text-pd-text-primary shadow-lg" @mouseenter="keepOpen" @mouseleave="leave">
                {{ explanation }}
            </span>
        </Teleport>
    </span>
</template>
