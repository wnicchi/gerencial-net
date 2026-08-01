<!-- CambioClaveView.vue — Sistema / Seguridad / Cambio de Clave. -->
<template>
  <div class="cc-view">
    <div class="cc-cab">
      <div class="cc-cab-ico">🔑</div>
      <div class="cc-cab-tx"><h1>Cambio de Clave</h1><p>Cambiá tu contraseña de acceso al sistema</p></div>
    </div>

    <div class="cc-card" v-enter-next>
      <p class="cc-user" v-if="usuario">Usuario: <b>{{ usuario }}</b></p>

      <div class="cc-f">
        <span class="cc-lbl">Contraseña actual</span>
        <div class="cc-pwrap"><input :type="ver1 ? 'text' : 'password'" v-model="actual" class="cc-inp" autocomplete="current-password" /><button class="cc-eye" @click="ver1 = !ver1">{{ ver1 ? '🙈' : '👁' }}</button></div>
      </div>
      <div class="cc-f">
        <span class="cc-lbl">Nueva contraseña</span>
        <div class="cc-pwrap"><input :type="ver2 ? 'text' : 'password'" v-model="nueva" class="cc-inp" autocomplete="new-password" /><button class="cc-eye" @click="ver2 = !ver2">{{ ver2 ? '🙈' : '👁' }}</button></div>
        <ul class="cc-reglas">
          <li :class="{ ok: r.largo }">Mínimo 8 caracteres</li>
          <li :class="{ ok: r.mayus }">Mayúsculas y minúsculas</li>
          <li :class="{ ok: r.numero }">Al menos un número</li>
          <li :class="{ ok: r.simbolo }">Al menos un símbolo</li>
          <li :class="{ ok: r.distinta }">Distinta a la actual</li>
        </ul>
      </div>
      <div class="cc-f">
        <span class="cc-lbl">Repetir nueva contraseña</span>
        <input type="password" v-model="repetir" class="cc-inp" autocomplete="new-password" />
        <span v-if="repetir && repetir !== nueva" class="cc-err-mini">No coincide con la nueva contraseña.</span>
      </div>

      <button class="cc-btn" :disabled="!valido || procesando" @click="cambiar">{{ procesando ? '⟳…' : 'Cambiar contraseña' }}</button>
      <p v-if="msg" :class="['cc-msg', msgError ? 'err' : 'ok']">{{ msg }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import api from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const usuario = computed(() => (auth.usuario as any)?.NOMBRE ?? (auth.usuario as any)?.DATO1 ?? '')
const actual = ref(''); const nueva = ref(''); const repetir = ref('')
const ver1 = ref(false); const ver2 = ref(false)
const procesando = ref(false); const msg = ref(''); const msgError = ref(false)

const r = computed(() => ({
  largo: nueva.value.length >= 8,
  mayus: /[a-z]/.test(nueva.value) && /[A-Z]/.test(nueva.value),
  numero: /\d/.test(nueva.value),
  simbolo: /[^A-Za-z0-9]/.test(nueva.value),
  distinta: nueva.value !== '' && nueva.value !== actual.value,
}))
const valido = computed(() => actual.value !== '' && Object.values(r.value).every(Boolean) && nueva.value === repetir.value)

const cambiar = async () => {
  procesando.value = true; msg.value = ''
  try {
    await api.post('/auth/cambiar-password', { actual: actual.value, password: nueva.value, password_confirmation: repetir.value })
    msg.value = 'Contraseña cambiada correctamente.'; msgError.value = false
    actual.value = ''; nueva.value = ''; repetir.value = ''
  } catch (e: any) {
    msg.value = e?.response?.data?.message ?? (Object.values(e?.response?.data?.errors ?? {})[0] as any)?.[0] ?? 'No se pudo cambiar la contraseña.'
    msgError.value = true
  } finally { procesando.value = false }
}
</script>

<style scoped>
.cc-view { display:flex; flex-direction:column; height:100%; overflow:auto; }
.cc-cab { display:flex; align-items:center; gap:14px; padding:14px 18px; background:#fff; border-bottom:1px solid #e2e8f0; }
.cc-cab-ico { font-size:28px; } .cc-cab-tx h1 { margin:0; font-size:19px; color:#1e293b; } .cc-cab-tx p { margin:2px 0 0; font-size:13px; color:#6b7280; }
.cc-card { margin:24px auto; width:min(440px,92vw); display:flex; flex-direction:column; gap:16px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; box-shadow:0 10px 30px rgba(0,0,0,.05); }
.cc-user { margin:0; font-size:13px; color:#475569; } .cc-user b { color:#1b4332; }
.cc-f { display:flex; flex-direction:column; gap:5px; } .cc-lbl { font-size:12.5px; font-weight:700; color:#1b4332; }
.cc-inp { border:1px solid #d1d5db; border-radius:7px; padding:10px 11px; font-size:14px; color:#1e293b; width:100%; box-sizing:border-box; }
.cc-pwrap { position:relative; } .cc-eye { position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:15px; }
.cc-reglas { margin:4px 0 0; padding:0; list-style:none; display:flex; flex-wrap:wrap; gap:4px 14px; }
.cc-reglas li { font-size:11.5px; color:#94a3b8; } .cc-reglas li::before { content:'○ '; } .cc-reglas li.ok { color:#16a34a; } .cc-reglas li.ok::before { content:'✓ '; }
.cc-err-mini { font-size:11.5px; color:#b91c1c; }
.cc-btn { background:#16a34a; color:#fff; border:none; padding:12px; border-radius:8px; cursor:pointer; font-size:14px; font-weight:800; } .cc-btn:disabled { background:#cbd5e1; cursor:default; }
.cc-msg { padding:10px 14px; font-size:13px; border-radius:7px; } .cc-msg.ok { background:#dcfce7; color:#166534; } .cc-msg.err { background:#fee2e2; color:#b91c1c; }
</style>
