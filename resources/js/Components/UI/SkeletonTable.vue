<script setup>
defineProps({
    rows: { type: Number, default: 5 },
    cols: { type: Number, default: 4 },
});
</script>

<template>
    <div class="bg-white shadow-sm border border-[var(--nord4)] rounded-[10px] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-[var(--surface-header)]">
                        <th v-for="c in cols" :key="c" class="py-[12px] px-[16px]">
                            <div class="h-3 w-16 bg-[var(--nord4)] rounded-full animate-skeleton" />
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--nord5)]">
                    <tr v-for="r in rows" :key="r">
                        <td v-for="c in cols" :key="c" class="py-[12px] px-[16px]">
                            <div v-if="c === 1" class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-[var(--nord4)] animate-skeleton shrink-0" />
                                <div class="space-y-1.5">
                                    <div class="h-3 rounded-full animate-skeleton" :class="`w-${20 + (r * 7) % 30}`" :style="{ width: `${120 + (r * 20) % 60}px`, backgroundColor: 'var(--nord4)' }" />
                                    <div class="h-2 w-12 rounded-full bg-[var(--nord4)] animate-skeleton" />
                                </div>
                            </div>
                            <div v-else class="h-3 rounded-full animate-skeleton" :class="{ 'w-full': c > 1 }" :style="{ width: `${60 + (r + c) * 15 % 40}%`, backgroundColor: 'var(--nord4)' }" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
