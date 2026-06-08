<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useTheme } from "@/Composables/useTheme";

const showPassword = ref(false);
const { isDark, toggleTheme } = useTheme();

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

function submitLogin() {
  form.post("/login", {
    preserveScroll: true,
    onFinish: () => {
      if (form.hasErrors) form.reset("password");
    },
  });
}
</script>

<template>
  <Head title="Iniciar Sesión" />

  <div class="min-h-screen flex font-sans">
    <!-- ===== Left Panel: Branding ===== -->
    <div class="hidden lg:flex lg:w-[480px] xl:w-[560px] bg-[var(--chrome-topbar)] relative overflow-hidden flex-col justify-between px-12 py-12">
      <!-- Abstract illustration background -->
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
            <line x1="88" y1="88" x2="512" y2="512" />
            <line x1="512" y1="88" x2="88" y2="512" />
          </g>
        </svg>
        <svg class="absolute -bottom-24 -left-24 w-[500px] h-[500px] opacity-[0.06]" viewBox="0 0 500 500" fill="none">
          <g stroke="white" stroke-width="1">
            <rect x="50" y="50" width="400" height="400" rx="80" />
            <rect x="100" y="100" width="300" height="300" rx="60" />
            <rect x="150" y="150" width="200" height="200" rx="40" />
            <rect x="200" y="200" width="100" height="100" rx="20" />
            <line x1="100" y1="100" x2="400" y2="400" />
            <line x1="400" y1="100" x2="100" y2="400" />
          </g>
        </svg>
        <div class="absolute top-1/3 right-12 w-48 h-48 rounded-full bg-[var(--frost4)] opacity-[0.07] blur-3xl" />
        <div class="absolute bottom-1/4 left-12 w-40 h-40 rounded-full bg-[var(--aurora-purple)] opacity-[0.07] blur-3xl" />
      </div>

      <!-- Top: Logo & Brand -->
      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-8">
          <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur flex items-center justify-center border border-white/10">
            <svg class="w-5 h-5 text-[var(--frost2)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
          </div>
          <span class="text-[13px] font-medium text-[var(--chrome-text-muted)] tracking-wide">Sistema de Gestión Clínica</span>
        </div>

        <h1 class="text-[42px] font-bold text-white tracking-tight leading-tight">
          PANDORA
        </h1>
        <p class="text-[16px] text-[var(--chrome-text-muted)] mt-3 leading-relaxed max-w-[340px]">
          Plataforma segura de gestión de expedientes clínicos con cifrado de grado médico.
        </p>

        <div class="mt-10 space-y-4">
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-[var(--frost4)]/10 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-4 h-4 text-[var(--frost2)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </div>
            <div>
              <p class="text-[13px] font-medium text-white">Cifrado de extremo a extremo</p>
              <p class="text-[12px] text-[var(--chrome-text-muted)]">Todos los expedientes se protegen con cifrado AES-256</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-[var(--aurora-purple)]/10 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-4 h-4 text-[var(--aurora-purple)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            </div>
            <div>
              <p class="text-[13px] font-medium text-white">Control de acceso basado en roles</p>
              <p class="text-[12px] text-[var(--chrome-text-muted)]">Cada perfil accede solo a la información que necesita</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-[var(--aurora-green)]/10 flex items-center justify-center shrink-0 mt-0.5">
              <svg class="w-4 h-4 text-[var(--aurora-green)]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
            </div>
            <div>
              <p class="text-[13px] font-medium text-white">Auditoría completa</p>
              <p class="text-[12px] text-[var(--chrome-text-muted)]">Cada acceso queda registrado para trazabilidad</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom: Credits -->
      <div class="relative z-10">
        <div class="text-[11px] text-[var(--chrome-text-muted)] space-y-1">
          <p class="font-medium text-white/60">© 2025 PANDORA — v1.0</p>
          <p>Desarrollado por Camilo, Eduardo y Marcos</p>
          <p>DSI115 · Universidad</p>
        </div>
      </div>
    </div>

    <!-- ===== Mobile Brand Header (visible only on small screens) ===== -->
    <div class="lg:hidden bg-[var(--chrome-topbar)] px-6 py-4 flex items-center gap-3 absolute top-0 left-0 right-0 z-20">
      <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center border border-white/10">
        <svg class="w-4 h-4 text-[var(--frost2)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>
      <span class="text-[16px] font-bold text-white">PANDORA</span>
      <span class="text-[11px] text-[var(--chrome-text-muted)]">Clínico</span>
    </div>

    <!-- ===== Right Panel: Login Form ===== -->
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
        <!-- Mobile greeting -->
        <div class="lg:hidden text-center">
          <h2 class="text-[22px] font-bold text-[var(--nord0)] tracking-tight">Bienvenido de vuelta</h2>
          <p class="text-[13px] text-[var(--nord3)] mt-1">Ingresa tus credenciales para acceder al sistema</p>
        </div>

        <!-- Desktop title -->
        <div class="hidden lg:block mb-2">
          <h2 class="text-[26px] font-bold text-[var(--nord0)] tracking-tight">Iniciar sesión</h2>
          <p class="text-[13px] text-[var(--nord3)] mt-1">Ingresa tus credenciales para acceder al sistema PANDORA</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white p-6 sm:p-8 shadow-md rounded-2xl border border-[var(--nord4)]">
          <!-- Session Error -->
          <div
            v-if="form.errors.session"
            class="mb-6 px-4 py-3 text-[13px] text-[var(--aurora-red)] border border-[var(--aurora-red)]/30 rounded-lg flex items-center gap-2.5 bg-[var(--aurora-red)]/5 animate-fade-in"
          >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            {{ form.errors.session }}
          </div>

          <!-- Throttle Error -->
          <div
            v-if="form.errors.throttle"
            class="mb-6 px-4 py-3 text-[13px] text-[var(--aurora-orange)] border border-[var(--aurora-orange)]/30 rounded-lg flex items-center gap-2.5 bg-[var(--aurora-orange)]/5 animate-fade-in"
          >
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ form.errors.throttle }}
          </div>

          <form @submit.prevent="submitLogin" class="space-y-5">
            <!-- Email -->
            <div>
              <label for="login-email" class="block text-[13px] font-medium text-[var(--nord3)] mb-1.5">Correo electrónico</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 11-8 0 4 4 0 018 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                  </svg>
                </div>
                <input
                  id="login-email"
                  v-model="form.email"
                  type="email"
                  autocomplete="email"
                  placeholder="tu@correo.com"
                  class="w-full border rounded-lg pl-10 pr-4 py-2.5 text-[14px] text-[var(--nord0)] bg-white transition-all duration-200 outline-none placeholder:text-[var(--nord3)]"
                  :class="form.errors.email
                    ? 'border-[var(--aurora-red)] focus:border-[var(--aurora-red)] focus:ring-2 focus:ring-[var(--aurora-red)]/20'
                    : 'border-[var(--nord4)] focus:border-[var(--frost3)] focus:ring-2 focus:ring-[var(--frost3)]/20'"
                />
              </div>
              <p v-if="form.errors.email" class="mt-1.5 text-xs text-[var(--aurora-red)] flex items-center gap-1">
                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ form.errors.email }}
              </p>
            </div>

            <!-- Password -->
            <div>
              <label for="login-password" class="block text-[13px] font-medium text-[var(--nord3)] mb-1.5">Contraseña</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="h-4 w-4 text-[var(--nord3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <input
                  id="login-password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  placeholder="Tu contraseña"
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
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

            <!-- Remember me + Forgot password -->
            <div class="flex items-center justify-between text-[13px]">
              <label class="flex items-center gap-2 cursor-pointer select-none text-[var(--nord3)] hover:text-[var(--nord0)] transition-colors">
                <input
                  v-model="form.remember"
                  type="checkbox"
                  class="w-4 h-4 rounded border-[var(--nord4)] text-[var(--nord10)] focus:ring-[var(--frost3)] focus:ring-offset-0 cursor-pointer"
                />
                Recordarme
              </label>
              <a href="#" class="text-[var(--nord10)] hover:text-[var(--nord9)] hover:underline font-medium transition-colors">
                ¿Olvidaste tu contraseña?
              </a>
            </div>

            <!-- Submit -->
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
                Autenticando...
              </template>
              <template v-else>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                Iniciar Sesión
              </template>
            </button>
          </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-[11px] text-[var(--nord3)]">
          © 2025 PANDORA · Camilo, Eduardo y Marcos · DSI115
        </p>
      </div>
    </div>
  </div>
</template>
