<!-- EstadoCivilAyuda.vue — Modal de ayuda del módulo Estado Civil. -->
<template>
  <Teleport to="body">
    <transition name="ec-fade" appear>
      <div class="ec-ov" @click.self="$emit('close')">
        <transition name="ec-pop" appear>
          <div class="ec-md">
            <div class="ec-hero">
              <div class="ec-hero-ico">📋</div>
              <div class="ec-hero-tx">
                <h3>Ayuda — Estado Civil</h3>
                <p>ABM de estados civiles</p>
              </div>
              <button class="ec-x" @click="$emit('close')">✕</button>
            </div>

            <div class="ec-body">
              <p class="ec-intro">Este módulo administra la lista de <b>estados civiles</b> (Soltero/a, Casado/a, etc.) que después se asignan a los empleados.</p>

              <div class="ec-diag">
                <svg viewBox="0 0 300 90" width="100%" style="max-width:300px">
                  <rect x="10" y="20" width="120" height="50" rx="8" fill="#fff" stroke="#16a34a" stroke-width="1.5"/>
                  <text x="70" y="40" text-anchor="middle" font-size="11" fill="#1b4332" font-weight="700">Estados</text>
                  <text x="70" y="56" text-anchor="middle" font-size="11" fill="#1b4332" font-weight="700">civiles</text>
                  <path d="M132 45 L168 45" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:flecha 1.8s ease-in-out infinite"/>
                  <path d="M162 40 L170 45 L162 50" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" style="animation:flecha 1.8s ease-in-out infinite"/>
                  <rect x="172" y="20" width="118" height="50" rx="8" fill="#f0fdf4" stroke="#94a3b8" stroke-width="1.5"/>
                  <text x="231" y="48" text-anchor="middle" font-size="11" fill="#1b4332" font-weight="700">Empleados</text>
                </svg>
              </div>

              <div class="ec-pasos">
                <div class="ec-pasos-t">⚡ Cómo se usa</div>
                <ol>
                  <li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }">
                    <span class="ec-num">{{ i+1 }}</span><span v-html="p"></span>
                  </li>
                </ol>
              </div>

              <ul class="ec-notas">
                <li>El <b>código</b> es incremental: lo asigna el sistema (no se edita).</li>
                <li>No se puede <b>eliminar</b> un estado civil si hay empleados que lo tienen asignado.</li>
              </ul>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
defineEmits<{ close: [] }>()
const pasos = [
  '<b>＋ Nuevo:</b> ingresá la descripción y guardá (el código se asigna solo).',
  '<b>✏️ Editar:</b> cambiá la descripción de un registro.',
  '<b>🗑️ Eliminar:</b> borra el registro (si no está en uso).',
]
</script>

<style scoped>
.ec-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000;
  display:flex; align-items:center; justify-content:center; }
.ec-md { width:min(480px,94vw); background:#fff; border-radius:16px; overflow:hidden;
  box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.ec-fade-enter-active,.ec-fade-leave-active{ transition:opacity .25s ease; }
.ec-fade-enter-from,.ec-fade-leave-to{ opacity:0; }
.ec-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.ec-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.ec-hero { display:flex; align-items:center; gap:12px; padding:14px 18px;
  background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.ec-hero-ico { font-size:26px; animation:lat 2.4s ease-in-out infinite; }
@keyframes lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.ec-hero-tx h3 { margin:0; font-size:15px; }
.ec-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.ec-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px;
  border-radius:8px; font-size:14px; cursor:pointer; }
.ec-x:hover{ background:rgba(255,255,255,.3); }
.ec-body { padding:18px 22px; }
.ec-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.ec-diag { display:flex; justify-content:center; background:#f8fafc; border:1px solid #eef2f7;
  border-radius:12px; padding:12px; margin-bottom:14px; }
@keyframes flecha{0%,100%{opacity:.5}50%{opacity:1}}
.ec-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.ec-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.ec-pasos ol { margin:0; padding:0; list-style:none; }
.ec-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5;
  margin-bottom:8px; opacity:0; animation:pasoIn .4s ease forwards; }
.ec-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff;
  font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.ec-notas { margin:0; padding-left:6px; list-style:none; }
.ec-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.ec-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
