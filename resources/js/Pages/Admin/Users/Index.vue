<script setup>
import { ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import Pagination from "@/Components/Pagination.vue";

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
</script>

<template>
  <Head title="Gestión de Personal" />

  <div class="space-y-[14px]">
    <!-- Header Section -->
    <div class="flex justify-between items-center bg-white py-4 px-5 shadow-sm border border-[var(--nord4)] rounded-2xl">
      <div>
        <h2 class="text-[17px] font-bold text-[var(--nord0)] tracking-tight">Gestión de Personal</h2>
        <p class="text-[12px] text-[var(--nord3)] mt-0.5">Administra los accesos y perfiles del sistema PANDORA.</p>
      </div>
      <Link
        href="/admin/users/create"
        class="group relative inline-flex items-center gap-2 overflow-hidden px-4 py-2.5 rounded-xl text-[13px] font-semibold text-white shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
        style="background: linear-gradient(135deg, var(--aurora-purple) 0%, #9A6FA0 100%);"
      >
        <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/15 to-transparent group-hover:translate-x-full transition-transform duration-500 ease-in-out"></span>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
          <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
          <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
          <path d="M16 19h6" />
          <path d="M19 16v6" />
        </svg>
        Nuevo Usuario
      </Link>
    </div>

    <!-- Alert for Success Message -->
    <div v-if="$page.props.flash.message"
        class="relative bg-white border border-[var(--aurora-green)]/40 rounded-2xl p-6 shadow-sm animate-fade-in overflow-hidden">
        <!-- Fondo sutil -->  
        <div class="absolute inset-0 opacity-[0.03]" style="background: radial-gradient(ellipse at top left, var(--aurora-green), transparent 60%);"></div>
        <div class="relative z-10 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: color-mix(in srgb, var(--aurora-green) 15%, transparent);">
                <svg class="w-5 h-5 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <h4 class="text-[var(--aurora-green)] font-bold mb-0.5 text-[14px]">Operación Exitosa</h4>
                <p class="text-[13px] text-[var(--nord3)] m-0">{{ $page.props.flash.message }}</p>
            </div>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="relative overflow-hidden bg-white rounded-2xl py-4 px-4 shadow-sm border border-[var(--nord4)] group hover:shadow-md transition-all duration-200">
            <div class="absolute top-0 right-0 w-20 h-20 rounded-full opacity-[0.05] -translate-y-6 translate-x-6" style="background: radial-gradient(circle, var(--nord3), transparent);"></div>
            <div class="text-[10px] text-[var(--nord3)] uppercase tracking-wider font-semibold mb-1">Total</div>
            <div class="text-[28px] font-bold text-[var(--nord0)] tabular-nums">{{ metrics.total }}</div>
        </div>
        <div class="relative overflow-hidden bg-white rounded-2xl py-4 px-4 shadow-sm border border-[var(--nord4)] group hover:shadow-md transition-all duration-200">
            <div class="absolute top-0 right-0 w-20 h-20 rounded-full opacity-[0.06] -translate-y-6 translate-x-6" style="background: radial-gradient(circle, var(--aurora-green), transparent);"></div>
            <div class="text-[10px] text-[var(--nord3)] uppercase tracking-wider font-semibold mb-1">Activos</div>
            <div class="text-[28px] font-bold text-[var(--aurora-green)] tabular-nums">{{ metrics.active }}</div>
        </div>
        <div class="relative overflow-hidden bg-white rounded-2xl py-4 px-4 shadow-sm border border-[var(--nord4)] group hover:shadow-md transition-all duration-200">
            <div class="absolute top-0 right-0 w-20 h-20 rounded-full opacity-[0.06] -translate-y-6 translate-x-6" style="background: radial-gradient(circle, var(--aurora-red), transparent);"></div>
            <div class="text-[10px] text-[var(--nord3)] uppercase tracking-wider font-semibold mb-1">Inactivos</div>
            <div class="text-[28px] font-bold text-[var(--aurora-red)] tabular-nums">{{ metrics.inactive }}</div>
        </div>
        <div class="relative overflow-hidden bg-white rounded-2xl py-4 px-4 shadow-sm border border-[var(--nord4)] group hover:shadow-md transition-all duration-200">
            <div class="absolute top-0 right-0 w-20 h-20 rounded-full opacity-[0.05] -translate-y-6 translate-x-6" style="background: radial-gradient(circle, var(--frost4), transparent);"></div>
            <div class="text-[10px] text-[var(--nord3)] uppercase tracking-wider font-semibold mb-1">Coordinadores</div>
            <div class="text-[28px] font-bold text-[var(--frost4)] tabular-nums">{{ metrics.coordinators_ratio }}</div>
        </div>
        <div class="relative overflow-hidden bg-white rounded-2xl py-4 px-4 shadow-sm border border-[var(--nord4)] group hover:shadow-md transition-all duration-200">
            <div class="absolute top-0 right-0 w-20 h-20 rounded-full opacity-[0.05] -translate-y-6 translate-x-6" style="background: radial-gradient(circle, var(--aurora-purple), transparent);"></div>
            <div class="text-[10px] text-[var(--nord3)] uppercase tracking-wider font-semibold mb-1">Ref. Psicosociales</div>
            <div class="text-[28px] font-bold text-[var(--aurora-purple)] tabular-nums">{{ metrics.psychosocial_referents }}</div>
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
            <tr class="bg-[var(--surface-header)] text-[var(--nord3)] font-medium text-[11px] uppercase tracking-[0.05em]">
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
              :class="{ 'bg-[var(--surface-subtle)]': idx % 2 === 1 }"
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
              <td class="py-3 px-4 text-right">
                <template v-if="user.id !== $page.props.auth.user.id">
                    <div class="flex items-center justify-end gap-2">
                        <Link
                            :href="`/admin/users/${user.id}/edit`"
                            class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-3 py-1.5 rounded-lg border border-[var(--frost4)]/30 text-[var(--frost4)] bg-[var(--frost4)]/5 hover:bg-[var(--frost4)]/12 hover:border-[var(--frost4)]/50 transition-all duration-150"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Editar
                        </Link>
                        <button
                            @click="deactivateUser(user.id, user.name)"
                            class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-3 py-1.5 rounded-lg border border-[var(--aurora-red)]/30 text-[var(--aurora-red)] bg-[var(--aurora-red)]/5 hover:bg-[var(--aurora-red)]/12 hover:border-[var(--aurora-red)]/50 transition-all duration-150"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Desactivar
                        </button>
                    </div>
                </template>
                <div v-else class="text-[var(--nord3)] text-[11px] font-medium inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface-subtle)] border border-[var(--nord4)]">
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
      <Pagination :links="users.links" />
    </div>
  </div>
</template>
