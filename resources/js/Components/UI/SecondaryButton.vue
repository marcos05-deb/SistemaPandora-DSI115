<script setup>
defineProps({
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        class="inline-flex items-center justify-center gap-2 rounded-xl border border-[var(--nord4)] bg-[var(--surface)] px-5 py-2.5 text-sm font-medium text-[var(--nord3)] shadow-sm hover:bg-[var(--nord6)] hover:text-[var(--nord0)] hover:border-[var(--nord3)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--frost3)] focus-visible:ring-offset-2 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
    >
        <slot />
    </button>
</template>
