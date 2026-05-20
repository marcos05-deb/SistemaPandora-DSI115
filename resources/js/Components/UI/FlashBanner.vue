<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const message = computed(() => page.props.flash?.message ?? null);
const variant = computed(() => page.props.flash?.variant ?? 'success');

const classes = computed(() => {
    const map = {
        success: 'bg-green-50 border-green-200 text-green-900',
        info: 'bg-blue-50 border-blue-200 text-blue-900',
        warning: 'bg-orange-50 border-orange-200 text-orange-900',
        error: 'bg-red-50 border-red-200 text-red-900',
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
