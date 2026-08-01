<!-- DescuentosAyuda.vue — Modal de ayuda del módulo Descuentos. -->
<template>
  <Teleport to="body">
    <transition name="dc-fade" appear>
      <div class="dc-ov" @click.self="$emit('close')">
        <transition name="dc-pop" appear>
          <div class="dc-md">
            <div class="dc-hero">
              <div class="dc-hero-ico">➖</div>
              <div class="dc-hero-tx"><h3>Ayuda — Descuentos</h3><p>Conceptos que se le descuentan</p></div>
              <button class="dc-x" @click="$emit('close')">✕</button>
            </div>
            <div class="dc-body">
              <p class="dc-intro">Acá se administran los <b>descuentos</b> (conceptos que se le <b>descuentan</b> al empleado), con sus indicadores, estado y convenio.</p>
              <div class="dc-pasos">
                <div class="dc-pasos-t">⚡ Cómo se usa</div>
                <ol><li v-for="(p, i) in pasos" :key="i" :style="{ animationDelay: (i*90+120)+'ms' }"><span class="dc-num">{{ i+1 }}</span><span v-html="p"></span></li></ol>
              </div>
              <ul class="dc-notas">
                <li><b>Variable</b> usa una <b>alícuota</b> (porcentaje); <b>Fijo</b> usa un <b>importe</b>.</li>
                <li>Según el convenio sea <b>mensualizado</b> o <b>jornal</b>, se define si va en los meses o en 1ra/2da quincena.</li>
                <li><b>Va SAC</b> indica si el descuento también se aplica al aguinaldo (Sí, No o M).</li>
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
  '<b>＋ Nuevo:</b> cargá descripción, los indicadores, el estado (Variable/Fijo) y el convenio.',
  '<b>✏️ Editar:</b> modificá los datos del descuento.',
  '<b>🗑️ Eliminar:</b> borra el descuento.',
]
</script>

<style scoped>
.dc-ov { position:fixed; inset:0; background:rgba(15,23,42,.55); backdrop-filter:blur(3px); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.dc-md { width:min(460px,96vw); background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); font-family:var(--font-sans,sans-serif); }
.dc-fade-enter-active,.dc-fade-leave-active{ transition:opacity .25s ease; } .dc-fade-enter-from,.dc-fade-leave-to{ opacity:0; }
.dc-pop-enter-active{ transition:transform .38s cubic-bezier(.2,.9,.3,1.2),opacity .3s ease; } .dc-pop-enter-from{ transform:translateY(30px) scale(.94); opacity:0; }
.dc-hero { display:flex; align-items:center; gap:12px; padding:14px 18px; background:linear-gradient(120deg,#1b4332,#40916c); color:#fff; }
.dc-hero-ico { font-size:26px; animation:dc-lat 2.4s ease-in-out infinite; } @keyframes dc-lat{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
.dc-hero-tx h3 { margin:0; font-size:15px; } .dc-hero-tx p { margin:2px 0 0; font-size:11px; opacity:.85; }
.dc-x { margin-left:auto; background:rgba(255,255,255,.15); border:none; color:#fff; width:28px; height:28px; border-radius:8px; font-size:14px; cursor:pointer; } .dc-x:hover{ background:rgba(255,255,255,.3); }
.dc-body { padding:18px 22px; } .dc-intro { font-size:14px; color:#374151; line-height:1.55; margin:0 0 14px; }
.dc-pasos { background:#f8fafc; border:1px solid #eef2f7; border-radius:12px; padding:12px 16px; margin-bottom:12px; }
.dc-pasos-t { font-size:12px; font-weight:700; color:#1b4332; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }
.dc-pasos ol { margin:0; padding:0; list-style:none; }
.dc-pasos li { display:flex; align-items:flex-start; gap:10px; font-size:13px; color:#374151; line-height:1.5; margin-bottom:8px; opacity:0; animation:dc-pasoIn .4s ease forwards; }
.dc-num { flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#16a34a; color:#fff; font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; }
@keyframes dc-pasoIn{ from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:none} }
.dc-notas { margin:0; padding-left:6px; list-style:none; }
.dc-notas li { font-size:13px; color:#475569; line-height:1.55; margin-bottom:6px; padding-left:24px; position:relative; } .dc-notas li::before { content:'💡'; position:absolute; left:0; }
</style>
