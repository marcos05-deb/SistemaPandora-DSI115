<script setup>
defineProps({
    type: { type: String, default: 'button' },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-lg border border-[var(--nord4)] bg-white px-5 py-2.5 text-sm font-medium text-[var(--nord3)] shadow-sm hover:bg-[var(--nord6)] hover:text-[var(--nord0)] focus:outline-none focus:ring-2 focus:ring-[var(--frost3)] focus:ring-offset-2 transition-all duration-200"
    >
        <slot />
    </button>
</template>
