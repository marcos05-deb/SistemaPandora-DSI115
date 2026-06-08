<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/UI/PrimaryButton.vue';
import SecondaryButton from '@/Components/UI/SecondaryButton.vue';

const props = defineProps({
    userLabel: String,
    navigation: Array,
    faculties: Array,
    generatedCode: { type: String, default: null },
});

const form = useForm({
    name: '',
    age: '',
    faculty: '',
    phone: '',
    guardian: '',
});

function submit() {
    form.post('/registro-paciente', {
        preserveScroll: true,
    });
}

function cancel() {
    form.reset();
    form.clearErrors();
}

defineOptions({
    layout: (h, page) =>
        h(AuthenticatedLayout, {
            userLabel: page.props.userLabel,
            navigation: page.props.navigation,
        }, () => page),
});
</script>

<template>
    <Head title="Registro de Paciente" />

    <div class="max-w-3xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[var(--nord0)]">Registro de Paciente</h1>
            <p class="text-sm text-[var(--nord3)] mt-1">
                Capture los datos sociodemográficos del estudiante
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-[var(--nord4)] p-8">
            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-[var(--nord3)] mb-1">
                        Nombre Completo <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="Ingrese el nombre del estudiante"
                        class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors"
                        :class="{ 'border-[var(--aurora-red)]': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-[var(--aurora-red)]">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-[var(--nord3)] mb-1">
                            Edad <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <input
                            v-model="form.age"
                            type="number"
                            placeholder="Ej: 22"
                            min="15"
                            max="60"
                            class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors"
                            :class="{ 'border-[var(--aurora-red)]': form.errors.age }"
                        />
                        <p v-if="form.errors.age" class="mt-1 text-xs text-[var(--aurora-red)]">{{ form.errors.age }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--nord3)] mb-1">
                            Facultad <span class="text-[var(--aurora-red)]">*</span>
                        </label>
                        <select
                            v-model="form.faculty"
                            class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors"
                            :class="{ 'border-[var(--aurora-red)]': form.errors.faculty }"
                        >
                            <option value="">Seleccione una facultad</option>
                            <option v-for="f in faculties" :key="f" :value="f">{{ f }}</option>
                        </select>
                        <p v-if="form.errors.faculty" class="mt-1 text-xs text-[var(--aurora-red)]">{{ form.errors.faculty }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--nord3)] mb-1">Teléfono</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        placeholder="Ej: 7000-0000"
                        class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--nord3)] mb-1">
                        Responsable <span class="text-[var(--aurora-red)]">*</span>
                    </label>
                    <input
                        v-model="form.guardian"
                        type="text"
                        placeholder="Nombre del responsable (familiar/tutor)"
                        class="w-full rounded-[7px] border border-[var(--nord4)] px-[10px] py-[7px] text-[13px] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] outline-none transition-colors"
                        :class="{ 'border-[var(--aurora-red)]': form.errors.guardian }"
                    />
                    <p v-if="form.errors.guardian" class="mt-1 text-xs text-[var(--aurora-red)]">{{ form.errors.guardian }}</p>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <SecondaryButton type="button" @click="cancel">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">Guardar Paciente</PrimaryButton>
                </div>

                <div
                    v-if="generatedCode"
                    class="rounded-lg border border-[var(--aurora-green)]/30 bg-[var(--aurora-green)]/5 px-4 py-3 text-sm text-[var(--aurora-green)]"
                >
                    <strong>Código de Privacidad Generado:</strong>
                    {{ generatedCode }}
                    <p class="text-[var(--aurora-green)]/70 mt-1 text-xs">
                        Este código se genera automáticamente al guardar el registro del paciente
                    </p>
                </div>
            </form>

            <p class="mt-6 text-xs text-[var(--nord3)]">
                * Campos obligatorios | Edad: 15-60 años | Formato email válido requerido
            </p>
        </div>
    </div>
</template>
