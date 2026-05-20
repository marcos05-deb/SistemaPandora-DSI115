<script setup>
const variants = {
    warning: 'bg-orange-50 border-orange-200 text-orange-900',
    info: 'bg-blue-50 border-blue-200 text-blue-900',
};

defineProps({
    variant: { type: String, default: 'info' },
});
</script>

<template>
    <div
        :class="[
            'rounded-lg border px-4 py-3 text-sm leading-relaxed',
            variants[variant] || variants.info,
        ]"
    >
        <slot />
    </div>
</template>
