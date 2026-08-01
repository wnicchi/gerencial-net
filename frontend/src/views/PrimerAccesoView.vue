<template>
  <div class="login-container">

    <!-- Panel izquierdo — Logo -->
    <div class="panel-logo">
      <img :src="auth.fondoLogin" alt="Sistema de Recursos Humanos" class="logo-img" />
    </div>

    <!-- Panel derecho — Formulario -->
    <div class="panel-form">
      <div class="form-card">
        <h1>Sistema RRHH</h1>
        <p class="subtitulo">Activación de cuenta</p>

        <!-- PASO 1: Nombre de usuario -->
        <div v-if="paso === 1">
          <p class="paso-desc">Ingresá tu nombre de usuario para comenzar.</p>
          <form @submit.prevent="verificarUsuario" class="form">
            <div class="form-group">
              <label>Nombre de usuario</label>
              <input v-model="form.usuario" type="text" placeholder="Tu usuario" required />
            </div>
            <div v-if="error" class="alert-error">{{ error }}</div>
            <button type="submit" class="btn-primary" :disabled="cargando">
              {{ cargando ? 'Verificando...' : 'Continuar' }}
            </button>
          </form>
        </div>

        <!-- PASO 2: Email -->
        <div v-if="paso === 2">
          <p class="paso-desc">Hola <strong>{{ nombreUsuario }}</strong>. Ingresá tu email para recibir el código de verificación.</p>
          <form @submit.prevent="enviarCodigo" class="form">
            <div class="form-group">
              <label>Email</label>
              <input v-model="form.email" type="email" placeholder="tu@email.com" required />
            </div>
            <div v-if="error" class="alert-error">{{ error }}</div>
            <button type="submit" class="btn-primary" :disabled="cargando">
              {{ cargando ? 'Enviando...' : 'Enviar código' }}
            </button>
          </form>
        </div>

        <!-- PASO 3: Código -->
        <div v-if="paso === 3">
          <p class="paso-desc">Ingresá el código de 6 dígitos enviado a <strong>{{ form.email }}</strong>. Válido 15 minutos.</p>
          <form @submit.prevent="validarCodigo" class="form">
            <div class="form-group">
              <label>Código de verificación</label>
              <input v-model="form.codigo" type="text" maxlength="6" placeholder="123456" class="input-codigo" required />
            </div>
            <div v-if="error" class="alert-error">{{ error }}</div>
            <button type="submit" class="btn-primary" :disabled="cargando">
              {{ cargando ? 'Verificando...' : 'Verificar código' }}
            </button>
            <button type="button" class="btn-secondary" @click="enviarCodigo" :disabled="cargando">
              Reenviar código
            </button>
          </form>
        </div>

        <!-- PASO 4: Nueva contraseña -->
        <div v-if="paso === 4">
          <p class="paso-desc">¡Email verificado! Creá tu contraseña de acceso.</p>
          <form @submit.prevent="crearPassword" class="form">
            <div class="form-group">
              <label>Nueva contraseña</label>
              <input v-model="form.password" type="password" placeholder="••••••••" required />
              <small class="hint">Mínimo 8 caracteres, mayúscula, número y símbolo.</small>
            </div>
            <div class="form-group">
              <label>Confirmar contraseña</label>
              <input v-model="form.password_confirmation" type="password" placeholder="••••••••" required />
            </div>
            <div v-if="error" class="alert-error">{{ error }}</div>
            <button type="submit" class="btn-primary" :disabled="cargando">
              {{ cargando ? 'Activando...' : 'Activar cuenta' }}
            </button>
          </form>
        </div>

        <!-- Indicador de pasos -->
        <div class="pasos-indicator">
          <span v-for="n in 4" :key="n" :class="['paso-dot', { active: paso === n, done: paso > n }]"></span>
        </div>

        <div class="back-link">
          <router-link to="/login">← Volver al login</router-link>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/auth'

const router        = useRouter()
const auth          = useAuthStore()
const paso          = ref(1)
const cargando      = ref(false)
const error         = ref('')
const nombreUsuario = ref('')

const form = ref({
  usuario: '', email: '', codigo: '',
  password: '', password_confirmation: '', token_temporal: '',
})

async function verificarUsuario() {
  error.value = ''; cargando.value = true
  try {
    const res = await authService.verificarUsuario(form.value.usuario)
    nombreUsuario.value = res.data.nombre
    paso.value = 2
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Error al verificar usuario.'
  } finally { cargando.value = false }
}

async function enviarCodigo() {
  error.value = ''; cargando.value = true
  try {
    await authService.enviarCodigo(form.value.usuario, form.value.email)
    paso.value = 3
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Error al enviar el código.'
  } finally { cargando.value = false }
}

async function validarCodigo() {
  error.value = ''; cargando.value = true
  try {
    const res = await authService.validarCodigo(form.value.usuario, form.value.codigo)
    form.value.token_temporal = res.data.token_temporal
    paso.value = 4
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Código incorrecto o expirado.'
  } finally { cargando.value = false }
}

async function crearPassword() {
  error.value = ''; cargando.value = true
  try {
    const res = await authService.crearPassword(
      form.value.usuario, form.value.token_temporal,
      form.value.password, form.value.password_confirmation,
    )
    auth.guardarSesion(res.data.token, res.data.usuario)
    router.push('/dashboard')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Error al crear la contraseña.'
  } finally { cargando.value = false }
}
</script>

<style scoped>
.login-container { display: flex; min-height: 100vh; }

.panel-logo {
  flex: 1;
  overflow: hidden;
}
.logo-img { width: 100%; height: 100%; object-fit: cover; }

.panel-form {
  width: 420px; min-width: 420px;
  background: #f5f7fb;
  display: flex; align-items: center; justify-content: center;
  padding: 2rem;
}

.form-card {
  width: 100%; background: #fff;
  border-radius: 12px; padding: 2.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,0.1);
}

h1 { font-size: 1.8rem; color: #1e3a5f; margin: 0 0 0.4rem; text-align: center; }
.subtitulo { color: #666; text-align: center; margin: 0 0 1.5rem; font-size: 0.95rem; }
.paso-desc { color: #444; margin-bottom: 1.2rem; font-size: 0.95rem; }

.form { display: flex; flex-direction: column; gap: 1.2rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group label { font-weight: 600; color: #333; font-size: 0.9rem; }
.form-group input {
  padding: 0.75rem 1rem; border: 1.5px solid #ddd;
  border-radius: 8px; font-size: 1rem; outline: none; transition: border-color 0.2s;
}
.form-group input:focus { border-color: #2d6a9f; }
.input-codigo { text-align: center; letter-spacing: 0.5rem; font-size: 1.4rem; font-weight: 700; }
.hint { color: #888; font-size: 0.8rem; }

.alert-error { background: #fee2e2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; }

.btn-primary {
  background: #1e3a5f; color: #fff; border: none;
  padding: 0.85rem; border-radius: 8px; font-size: 1rem;
  font-weight: 600; cursor: pointer; transition: background 0.2s;
}
.btn-primary:hover:not(:disabled) { background: #2d6a9f; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-secondary {
  background: transparent; color: #2d6a9f;
  border: 1.5px solid #2d6a9f;
  padding: 0.75rem; border-radius: 8px; font-size: 0.9rem;
  cursor: pointer; transition: all 0.2s;
}
.btn-secondary:hover:not(:disabled) { background: #f0f7ff; }

.pasos-indicator { display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem; }
.paso-dot { width: 10px; height: 10px; border-radius: 50%; background: #ddd; transition: background 0.3s; }
.paso-dot.active { background: #2d6a9f; }
.paso-dot.done { background: #22c55e; }

.back-link { text-align: center; margin-top: 1rem; font-size: 0.875rem; }
.back-link a { color: #2d6a9f; text-decoration: none; }
.back-link a:hover { text-decoration: underline; }

@media (max-width: 768px) {
  .login-container { flex-direction: column; }
  .panel-logo { height: 200px; flex: none; }
  .panel-form { width: 100%; min-width: unset; }
}
</style>
