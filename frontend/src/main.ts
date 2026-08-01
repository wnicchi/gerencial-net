import './assets/main.css'
import './assets/emoji.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { enterNext } from './directives/enterNext'
import { instalarLogoPdf, precargarLogosPdf } from './utils/pdfLogo'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Regla del proyecto: Enter avanza al siguiente campo en las altas/formularios.
app.directive('enter-next', enterNext)

app.mount('#app')

// Logo de la empresa en el encabezado de todos los PDF (intercepta jsPDF).
instalarLogoPdf()
precargarLogosPdf()
