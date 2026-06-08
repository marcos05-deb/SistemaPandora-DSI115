<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const message = computed(() => page.props.flash?.message ?? null);
const variant = computed(() => page.props.flash?.variant ?? 'success');

const classes = computed(() => {
    const map = {
        success: 'border-[var(--aurora-green)] text-[var(--aurora-green)] bg-[var(--aurora-green)]/5',
        info: 'border-[var(--frost3)] text-[var(--frost4)] bg-[var(--frost3)]/5',
        warning: 'border-[var(--aurora-yellow)] text-[var(--aurora-orange)] bg-[var(--aurora-yellow)]/5',
        error: 'border-[var(--aurora-red)] text-[var(--aurora-red)] bg-[var(--aurora-red)]/5',
    };
    return map[variant.value] ?? map.success;
});
</script>

<template>
    <div
        v-if="message"
        :class="['mb-6 rounded-lg border px-4 py-3 text-sm', classes]"
        role="status"
    >
        {{ message }}
    </div>
</template>
