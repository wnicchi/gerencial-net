<!--
  AlertasPanel.vue — Panel de alertas (barra roja "ALERTAS" + secciones).
  Consulta /alertas y muestra los avisos agrupados. Reutilizable en el menú y
  en el modal que aparece al iniciar sesión.
-->
<template>
  <div class="al-panel">
    <div class="al-bar">
      <div class="al-ico">
        <span class="al-pulse"></span>
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 3.5a5.5 5.5 0 0 0-5.5 5.5c0 3.2-.8 5-1.6 6.1-.4.6 0 1.4.7 1.4h12.8c.7 0 1.1-.8.7-1.4-.8-1.1-1.6-2.9-1.6-6.1A5.5 5.5 0 0 0 12 3.5Z"
                stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
          <path d="M10 20a2 2 0 0 0 4 0" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
          <circle cx="17.5" cy="6.5" r="3" fill="#fde047" stroke="#dc2626" stroke-width="1.2"/>
        </svg>
      </div>
      <div class="al-bar-txt">ALERTAS</div>
    </div>

    <div class="al-cont">
      <div v-if="cargando" class="al-cargando">
        <div class="al-spin">⟳</div>
        <p>Analizando datos…</p>
      </div>

      <div v-else-if="secciones.length === 0" class="al-vacio">
        <div class="al-vacio-ico">✅</div>
        <p>¡No existen alertas!</p>
      </div>

      <template v-else>
        <section v-for="(s, i) in secciones" :key="i" class="al-sec">
          <h4 class="al-sec-tit">{{ s.titulo }}:</h4>
          <p v-for="(l, j) in s.lineas" :key="j" class="al-linea">{{ l }}</p>
        </section>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/services/auth'

interface Seccion { titulo: string; lineas: string[] }
const secciones = ref<Seccion[]>([])
const cargando = ref(true)
const emit = defineEmits<{ cargado: [total: number] }>()

onMounted(async () => {
  try {
    const { data } = await api.get('/alertas')
    secciones.value = data.secciones ?? []
  } catch (e) { console.error('Error cargando alertas', e) }
  finally { cargando.value = false; emit('cargado', secciones.value.length) }
})
</script>

<style scoped>
.al-panel { display:flex; height:100%; min-height:0; background:#fff; }
.al-bar { width:74px; flex-shrink:0; background:#dc2626;
  display:flex; flex-direction:column; align-items:center; }
.al-ico { position:relative; padding:18px 0 12px; display:flex; align-items:center; justify-content:center; }
.al-ico svg { position:relative; z-index:1; animation:al-tilt 2.4s ease-in-out infinite; transform-origin:50% 12%; }
@keyframes al-tilt { 0%,60%,100% { transform:rotate(0); } 70% { transform:rotate(10deg); } 80% { transform:rotate(-8deg); } 90% { transform:rotate(4deg); } }
.al-pulse { position:absolute; width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,.55); animation:al-pulse 1.8s ease-out infinite; }
@keyframes al-pulse { 0% { transform:scale(.6); opacity:.6; } 100% { transform:scale(2.1); opacity:0; } }
.al-bar-txt { writing-mode:vertical-rl; transform:rotate(180deg); color:#fff; font-weight:900; font-size:28px;
  letter-spacing:6px; margin-top:10px; }
.al-cont { flex:1; overflow:auto; padding:18px 22px; min-width:0; }

.al-cargando, .al-vacio { height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b; gap:8px; }
.al-spin { font-size:34px; animation:al-rot 1s linear infinite; color:#dc2626; }
@keyframes al-rot { to { transform:rotate(360deg); } }
.al-vacio-ico { font-size:44px; }
.al-vacio p { font-size:18px; font-weight:700; color:#16a34a; }

.al-sec { margin-bottom:18px; }
.al-sec-tit { margin:0 0 8px; font-size:13.5px; font-weight:800; color:#1d4ed8; letter-spacing:.2px; }
.al-linea { margin:0 0 3px; font-size:12.5px; color:#334155; font-family:'Consolas','Courier New',monospace; white-space:pre-wrap; line-height:1.5; padding-left:14px; }
</style>
