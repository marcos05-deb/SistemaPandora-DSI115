<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { formatLocalDate } from '@/utils/dates';
import FieldTooltip from '@/Components/UI/FieldTooltip.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';

defineOptions({
    layout: (props) => [props.layout === 'admin' ? AdminLayout : ClinicalLayout],
});

const props = defineProps({
    layout: { type: String, required: true },
    resultados: { type: Object, required: true },
});

const page = usePage();
const rangoMaxDias = computed(() => page.props.limites?.analitica_rango_dias ?? 366);
const minCelda = computed(() => props.resultados?.minimo_celda ?? page.props.privacidad?.min_celda);
const pageError = ref('');
const errorSummary = ref(null);
const form = useForm({
    desde: props.resultados.filtros.desde,
    hasta: props.resultados.filtros.hasta,
    area_id: props.resultados.filtros.area_id === null ? '' : String(props.resultados.filtros.area_id),
    indicadores: [...props.resultados.filtros.indicadores],
});

const selectedCatalog = computed(() => props.resultados.catalogo.filter((item) => props.resultados.filtros.indicadores.includes(item.id)));
const totals = computed(() => props.resultados.totales);
const rows = computed(() => props.resultados.filas);
const categories = computed(() => [...new Set(selectedCatalog.value.map((item) => item.categoria))]);
const formatter = new Intl.NumberFormat('es-SV', { maximumFractionDigits: 1 });

const categoryLabels = {
    atencion: 'Atención',
    asistencia: 'Citas y asistencia',
    expedientes: 'Expedientes',
    riesgo_preventivo: 'Riesgo preventivo',
};

function submitFilters() {
    pageError.value = '';
    form.get(route('indicadores.index'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => nextTick(() => errorSummary.value?.focus()),
        onHttpException: (response) => {
            pageError.value = response.status === 403
                ? 'No tienes acceso a estos indicadores.'
                : 'No fue posible consultar los indicadores.';
        },
        onNetworkError: () => {
            pageError.value = 'No se pudo conectar. Revisa tu conexión e inténtalo de nuevo.';
        },
    });
}

function resetFilters() {
    router.get(route('indicadores.index'), {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

function indicatorRows(indicatorId) {
    return rows.value.filter((row) => row.indicador === indicatorId);
}

function indicatorTotal(indicatorId) {
    return totals.value[indicatorId];
}

function displayValue(item, unit = 'conteo') {
    if (item.suprimido) {
        return 'Oculto';
    }

    const value = formatter.format(item.valor ?? 0);
    return unit === 'porcentaje' ? `${value}%` : value;
}

function barWidth(item, indicatorId) {
    if (item.suprimido || item.valor === null) return 0;
    const values = indicatorRows(indicatorId).map((row) => row.valor).filter((value) => value !== null);
    const max = Math.max(...values, 0);
    if (max <= 0) return 0;
    return Math.max(3, Math.round((item.valor / max) * 100));
}

function errorMessage(field) {
    return form.errors[field] || form.errors[`${field}.0`] || '';
}

function inputDescription(field) {
    return errorMessage(field) ? `${field}-error` : undefined;
}

function formatDate(value) {
    return formatLocalDate(value, { dateStyle: 'medium' }, undefined, '-');
}
</script>

<template>
    <Head title="Indicadores institucionales" />

    <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight text-pd-text-primary sm:text-3xl">Indicadores de bienestar</h1>
                <p class="max-w-3xl text-sm leading-relaxed text-pd-text-secondary">
                    Consulta tendencias agregadas. Los resultados no incluyen pacientes, identificadores ni notas clínicas.
                </p>
            </div>
            <span class="rounded-full bg-pd-brand-soft px-3 py-1.5 text-xs font-semibold text-pd-brand">Umbral mínimo · {{ minCelda }} casos</span>
        </header>

        <section aria-labelledby="filters-title" class="rounded-xl border border-pd-border bg-pd-surface p-4  sm:p-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="filters-title" class="text-lg font-semibold text-pd-text-primary">Filtros</h2>
                    <p class="mt-1 text-xs text-pd-text-muted">Período máximo: {{ rangoMaxDias }} días. Se usan días calendario de El Salvador.</p>
                </div>
                <button type="button" class="min-h-10 rounded-lg px-3 text-sm font-medium text-pd-text-secondary underline decoration-pd-border underline-offset-4 hover:text-pd-text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand" @click="resetFilters">
                    Restablecer
                </button>
            </div>

            <div v-if="Object.keys(form.errors).length" ref="errorSummary" role="alert" tabindex="-1" class="mb-4 rounded-xl border border-pd-danger/40 bg-pd-danger/10 p-3 text-sm text-pd-text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-pd-danger">
                <h3 class="font-semibold">Revisa los filtros</h3>
                <ul class="mt-1 list-inside list-disc">
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
            </div>

            <div v-if="pageError" role="alert" class="mb-4 rounded-xl border border-pd-danger/40 bg-pd-danger/10 p-3 text-sm text-pd-text-primary">
                {{ pageError }}
            </div>

            <form class="space-y-5" @submit.prevent="submitFilters">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="indicator-from" class="mb-1.5 block text-sm font-medium text-pd-text-primary">Desde</label>
                        <input id="indicator-from" v-model="form.desde" type="date" required :aria-invalid="Boolean(form.errors.desde)" :aria-describedby="inputDescription('desde')" class="min-h-11 w-full rounded-lg border border-pd-border bg-pd-surface px-3 text-sm text-pd-text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand" />
                        <p v-if="form.errors.desde" id="desde-error" class="mt-1 text-sm text-pd-danger">{{ form.errors.desde }}</p>
                    </div>
                    <div>
                        <label for="indicator-through" class="mb-1.5 block text-sm font-medium text-pd-text-primary">Hasta</label>
                        <input id="indicator-through" v-model="form.hasta" type="date" required :aria-invalid="Boolean(form.errors.hasta)" :aria-describedby="inputDescription('hasta')" class="min-h-11 w-full rounded-lg border border-pd-border bg-pd-surface px-3 text-sm text-pd-text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand" />
                        <p v-if="form.errors.hasta" id="hasta-error" class="mt-1 text-sm text-pd-danger">{{ form.errors.hasta }}</p>
                    </div>
                    <div>
                        <label for="indicator-area" class="mb-1.5 block text-sm font-medium text-pd-text-primary">Área</label>
                        <select id="indicator-area" v-model="form.area_id" :aria-invalid="Boolean(form.errors.area_id)" :aria-describedby="inputDescription('area_id')" class="min-h-11 w-full rounded-lg border border-pd-border bg-pd-surface px-3 text-sm text-pd-text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand">
                        <option value="">{{ resultados.areas_disponibles.length > 1 ? 'Todas las áreas permitidas' : resultados.areas_disponibles[0]?.nombre || 'Área' }}</option>
                        <option v-for="area in resultados.areas_disponibles" :key="area.id" :value="String(area.id)">{{ area.nombre }}</option>
                        </select>
                        <p v-if="form.errors.area_id" id="area_id-error" class="mt-1 text-sm text-pd-danger">{{ form.errors.area_id }}</p>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-pd-text-primary">Indicadores</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="item in resultados.catalogo" :key="item.id" class="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-pd-border bg-pd-surface px-3 py-2 text-sm font-medium text-pd-text-primary transition hover:bg-pd-surface-subtle has-[:checked]:border-pd-brand/40 has-[:checked]:bg-pd-brand-soft has-[:checked]:text-pd-brand focus-within:outline focus-within:outline-2 focus-within:outline-pd-brand">
                            <input v-model="form.indicadores" type="checkbox" :value="item.id" class="mt-0.5 rounded border-pd-text-muted text-pd-brand focus:ring-pd-brand" />
                            <span class="leading-snug">{{ item.etiqueta }}</span>
                        </label>
                    </div>
                    <p v-if="errorMessage('indicadores')" id="indicadores-error" class="mt-1 text-sm text-pd-danger">{{ errorMessage('indicadores') }}</p>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 border-t border-pd-border pt-4">
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-pd-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-pd-brand/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pd-brand disabled:cursor-not-allowed disabled:opacity-60">
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        {{ form.processing ? 'Calculando…' : 'Aplicar filtros' }}
                    </button>
                    <span v-if="form.processing" role="status" class="text-sm text-pd-text-muted">Actualizando resultados</span>
                    <span class="text-xs text-pd-text-muted">{{ formatDate(resultados.filtros.desde) }} — {{ formatDate(resultados.filtros.hasta) }}</span>
                </div>
            </form>
        </section>

        <section aria-labelledby="results-title" aria-live="polite" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 id="results-title" class="text-xl font-semibold text-pd-text-primary">Resultados agregados</h2>
                    <p class="mt-1 text-xs text-pd-text-muted">Por privacidad, los grupos con menos de {{ minCelda }} casos y los totales que permitirían deducirlos se ocultan.</p>
                </div>
                <span class="rounded-full bg-pd-brand-soft px-3 py-1.5 text-xs font-semibold uppercase text-pd-brand">{{ resultados.areas.length }} {{ resultados.areas.length === 1 ? 'área' : 'áreas' }} en el alcance</span>
            </div>

            <div v-if="resultados.sin_datos" class="rounded-xl border border-pd-border bg-pd-surface p-8 text-center ">
                <p class="text-lg font-semibold text-pd-text-primary">Sin datos en el período</p>
                <p class="mt-1 text-sm text-pd-text-muted">Prueba otro rango de fechas o área.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="item in selectedCatalog" :key="item.id" class="rounded-xl border border-pd-border bg-pd-surface p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-pd-text-muted">{{ categoryLabels[item.categoria] || item.categoria }}</p>
                            <h3 class="mt-1 text-sm font-semibold text-pd-text-primary">{{ item.etiqueta }}</h3>
                        </div>
                        <FieldTooltip :text="`${item.definicion} Fórmula: ${item.formula}. Fuente: ${item.fuente}.`" />
                    </div>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-pd-text-primary">
                        {{ displayValue(indicatorTotal(item.id), indicatorTotal(item.id).unidad) }}
                    </p>
                    <p v-if="indicatorTotal(item.id).suprimido" class="mt-1 text-xs text-pd-text-muted">
                        {{ indicatorTotal(item.id).supresion === 'primaria' ? 'Oculto porque el grupo es pequeño' : 'Oculto para evitar deducir otros conteos' }}
                    </p>
                    <p v-else class="mt-1 text-xs text-pd-text-muted">{{ item.definicion }}</p>
                </article>
            </div>

            <div v-for="category in categories" :key="category" class="space-y-4">
                <h3 class="pt-2 text-lg font-semibold text-pd-text-primary">{{ categoryLabels[category] || category }}</h3>
                <section v-for="item in selectedCatalog.filter((entry) => entry.categoria === category)" :key="item.id" class="overflow-hidden rounded-xl border border-pd-border bg-pd-surface ">
                    <div class="border-b border-pd-border px-4 py-3 sm:px-5">
                        <h4 class="font-semibold text-pd-text-primary">{{ item.etiqueta }}</h4>
                        <p class="mt-0.5 text-xs text-pd-text-muted">{{ item.definicion }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] border-collapse text-left text-sm">
                            <caption class="sr-only">{{ item.etiqueta }} por área{{ item.id === 'alertas_generadas' ? ' y señal' : '' }}</caption>
                            <thead class="bg-pd-surface-header text-xs tracking-wide text-pd-text-secondary">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-semibold">Área</th>
                                    <th v-if="item.id === 'alertas_generadas'" scope="col" class="px-4 py-3 font-semibold">Señal</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold">{{ item.id === 'tasa_asistencia' ? 'Tasa' : 'Total' }}</th>
                                    <th scope="col" class="w-40 px-4 py-3 font-semibold"><span class="sr-only">Visualización</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-pd-border">
                                <tr v-for="row in indicatorRows(item.id)" :key="`${row.area_id}-${row.segmento || 'all'}`" class="hover:bg-pd-surface-subtle/70">
                                    <th scope="row" class="px-4 py-3 font-medium text-pd-text-primary">{{ row.area }}</th>
                                    <td v-if="item.id === 'alertas_generadas'" class="px-4 py-3 text-pd-text-secondary">{{ row.segmento_etiqueta || row.segmento }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-pd-text-primary">
                                        <span v-if="row.suprimido" :title="row.supresion === 'primaria' ? 'Oculto porque el grupo es pequeño' : 'Oculto para evitar que se deduzcan otros conteos'">
                                            {{ displayValue(row, indicatorTotal(item.id).unidad) }}
                                            <span class="sr-only">{{ row.supresion === 'primaria' ? 'Oculto porque el grupo es pequeño' : 'Oculto para evitar que se deduzcan otros conteos' }}</span>
                                        </span>
                                        <template v-else>{{ displayValue(row, indicatorTotal(item.id).unidad) }}</template>
                                    </td>
                                    <td class="px-4 py-3">
                                        <svg v-if="!row.suprimido" class="h-3 w-full" viewBox="0 0 100 12" preserveAspectRatio="none" role="img" :aria-label="`${row.area}: ${displayValue(row, indicatorTotal(item.id).unidad)}`">
                                            <title>{{ row.area }}: {{ displayValue(row, indicatorTotal(item.id).unidad) }}</title>
                                            <rect x="0" y="2" width="100" height="8" rx="4" fill="var(--pd-border)" />
                                            <rect x="0" y="2" :width="barWidth(row, item.id)" height="8" rx="4" fill="var(--pd-brand)" />
                                        </svg>
                                        <span v-else class="text-xs text-pd-text-muted">Oculto por privacidad</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t border-pd-border bg-pd-surface-subtle">
                                <tr>
                                    <th scope="row" :colspan="item.id === 'alertas_generadas' ? 2 : 1" class="px-4 py-3 text-sm font-semibold text-pd-text-primary">Total del alcance</th>
                                    <td class="px-4 py-3 text-right text-sm font-bold tabular-nums text-pd-text-primary">
                                        {{ displayValue(indicatorTotal(item.id), indicatorTotal(item.id).unidad) }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-pd-text-muted">
                                        <span v-if="indicatorTotal(item.id).suprimido">{{ indicatorTotal(item.id).supresion === 'primaria' ? 'Oculto porque el grupo es pequeño' : 'Oculto para evitar deducir otros conteos' }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="border-t border-pd-border bg-pd-surface-subtle px-4 py-3 text-xs text-pd-text-muted sm:px-5">{{ item.formula }} · Fuente: {{ item.fuente }}. Los valores ocultos no se representan en las barras.</p>
                </section>
            </div>
        </section>
    </main>
</template>
