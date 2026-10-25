<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AlertOverviewCharts from '@/Components/Dashboard/AlertOverviewCharts.vue';

defineOptions({ layout: AdminLayout });

const page = usePage();

defineProps({
    alertOverview: {
        type: Object,
        default: () => ({
            visible: false,
            reveal_carnets: false,
            scope: null,
            total: 0,
            abiertas: 0,
            por_estado: [],
            por_senal: [],
            urgentes: [],
            por_etiqueta: [],
            tendencia_suicida: {
                total_alumnos: 0,
                por_facultad: [],
                por_carrera: [],
            },
        }),
    },
});
</script>

<template>
    <Head title="Panel de Administración" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Panel de Administración</h1>
            <p class="text-sm text-[var(--nord3)] mt-1">
                Métricas agregadas del sistema. Sin carnets ni datos personales de pacientes.
            </p>
        </div>

        <AlertOverviewCharts
            :overview="alertOverview"
            :show-bandeja-link="false"
            :link-urgentes="false"
        />

        <div class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="relative px-6 py-10 text-center overflow-hidden">
                <div class="absolute inset-0 opacity-[0.025]"
                    style="background: repeating-linear-gradient(45deg, var(--nord0) 0, var(--nord0) 1px, transparent 0, transparent 50%); background-size: 16px 16px;">
                </div>

                <div class="relative z-10 max-w-2xl mx-auto">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mb-5"
                        style="background: color-mix(in srgb, var(--aurora-yellow) 12%, transparent); border: 1px solid color-mix(in srgb, var(--aurora-yellow) 30%, transparent); color: var(--aurora-yellow);">
                        <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                        Analítica institucional
                    </div>

                    <h2 class="text-[18px] font-bold text-[var(--nord0)] mb-2 tracking-tight">Más métricas del sistema</h2>
                    <p class="text-[13px] text-[var(--nord3)] leading-relaxed mb-6">
                        Indicadores con umbral de privacidad, personal y auditoría (sin identificadores clínicos).
                    </p>

                    <div class="flex flex-wrap items-center justify-center gap-3 mb-6">
                        <Link v-if="page.props.can?.indicadores" href="/indicadores"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[var(--aurora-purple)] px-5 py-3 text-sm font-semibold text-white transition hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--aurora-purple)]">
                            Abrir indicadores
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7 7 7-7 7" /></svg>
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                        <Link href="/admin/users"
                            class="group flex items-center gap-3 p-3.5 rounded-xl border border-[var(--nord4)] bg-[var(--surface-subtle)] hover:bg-[var(--nord5)] hover:border-[var(--frost4)]/30 transition-all duration-150">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                style="background: color-mix(in srgb, var(--frost4) 12%, transparent);">
                                <svg class="h-4 w-4 text-[var(--frost4)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-[var(--nord0)]">Personal del Sistema</p>
                                <p class="text-[11px] text-[var(--nord3)]">Gestión de accesos y roles</p>
                            </div>
                            <svg class="h-4 w-4 text-[var(--nord4)] ml-auto shrink-0 group-hover:text-[var(--frost4)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </Link>
                        <Link href="/admin/pacientes"
                            class="group flex items-center gap-3 p-3.5 rounded-xl border border-[var(--nord4)] bg-[var(--surface-subtle)] hover:bg-[var(--nord5)] hover:border-[var(--aurora-purple)]/30 transition-all duration-150">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                style="background: color-mix(in srgb, var(--aurora-purple) 12%, transparent);">
                                <svg class="h-4 w-4 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[13px] font-semibold text-[var(--nord0)]">Auditoría de Pacientes</p>
                                <p class="text-[11px] text-[var(--nord3)]">Revisión de expedientes</p>
                            </div>
                            <svg class="h-4 w-4 text-[var(--nord4)] ml-auto shrink-0 group-hover:text-[var(--aurora-purple)] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
