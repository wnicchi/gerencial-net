<!-- ImportarObraSocialAyuda.vue — Modal de ayuda del módulo Importar Costos Obras Sociales. -->
<template>
  <Teleport to="body">
    <transition name="os-fade" appear>
      <div class="os-ov" @click.self="$emit('close')">
        <transition name="os-pop" appear>
          <div class="os-md">
            <div class="os-hero">
              <div class="os-hero-ico">🏥</div>
              <div class="os-hero-tx">
                <h3>Ayuda — Costos Obras Sociales</h3>
                <p>Importar, consultar y generar informes</p>
              </div>
              <button class="os-x" @click="$emit('close')">✕</button>
            </div>

            <div class="os-body">
              <p class="os-intro">Este módulo maneja los costos de <b>obras sociales</b> de los empleados, mes a mes. Tiene <b>tres solapas</b>.</p>

              <div class="os-secs">
                <div class="os-sec">
                  <div class="os-sec-h">📥 Importar Costo O.S.</div>
                  <ol>
                    <li v-for="(p, i) in pasos1" :key="i"><span class="os-n">{{ i+1 }}</span><span v-html="p"></span></li>
                  </ol>
                </div>
                <div class="os-sec">
                  <div class="os-sec-h">📋 Historial</div>
                  <p>Muestra <b>todo lo importado</b> (empleado, obra social, mes/año, neto, aporte y diferencia) con los <b>totales</b>. Podés filtrar por nombre, obra o CUIL.</p>
                </div>
                <div class="os-sec">
                  <div class="os-sec-h">📊 Informes</div>
                  <p>Para un <b>período</b> (mes/año), opcionalmente por <b>obra social</b> y/o <b>convenio</b>, genera el informe con sueldo, promedio de horas extras del último semestre, plan, aporte, diferencia y costo real. Lo podés <b>ver en PDF</b> o <b>exportar a Excel</b>.</p>
                </div>
              </div>

              <ul class="os-notas">
                <li><b>Plan</b> carga el neto; <b>Aportes</b> carga el aporte; la <b>diferencia</b> se calcula sola.</li>
                <li>Podés importar haciendo coincidir por <b>CUIL</b> o por <b>DNI</b>, según lo que tenga la planilla.</li>
                <li>Si un empleado ya tenía cargado ese mes, la importación lo <b>actualiza</b>.</li>
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
const pasos1 = [
  'Elegí <b>mes/año</b>, la <b>obra social</b> y el <b>tipo de planilla</b> (Plan o Aportes).',
  '<b>Buscá el archivo</b> Excel y revisá la grilla (columnas A, B, C…).',
  'Indicá en qué columna están el <b>CUIL/DNI</b> y el <b>Importe</b>.',
  'Tocá <b>Importar por CUIL</b> o <b>Importar por DNI</b>.',
]
</script>

<style scoped>
.os-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.os-md { width:min(560px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.os-fade-enter-active,.os-fade-leave-active{ transition:opacity .25s ease; }
.os-fade-enter-from,.os-fade-leave-to{ opacity:0; }
.os-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; }
.os-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.os-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; position:sticky; top:0; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.os-hero-ico { font-size:26px; animation:os-lat 2.4s ease-in-out infinite; }
@keyframes os-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.os-hero-tx h3 { margin:0; font-size:15px; } .os-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.os-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; }
.os-body { padding:18px 22px; }
.os-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.os-secs { display:flex; flex-direction:column; gap:10px; margin-bottom:14px; }
.os-sec { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; }
.os-sec-h { font-size:12.5px; font-weight:700; color:#1b4332; margin-bottom:8px; }
.os-sec p { margin:0; font-size:13px; color:#475569; line-height:1.5; }
.os-sec ol { margin:0; padding:0; list-style:none; }
.os-sec li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:7px; }
.os-n { flex-shrink:0; width:21px; height:21px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
.os-notas { margin:0; padding-left:6px; list-style:none; }
.os-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; }
.os-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
