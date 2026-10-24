<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({
    layout: (props) => [props.layout === 'admin' ? AdminLayout : ClinicalLayout],
});

const props = defineProps({
    layout: { type: String, required: true },
    areas: { type: Array, default: () => [] },
    predeterminados: { type: Object, required: true },
    puede_todas_areas: { type: Boolean, default: false },
    catalogo: { type: Array, default: () => [] },
    agrupaciones: { type: Array, default: () => [] },
    reporte: { type: Object, default: null },
});

const page = usePage();
const pageError = ref('');
const exportError = ref('');
const exportSuccess = ref('');
const exporting = ref('');
const errorSummary = ref(null);
const form = useForm({
    desde: props.reporte?.parametros_aplicados.desde ?? props.predeterminados.desde,
    hasta: props.reporte?.parametros_aplicados.hasta ?? props.predeterminados.hasta,
    area: String(props.reporte?.parametros_aplicados.area ?? props.predeterminados.area),
    indicadores: [...(props.reporte?.parametros_aplicados.indicadores ?? props.predeterminados.indicadores)],
    agrupar_por: [...(props.reporte?.parametros_aplicados.agrupar_por ?? props.predeterminados.agrupar_por)],
});

const canExport = computed(() => Boolean(props.reporte) && Boolean(page.props.can?.exportar));
const formatter = new Intl.NumberFormat('es-SV', { maximumFractionDigits: 1 });
const groupLabels = {
    indicador: 'Indicador',
    area: 'Área',
    mes: 'Mes',
    semana: 'Semana desde',
};
const dimensionHeaders = computed(() => form.agrupar_por.map((group) => ({ id: group, label: groupLabels[group] || group })));

function generateReport() {
    pageError.value = '';
    exportError.value = '';
    exportSuccess.value = '';
    form.get('/reportes/generar', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => nextTick(() => errorSummary.value?.focus()),
        onHttpException: (response) => {
            pageError.value = response.status === 403
                ? 'No tienes permiso para generar un reporte con esos parámetros.'
                : 'No fue posible generar el reporte.';
        },
        onNetworkError: () => {
            pageError.value = 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.';
        },
    });
}

function exportQuery() {
    const params = new URLSearchParams();
    params.set('desde', form.desde);
    params.set('hasta', form.hasta);
    params.set('area', form.area);
    form.indicadores.forEach((id) => params.append('indicadores[]', id));
    form.agrupar_por.forEach((id) => params.append('agrupar_por[]', id));
    return params.toString();
}

async function downloadExport(formato) {
    if (!canExport.value || exporting.value) {
        return;
    }
    exportError.value = '';
    exportSuccess.value = '';
    exporting.value = formato;
    const path = formato === 'pdf' ? '/reportes/exportar/pdf' : '/reportes/exportar/excel';
    try {
        const response = await fetch(`${path}?${exportQuery()}`, {
            headers: { Accept: 'application/octet-stream', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (response.status === 403) {
            exportError.value = 'No tienes permiso para exportar con esos parámetros.';
            return;
        }
        if (response.status === 422) {
            const payload = await response.json().catch(() => ({}));
            const first = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            exportError.value = first || 'Revisa los parámetros antes de exportar.';
            return;
        }
        if (!response.ok) {
            exportError.value = 'No fue posible generar el archivo de exportación.';
            return;
        }
        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match?.[1] || `reporte.${formato === 'pdf' ? 'pdf' : 'xlsx'}`;
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = filename;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        URL.revokeObjectURL(url);
        exportSuccess.value = formato === 'pdf' ? 'PDF descargado.' : 'Excel descargado.';
    } catch {
        exportError.value = 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.';
    } finally {
        exporting.value = '';
    }
}

function resetForm() {
    router.get('/reportes', {}, { preserveState: false, preserveScroll: true, replace: true });
}

function formatValue(value) {
    if (!value || value.suprimido) {
        if (!value) return '0';
        return value.supresion === 'primaria' ? `< ${reporte.value?.minimo_celda ?? 5}` : 'Protegido';
    }

    const formatted = formatter.format(value.valor ?? 0);
    return value.unidad === 'porcentaje' ? `${formatted}%` : formatted;
}

const reporte = computed(() => props.reporte);
const appliedDimensions = computed(() => reporte.value?.parametros_aplicados.agrupar_por ?? []);
const tableDimensions = computed(() => appliedDimensions.value.map((id) => ({ id, label: groupLabels[id] || id })));
const reportColumns = computed(() => reporte.value?.columnas ?? []);
const groupedByIndicator = computed(() => appliedDimensions.value.includes('indicador'));

function dimensionValue(group, dimension) {
    return group.dimensiones[dimension === 'indicador' ? 'indicador_etiqueta' : dimension] ?? '—';
}

function areaLabel(area) {
    if (area === 'todas') return 'Todas las áreas permitidas';
    return props.areas.find((item) => String(item.id) === String(area))?.nombre || 'Área seleccionada';
}

function error(field) {
    return form.errors[field] || form.errors[`${field}.0`] || '';
}

function dateLabel(value) {
    return new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));
}
</script>

<template>
    <Head title="Reportes parametrizados" />

    <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <header class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[var(--frost3)]">Analítica institucional</p>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--nord0)] sm:text-3xl">Reportes parametrizados</h1>
            <p class="max-w-3xl text-sm leading-relaxed text-[var(--nord3)]">Genera comparaciones agregadas bajo demanda. Los parámetros quedan en la URL para reproducir el reporte.</p>
        </header>

        <section aria-labelledby="report-filters-title" class="rounded-2xl border border-[var(--nord4)] bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="report-filters-title" class="text-lg font-semibold text-[var(--nord0)]">Parámetros del reporte</h2>
                    <p class="mt-1 text-xs text-[var(--nord3)]">Área e indicadores son obligatorios. El período máximo es de 366 días.</p>
                </div>
                <button type="button" class="min-h-10 rounded-lg px-3 text-sm font-medium text-[var(--nord2)] underline decoration-[var(--nord4)] underline-offset-4 hover:text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]" @click="resetForm">
                    Restablecer
                </button>
            </div>

            <div v-if="Object.keys(form.errors).length" ref="errorSummary" role="alert" tabindex="-1" class="mb-4 rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--aurora-red)]">
                <h3 class="font-semibold">Revisa los parámetros</h3>
                <ul class="mt-1 list-inside list-disc"><li v-for="(message, field) in form.errors" :key="field">{{ message }}</li></ul>
            </div>
            <div v-if="pageError" role="alert" class="mb-4 rounded-xl border border-[var(--aurora-red)]/40 bg-[var(--aurora-red)]/10 p-3 text-sm text-[var(--nord0)]">{{ pageError }}</div>

            <form class="space-y-5" @submit.prevent="generateReport">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="report-from" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Desde</label>
                        <input id="report-from" v-model="form.desde" type="date" required :aria-invalid="Boolean(form.errors.desde)" aria-describedby="report-from-error" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]" />
                        <p v-if="form.errors.desde" id="report-from-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.desde }}</p>
                    </div>
                    <div>
                        <label for="report-through" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Hasta</label>
                        <input id="report-through" v-model="form.hasta" type="date" required :aria-invalid="Boolean(form.errors.hasta)" aria-describedby="report-through-error" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]" />
                        <p v-if="form.errors.hasta" id="report-through-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.hasta }}</p>
                    </div>
                    <div>
                        <label for="report-area" class="mb-1.5 block text-sm font-medium text-[var(--nord1)]">Área</label>
                        <select id="report-area" v-model="form.area" required :aria-invalid="Boolean(form.errors.area)" aria-describedby="report-area-error" class="min-h-11 w-full rounded-lg border border-[var(--nord4)] bg-white px-3 text-sm text-[var(--nord0)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)]">
                            <option v-if="puede_todas_areas" value="todas">Todas las áreas</option>
                            <option v-for="area in areas" :key="area.id" :value="String(area.id)">{{ area.nombre }}</option>
                        </select>
                        <p v-if="form.errors.area" id="report-area-error" class="mt-1 text-sm text-[var(--aurora-red)]">{{ form.errors.area }}</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-[var(--nord1)]">Indicadores</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="item in catalogo" :key="item.id" class="flex min-h-11 cursor-pointer items-start gap-2 rounded-lg border border-[var(--nord4)] px-3 py-2.5 text-sm text-[var(--nord1)] hover:bg-[var(--nord6)] focus-within:outline focus-within:outline-2 focus-within:outline-[var(--frost3)]">
                            <input v-model="form.indicadores" type="checkbox" :value="item.id" class="mt-0.5 rounded border-[var(--nord3)] text-[var(--frost3)] focus:ring-[var(--frost3)]" />
                            <span class="leading-snug">{{ item.etiqueta }}</span>
                        </label>
                    </div>
                    <p v-if="error('indicadores')" class="mt-1 text-sm text-[var(--aurora-red)]">{{ error('indicadores') }}</p>
                </fieldset>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-[var(--nord1)]">Agrupar por (máximo dos)</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <label v-for="group in agrupaciones" :key="group.id" class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-[var(--nord4)] px-3 py-2.5 text-sm text-[var(--nord1)] hover:bg-[var(--nord6)] focus-within:outline focus-within:outline-2 focus-within:outline-[var(--frost3)]">
                            <input v-model="form.agrupar_por" type="checkbox" :value="group.id" :disabled="form.agrupar_por.length >= 2 && !form.agrupar_por.includes(group.id)" class="rounded border-[var(--nord3)] text-[var(--frost3)] focus:ring-[var(--frost3)] disabled:opacity-50" />
                            <span>{{ group.etiqueta }}</span>
                        </label>
                    </div>
                    <p v-if="error('agrupar_por')" class="mt-1 text-sm text-[var(--aurora-red)]">{{ error('agrupar_por') }}</p>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 border-t border-[var(--nord5)] pt-4">
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[var(--nord10)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)] disabled:cursor-not-allowed disabled:opacity-60">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        {{ form.processing ? 'Generando…' : 'Generar reporte' }}
                    </button>
                    <span v-if="form.processing" role="status" class="text-sm text-[var(--nord3)]">Generando reporte</span>
                </div>
            </form>
        </section>

        <section v-if="reporte" aria-labelledby="report-results-title" aria-live="polite" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="report-results-title" class="text-xl font-semibold text-[var(--nord0)]">Reporte generado</h2>
                    <p class="mt-1 text-xs text-[var(--nord3)]">{{ dateLabel(reporte.parametros_aplicados.desde) }} — {{ dateLabel(reporte.parametros_aplicados.hasta) }} · {{ areaLabel(reporte.parametros_aplicados.area) }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-[var(--nord3)]">
                        Indicadores: {{ reporte.columnas.map((column) => column.etiqueta).join(', ') }} ·
                        Agrupar por: {{ reporte.parametros_aplicados.agrupar_por.map((group) => groupLabels[group]).join(' + ') }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <p class="text-xs text-[var(--nord3)]">Generado por {{ reporte.generado_por }} · {{ new Intl.DateTimeFormat('es-SV', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(reporte.generado_en)) }}</p>
                    <div v-if="canExport" class="flex flex-wrap justify-end gap-2">
                        <button type="button" :disabled="Boolean(exporting)" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[var(--nord4)] bg-white px-4 py-2 text-sm font-semibold text-[var(--nord1)] transition hover:bg-[var(--nord6)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)] disabled:cursor-not-allowed disabled:opacity-60" @click="downloadExport('pdf')">
                            <svg v-if="exporting === 'pdf'" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            {{ exporting === 'pdf' ? 'Generando PDF…' : 'Descargar PDF' }}
                        </button>
                        <button type="button" :disabled="Boolean(exporting)" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[var(--nord4)] bg-white px-4 py-2 text-sm font-semibold text-[var(--nord1)] transition hover:bg-[var(--nord6)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--frost3)] disabled:cursor-not-allowed disabled:opacity-60" @click="downloadExport('excel')">
                            <svg v-if="exporting === 'excel'" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            {{ exporting === 'excel' ? 'Generando Excel…' : 'Descargar Excel' }}
                        </button>
                    </div>
                    <p v-if="exportSuccess" role="status" class="text-sm text-[var(--aurora-green)]">{{ exportSuccess }}</p>
                    <p v-if="exportError" role="alert" class="text-sm text-[var(--aurora-red)]">{{ exportError }}</p>
                </div>
            </div>

            <p v-if="reporte.sin_datos" class="rounded-xl border border-[var(--nord4)] bg-white p-5 text-sm text-[var(--nord2)]">Sin datos en el período; los totales son cero.</p>

            <section aria-labelledby="report-totals-title" class="rounded-2xl border border-[var(--nord4)] bg-white p-4 shadow-sm">
                <h3 id="report-totals-title" class="mb-3 text-base font-semibold text-[var(--nord0)]">Totales del período</h3>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="column in reporte.columnas" :key="column.id" class="rounded-xl border border-[var(--nord5)] p-3">
                        <p class="text-xs font-medium text-[var(--nord3)]">{{ column.etiqueta }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-[var(--nord0)]">{{ formatValue(reporte.totales[column.id]) }}</p>
                    </article>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-[var(--nord4)] bg-white shadow-sm">
                <div class="border-b border-[var(--nord5)] px-4 py-3 sm:px-5">
                    <h3 class="font-semibold text-[var(--nord0)]">Grupos</h3>
                    <p class="mt-0.5 text-xs text-[var(--nord3)]">Los conteos menores que {{ reporte.minimo_celda }} se suprimen igual que en HU-18.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[650px] border-collapse text-left text-sm">
                        <caption class="sr-only">Resultados del reporte agrupados por los parámetros seleccionados</caption>
                        <thead class="bg-[var(--nord6)] text-xs uppercase tracking-wide text-[var(--nord2)]">
                            <tr>
                                <th v-for="dimension in tableDimensions" :key="dimension.id" scope="col" class="px-4 py-3 font-semibold">{{ groupLabels[dimension.id] }}</th>
                                <th v-if="groupedByIndicator" scope="col" class="px-4 py-3 text-right font-semibold">Total</th>
                                <th v-else v-for="column in reportColumns" :key="column.id" scope="col" class="px-4 py-3 text-right font-semibold">{{ column.etiqueta }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--nord5)]">
                            <tr v-for="(group, index) in reporte.grupos" :key="index" class="hover:bg-[var(--nord6)]/70">
                                <th v-for="(dimension, dimensionIndex) in tableDimensions" :key="dimension.id" :scope="dimensionIndex === 0 ? 'row' : undefined" class="px-4 py-3 font-medium text-[var(--nord1)]">{{ dimensionValue(group, dimension.id) }}</th>
                                <td v-if="groupedByIndicator" class="px-4 py-3 text-right font-semibold tabular-nums text-[var(--nord0)]">{{ formatValue(group.valores[group.dimensiones.indicador]) }}</td>
                                <td v-else v-for="column in reportColumns" :key="column.id" class="px-4 py-3 text-right font-semibold tabular-nums text-[var(--nord0)]">{{ formatValue(group.valores[column.id]) }}</td>
                            </tr>
                            <tr v-if="reporte.grupos.length === 0"><td :colspan="tableDimensions.length + reportColumns.length" class="px-4 py-8 text-center text-sm text-[var(--nord3)]">No hay grupos para los parámetros seleccionados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</template>
