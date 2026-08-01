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
        <p class="subtitulo">Iniciá sesión para continuar</p>

        <form @submit.prevent="handleLogin">
          <div class="form-group">
            <label for="login">Usuario o Email</label>
            <input
              id="login"
              v-model="form.login"
              type="text"
              placeholder="usuario o tu@email.com"
              autocomplete="username"
              required
            />
          </div>

          <div class="form-group">
            <label for="password">Contraseña</label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            />
          </div>

          <div v-if="error" class="alert-error">{{ error }}</div>

          <button type="submit" class="btn-primary" :disabled="cargando">
            {{ cargando ? 'Ingresando...' : 'Ingresar' }}
          </button>

          <div class="login-links">
            <router-link to="/forgot-password">¿Olvidaste tu contraseña?</router-link>
            <span>|</span>
            <router-link to="/primer-acceso">Primer acceso</router-link>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router   = useRouter()
const auth     = useAuthStore()
const cargando = ref(false)
const error    = ref('')
const form     = ref({ login: '', password: '' })

async function handleLogin() {
  error.value    = ''
  cargando.value = true
  try {
    await auth.login(form.value.login, form.value.password)
    router.push('/dashboard')
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Error al iniciar sesión.'
  } finally {
    cargando.value = false
  }
}
</script>

<style scoped>
.login-container {
  display: flex;
  min-height: 100vh;
}

/* ── Panel izquierdo ── */
.panel-logo {
  flex: 1;
  background: #f0f0f0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.logo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ── Panel derecho ── */
.panel-form {
  width: 420px;
  min-width: 420px;
  background: #f5f7fb;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.form-card {
  width: 100%;
  background: #fff;
  border-radius: 12px;
  padding: 2.5rem;
  box-shadow: 0 4px 24px rgba(0,0,0,0.1);
}

h1 {
  font-size: 1.8rem;
  color: #1e3a5f;
  margin: 0 0 0.4rem;
  text-align: center;
}

.subtitulo {
  color: #666;
  text-align: center;
  margin: 0 0 2rem;
  font-size: 0.95rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.form-group label {
  font-weight: 600;
  color: #333;
  font-size: 0.9rem;
}

.form-group input {
  padding: 0.75rem 1rem;
  border: 1.5px solid #ddd;
  border-radius: 8px;
  font-size: 1rem;
  outline: none;
  transition: border-color 0.2s;
}

.form-group input:focus {
  border-color: #2d6a9f;
}

.alert-error {
  background: #fee2e2;
  color: #dc2626;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.9rem;
}

.btn-primary {
  background: #1e3a5f;
  color: #fff;
  border: none;
  padding: 0.85rem;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover:not(:disabled) {
  background: #2d6a9f;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.login-links {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
  font-size: 0.875rem;
}

.login-links a {
  color: #2d6a9f;
  text-decoration: none;
}

.login-links a:hover {
  text-decoration: underline;
}

.login-links span {
  color: #ccc;
}

/* ── Responsive ── */
@media (max-width: 768px) {
  .login-container {
    flex-direction: column;
  }
  .panel-logo {
    height: 200px;
    flex: none;
  }
  .panel-form {
    width: 100%;
    min-width: unset;
  }
}
</style>
