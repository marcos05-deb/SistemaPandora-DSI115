<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    roles: Array,
    areas: Array,
    totales: Object,
});
</script>

<template>
    <Head title="Organigrama del Sistema" />

    <div class="space-y-[14px]">
        <div class="flex justify-between items-center bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
            <div>
                <h2 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight">Organigrama del Sistema</h2>
                <p class="text-[12px] text-[var(--nord3)] mt-0.5">Estructura jerárquica de roles, áreas clínicas y personal asignado.</p>
            </div>
        </div>

        <!-- Totales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
                <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Usuarios Totales</div>
                <div class="text-[24px] font-bold text-[var(--nord0)] mt-1">{{ totales.usuarios }}</div>
            </div>
            <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
                <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Activos</div>
                <div class="text-[24px] font-bold text-[var(--aurora-green)] mt-1">{{ totales.activos }}</div>
            </div>
            <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
                <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Inactivos</div>
                <div class="text-[24px] font-bold text-[var(--aurora-red)] mt-1">{{ totales.inactivos }}</div>
            </div>
            <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
                <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Áreas Clínicas</div>
                <div class="text-[24px] font-bold text-[var(--frost4)] mt-1">{{ totales.areas }}</div>
            </div>
        </div>

        <!-- Jerarquía de Roles -->
        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                <h3 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Jerarquía de Roles
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div v-for="role in roles" :key="role.id" class="border border-[var(--nord4)] rounded-[8px] p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-[13px] font-bold"
                                    :class="{
                                        'bg-[var(--aurora-red)]': role.slug === 'sysadmin',
                                        'bg-[var(--nord10)]': role.slug === 'area_coordinator',
                                        'bg-[var(--frost4)]': role.slug === 'psychosocial_referent',
                                        'bg-[var(--nord9)]': role.slug === 'specialist',
                                    }"
                                >
                                    {{ role.total_usuarios }}
                                </div>
                                <div>
                                    <h4 class="text-[14px] font-medium text-[var(--nord0)]">{{ role.nombre }}</h4>
                                    <p class="text-[11px] text-[var(--nord3)]">Nivel {{ role.nivel }} — {{ role.activos }} activos de {{ role.total_usuarios }}</p>
                                </div>
                            </div>
                            <span class="text-[11px] px-[8px] py-[2px] rounded-[99px] font-medium"
                                :class="role.slug === 'sysadmin' ? 'bg-[var(--surface-header)] text-[var(--nord3)]' : 'bg-[var(--surface-subtle)] text-[var(--nord3)]'">
                                {{ role.slug }}
                            </span>
                        </div>
                        <div v-if="role.usuarios.length > 0" class="border-t border-[var(--nord5)] pt-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="u in role.usuarios" :key="u.id" class="flex items-center gap-2 text-[12px]">
                                    <span class="w-2 h-2 rounded-full" :class="u.is_active ? 'bg-[var(--aurora-green)]' : 'bg-[var(--aurora-red)]'"></span>
                                    <span class="text-[var(--nord0)]">{{ u.name }}</span>
                                    <span class="text-[var(--nord3)]">{{ u.email }}</span>
                                    <span v-if="u.area" class="text-[var(--nord4)]">— {{ u.area }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="border-t border-[var(--nord5)] pt-3 text-[12px] text-[var(--nord3)] italic">
                            Sin usuarios asignados a este rol
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Áreas Clínicas -->
        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                <h3 class="text-[14px] font-medium text-[var(--nord0)] flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[var(--aurora-orange)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    Áreas Clínicas
                </h3>
            </div>
            <div class="p-6">
                <div v-if="areas.length === 0" class="text-center py-8 text-[var(--nord3)] text-[13px]">
                    No hay áreas clínicas registradas.
                </div>
                <div v-else class="space-y-4">
                    <div v-for="area in areas" :key="area.id" class="border border-[var(--nord4)] rounded-[8px] overflow-hidden">
                        <div class="px-4 py-3 bg-[var(--surface-header)] flex items-center justify-between">
                            <h4 class="text-[13px] font-medium text-[var(--nord0)]">{{ area.nombre }}</h4>
                            <span class="text-[11px] text-[var(--nord3)]">{{ area.total_especialistas }} especialista(s)</span>
                        </div>
                        <div class="p-4 space-y-3">
                            <!-- Coordinador -->
                            <div>
                                <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Coordinador de Área</p>
                                <div v-if="area.coordinador" class="flex items-center gap-2 text-[12px]">
                                    <span class="w-2 h-2 rounded-full" :class="area.coordinador.is_active ? 'bg-[var(--aurora-green)]' : 'bg-[var(--aurora-red)]'"></span>
                                    <span class="text-[var(--nord0)] font-medium">{{ area.coordinador.name }}</span>
                                    <span class="text-[var(--nord3)]">{{ area.coordinador.email }}</span>
                                </div>
                                <p v-else class="text-[12px] text-[var(--aurora-red)]">Sin coordinador asignado</p>
                            </div>
                            <!-- Especialistas -->
                            <div v-if="area.especialistas.length > 0">
                                <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Especialistas</p>
                                <div class="space-y-1">
                                    <div v-for="esp in area.especialistas" :key="esp.id" class="flex items-center gap-2 text-[12px]">
                                        <span class="w-2 h-2 rounded-full" :class="esp.is_active ? 'bg-[var(--aurora-green)]' : 'bg-[var(--aurora-red)]'"></span>
                                        <span class="text-[var(--nord0)]">{{ esp.name }}</span>
                                        <span class="text-[var(--nord3)]">{{ esp.especialidad }}</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Psicosociales -->
                            <div v-if="area.psicosociales.length > 0">
                                <p class="text-[11px] font-semibold text-[var(--nord3)] uppercase tracking-wider mb-1">Referentes Psicosociales</p>
                                <div class="space-y-1">
                                    <div v-for="psi in area.psicosociales" :key="psi.id" class="flex items-center gap-2 text-[12px]">
                                        <span class="w-2 h-2 rounded-full" :class="psi.is_active ? 'bg-[var(--aurora-green)]' : 'bg-[var(--aurora-red)]'"></span>
                                        <span class="text-[var(--nord0)]">{{ psi.name }}</span>
                                        <span class="text-[var(--nord3)]">{{ psi.email }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
