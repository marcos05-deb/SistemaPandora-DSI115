<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    expediente: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
    motivo_consulta: '',
    notas_clinicas: '',
    diagnostico: '',
    motivo_cambio: '',
});

const isSubmitting = ref(false);

watch(() => props.show, (open) => {
    if (open && props.expediente) {
        form.motivo_consulta = props.expediente.motivo_consulta || '';
        form.notas_clinicas = props.expediente.notas_clinicas || '';
        form.diagnostico = props.expediente.diagnostico || '';
        form.motivo_cambio = '';
        form.clearErrors();
        isSubmitting.value = false;
    }
});

const close = () => emit('close');

const submit = () => {
    if (!props.expediente) return;
    isSubmitting.value = true;
    form.patch(`/expedientes/${props.expediente.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            close();
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="close"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="relative z-10 inline-block align-bottom bg-[var(--surface)] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-[var(--nord4)]">
                <div class="bg-[var(--surface-header)] px-6 py-4 border-b border-[var(--nord4)]">
                    <h3 class="text-[16px] font-semibold text-[var(--nord0)]">Actualizar expediente clínico</h3>
                    <p class="text-[12px] text-[var(--nord3)] mt-1">La versión anterior queda registrada en auditoría. Indique el motivo del cambio.</p>
                </div>
                <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">Motivo de consulta</label>
                        <textarea v-model="form.motivo_consulta" rows="2" class="w-full rounded-lg border border-[var(--nord4)] bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)]" :disabled="isSubmitting"></textarea>
                        <p v-if="form.errors.motivo_consulta" class="text-[12px] text-[var(--aurora-red)] mt-1">{{ form.errors.motivo_consulta }}</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">Notas clínicas</label>
                        <textarea v-model="form.notas_clinicas" rows="3" class="w-full rounded-lg border border-[var(--nord4)] bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)]" :disabled="isSubmitting"></textarea>
                    </div>
                    <div>
                        <label class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">Diagnóstico</label>
                        <textarea v-model="form.diagnostico" rows="2" class="w-full rounded-lg border border-[var(--nord4)] bg-[var(--nord6)] px-3 py-2 text-[14px] text-[var(--nord0)]" :disabled="isSubmitting"></textarea>
                    </div>
                    <div>
                        <label class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">Motivo del cambio <span class="text-[var(--aurora-red)]">*</span></label>
                        <textarea v-model="form.motivo_cambio" rows="2" required class="w-full rounded-lg border px-3 py-2 text-[14px] text-[var(--nord0)]" :class="form.errors.motivo_cambio ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] bg-[var(--nord6)]'" :disabled="isSubmitting" placeholder="Explique por qué se actualiza el expediente..."></textarea>
                        <p v-if="form.errors.motivo_cambio" class="text-[12px] text-[var(--aurora-red)] mt-1">{{ form.errors.motivo_cambio }}</p>
                        <p v-if="form.errors.estado" class="text-[12px] text-[var(--aurora-red)] mt-1">{{ form.errors.estado }}</p>
                    </div>
                </form>
                <div class="bg-[var(--surface-subtle)] px-6 py-4 flex justify-end gap-3 border-t border-[var(--nord4)]">
                    <button type="button" @click="close" class="px-4 py-2 text-[13px] font-medium text-[var(--nord3)] rounded-lg border border-[var(--nord4)]">Cancelar</button>
                    <button type="button" @click="submit" :disabled="isSubmitting || !form.motivo_cambio" class="px-5 py-2 text-white text-[13px] font-medium rounded-lg bg-[var(--nord8)] hover:bg-[var(--nord9)] disabled:opacity-50">
                        Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
