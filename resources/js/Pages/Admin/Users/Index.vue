<script setup>
import { ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";

defineOptions({ layout: AdminLayout });

const props = defineProps({
  users: Object,
  roles: Array,
  areas: Array,
  filters: Object,
  metrics: Object,
});

const page = usePage();

const search = ref(props.filters.search || '');
const roleFilter = ref(props.filters.role || '');
const trashedFilter = ref(props.filters.trashed || false);
const copied = ref(false);

watch([search, roleFilter, trashedFilter], ([newSearch, newRole, newTrashed]) => {
  router.get('/admin/users', { search: newSearch, role: newRole, trashed: newTrashed ? 1 : '' }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
});

function deactivateUser(id, name) {
  if (confirm(`¿Desactivar a ${name}?\nEste usuario ya no podrá ingresar al sistema.`)) {
    router.delete(`/admin/users/${id}`, { preserveScroll: true });
  }
}

function copyPassword(password) {
  navigator.clipboard.writeText(password).then(() => {
    copied.value = true;
    setTimeout(() => { clearGeneratedPassword(); }, 5000);
  }).catch(() => {
    alert('Error al copiar al portapapeles. Selecciónalo y cópialo manualmente.');
  });
}

function clearGeneratedPassword() {
  page.props.flash.generated_password = null;
  copied.value = false;
}
</script>

<template>
  <Head title="Gestión de Personal" />

  <div class="space-y-[14px]">
    <!-- Header Section -->
    <div class="flex justify-between items-center bg-white py-[14px] px-[18px] shadow-sm border border-[var(--nord4)] rounded-[10px]">
      <div>
        <h2 class="text-[16px] font-medium text-[var(--nord0)] tracking-tight">
          Gestión de Personal
        </h2>
        <p class="text-[12px] text-[var(--nord3)] mt-0.5">
          Administra los accesos y perfiles del sistema PANDORA.
        </p>
      </div>
      <Link
        href="/admin/users/create"
        class="bg-[var(--frost4)] text-white py-2 px-4 rounded-[8px] text-[13px] font-medium hover:bg-[#4C6A8D] transition-all duration-200 flex items-center gap-2 hover:shadow-md hover:-translate-y-0.5 active:translate-y-0"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>
        Nuevo Usuario
      </Link>
    </div>

    <!-- Alert for Generated Password -->
    <div v-if="$page.props.flash.generated_password" class="bg-white border border-[var(--aurora-green)] rounded-[10px] p-6 relative shadow-sm animate-fade-in">
        <button @click="clearGeneratedPassword" class="absolute top-4 right-4 text-[var(--aurora-green)] hover:text-[var(--aurora-green)]/80 transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
        <h4 class="text-[var(--aurora-green)] font-medium mb-2 flex items-center gap-2 text-[14px]">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Usuario Creado Exitosamente
        </h4>
        <p class="text-[12px] text-[var(--nord3)] mb-4">El sistema ha generado una contraseña segura temporal para este usuario. Cópiala y compártela a través de un canal seguro.</p>
        <div class="bg-white px-4 py-3 rounded-lg border border-[var(--nord4)] flex items-center justify-between">
            <code class="text-[16px] font-mono text-[var(--nord0)]">{{ $page.props.flash.generated_password }}</code>
            <button 
                @click="copyPassword($page.props.flash.generated_password)" 
                :class="['text-[12px] font-medium transition-all duration-200 px-3 py-1.5 rounded-[8px]', copied ? 'bg-[var(--aurora-green)]/10 text-[var(--aurora-green)]' : 'bg-[var(--frost4)] text-white hover:bg-[#4C6A8D]']"
            >
                <span v-if="copied" class="flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    ¡Copiada!
                </span>
                <span v-else>Copiar</span>
            </button>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
            <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Usuarios totales</div>
            <div class="text-[24px] font-bold text-[var(--nord0)] mt-1">{{ metrics.total }}</div>
        </div>
        <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
            <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Activos</div>
            <div class="text-[24px] font-bold text-[var(--aurora-green)] mt-1">{{ metrics.active }}</div>
        </div>
        <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
            <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Inactivos</div>
            <div class="text-[24px] font-bold text-[var(--aurora-red)] mt-1">{{ metrics.inactive }}</div>
        </div>
        <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
            <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Coordinadores</div>
            <div class="text-[24px] font-bold text-[var(--frost4)] mt-1">{{ metrics.coordinators_ratio }}</div>
        </div>
        <div class="bg-white rounded-[10px] py-[14px] px-[16px] shadow-sm border border-[var(--nord4)] hover:shadow-md transition-shadow duration-200">
            <div class="text-[11px] text-[var(--nord3)] uppercase tracking-wider font-medium">Ref. Psicosociales</div>
            <div class="text-[24px] font-bold text-[var(--nord10)] mt-1">{{ metrics.psychosocial_referents }}</div>
        </div>
    </div>

    <!-- Toolbar: Search & Filters -->
    <div class="flex flex-col sm:flex-row gap-3 bg-white p-4 rounded-[10px] shadow-sm border border-[var(--nord4)]">
        <div class="flex-1">
            <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input 
                    id="search"
                    v-model="search" 
                    type="text" 
                    placeholder="Buscar por nombre o correo..." 
                    class="w-full border border-[var(--nord4)] rounded-[8px] pl-9 pr-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] bg-white transition-colors placeholder-[var(--nord3)] outline-none" 
                />
            </div>
        </div>
        <div class="w-full sm:w-[200px]">
            <select 
                id="roleFilter"
                v-model="roleFilter" 
                class="w-full border border-[var(--nord4)] rounded-[8px] px-[10px] py-[7px] text-[13px] text-[var(--nord0)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[rgba(129,161,193,0.2)] bg-white transition-colors outline-none"
            >
                <option value="">Todos los roles</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.nombre }}</option>
            </select>
        </div>
        <div class="flex items-center">
            <label class="flex items-center gap-2 cursor-pointer text-[12px] text-[var(--nord3)] select-none">
                <input type="checkbox" v-model="trashedFilter" class="rounded border-[var(--nord4)] text-[var(--frost4)] focus:ring-[var(--frost3)]" />
                Mostrar inactivos
            </label>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white shadow-sm border border-[var(--nord4)] rounded-[10px] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="bg-[var(--nord5)] text-[var(--nord3)] font-medium text-[11px] uppercase tracking-[0.05em]">
              <th class="py-[12px] px-[16px] font-semibold">Nombre</th>
              <th class="py-[12px] px-[16px] font-semibold">Correo</th>
              <th class="py-[12px] px-[16px] font-semibold">Rol</th>
              <th class="py-[12px] px-[16px] font-semibold">Área / Especialidad</th>
              <th class="py-[12px] px-[16px] font-semibold text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[var(--nord5)]">
            <tr
              v-for="(user, idx) in users.data"
              :key="user.id"
              class="hover:bg-[var(--nord6)] transition-colors duration-150"
              :class="{ 'bg-[var(--nord6)]/30': idx % 2 === 1 }"
            >
              <td class="py-[12px] px-[16px]">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-[var(--frost4)]/10 flex items-center justify-center text-[var(--frost4)] text-[12px] font-bold">
                        {{ (user.name || 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <div class="text-[13px] font-medium text-[var(--nord0)]">{{ user.name || "-" }}</div>
                        <div class="text-[11px] mt-0.5" :class="user.is_active ? 'text-[var(--aurora-green)]' : 'text-[var(--aurora-red)]'">
                            {{ user.is_active ? 'Activo' : 'Inactivo' }}
                        </div>
                    </div>
                </div>
              </td>
              <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">{{ user.email }}</td>
              <td class="py-[12px] px-[16px]">
                <span
                  v-if="user.roles[0]?.slug === 'sysadmin'"
                  class="inline-flex items-center px-[9px] py-[3px] rounded-[99px] text-[11px] font-medium pill-admin"
                >
                  Admin sistema
                </span>
                <span
                  v-else
                  class="inline-flex items-center px-[9px] py-[3px] rounded-[99px] text-[11px] font-medium pill-aurora"
                >
                  {{ user.roles[0]?.nombre || "Especialista" }}
                </span>
              </td>
              <td class="py-[12px] px-[16px] text-[12px] text-[var(--nord3)]">
                <template v-if="user.profesional">
                  <div class="text-[13px] text-[var(--nord0)]">{{ user.areas[0]?.nombre || "Sin área asignada" }}</div>
                  <div class="text-[11px] text-[var(--nord3)]">{{ user.profesional.especialidad }}</div>
                </template>
                <span v-else class="text-[11px] italic text-[var(--nord3)]">N/A</span>
              </td>
              <td class="py-[12px] px-[16px] text-right space-x-3">
                <template v-if="user.id !== $page.props.auth.user.id">
                    <Link
                    :href="`/admin/users/${user.id}/edit`"
                    class="text-[var(--frost4)] hover:text-[#4C6A8D] transition-colors text-[12px] font-medium inline-flex items-center gap-1"
                    >
                    Editar
                    </Link>
                    <button
                    @click="deactivateUser(user.id, user.name)"
                    class="text-[var(--aurora-red)] hover:text-[#a05058] transition-colors text-[12px] font-medium inline-flex items-center gap-1"
                    >
                    Desactivar
                    </button>
                </template>
                <div v-else class="text-[var(--nord3)] text-[12px] font-medium inline-flex items-center gap-1 justify-end w-full">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z" /><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M8 11v-4a4 4 0 1 1 8 0v4" /></svg>
                  Tu usuario
                </div>
              </td>
            </tr>
            <!-- Empty State - Filter -->
            <tr v-if="users.data.length === 0 && (search || roleFilter)">
              <td colspan="5" class="py-16 text-center text-[var(--nord3)]">
                <div class="flex flex-col items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-[var(--nord4)] mb-3" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                    <div class="text-[14px] font-medium text-[var(--nord0)]">No se encontraron usuarios</div>
                    <div class="text-[12px] mt-1">Intenta con otro nombre, correo o rol.</div>
                </div>
              </td>
            </tr>
            <!-- Empty State - No Data -->
            <tr v-if="users.data.length === 0 && !search && !roleFilter">
              <td colspan="5" class="py-16 text-center text-[var(--nord3)]">
                <div class="flex flex-col items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-[var(--nord4)] mb-3" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 13a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M8 21v-1a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2v1" /><path d="M15 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M17 10h2a2 2 0 0 1 2 2v1" /><path d="M5 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M3 13v-1a2 2 0 0 1 2 -2h2" /></svg>
                    <div class="text-[14px] font-medium text-[var(--nord0)]">No hay usuarios registrados</div>
                    <div class="mt-4">
                        <Link href="/admin/users/create" class="bg-[var(--frost4)] text-white py-1.5 px-3 rounded-[8px] text-[12px] font-medium hover:bg-[#4C6A8D] transition-all duration-200 inline-flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" /><path d="M16 19h6" /><path d="M19 16v6" /></svg>
                            Crear primer usuario
                        </Link>
                    </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div v-if="users.links && users.links.length > 3" class="px-4 py-3 border-t border-[var(--nord5)] flex justify-center bg-[var(--nord6)]">
        <div class="flex flex-wrap gap-1.5">
            <template v-for="(link, p) in users.links" :key="p">
                <div v-if="link.url === null" 
                    class="px-3 py-1.5 text-[12px] rounded-[8px] text-[var(--nord4)] border border-[var(--nord4)]/50 bg-transparent cursor-not-allowed"
                    v-html="link.label" 
                />
                <Link v-else
                    class="px-3 py-1.5 text-[12px] font-medium rounded-[8px] transition-all duration-200 border"
                    :class="link.active 
                        ? 'bg-[var(--frost4)] text-white border-[var(--frost4)] shadow-sm' 
                        : 'border-[var(--nord4)] text-[var(--nord3)] hover:bg-[var(--nord5)] hover:border-[var(--frost3)]'"
                    :href="link.url" 
                    v-html="link.label" 
                />
            </template>
        </div>
      </div>
    </div>
  </div>
</template>
