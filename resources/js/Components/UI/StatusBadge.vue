<script setup>
const variants = {
    active: 'bg-[var(--aurora-green)]/10 text-[var(--aurora-green)]',
    inactive: 'bg-[var(--aurora-orange)]/10 text-[var(--aurora-orange)]',
    blocked: 'bg-[var(--aurora-red)]/10 text-[var(--aurora-red)]',
    director: 'bg-[var(--frost4)]/10 text-[var(--frost4)]',
    specialist: 'bg-[var(--aurora-yellow)]/10 text-[var(--aurora-orange)]',
    reception: 'bg-[var(--aurora-purple)]/10 text-[var(--aurora-purple)]',
};

defineProps({
    label: { type: String, required: true },
    variant: { type: String, default: 'active' },
});
</script>

<template>
    <span
        :class="[
            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
            variants[variant] || variants.active,
        ]"
    >
        {{ label }}
    </span>
</template>
