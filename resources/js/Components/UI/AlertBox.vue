<script setup>
const variants = {
    warning: 'border-[var(--aurora-yellow)] text-[var(--aurora-orange)] bg-[var(--aurora-yellow)]/5',
    info: 'border-[var(--frost3)] text-[var(--frost4)] bg-[var(--frost3)]/5',
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
