import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

// Inyectar token en cada request (sessionStorage: se pierde al cerrar el navegador)
api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export const authService = {
  login: (login: string, password: string) =>
    api.post('/auth/login', { login, password }),

  verificarUsuario: (usuario: string) =>
    api.post('/auth/verificar-usuario', { usuario }),

  enviarCodigo: (usuario: string, email: string) =>
    api.post('/auth/enviar-codigo', { usuario, email }),

  validarCodigo: (usuario: string, codigo: string) =>
    api.post('/auth/validar-codigo', { usuario, codigo }),

  crearPassword: (usuario: string, token_temporal: string, password: string, password_confirmation: string) =>
    api.post('/auth/crear-password', { usuario, token_temporal, password, password_confirmation }),

  // Recuperación por código de 6 dígitos al email registrado
  recuperar: (usuario: string) =>
    api.post('/auth/recuperar', { usuario }),

  forgotPassword: (email: string) =>
    api.post('/auth/forgot-password', { email }),

  resetPassword: (email: string, token: string, password: string, password_confirmation: string) =>
    api.post('/auth/reset-password', { email, token, password, password_confirmation }),

  logout: () => api.post('/auth/logout'),

  me: () => api.get('/auth/me'),
}

export default api
