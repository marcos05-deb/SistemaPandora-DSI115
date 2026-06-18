<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
</script>

<template>
    <Head title="Panel de Administración" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-[var(--nord0)] tracking-tight">Panel de Administración</h1>
            <p class="text-sm text-[var(--nord3)] mt-1">Métricas y monitoreo del sistema PANDORA.</p>
        </div>

        <!-- Coming soon card -->
        <div class="bg-white rounded-2xl shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="relative px-6 py-14 text-center overflow-hidden">
                <!-- Fondo reticulado -->
                <div class="absolute inset-0 opacity-[0.025]"
                    style="background: repeating-linear-gradient(45deg, var(--nord0) 0, var(--nord0) 1px, transparent 0, transparent 50%); background-size: 16px 16px;">
                </div>
                <!-- Orbes decorativos -->
                <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-[0.04] -translate-y-16 translate-x-16 blur-3xl"
                    style="background: var(--aurora-purple);"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full opacity-[0.04] translate-y-12 -translate-x-12 blur-3xl"
                    style="background: var(--frost4);"></div>

                <div class="relative z-10 max-w-md mx-auto">
                    <!-- Badge -->
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mb-6"
                        style="background: color-mix(in srgb, var(--aurora-yellow) 12%, transparent); border: 1px solid color-mix(in srgb, var(--aurora-yellow) 30%, transparent); color: var(--aurora-yellow);">
                        <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
                        En Desarrollo
                    </div>

                    <!-- Ícono -->
                    <div class="w-16 h-16 mx-auto mb-5 rounded-2xl flex items-center justify-center shadow-sm"
                        style="background: linear-gradient(135deg, var(--nord5) 0%, var(--nord4) 100%);">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>

                    <h2 class="text-[18px] font-bold text-[var(--nord0)] mb-2 tracking-tight">Métricas del Sistema</h2>
                    <p class="text-[13px] text-[var(--nord3)] leading-relaxed mb-8">
                        Los indicadores y estadísticas avanzadas del sistema estarán<br>disponibles en el próximo sprint de desarrollo.
                    </p>

                    <!-- Accesos rápidos disponibles -->
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
