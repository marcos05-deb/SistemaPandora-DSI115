<script setup>
const variants = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-orange-100 text-orange-800',
    blocked: 'bg-red-100 text-red-800',
    director: 'bg-blue-100 text-blue-800',
    specialist: 'bg-amber-100 text-amber-800',
    reception: 'bg-purple-100 text-purple-800',
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
