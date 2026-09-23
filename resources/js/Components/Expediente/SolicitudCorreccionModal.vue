<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    tipo: { type: String, default: 'datos' }, // datos | clinico
    endpoint: { type: String, required: true },
    camposDisponibles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    campos: [],
    motivo: '',
});

const titulo = computed(() =>
    props.tipo === 'clinico'
        ? 'Solicitar permiso para actualizar expediente'
        : 'Solicitar permiso para corregir datos'
);

const descripcion = computed(() =>
    props.tipo === 'clinico'
        ? 'Este expediente ya usó su actualización libre. Indique qué campos necesita modificar y el motivo. El administrador autorizará un único cambio.'
        : 'Este paciente ya usó su corrección libre. Indique qué campos necesita corregir y el motivo. El administrador autorizará un único cambio.'
);

watch(() => props.show, (open) => {
    if (open) {
        form.campos = [];
        form.motivo = '';
        form.clearErrors();
    }
});

function toggleCampo(campo) {
    const i = form.campos.indexOf(campo);
    if (i >= 0) form.campos.splice(i, 1);
    else form.campos.push(campo);
}

function submit() {
    form.post(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

function close() {
    emit('close');
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="close" />
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="relative z-10 inline-block align-bottom bg-[var(--surface)] rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-[var(--nord4)]">
                <div class="bg-[var(--surface-header)] px-6 py-4 border-b border-[var(--nord4)]">
                    <h3 class="text-[16px] font-semibold text-[var(--nord0)]">{{ titulo }}</h3>
                    <p class="text-[12px] text-[var(--nord3)] mt-1">{{ descripcion }}</p>
                </div>
                <form class="px-6 py-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <p class="block text-[13px] font-semibold text-[var(--nord0)] mb-2">Campos a modificar</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="campo in camposDisponibles"
                                :key="campo"
                                type="button"
                                class="px-2.5 py-1 rounded-lg text-[11px] border transition-colors"
                                :class="form.campos.includes(campo)
                                    ? 'bg-[var(--nord8)] text-white border-[var(--nord8)]'
                                    : 'bg-white text-[var(--nord0)] border-[var(--nord4)] hover:bg-[var(--surface-subtle)]'"
                                @click="toggleCampo(campo)"
                            >
                                {{ campo }}
                            </button>
                        </div>
                        <p v-if="form.errors.campos" class="text-[12px] text-[var(--aurora-red)] mt-1">{{ form.errors.campos }}</p>
                    </div>
                    <div>
                        <label class="block text-[13px] font-semibold text-[var(--nord0)] mb-1.5">Motivo <span class="text-[var(--aurora-red)]">*</span></label>
                        <textarea
                            v-model="form.motivo"
                            rows="3"
                            required
                            class="w-full rounded-lg border px-3 py-2 text-[14px]"
                            :class="form.errors.motivo ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)] bg-[var(--nord6)]'"
                            placeholder="Explique por qué necesita corregir de nuevo..."
                        />
                        <p v-if="form.errors.motivo" class="text-[12px] text-[var(--aurora-red)] mt-1">{{ form.errors.motivo }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 text-[13px] rounded-lg border border-[var(--nord4)]" :disabled="form.processing" @click="close">Cancelar</button>
                        <button type="submit" class="px-4 py-2 text-[13px] rounded-lg bg-[var(--nord8)] text-white" :disabled="form.processing">
                            {{ form.processing ? 'Enviando…' : 'Enviar solicitud' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
