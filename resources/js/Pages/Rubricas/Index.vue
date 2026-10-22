<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import StatusBadge from '@/Components/UI/StatusBadge.vue';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import { formatElSalvadorDateTime, formatLocalDate } from '@/utils/dates';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    versions: { type: Object, default: () => ({ data: [], links: [] }) },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.variant === 'error' ? page.props.flash.message : '');
const versionRows = computed(() => props.versions?.data ?? []);
const paginationLinks = computed(() => props.versions?.links ?? []);

function status(version) {
    return {
        vigente: { label: 'Vigente', variant: 'active' },
        programada: { label: 'Programada', variant: 'inactive' },
        historica: { label: 'Histórica', variant: 'specialist' },
        demo: { label: 'DEMO', variant: 'inactive' },
    }[version.estado_vigencia] ?? { label: 'Sin vigencia', variant: 'inactive' };
}

function pageLabel(link, index) {
    if (index === 0) return 'Anterior';
    if (index === paginationLinks.value.length - 1) return 'Siguiente';
    return link.label;
}
</script>

<template>
    <Head title="Rúbrica de riesgo" />

    <div class="space-y-5">
        <Breadcrumbs :items="[
            { label: 'Panel clínico', href: '/dashboard' },
            { label: 'Rúbrica de riesgo' },
        ]" />

        <header class="flex flex-col gap-4 rounded-xl border border-[var(--nord4)] bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-[var(--nord0)]">Rúbrica institucional de riesgo</h1>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-[var(--nord3)]">
                    Consulta las versiones validadas y conserva el historial de cada umbral clínico.
                </p>
            </div>
            <Link
                v-if="page.props.clinicalPermissions?.crear"
                href="/rubricas/versiones/crear"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-[var(--nord10)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[var(--nord9)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
            >
                Crear versión
            </Link>
        </header>

        <div v-if="flashError" role="alert" class="rounded-lg border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/10 px-4 py-3 text-sm text-[var(--nord0)]">
            {{ flashError }}
        </div>

        <section aria-labelledby="versions-heading" class="overflow-hidden rounded-xl border border-[var(--nord4)] bg-white shadow-sm">
            <div class="border-b border-[var(--nord4)] bg-[var(--surface-header)] px-5 py-4">
                <h2 id="versions-heading" class="text-base font-semibold text-[var(--nord0)]">Versiones registradas</h2>
            </div>

            <div v-if="versionRows.length === 0" class="px-5 py-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[var(--surface-subtle)] text-[var(--nord10)]" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-semibold text-[var(--nord0)]">Aún no hay versiones</h3>
                <p class="mx-auto mt-1 max-w-md text-sm leading-relaxed text-[var(--nord3)]">
                    Registra una versión con su fecha y referencia de validación para iniciar el historial.
                </p>
                <Link
                    v-if="page.props.clinicalPermissions?.crear"
                    href="/rubricas/versiones/crear"
                    class="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg border border-[var(--nord10)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] transition-colors hover:bg-[var(--nord10)]/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                >
                    Crear primera versión
                </Link>
            </div>

            <ol v-else class="divide-y divide-[var(--nord5)]">
                <li v-for="version in versionRows" :key="version.id" class="p-4 sm:p-5">
                    <article class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-[var(--nord0)]">Versión {{ version.numero_version }}</h3>
                                <StatusBadge :label="status(version).label" :variant="status(version).variant" />
                            </div>
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="inline text-[var(--nord3)]">Vigente desde (hora de El Salvador): </dt>
                                    <dd class="inline font-medium text-[var(--nord1)]">{{ formatElSalvadorDateTime(version.vigente_desde) }}</dd>
                                </div>
                                <div>
                                    <dt class="inline text-[var(--nord3)]">Validada el: </dt>
                                    <dd class="inline font-medium text-[var(--nord1)]">{{ formatLocalDate(version.validada_en) }}</dd>
                                </div>
                                <div>
                                    <dt class="inline text-[var(--nord3)]">Responsable: </dt>
                                    <dd class="inline font-medium text-[var(--nord1)]">{{ version.responsable_profesional?.nombre || 'No registrado' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <Link
                            :href="`/rubricas/versiones/${version.id}`"
                            class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-[var(--nord4)] px-4 py-2 text-sm font-semibold text-[var(--nord10)] transition-colors hover:border-[var(--nord10)] hover:bg-[var(--surface-subtle)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                            :aria-label="`Ver detalles de la versión ${version.numero_version}`"
                        >
                            Ver versión
                        </Link>
                    </article>
                </li>
            </ol>

            <nav v-if="paginationLinks.length > 3" aria-label="Paginación de versiones" class="flex flex-wrap justify-center gap-2 border-t border-[var(--nord4)] p-4">
                <template v-for="(link, index) in paginationLinks" :key="`${index}-${link.label}`">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        :aria-current="link.active ? 'page' : undefined"
                        :aria-label="`Ir a ${pageLabel(link, index)}`"
                        class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--nord10)]"
                        :class="link.active ? 'border-[var(--nord10)] bg-[var(--nord10)] text-white' : 'border-[var(--nord4)] text-[var(--nord1)] hover:bg-[var(--surface-subtle)]'"
                    >
                        {{ pageLabel(link, index) }}
                    </Link>
                    <span v-else class="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-[var(--nord4)] px-3 text-sm text-[var(--nord3)]" aria-disabled="true">
                        {{ pageLabel(link, index) }}
                    </span>
                </template>
            </nav>
        </section>

        <p class="text-sm leading-relaxed text-[var(--nord3)]">
            Cada versión conserva sus propias señales y umbrales. No se calcula un puntaje global.
        </p>
    </div>
</template>
