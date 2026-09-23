<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ClinicalLayout from '@/Layouts/ClinicalLayout.vue';
import Breadcrumbs from '@/Components/UI/Breadcrumbs.vue';
import FieldTooltip from '@/Components/UI/FieldTooltip.vue';
import VueDatePicker from '@vuepic/vue-datepicker';

defineOptions({ layout: ClinicalLayout });

const props = defineProps({
    paciente: { type: Object, required: true },
    facultades: { type: Array, default: () => [] },
    estadoCorreccion: { type: Object, default: () => ({}) },
});

const showConfirm = ref(false);

const form = useForm({
    carnet: props.paciente.carnet || '',
    nombre_completo: props.paciente.nombre_completo || '',
    direccion: props.paciente.direccion || '',
    carrera_id: props.paciente.carrera_id || '',
    sexo: props.paciente.sexo || '',
    estado_civil: props.paciente.estado_civil || '',
    fecha_nacimiento: props.paciente.fecha_nacimiento || '',
    profesion_ocupacion: props.paciente.profesion_ocupacion || '',
    referido_por: props.paciente.referido_por || '',
    llevado_por: props.paciente.llevado_por || '',
    padre_nombre: props.paciente.padre_nombre || '',
    padre_telefono: props.paciente.padre_telefono || '',
    madre_nombre: props.paciente.madre_nombre || '',
    madre_telefono: props.paciente.madre_telefono || '',
    responsable_parentesco: props.paciente.responsable_parentesco || 'Padre',
    responsable_nombre: props.paciente.responsable_nombre || '',
    responsable_telefono: props.paciente.responsable_telefono || '',
    responsable_direccion: props.paciente.responsable_direccion || '',
    motivo_correccion: '',
});

const carnetCambio = computed(() => {
    return String(form.carnet || '').toUpperCase() !== String(props.paciente.carnet || '').toUpperCase();
});

const errorKeys = computed(() => Object.keys(form.errors));

function maskCarnet(e) {
    let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const letters = val.replace(/[^A-Z]/g, '').slice(0, 2);
    const digits = val.replace(/[^0-9]/g, '').slice(0, 5);
    form.carnet = letters + digits;
    form.clearErrors('carnet');
}

function maskPhone(field) {
    return (e) => {
        form[field] = e.target.value.replace(/\D/g, '').slice(0, 8);
        form.clearErrors(field);
    };
}

watch(() => form.responsable_parentesco, (val) => {
    if (val === 'Padre' && form.padre_telefono) {
        form.responsable_telefono = form.padre_telefono;
    } else if (val === 'Madre' && form.madre_telefono) {
        form.responsable_telefono = form.madre_telefono;
    }
});

function requestSubmit() {
    if (form.processing) return;
    if (!form.motivo_correccion || form.motivo_correccion.trim().length < 10) {
        form.setError('motivo_correccion', 'El motivo de la corrección debe tener al menos 10 caracteres.');
        return;
    }
    showConfirm.value = true;
}

function confirmAndSave() {
    if (form.processing) return;
    showConfirm.value = false;
    form.patch(`/pacientes/${props.paciente.codigo}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Corregir datos - ${paciente.carnet}`" />

    <div class="max-w-4xl mx-auto space-y-6 pb-10">
        <Breadcrumbs :items="[
            { label: 'Panel Clínico', href: '/dashboard' },
            { label: 'Pacientes', href: '/pacientes' },
            { label: paciente.carnet, href: `/pacientes/${paciente.carnet}` },
            { label: 'Corregir datos' },
        ]" />

        <div class="bg-white rounded-[10px] shadow-sm border border-[var(--nord4)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--nord4)] bg-[var(--surface-header)]">
                <h1 class="text-[16px] font-medium text-[var(--nord0)]">Corregir datos del paciente</h1>
                <p class="text-[12px] text-[var(--nord3)] mt-1">
                    Solo datos generales y contactos. La primera corrección es libre; las siguientes requieren permiso del administrador (un solo uso).
                    Queda registrado quién corrigió y qué campos; el admin no ve valores (carnet, nombre, etc.).
                </p>
                <p v-if="estadoCorreccion?.permiso_aprobado" class="text-[11px] text-[var(--aurora-orange)] mt-2">
                    Permiso autorizado para: {{ (estadoCorreccion.campos_autorizados || []).join(', ') }}.
                    Tras guardar, el permiso se consume.
                </p>
            </div>

            <form class="p-6 space-y-6" @submit.prevent="requestSubmit">
                <div v-if="errorKeys.length > 0" class="px-4 py-3 rounded-xl border border-[var(--aurora-red)]/30 bg-[var(--aurora-red)]/5">
                    <p class="text-[13px] font-semibold text-[var(--aurora-red)] mb-1">Corrige {{ errorKeys.length }} error(es)</p>
                    <ul class="list-disc pl-5 space-y-0.5">
                        <li v-for="key in errorKeys" :key="key" class="text-[12px] text-[var(--aurora-red)]">{{ form.errors[key] }}</li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4">
                    <div>
                        <label for="field-carnet" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Carnet <span class="text-[var(--aurora-red)]">*</span></label>
                        <input id="field-carnet" v-model="form.carnet" type="text" maxlength="7" @input="maskCarnet" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] font-mono uppercase tracking-wider text-[var(--nord0)] outline-none" :class="form.errors.carnet ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'" />
                        <p v-if="form.errors.carnet" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.carnet }}</p>
                        <p v-if="carnetCambio" class="text-[11px] text-[var(--aurora-orange)] mt-1">Se modificará el carnet (evento sensible en auditoría).</p>
                    </div>

                    <div>
                        <label for="field-nombre_completo" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre completo <span class="text-[var(--aurora-red)]">*</span></label>
                        <input id="field-nombre_completo" v-model="form.nombre_completo" type="text" maxlength="255" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]" />
                        <p v-if="form.errors.nombre_completo" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.nombre_completo }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="field-direccion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Dirección <span class="text-[var(--aurora-red)]">*</span></label>
                        <textarea id="field-direccion" v-model="form.direccion" rows="2" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)] resize-none" />
                        <p v-if="form.errors.direccion" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.direccion }}</p>
                    </div>

                    <div>
                        <label for="field-fecha_nacimiento" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Fecha de nacimiento <span class="text-[var(--aurora-red)]">*</span></label>
                        <VueDatePicker v-model="form.fecha_nacimiento" :format="'dd/MM/yyyy'" model-type="yyyy-MM-dd" :enable-time-picker="false" auto-apply :max-date="new Date()">
                            <template #dp-input="{ value }">
                                <input id="field-fecha_nacimiento" type="text" :value="value" readonly class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)] cursor-pointer" placeholder="Seleccionar fecha" />
                            </template>
                        </VueDatePicker>
                        <p v-if="form.errors.fecha_nacimiento" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.fecha_nacimiento }}</p>
                    </div>

                    <div>
                        <label for="field-sexo" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Sexo <span class="text-[var(--aurora-red)]">*</span></label>
                        <select id="field-sexo" v-model="form.sexo" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]">
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div>
                        <label for="field-estado_civil" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Estado civil <span class="text-[var(--aurora-red)]">*</span></label>
                        <select id="field-estado_civil" v-model="form.estado_civil" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]">
                            <option value="Soltero">Soltero</option>
                            <option value="Casado">Casado</option>
                            <option value="Divorciado">Divorciado</option>
                            <option value="Viudo">Viudo</option>
                            <option value="Unión Libre">Unión Libre</option>
                        </select>
                    </div>

                    <div>
                        <label for="field-carrera_id" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Carrera <span class="text-[var(--aurora-red)]">*</span></label>
                        <select id="field-carrera_id" v-model="form.carrera_id" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]">
                            <option value="" disabled>Seleccione una carrera</option>
                            <optgroup v-for="f in facultades" :key="f.id" :label="f.nombre">
                                <option v-for="c in f.carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </optgroup>
                        </select>
                        <p v-if="form.errors.carrera_id" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.carrera_id }}</p>
                    </div>

                    <div>
                        <label for="field-profesion_ocupacion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Profesión / ocupación</label>
                        <input id="field-profesion_ocupacion" v-model="form.profesion_ocupacion" type="text" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]" />
                    </div>

                    <div>
                        <label for="field-referido_por" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Referido por</label>
                        <input id="field-referido_por" v-model="form.referido_por" type="text" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]" />
                    </div>

                    <div>
                        <label for="field-llevado_por" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Llevado por</label>
                        <input id="field-llevado_por" v-model="form.llevado_por" type="text" class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none border-[var(--nord4)]" />
                    </div>
                </div>

                <div class="border-t border-[var(--nord4)] pt-5 space-y-4">
                    <h2 class="text-[14px] font-medium text-[var(--nord0)]">Contactos</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre del padre</label>
                            <input v-model="form.padre_nombre" type="text" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono del padre</label>
                            <input v-model="form.padre_telefono" type="text" maxlength="8" @input="maskPhone('padre_telefono')" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre de la madre</label>
                            <input v-model="form.madre_nombre" type="text" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono de la madre</label>
                            <input v-model="form.madre_telefono" type="text" maxlength="8" @input="maskPhone('madre_telefono')" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Parentesco del responsable <span class="text-[var(--aurora-red)]">*</span></label>
                            <select v-model="form.responsable_parentesco" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]">
                                <option value="Padre">Padre</option>
                                <option value="Madre">Madre</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        <div v-if="form.responsable_parentesco === 'Otro'">
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Nombre del responsable</label>
                            <input v-model="form.responsable_nombre" type="text" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]" />
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Teléfono del responsable <span class="text-[var(--aurora-red)]">*</span></label>
                            <input v-model="form.responsable_telefono" type="text" maxlength="8" @input="maskPhone('responsable_telefono')" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px]" />
                            <p v-if="form.errors.responsable_telefono" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.responsable_telefono }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[13px] font-medium text-[var(--nord3)] mb-1">Dirección del responsable <span class="text-[var(--aurora-red)]">*</span></label>
                            <textarea v-model="form.responsable_direccion" rows="2" class="w-full bg-[var(--surface)] border border-[var(--nord4)] rounded-lg px-3 py-2 text-[13px] resize-none" />
                            <p v-if="form.errors.responsable_direccion" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.responsable_direccion }}</p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-[var(--nord4)] pt-5">
                    <label for="field-motivo_correccion" class="block text-[13px] font-medium text-[var(--nord3)] mb-1">
                        Motivo de la corrección <span class="text-[var(--aurora-red)]">*</span>
                        <FieldTooltip text="Entre 10 y 500 caracteres. Se guarda solo en auditoría, no en notas clínicas." />
                    </label>
                    <textarea
                        id="field-motivo_correccion"
                        v-model="form.motivo_correccion"
                        rows="3"
                        maxlength="500"
                        placeholder="Ej: Corrección solicitada porque el carnet fue digitado incorrectamente durante el registro inicial."
                        class="w-full bg-[var(--surface)] border rounded-lg px-3 py-2 text-[13px] text-[var(--nord0)] outline-none resize-none"
                        :class="form.errors.motivo_correccion ? 'border-[var(--aurora-red)]' : 'border-[var(--nord4)]'"
                        required
                    />
                    <p class="text-[11px] text-[var(--nord3)] mt-1">{{ form.motivo_correccion.length }}/500</p>
                    <p v-if="form.errors.motivo_correccion" class="text-[11px] text-[var(--aurora-red)] mt-1">{{ form.errors.motivo_correccion }}</p>
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <Link :href="`/pacientes/${paciente.carnet}`" class="px-4 py-2 text-[13px] text-[var(--nord3)] border border-[var(--nord4)] rounded-lg hover:bg-[var(--surface-subtle)]">
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        class="px-4 py-2 text-[13px] font-medium text-white bg-[var(--nord8)] hover:bg-[var(--nord9)] rounded-lg disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Guardando…' : 'Guardar corrección' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-[12px] shadow-xl border border-[var(--nord4)] max-w-md w-full p-5 space-y-4">
                <h3 class="text-[15px] font-semibold text-[var(--nord0)]">Confirmar corrección</h3>
                <p class="text-[13px] text-[var(--nord3)]">
                    Esta corrección quedará registrada permanentemente en la auditoría del sistema. Confirma que los nuevos datos fueron verificados con la documentación del paciente.
                </p>
                <p v-if="carnetCambio" class="text-[12px] text-[var(--aurora-orange)] font-medium">
                    Atención: el carnet cambiará de {{ paciente.carnet }} a {{ form.carnet.toUpperCase() }}. Se generará un aviso sensible para el administrador.
                </p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="px-3 py-1.5 text-[12px] border border-[var(--nord4)] rounded-lg" @click="showConfirm = false">Cancelar</button>
                    <button type="button" class="px-3 py-1.5 text-[12px] font-medium text-white bg-[var(--nord8)] rounded-lg disabled:opacity-60" :disabled="form.processing" @click="confirmAndSave">
                        Confirmar y guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
