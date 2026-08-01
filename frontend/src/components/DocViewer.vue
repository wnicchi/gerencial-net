<!-- DocViewer.vue — Visor de documentos reutilizable.
     Regla: PDF/imágenes en iframe, Excel (.xlsx/.xls) como tabla (SheetJS),
     Word (.docx) con docx-preview; cualquier otro formato se descarga directo.
     Uso: <DocViewer ref="visor" /> y luego visor.value.open(blob, nombre). -->
<template>
  <Teleport to="body">
    <div v-if="preview" class="dv-ov" @click.self="cerrar">
      <div class="dv-md">
        <div class="dv-head"><span>{{ preview.nombre }}</span>
          <div class="dv-acc">
            <button class="dv-b ok" @click="descargar">⬇ Descargar</button>
            <button class="dv-b cancel" @click="cerrar">✕ Cerrar</button>
          </div>
        </div>
        <iframe v-if="preview.tipo === 'pdf'" :src="preview.url" class="dv-frame"></iframe>
        <div v-else-if="preview.tipo === 'xlsx'" class="dv-scroll dv-xls" v-html="preview.html"></div>
        <div v-else-if="preview.tipo === 'docx'" class="dv-scroll"><div ref="docxBox"></div></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { guardarDesdeUrl } from '@/utils/descargas'
import * as XLSX from 'xlsx'
import { renderAsync } from 'docx-preview'

interface Preview { tipo: 'pdf' | 'xlsx' | 'docx'; nombre: string; url: string; html?: string }
const preview = ref<Preview | null>(null)
const docxBox = ref<HTMLElement | null>(null)
const IMG_PDF = ['PDF', 'PNG', 'JPG', 'JPEG', 'GIF', 'WEBP', 'BMP', 'SVG']

const cerrar = () => { if (preview.value?.url) URL.revokeObjectURL(preview.value.url); preview.value = null }
const descargar = () => { if (preview.value) guardarDesdeUrl(preview.value.url, preview.value.nombre) }

async function open (blob: Blob, nombre: string) {
  const ext = (nombre.split('.').pop() || '').toUpperCase()
  const url = URL.createObjectURL(blob)
  if (IMG_PDF.includes(ext)) {
    cerrar(); preview.value = { tipo: 'pdf', nombre, url }
  } else if (ext === 'XLSX' || ext === 'XLS') {
    const wb = XLSX.read(await blob.arrayBuffer(), { type: 'array' })
    let html = ''
    for (const n of wb.SheetNames) html += `<div class="xls-h">${n}</div>` + XLSX.utils.sheet_to_html(wb.Sheets[n]!)
    cerrar(); preview.value = { tipo: 'xlsx', nombre, url, html }
  } else if (ext === 'DOCX') {
    cerrar(); preview.value = { tipo: 'docx', nombre, url }
    await nextTick()
    if (docxBox.value) { docxBox.value.innerHTML = ''; await renderAsync(blob, docxBox.value, undefined, { className: 'docx', inWrapper: true }) }
  } else {
    // Sin previsualización posible: descargar directo.
    guardarDesdeUrl(url, nombre); setTimeout(() => URL.revokeObjectURL(url), 4000)
  }
}
defineExpose({ open })
</script>

<style scoped>
.dv-ov { position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:10000; display:flex; align-items:center; justify-content:center; padding:18px; }
.dv-md { width:min(900px,97vw); height:92vh; background:#fff; border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.dv-head { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#1b4332; color:#fff; font-size:13px; } .dv-acc { margin-left:auto; display:flex; gap:8px; }
.dv-b { border:none; padding:7px 12px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600; } .dv-b.ok { background:#22c55e; color:#fff; } .dv-b.cancel { background:#ef4444; color:#fff; }
.dv-frame { flex:1; border:none; width:100%; }
.dv-scroll { flex:1; overflow:auto; background:#f1f5f9; padding:16px; }
.dv-xls :deep(table) { border-collapse:collapse; background:#fff; font-size:12.5px; margin-bottom:18px; box-shadow:0 1px 4px rgba(0,0,0,.1); }
.dv-xls :deep(td), .dv-xls :deep(th) { border:1px solid #cbd5e1; padding:3px 8px; color:#1e293b; white-space:nowrap; }
.dv-xls :deep(.xls-h) { font-weight:700; color:#1b4332; margin:4px 0 6px; font-size:13px; }
.dv-scroll :deep(.docx-wrapper) { background:#f1f5f9; padding:0; }
.dv-scroll :deep(.docx) { background:#fff; box-shadow:0 1px 8px rgba(0,0,0,.15); margin:0 auto; }
</style>
