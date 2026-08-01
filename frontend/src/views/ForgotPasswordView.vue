<!--
  ForgotPasswordView.vue — Recuperar contraseña por código.
  El usuario ingresa su login; si tiene email registrado, se le envía un código
  de 6 dígitos a ese email (no se permite ingresar uno nuevo, por seguridad) y
  con eso define una nueva contraseña. Si no tiene email registrado, se le indica
  que debe pedirle la contraseña al administrador.
-->
<template>
  <div class="login-container">
    <div class="panel-logo">
      <img :src="auth.fondoLogin" alt="RRHH" class="logo-img" />
    </div>
    <div class="panel-form">
      <div class="form-card">
        <h1>Sistema RRHH</h1>
        <p class="subtitulo">Recuperar contraseña</p>

        <!-- PASO 1: login -->
        <form v-if="paso === 'login'" @submit.prevent="pedirCodigo" class="form">
          <p class="paso-desc">Ingresá tu usuario y te enviaremos un código a tu email para restablecer la contraseña.</p>
          <div class="form-group">
            <label>Usuario</label>
            <input v-model="login" type="text" placeholder="tu usuario" required autofocus />
          </div>
          <div v-if="error" class="alert-error">{{ error }}</div>
          <button type="submit" class="btn-primary" :disabled="cargando">
            {{ cargando ? 'Enviando...' : 'Enviar código' }}
          </button>
        </form>

        <!-- PASO 2: código -->
        <form v-else-if="paso === 'codigo'" @submit.prevent="validar" class="form">
          <p class="paso-desc">Te enviamos un código de 6 dígitos a <strong>{{ pista }}</strong>. Ingresalo acá.</p>
          <div class="form-group">
            <label>Código</label>
            <input v-model="codigo" type="text" inputmode="numeric" maxlength="6" placeholder="______" required autofocus class="cod-input" />
          </div>
          <div v-if="error" class="alert-error">{{ error }}</div>
          <button type="submit" class="btn-primary" :disabled="cargando">
            {{ cargando ? 'Validando...' : 'Validar código' }}
          </button>
          <button type="button" class="link-btn" @click="paso = 'login'; error = ''">← Cambiar usuario</button>
        </form>

        <!-- PASO 3: nueva contraseña -->
        <form v-else-if="paso === 'clave'" @submit.prevent="guardarClave" class="form">
          <p class="paso-desc">Definí tu nueva contraseña.</p>
          <div class="form-group">
            <label>Nueva contraseña</label>
            <input v-model="password" type="password" placeholder="••••••••" required autofocus />
          </div>
          <div class="form-group">
            <label>Repetir contraseña</label>
            <input v-model="password2" type="password" placeholder="••••••••" required />
          </div>
          <p class="hint">Mínimo 8 caracteres, con mayúsculas, minúsculas, número y símbolo.</p>
          <div v-if="error" class="alert-error">{{ error }}</div>
          <button type="submit" class="btn-primary" :disabled="cargando">
            {{ cargando ? 'Guardando...' : 'Guardar contraseña' }}
          </button>
        </form>

        <!-- SIN EMAIL: cartel para el administrador -->
        <div v-else-if="paso === 'sinEmail'" class="cartel-admin">
          <div class="cartel-ico">🔒</div>
          <div class="cartel-titulo">Necesitás al administrador</div>
          <p class="cartel-texto">
            Tu usuario no tiene un email registrado, así que no podemos enviarte un código.
            <strong>Solicitale al administrador del sistema que te asigne una contraseña.</strong>
          </p>
        </div>

        <!-- LISTO -->
        <div v-else class="alert-success">
          <strong>¡Listo!</strong> Tu contraseña fue actualizada. Ya podés iniciar sesión.
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
import { authService } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

type Paso = 'login' | 'codigo' | 'clave' | 'sinEmail' | 'listo'
const paso = ref<Paso>('login')

const login     = ref('')
const codigo    = ref('')
const password  = ref('')
const password2 = ref('')
const pista     = ref('')
const tokenTemporal = ref('')

const cargando = ref(false)
const error    = ref('')

async function pedirCodigo () {
  error.value = ''; cargando.value = true
  try {
    const { data } = await authService.recuperar(login.value.trim())
    if (data?.sin_email) {
      paso.value = 'sinEmail'
    } else {
      pista.value = data?.pista ?? 'tu email'
      paso.value = 'codigo'
    }
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'No se pudo procesar la solicitud.'
  } finally { cargando.value = false }
}

async function validar () {
  error.value = ''; cargando.value = true
  try {
    const { data } = await authService.validarCodigo(login.value.trim(), codigo.value.trim())
    tokenTemporal.value = data?.token_temporal ?? ''
    paso.value = 'clave'
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Código inválido o expirado.'
  } finally { cargando.value = false }
}

async function guardarClave () {
  error.value = ''
  if (password.value !== password2.value) { error.value = 'Las contraseñas no coinciden.'; return }
  cargando.value = true
  try {
    await authService.crearPassword(login.value.trim(), tokenTemporal.value, password.value, password2.value)
    paso.value = 'listo'
  } catch (e: any) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message ?? 'No se pudo guardar la contraseña.')
  } finally { cargando.value = false }
}
</script>

<style scoped>
.login-container { display: flex; min-height: 100vh; }
.panel-logo { flex: 1; overflow: hidden; }
.logo-img { width: 100%; height: 100%; object-fit: cover; }
.panel-form { width: 420px; min-width: 420px; background: #f5f7fb; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.form-card { width: 100%; background: #fff; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 24px rgba(0,0,0,0.1); }
h1 { font-size: 1.8rem; color: #1e3a5f; margin: 0 0 0.4rem; text-align: center; }
.subtitulo { color: #666; text-align: center; margin: 0 0 1.5rem; font-size: 0.95rem; }
.paso-desc { color: #444; margin-bottom: 1.2rem; font-size: 0.95rem; }
.form { display: flex; flex-direction: column; gap: 1.2rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group label { font-weight: 600; color: #333; font-size: 0.9rem; }
.form-group input { padding: 0.75rem 1rem; border: 1.5px solid #ddd; border-radius: 8px; font-size: 1rem; outline: none; transition: border-color 0.2s; }
.form-group input:focus { border-color: #2d6a9f; }
.cod-input { letter-spacing: 0.5rem; text-align: center; font-size: 1.4rem; font-weight: 700; }
.hint { font-size: 0.78rem; color: #888; margin: -0.4rem 0 0; }
.alert-error { background: #fee2e2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; }
.alert-success { background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; font-size: 0.95rem; }
.btn-primary { background: #1e3a5f; color: #fff; border: none; padding: 0.85rem; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover:not(:disabled) { background: #2d6a9f; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.link-btn { background: none; border: none; color: #2d6a9f; font-size: 0.85rem; cursor: pointer; text-decoration: underline; padding: 0; }

/* Cartel administrador */
.cartel-admin { text-align: center; background: #fff7ed; border: 2px solid #fed7aa; border-radius: 12px; padding: 1.5rem 1.2rem; }
.cartel-ico { font-size: 2.6rem; }
.cartel-titulo { font-size: 1.25rem; font-weight: 800; color: #9a3412; margin: 0.4rem 0 0.6rem; }
.cartel-texto { color: #7c2d12; font-size: 0.95rem; line-height: 1.45; margin: 0; }

.back-link { text-align: center; margin-top: 1.5rem; font-size: 0.875rem; }
.back-link a { color: #2d6a9f; text-decoration: none; }
.back-link a:hover { text-decoration: underline; }
@media (max-width: 768px) { .login-container { flex-direction: column; } .panel-logo { height: 200px; flex: none; } .panel-form { width: 100%; min-width: unset; } }
</style>
