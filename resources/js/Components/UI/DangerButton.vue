<script setup>
defineProps({
    type: { type: String, default: 'button' },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center rounded-lg bg-[var(--aurora-red)] px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#A05058] focus:outline-none focus:ring-2 focus:ring-[var(--aurora-red)] focus:ring-offset-2 transition-all duration-200 hover:shadow-md"
    >
        <slot />
    </button>
</template>
