<template>
  <div class="login-container">
    <div class="panel-logo">
      <img :src="auth.fondoLogin" alt="Sistema de Recursos Humanos" class="logo-img" />
    </div>
    <div class="panel-form">
      <div class="form-card">
        <h1>Sistema RRHH</h1>
        <p class="subtitulo">Nueva contraseña</p>

        <div v-if="!reseteado">
          <form @submit.prevent="resetear" class="form">
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
              {{ cargando ? 'Guardando...' : 'Guardar nueva contraseña' }}
            </button>
          </form>
        </div>

        <div v-else class="alert-success">
          <strong>¡Contraseña actualizada!</strong> Ya podés iniciar sesión.
          <br /><br />
          <router-link to="/login" class="btn-primary" style="display:inline-block;text-decoration:none;text-align:center;width:100%;">
            Ir al login
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { authService } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const auth      = useAuthStore()
const route     = useRoute()
const cargando  = ref(false)
const error     = ref('')
const reseteado = ref(false)
const form      = ref({ password: '', password_confirmation: '' })
const email     = ref('')
const token     = ref('')

onMounted(() => {
  email.value = route.query.email as string ?? ''
  token.value = route.query.token as string ?? ''
})

async function resetear() {
  error.value = ''; cargando.value = true
  try {
    await authService.resetPassword(email.value, token.value, form.value.password, form.value.password_confirmation)
    reseteado.value = true
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Error al restablecer la contraseña.'
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
.form { display: flex; flex-direction: column; gap: 1.2rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group label { font-weight: 600; color: #333; font-size: 0.9rem; }
.form-group input { padding: 0.75rem 1rem; border: 1.5px solid #ddd; border-radius: 8px; font-size: 1rem; outline: none; transition: border-color 0.2s; }
.form-group input:focus { border-color: #2d6a9f; }
.hint { color: #888; font-size: 0.8rem; }
.alert-error { background: #fee2e2; color: #dc2626; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; }
.alert-success { background: #dcfce7; color: #16a34a; padding: 1rem; border-radius: 8px; font-size: 0.95rem; }
.btn-primary { background: #1e3a5f; color: #fff; border: none; padding: 0.85rem; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover:not(:disabled) { background: #2d6a9f; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
@media (max-width: 768px) { .login-container { flex-direction: column; } .panel-logo { height: 200px; flex: none; } .panel-form { width: 100%; min-width: unset; } }
</style>
