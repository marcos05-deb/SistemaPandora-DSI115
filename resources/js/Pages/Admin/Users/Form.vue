<script setup>
import { ref, computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
  user: {
    type: Object,
    default: null
  },
  roles: Array,
  areas: Array,
});

const isEditMode = computed(() => !!props.user);
const isEditingSysadmin = computed(() => isEditMode.value && props.user.roles?.some((r) => r.slug === "sysadmin"));

const form = useForm({
  name: props.user?.name || "",
  email: props.user?.email || "",
  phone: props.user?.phone || "",
  is_active: props.user ? props.user.is_active : true,
  role_id: props.user?.roles?.[0]?.id || "",
  area_id: props.user?.profesional?.area_id || "",
  especialidad: props.user?.profesional?.especialidad || "",
  numero_registro: props.user?.profesional?.numero_registro || "",
});

const isSysadminSelected = ref(false);

// Inicializar estado visual si estamos en edición y el rol está precargado
if (isEditMode.value) {
    onRoleChange();
}

function onRoleChange() {
  const selectedRole = props.roles.find((r) => r.id === form.role_id);
  isSysadminSelected.value = selectedRole?.slug === "sysadmin";
  if (isSysadminSelected.value) {
    form.area_id = "";
    form.especialidad = "";
    form.numero_registro = "";
  }
}

function submitUser() {
  if (isEditMode.value) {
    form.put(`/admin/users/${props.user.id}`);
  } else {
    form.post("/admin/users");
  }
}
</script>

<template>
  <Head :title="isEditMode ? 'Editar Usuario' : 'Nuevo Usuario'" />

  <div class="space-y-[14px] max-w-5xl mx-auto">
    <!-- Header Section -->
    <div class="flex items-center gap-4 bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
      <Link href="/admin/users" class="p-2 text-[var(--nord3)] hover:text-[var(--nord0)] bg-[var(--nord6)] hover:bg-[var(--nord5)] rounded-[7px] transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M5 12l6 6" /><path d="M5 12l6 -6" /></svg>
      </Link>
      <div>
        <h2 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight">
          {{ isEditMode ? 'Editar Usuario' : 'Nuevo Usuario' }}
        </h2>
        <p class="text-[12px] text-[var(--nord3)] mt-0.5">
          {{ isEditMode ? 'Modifica los datos y permisos de este perfil.' : 'Registra un nuevo usuario en el sistema. Se generará una contraseña segura automáticamente.' }}
        </p>
      </div>
    </div>

    <!-- Form Section -->
    <div class="bg-white p-6 shadow-sm border border-[var(--nord4)] rounded-[10px]">
      <form @submit.prevent="submitUser" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          
          <!-- Columna Izquierda: Basic Info -->
          <div class="space-y-[18px]">
            <h3 class="text-[11px] font-medium text-[var(--nord3)] uppercase tracking-wider mb-4 border-b border-[var(--nord5)] pb-2">Información Básica</h3>
            
            <div>
              <label for="name" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Nombre completo <span class="text-[var(--aurora-red)]">*</span></label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                @blur="form.validate('name')"
                :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.name ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                placeholder="Ej: Dra. María López"
              />
              <p v-if="form.errors.name" class="text-[var(--aurora-red)] text-[12px] mt-1">
                {{ form.errors.name }}
              </p>
            </div>

            <div>
              <label for="email" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Correo electrónico <span class="text-[var(--aurora-red)]">*</span></label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                @blur="form.validate('email')"
                :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.email ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                placeholder="usuario@institucion.com"
              />
              <p v-if="form.errors.email" class="text-[var(--aurora-red)] text-[12px] mt-1">
                {{ form.errors.email }}
              </p>
            </div>

            <div>
              <label for="phone" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Teléfono</label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                @blur="form.validate('phone')"
                :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.phone ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                placeholder="Ej: +503 7000-0000"
              />
              <p v-if="form.errors.phone" class="text-[var(--aurora-red)] text-[12px] mt-1">
                {{ form.errors.phone }}
              </p>
            </div>
            
            <div v-if="!isEditMode" class="bg-[rgba(129,161,193,0.1)] border-l-2 border-[var(--frost3)] p-4 rounded-r-[7px] mt-6">
                <div class="flex gap-3 items-start">
                    <svg class="w-5 h-5 text-[var(--frost3)] mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <div>
                        <h4 class="text-[12px] font-medium text-[var(--frost4)]">Seguridad criptográfica</h4>
                        <p class="text-[11px] text-[var(--frost4)] mt-1">Al guardar, se generará una contraseña temporal de 16 caracteres. Podrás copiarla en la siguiente pantalla.</p>
                    </div>
                </div>
            </div>
            <div v-else class="bg-[var(--nord5)] border border-[var(--nord4)] p-4 rounded-[7px] mt-6">
                <div class="flex gap-3 items-start">
                    <svg class="w-5 h-5 text-[var(--nord3)] mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <div>
                        <h4 class="text-[12px] font-medium text-[var(--nord3)]">Contraseña bloqueada</h4>
                        <p class="text-[11px] text-[var(--nord3)] mt-1 opacity-80">El cambio de contraseña administrativa está deshabilitado temporalmente (Deuda técnica Sprint 3) para proteger la integridad de los datos cifrados.</p>
                    </div>
                </div>
            </div>
          </div>

          <!-- Columna Derecha: Role & Professional Info -->
          <div v-if="!isEditingSysadmin" class="space-y-[18px]">
            <h3 class="text-[11px] font-medium text-[var(--nord3)] uppercase tracking-wider mb-4 border-b border-[var(--nord5)] pb-2">Roles y Perfil</h3>
            
            <div class="grid grid-cols-2 gap-4">
              <div class="col-span-2">
                <label for="role" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Rol en el sistema <span class="text-[var(--aurora-red)]">*</span></label>
                <select
                  id="role"
                  v-model="form.role_id"
                  @change="onRoleChange"
                  @blur="form.validate('role_id')"
                  :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.role_id ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                >
                  <option value="" disabled>Seleccionar Rol...</option>
                  <option v-for="role in roles" :key="role.id" :value="role.id">
                    {{ role.nombre }}
                  </option>
                </select>
                <p v-if="form.errors.role_id" class="text-[var(--aurora-red)] text-[12px] mt-1">
                  {{ form.errors.role_id }}
                </p>
              </div>

              <div class="col-span-2 sm:col-span-1">
                <label for="is_active" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Estado inicial</label>
                <select
                  id="is_active"
                  v-model="form.is_active"
                  :class="['w-full border border-[var(--nord4)] rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                >
                  <option :value="true">Activo</option>
                  <option :value="false">Inactivo</option>
                </select>
              </div>
            </div>

            <template v-if="form.role_id && !isSysadminSelected">
              <div class="pt-2 border-t border-dashed border-[var(--nord4)] mt-6">
                  <h4 class="text-[11px] font-medium text-[var(--nord3)] uppercase mb-4 mt-2">Datos Clínicos</h4>
                  
                  <div class="space-y-[18px]">
                      <div>
                        <label for="area" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Departamento / Área <span class="text-[var(--aurora-red)]">*</span></label>
                        <select
                          id="area"
                          v-model="form.area_id"
                          @blur="form.validate('area_id')"
                          :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.area_id ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                        >
                          <option value="" disabled>Seleccionar Área...</option>
                          <option v-for="area in areas" :key="area.id" :value="area.id">
                            {{ area.nombre }}
                          </option>
                        </select>
                        <p v-if="form.errors.area_id" class="text-[var(--aurora-red)] text-[12px] mt-1">
                          {{ form.errors.area_id }}
                        </p>
                      </div>

                      <div>
                        <label for="especialidad" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Especialidad <span class="text-[var(--aurora-red)]">*</span></label>
                        <input
                          id="especialidad"
                          v-model="form.especialidad"
                          type="text"
                          @blur="form.validate('especialidad')"
                          :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.especialidad ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                          placeholder="Ej: Psicología Clínica"
                        />
                        <p v-if="form.errors.especialidad" class="text-[var(--aurora-red)] text-[12px] mt-1">
                          {{ form.errors.especialidad }}
                        </p>
                      </div>

                      <div>
                        <label for="numero_registro" class="block text-[13px] font-medium text-[var(--nord3)] mb-[5px]">Número de Registro <span class="text-[var(--aurora-red)]">*</span></label>
                        <input
                          id="numero_registro"
                          v-model="form.numero_registro"
                          type="text"
                          @blur="form.validate('numero_registro')"
                          :class="['w-full border rounded-[7px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] bg-white transition-colors outline-none', form.errors.numero_registro ? 'border-[var(--aurora-red)] focus:ring-[rgba(191,97,106,0.2)]' : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)]']"
                          placeholder="Ej: COL-12345"
                        />
                        <p v-if="form.errors.numero_registro" class="text-[var(--aurora-red)] text-[12px] mt-1">
                          {{ form.errors.numero_registro }}
                        </p>
                      </div>
                  </div>
              </div>
            </template>
          </div>
          
          <div
            v-else
            class="space-y-6 flex items-center justify-center bg-[var(--nord6)] rounded-[10px] border border-[var(--nord4)]"
          >
            <div class="text-center p-6">
              <div class="w-14 h-14 rounded-full bg-[var(--aurora-purple)]/10 flex items-center justify-center mx-auto mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h4 class="text-[var(--nord0)] font-medium text-[14px]">
                Rol de Administrador
              </h4>
              <p class="text-[12px] text-[var(--nord3)] mt-2">
                Los administradores no requieren área clínica ni especialidad. Su nivel de acceso es total.
              </p>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between pt-6 border-t border-[var(--nord5)] mt-8">
          <div class="text-[11px] text-[var(--nord3)]">
            <span class="text-[var(--aurora-red)]">*</span> Campos requeridos
          </div>
          <div class="flex gap-3">
            <Link
              href="/admin/users"
              class="px-4 py-2 text-[13px] font-medium text-[var(--nord3)] bg-transparent hover:bg-[var(--nord6)] border border-[var(--nord4)] rounded-[7px] transition-colors"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="bg-[var(--frost4)] text-white py-2 px-6 rounded-[7px] text-[13px] font-medium hover:bg-[#4C6A8D] transition-colors disabled:opacity-70 flex items-center gap-2 shadow-sm"
            >
              {{ isEditMode ? "Actualizar Usuario" : "Guardar y Generar Contraseña" }}
              <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
