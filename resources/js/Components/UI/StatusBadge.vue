<script setup>
const variants = {
    active:    { cls: 'bg-[var(--aurora-green)]/10 text-[var(--aurora-green)] border border-[var(--aurora-green)]/25', dot: true },
    inactive:  { cls: 'bg-[var(--aurora-orange)]/10 text-[var(--aurora-orange)] border border-[var(--aurora-orange)]/25', dot: false },
    blocked:   { cls: 'bg-[var(--aurora-red)]/10 text-[var(--aurora-red)] border border-[var(--aurora-red)]/25', dot: false },
    director:  { cls: 'bg-[var(--frost4)]/10 text-[var(--frost4)] border border-[var(--frost4)]/25', dot: false },
    specialist:{ cls: 'bg-[var(--aurora-yellow)]/10 text-[var(--aurora-orange)] border border-[var(--aurora-yellow)]/25', dot: false },
    reception: { cls: 'bg-[var(--aurora-purple)]/10 text-[var(--aurora-purple)] border border-[var(--aurora-purple)]/25', dot: false },
};

defineProps({
    label: { type: String, required: true },
    variant: { type: String, default: 'active' },
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
            (variants[variant] || variants.active).cls,
        ]"
    >
        <span
            v-if="(variants[variant] || variants.active).dot"
            class="w-1.5 h-1.5 rounded-full bg-current animate-pulse shrink-0"
        ></span>
        {{ label }}
    </span>
</template>
