<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useTheme } from "@/Composables/useTheme";

const showPassword = ref(false);
const { isDark, toggleTheme } = useTheme();

const form = useForm({
  password: "",
  password_confirmation: "",
});

function submitSetup() {
  form.post("/password/setup", {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}
</script>

<template>
  <Head title="Configurar Contraseña" />

  <div class="min-h-screen flex font-sans">
    <!-- ===== Left Panel: Branding ===== -->
    <div class="hidden lg:flex lg:w-[480px] xl:w-[560px] bg-[var(--chrome-topbar)] relative overflow-hidden flex-col justify-center items-center px-12 py-12">
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <svg class="absolute -top-32 -right-32 w-[600px] h-[600px] opacity-[0.08]" viewBox="0 0 600 600" fill="none">
          <g stroke="white" stroke-width="1">
            <circle cx="300" cy="300" r="250" />
            <circle cx="300" cy="300" r="200" />
            <circle cx="300" cy="300" r="150" />
            <circle cx="300" cy="300" r="100" />
            <circle cx="300" cy="300" r="50" />
            <line x1="300" y1="0" x2="300" y2="600" />
            <line x1="0" y1="300" x2="600" y2="300" />
          </g>
        </svg>
        <div class="absolute top-1/2 right-12 w-48 h-48 rounded-full bg-[var(--frost4)] opacity-[0.07] blur-3xl" />
      </div>

      <div class="relative z-10 text-center">
        <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur flex items-center justify-center mx-auto mb-6 border border-white/10">
          <svg class="w-8 h-8 text-[var(--frost2)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <h1 class="text-[52px] font-bold text-white tracking-tight">PANDORA</h1>
        <p class="text-[15px] text-[var(--chrome-text-muted)] mt-3">Configuración de contraseña segura</p>
      </div>

      <p class="absolute bottom-8 text-[11px] text-white/30">v1.0</p>
    </div>

    <!-- ===== Mobile Brand Header ===== -->
    <div class="lg:hidden bg-[var(--chrome-topbar)] px-6 py-4 flex items-center gap-3 absolute top-0 left-0 right-0 z-20">
      <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center border border-white/10">
        <svg class="w-4 h-4 text-[var(--frost2)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>
      <span class="text-[16px] font-bold text-white">PANDORA</span>
      <span class="text-[11px] text-[var(--chrome-text-muted)]">Configuración</span>
    </div>

    <!-- ===== Right Panel: Form ===== -->
    <div class="flex-1 flex items-center justify-center p-4 sm:p-8 bg-[var(--nord6)] lg:pt-0 pt-20 relative">
      <!-- Theme Toggle (floating) -->
      <button
        @click="toggleTheme"
        class="absolute top-4 right-4 lg:top-8 lg:right-8 p-2 rounded-lg border border-[var(--nord4)] bg-white hover:bg-[var(--nord6)] text-[var(--nord3)] hover:text-[var(--nord0)] transition-all duration-200 shadow-sm hover:shadow-md"
        :title="isDark ? 'Modo Claro' : 'Modo Oscuro'"
      >
        <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
      </button>

      <div class="w-full max-w-[420px] space-y-6 animate-fade-in-up">
        <div class="lg:hidden text-center">
          <h2 class="text-[22px] font-bold text-[var(--nord0)] tracking-tight">Bienvenido a PANDORA</h2>
          <p class="text-[13px] text-[var(--nord3)] mt-1">Configura tu contraseña segura</p>
        </div>

        <div class="hidden lg:block mb-2">
          <h2 class="text-[26px] font-bold text-[var(--nord0)] tracking-tight">Establecer contraseña</h2>
          <p class="text-[13px] text-[var(--nord3)] mt-1">Por seguridad, debes configurar una contraseña antes de ingresar</p>
        </div>

        <div class="bg-white p-6 sm:p-8 shadow-md rounded-2xl border border-[var(--nord4)]">
          <form @submit.prevent="submitSetup" class="space-y-5">
            <div>
              <label for="setup-password" class="block text-[13px] font-medium text-[var(--nord3)] mb-1.5">Nueva contraseña</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <input
                  id="setup-password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Mínimo 8 caracteres"
                  class="w-full border rounded-lg pl-10 pr-12 py-2.5 text-[14px] text-[var(--nord0)] bg-white transition-all duration-200 outline-none placeholder:text-[var(--nord3)]"
                  :class="form.errors.password
                    ? 'border-[var(--aurora-red)] focus:border-[var(--aurora-red)] focus:ring-2 focus:ring-[var(--aurora-red)]/20'
                    : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20'"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors"
                  @click="showPassword = !showPassword"
                  tabindex="-1"
                >
                  <svg v-if="!showPassword" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                  <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                </button>
              </div>
              <p v-if="form.errors.password" class="mt-1.5 text-xs text-[var(--aurora-red)] flex items-center gap-1">
                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ form.errors.password }}
              </p>
            </div>

            <div>
              <label for="setup-password-confirmation" class="block text-[13px] font-medium text-[var(--nord3)] mb-1.5">Confirmar contraseña</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <input
                  id="setup-password-confirmation"
                  v-model="form.password_confirmation"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  placeholder="Repite tu contraseña"
                  class="w-full border rounded-lg pl-10 pr-4 py-2.5 text-[14px] text-[var(--nord0)] bg-white transition-all duration-200 outline-none placeholder:text-[var(--nord3)]"
                  :class="form.errors.password_confirmation
                    ? 'border-[var(--aurora-red)] focus:border-[var(--aurora-red)] focus:ring-2 focus:ring-[var(--aurora-red)]/20'
                    : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20'"
                />
              </div>
              <p v-if="form.errors.password_confirmation" class="mt-1.5 text-xs text-[var(--aurora-red)] flex items-center gap-1">
                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ form.errors.password_confirmation }}
              </p>
            </div>

            <!-- Security Policy -->
            <div class="bg-[var(--nord6)] border border-[var(--nord4)] rounded-xl p-4 space-y-2">
              <h4 class="text-[11px] font-semibold text-[var(--nord0)] uppercase tracking-wider flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 text-[var(--nord10)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Política de Seguridad
              </h4>
              <ul class="text-[11px] text-[var(--nord3)] space-y-1 list-disc pl-4">
                <li>Mínimo 8 caracteres de longitud</li>
                <li>Al menos una letra mayúscula y minúscula</li>
                <li>Al menos un número (0-9)</li>
                <li>Al menos un símbolo especial (!@#$%, etc.)</li>
              </ul>
            </div>

            <button
              type="submit"
              :disabled="form.processing"
              class="w-full bg-[var(--nord10)] text-white py-2.5 px-4 rounded-xl text-[14px] font-semibold hover:bg-[var(--nord9)] hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0 flex items-center justify-center gap-2 shadow-sm"
            >
              <template v-if="form.processing">
                <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Configurando...
              </template>
              <template v-else>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                Establecer Contraseña e Ingresar
              </template>
            </button>
          </form>
        </div>
    </div>
  </div>
</template>
