<script setup>
defineProps({
    type: { type: String, default: 'button' },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-lg bg-[var(--nord10)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--nord9)] focus:outline-none focus:ring-2 focus:ring-[var(--frost3)] focus:ring-offset-2 transition-all duration-200 hover:shadow-md"
    >
        <slot />
    </button>
</template>
