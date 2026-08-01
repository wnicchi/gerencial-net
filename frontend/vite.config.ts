import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    port: 5176,      // GERENCIAL (RRHH 5173, WMS 5173, GESTION 5175)
    strictPort: true,
  },
  build: {
    // El servidor de producción corre sobre Windows 8.1 (Chrome máx. 109).
    // Vite 8 apunta por defecto a navegadores más nuevos y dejaba el bundle
    // sin ejecutar (pantalla en blanco). Bajamos el target a Chrome 109.
    target: ['es2020', 'chrome109', 'edge109', 'firefox115', 'safari15'],
  },
})
