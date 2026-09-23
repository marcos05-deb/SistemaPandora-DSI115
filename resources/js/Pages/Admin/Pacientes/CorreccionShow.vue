<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    correccion: { type: Object, required: true },
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-ES', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function marcarRevisado() {
    router.post(`/admin/pacientes/correcciones/${props.correccion.id}/revisar`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Detalle de corrección" />

    <div class="space-y-4 max-w-4xl">
        <div class="flex items-center justify-between gap-3">
            <Link href="/admin/pacientes" class="text-[13px] text-[var(--nord8)]">← Volver a auditoría</Link>
            <button
                v-if="correccion.aviso_sensible && !correccion.aviso_revisado"
                type="button"
                class="px-3 py-1.5 text-[12px] font-medium rounded-lg bg-[var(--nord8)] text-white"
                @click="marcarRevisado"
            >
                Marcar aviso como revisado
            </button>
        </div>

        <div
            v-if="correccion.aviso_sensible"
            class="rounded-[10px] border border-[var(--aurora-orange)]/50 bg-[var(--aurora-orange)]/10 px-4 py-3"
        >
            <p class="text-[13px] font-semibold text-[var(--nord0)]">Corrección sensible: se modificó el carnet de un paciente.</p>
            <p class="text-[12px] text-[var(--nord3)] mt-1">
                Estado del aviso:
                {{ correccion.aviso_revisado ? `Revisado${correccion.revisado_por ? ' por ' + correccion.revisado_por : ''} (${formatDate(correccion.revisado_en)})` : 'Pendiente de revisión' }}
            </p>
        </div>

        <div class="bg-white rounded-[10px] border border-[var(--nord4)] shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                <h1 class="text-[15px] font-medium text-[var(--nord0)]">Registro de auditoría</h1>
                <p class="text-[12px] text-[var(--nord3)] mt-0.5 font-mono">{{ correccion.id }}</p>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-[13px]">
                <div><span class="text-[var(--nord3)]">Paciente (UUID)</span><p class="font-mono text-[12px] mt-0.5">{{ correccion.paciente_id }}</p></div>
                <div><span class="text-[var(--nord3)]">Tipo / nivel</span><p class="mt-0.5">{{ correccion.tipo_evento }} · {{ correccion.nivel_evento }}</p></div>
                <div><span class="text-[var(--nord3)]">Carnet anterior</span><p class="mt-0.5 font-mono">{{ correccion.carnet_anterior }}</p></div>
                <div><span class="text-[var(--nord3)]">Carnet nuevo</span><p class="mt-0.5 font-mono">{{ correccion.carnet_nuevo }}</p></div>
                <div><span class="text-[var(--nord3)]">Responsable</span><p class="mt-0.5">{{ correccion.responsable }} ({{ correccion.rol_usuario }})</p></div>
                <div><span class="text-[var(--nord3)]">Fecha</span><p class="mt-0.5">{{ formatDate(correccion.created_at) }}</p></div>
                <div class="md:col-span-2"><span class="text-[var(--nord3)]">Motivo</span><p class="mt-0.5">{{ correccion.motivo }}</p></div>
                <div class="md:col-span-2"><span class="text-[var(--nord3)]">Campos modificados</span><p class="mt-0.5">{{ (correccion.campos_modificados || []).join(', ') }}</p></div>
            </div>

            <div class="px-5 pb-5">
                <h2 class="text-[13px] font-medium text-[var(--nord0)] mb-2">Valores (datos personales cifrados aparecen como [CIFRADO])</h2>
                <div class="overflow-x-auto border border-[var(--nord4)] rounded-lg">
                    <table class="w-full text-left text-[12px]">
                        <thead class="bg-[var(--surface-header)] text-[var(--nord3)]">
                            <tr>
                                <th class="py-2 px-3">Campo</th>
                                <th class="py-2 px-3">Anterior</th>
                                <th class="py-2 px-3">Nuevo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="campo in correccion.campos_modificados" :key="campo" class="border-t border-[var(--nord5)]">
                                <td class="py-2 px-3 font-mono">{{ campo }}</td>
                                <td class="py-2 px-3">{{ correccion.valores_anteriores?.[campo] ?? '—' }}</td>
                                <td class="py-2 px-3">{{ correccion.valores_nuevos?.[campo] ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-[var(--nord3)] mt-3">Esta pantalla es solo consulta. No permite editar ni eliminar el registro de auditoría.</p>
            </div>
        </div>
    </div>
</template>
