<template>
  <!-- Hero de las pantallas de autenticación (login, primer acceso, recuperar, reset) -->
  <div class="panel-logo">
    <div class="hero">
      <div class="hero-badge">📊 SILCAR · Logística</div>
      <h2 class="hero-title">Tablero<br />Gerencial</h2>
      <p class="hero-sub">RRHH y Logística consolidados en un solo lugar, para decidir con datos.</p>
      <div class="hero-chips">
        <span class="hero-chip"><span class="hc-ico">📈</span> Sueldos y Personal</span>
        <span class="hero-chip"><span class="hc-ico">📦</span> Stock por empresa</span>
        <span class="hero-chip"><span class="hc-ico">🔔</span> Alertas</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.panel-logo {
  flex: 1;
  background: radial-gradient(circle at 20% 20%, #2d6a5f 0%, #1b4332 55%, #12291f 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 3rem;
}
.hero { max-width: 460px; color: #eafff4; }
.hero-badge {
  display: inline-block;
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.22);
  color: #d7fbe9;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.32rem 0.8rem;
  border-radius: 20px;
  margin-bottom: 1.4rem;
}
.hero-title {
  font-size: 3.2rem;
  line-height: 1.02;
  font-weight: 800;
  margin: 0 0 1rem;
  color: #ffffff;
  letter-spacing: -0.01em;
}
.hero-sub {
  font-size: 1.05rem;
  line-height: 1.5;
  color: #b9e6d1;
  margin: 0 0 2rem;
}
.hero-chips { display: flex; flex-direction: column; gap: 0.6rem; }
.hero-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.14);
  border-radius: 10px;
  padding: 0.55rem 0.85rem;
  font-size: 0.95rem;
  color: #eafff4;
  width: fit-content;
}
.hc-ico { font-size: 1.15rem; }

@media (max-width: 768px) {
  .panel-logo { min-height: 220px; flex: none; padding: 1.8rem; }
  .hero-title { font-size: 2.4rem; }
}
</style>
