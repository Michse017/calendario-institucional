/* cro · utilidades globales + componentes Alpine. Se carga ANTES de Alpine (que va con defer). */
(function () {
  const meta = (n) => (document.querySelector(`meta[name="${n}"]`) || {}).content || '';
  const base = meta('base-url');
  window.CRO = {
    base,
    csrf: meta('csrf-token'),
    url(r, params = {}) {
      const p = new URLSearchParams({ r, ...params });
      return `${base}/?${p.toString()}`;
    },
    esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },
    // Clave de comparación: minúsculas, sin tildes y sin espacios de sobra (misma idea que Normalizador en PHP).
    norm(s) {
      return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    },
    async fetchJson(r, params = {}, init = {}) {
      const res = await fetch(this.url(r, params), {
        ...init,
        headers: { Accept: 'application/json', 'X-CSRF-Token': this.csrf, ...(init.headers || {}) },
      });
      let j = null;
      try { j = await res.json(); } catch (e) { j = { ok: false, error: `Respuesta inválida (${res.status})` }; }
      return j;
    },
    ymd(d) {
      const z = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
    },
  };
})();

// Confirmación declarativa: <form data-confirmar="…"> o <button data-confirmar="…" data-requiere="campo">
// (evita interpolar valores de usuario dentro de onclick="…confirm()…", que es inyección de JS: ver revisión de seguridad Task 13).
document.addEventListener('submit', (e) => {
  const f = e.target;
  const b = e.submitter && e.submitter.dataset.confirmar !== undefined ? e.submitter : null;
  const el = b || (f.dataset.confirmar !== undefined ? f : null);
  if (!el) return;
  const req = el.dataset.requiere;
  if (req && f.elements[req] && f.elements[req].value === '') { e.preventDefault(); return; }
  if (!window.confirm(el.dataset.confirmar)) e.preventDefault();
}, true);

document.addEventListener('alpine:init', () => {
  Alpine.data('app', () => ({
    oscuro: document.documentElement.classList.contains('dark'),
    menuUsuario: false,
    alternarTema() {
      this.oscuro = !this.oscuro;
      document.documentElement.classList.toggle('dark', this.oscuro);
      try { localStorage.setItem('cro-tema', this.oscuro ? 'oscuro' : 'claro'); } catch (e) {}
    },
  }));

  // Aviso de "Evento creado", "Evento eliminado"…: se va solo pasados unos segundos, con una barra que
  // muestra lo que queda y que se detiene mientras el ratón está encima para poder leerlo.
  Alpine.data('avisoFlash', (segundos = 6) => ({
    ver: true,
    restante: 100,
    _t: null,
    init() { this.arrancar(); },
    arrancar() {
      const paso = 100 / (segundos * 20);
      this._t = setInterval(() => {
        this.restante -= paso;
        if (this.restante <= 0) this.cerrar();
      }, 50);
    },
    pausar() { clearInterval(this._t); this._t = null; },
    seguir() { if (!this._t && this.ver) this.arrancar(); },
    cerrar() { clearInterval(this._t); this._t = null; this.ver = false; },
  }));

  Alpine.data('formularioEvento', (cfg) => ({
    enviando: false,
    inicio: cfg.inicio || '',
    fin: cfg.fin || '',
  }));

  Alpine.data('autocompletar', (cfg) => ({
    campo: cfg.campo,
    valor: cfg.valor || '',
    abierto: false,
    items: [],
    activo: -1,
    _t: null,
    _n: 0,
    buscar() {
      const q = this.valor.trim();
      clearTimeout(this._t);
      if (q === 'N/A') { this.items = []; this.abierto = false; return; }   // sin texto también consulta: al enfocar salen las opciones disponibles
      this._t = setTimeout(async () => {
        this._n = (this._n || 0) + 1;
        const n = this._n;
        const j = await CRO.fetchJson('api/sugerencias', { campo: this.campo, q });
        if (n !== this._n) return; // una respuesta más nueva ya llegó: esta se descarta
        this.items = (j && j.ok) ? j.datos : [];
        this.activo = -1;
        this.abierto = this.items.length > 0;
      }, 150);
    },
    elegir(v) { this.valor = v; this.items = []; this.abierto = false; },
    na() { this.elegir('N/A'); },
    tecla(e) {
      if (!this.abierto) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); this.activo = Math.min(this.activo + 1, this.items.length - 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); this.activo = Math.max(this.activo - 1, 0); }
      else if (e.key === 'Enter' && this.activo >= 0) { e.preventDefault(); this.elegir(this.items[this.activo].valor); }
      else if (e.key === 'Escape') { this.abierto = false; }
    },
  }));

  // Lista cerrada larga (mercado): se escribe para filtrar, pero solo se acepta un valor de la lista.
  // Si al salir el texto no coincide con ninguna opción, el campo vuelve al último valor válido.
  Alpine.data('listaCerrada', (cfg) => ({
    opciones: cfg.opciones || [],
    valor: cfg.valor || '',
    ultimo: cfg.valor || '',
    items: [],
    abierto: false,
    activo: -1,
    filtrar() {
      const q = CRO.norm(this.valor);
      let r = this.opciones;
      if (q !== '') {
        const empieza = [], contiene = [];
        for (const o of this.opciones) {
          const n = CRO.norm(o);
          if (n.startsWith(q)) empieza.push(o);
          else if (n.includes(q)) contiene.push(o);
        }
        r = empieza.concat(contiene);
      }
      this.items = r.slice(0, 60);
      this.activo = this.items.length ? 0 : -1;
      this.abierto = true;
    },
    elegir(v) { this.valor = v; this.ultimo = v; this.items = []; this.abierto = false; },
    cerrar() {
      const q = CRO.norm(this.valor);
      const exacto = this.opciones.find((o) => CRO.norm(o) === q);
      this.valor = exacto !== undefined ? exacto : this.ultimo;
      this.ultimo = this.valor;
      this.abierto = false;
    },
    tecla(e) {
      if (e.key === 'Escape') { this.abierto = false; return; }
      if (!this.abierto) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); this.activo = Math.min(this.activo + 1, this.items.length - 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); this.activo = Math.max(this.activo - 1, 0); }
      else if (e.key === 'Enter' && this.activo >= 0) { e.preventDefault(); this.elegir(this.items[this.activo]); }
    },
  }));

  // Lista cerrada de textos largos (línea estratégica): panel con las opciones completas y, ya elegida,
  // el campo muestra código + resumen y debajo queda el texto entero a la vista.
  Alpine.data('listaExpandible', (cfg) => ({
    opciones: cfg.opciones || [],
    valor: cfg.valor || '',
    abierto: false,
    init() {
      // Es un botón, no un <select>, así que el navegador no puede exigirlo: lo avisamos nosotros.
      const form = this.$el.closest('form');
      if (!form) return;
      form.addEventListener('submit', (e) => {
        if (this.valor) return;
        e.preventDefault();
        e.stopPropagation();
        this.abierto = true;
        this.$el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, true);
    },
    codigo(v) {
      const m = String(v || '').match(/^([A-Za-z]{1,2}\d{1,2})\s*[.:\-–]/);
      return m ? m[1].toUpperCase() : '';
    },
    sinCodigo(v) {
      return this.codigo(v) ? String(v).replace(/^[A-Za-z]{1,2}\d{1,2}\s*[.:\-–]\s*/, '') : String(v || '');
    },
    elegir(v) { this.valor = v; this.abierto = false; },
  }));

  Alpine.data('calendarioApp', (cfg) => ({
    filtros: { area_id: '', tipo_accion_id: '', segmento_id: '', estado: '', mios: '', ...cfg.filtros },
    proximos: cfg.proximos || [],
    txt: cfg.txt || {},
    cal: null, vista: cfg.vistaInicial || 'dayGridMonth', titulo: '',
    detalle: '', detalleAbierto: false, cargando: false,
    anio: cfg.anio || new Date().getFullYear(),
    mapa: { meses: [], max: 0, resumen: {} },
    modoMapa: 'activos',
    resaltado: [],   // fechas resaltadas al pulsar una de las cifras del resumen
    foco: '',
    init() {
      this.cal = new FullCalendar.Calendar(this.$refs.cal, {
        initialView: 'dayGridMonth', initialDate: cfg.fecha, locale: cfg.idioma || 'es', firstDay: 1,
        headerToolbar: false, height: 'auto', fixedWeekCount: false, dayMaxEvents: 4,
        editable: true, eventStartEditable: true, eventDurationEditable: true, eventResizableFromStart: true,
        events: (info, ok, fail) => this.cargar(info, ok, fail),
        eventContent: (arg) => this.chip(arg),
        eventClick: (i) => { i.jsEvent.preventDefault(); this.abrir(i.event.id); },
        dateClick: (i) => { if (!cfg.puedeCrear) return; window.location = `${cfg.nuevoUrl}&fecha=${i.dateStr}`; },
        eventAllow: (span, ev) => !!ev.extendedProps.puedeEditar,
        eventDrop: (i) => this.mover(i),
        eventResize: (i) => this.mover(i),
        datesSet: (a) => { this.titulo = a.view.title; },
        moreLinkContent: (a) => `+${a.num} ${this.txt.mas}`,
        noEventsContent: this.txt.sinEventosPeriodo,
      });
      this.cal.render();
      if (cfg.abrir) this.abrir(cfg.abrir);
      // Si se entra directamente al mapa de calor hay que pedirlo: FullCalendar queda montado
      // pero oculto, y al pasar a Mes cambiarVista() le recalcula el tamaño.
      if (this.vista === 'anio') this.cargarMapa();
    },
    cargar(info, ok, fail) {
      const params = { start: info.startStr.slice(0, 10), end: info.endStr.slice(0, 10) };
      for (const [k, v] of Object.entries(this.filtros)) if (v !== '' && v !== null) params[k] = v;
      CRO.fetchJson('api/eventos', params).then((d) => ok(Array.isArray(d) ? d : [])).catch(fail);
    },
    chip(arg) {
      const e = arg.event, p = e.extendedProps;
      const punto = p.cancelado ? p.areaColor : p.estadoColor;   // cancelado: chip gris, el punto conserva el color del área
      const clase = p.cancelado ? 'cro-chip cro-chip-cancelado' : 'cro-chip';
      const titulo = CRO.esc(e.title) + (p.cancelado ? ' (cancelado)' : '');
      return { html: `<div class="${clase}" style="--c:${e.backgroundColor}" title="${titulo}"><span class="cro-punto" style="background:${punto}"></span><span class="cro-chip-txt">${CRO.esc(e.title)}</span></div>` };
    },
    alternar(clave, valor) { this.filtros[clave] = this.filtros[clave] == valor ? '' : valor; this.aplicar(); },
    // ¿Hay algún filtro puesto? El botón "Limpiar filtros" solo aparece cuando lo hay.
    get hayFiltros() { return Object.values(this.filtros).some((v) => v !== '' && v !== null && v !== undefined); },
    limpiar() {
      for (const k of Object.keys(this.filtros)) this.filtros[k] = '';
      this.aplicar();
    },
    aplicar() {
      this.cal.refetchEvents();
      this.cargarProximos();
      if (this.vista === 'anio') this.cargarMapa();
    },
    // --- Vista "Año": mapa de calor por día ---
    async cargarMapa() {
      const params = { anio: this.anio, modo: this.modoMapa };
      for (const [k, v] of Object.entries(this.filtros)) if (v !== '' && v !== null) params[k] = v;
      const j = await CRO.fetchJson('api/mapa', params);
      if (j && j.ok) {
        j.sello = Date.now();   // cambia la clave del x-for para que las celdas se vuelvan a crear
        this.mapa = j;
        this.foco = '';         // las fechas cambiaron: el resaltado anterior ya no aplica
        this.resaltado = [];
      }
    },
    /** Pulsar una cifra del resumen resalta esos días en el mapa y lleva la vista hasta ellos. */
    resaltar(tipo, dias) {
      if (!tipo || this.foco === tipo) { this.foco = ''; this.resaltado = []; return; }
      this.foco = tipo;
      this.resaltado = Array.isArray(dias) ? dias : [];
      this.$nextTick(() => {
        const primera = this.resaltado[0] && document.querySelector(`[data-f="${this.resaltado[0]}"]`);
        if (primera) primera.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    },
    // Si se filtra por un área, el mapa toma su color; si no, una rampa cálida.
    // --- Filtros de varias opciones: el valor viaja como "18,19,21" ---
    sel(clave) { return String(this.filtros[clave] || '').split(',').filter(Boolean); },
    activo(clave, v) { return this.sel(clave).includes(String(v)); },
    alternarMulti(clave, v) {
      const s = this.sel(clave);
      const i = s.indexOf(String(v));
      if (i >= 0) s.splice(i, 1); else s.push(String(v));
      this.filtros[clave] = s.join(',');
      this.aplicar();
    },
    /** Añade sin quitar: lo usan los desplegables de tipo y segmento. */
    agregar(clave, v) {
      if (!v) return;
      const s = this.sel(clave);
      if (s.includes(String(v))) return;
      s.push(String(v));
      this.filtros[clave] = s.join(',');
      this.aplicar();
    },
    limpiarFiltro(clave) { this.filtros[clave] = ''; this.aplicar(); },
    nombreDe(clave, id) {
      const nombres = { area_id: cfg.areaNombres, tipo_accion_id: cfg.tipoNombres, segmento_id: cfg.segmentoNombres }[clave];
      return (nombres && nombres[String(id)]) || String(id);
    },
    // Áreas: los mismos ayudantes con nombre propio, que es como los usa la barra lateral.
    areasSel() { return this.sel('area_id'); },
    areaActiva(id) { return this.activo('area_id', id); },
    alternarArea(id) { this.alternarMulti('area_id', id); },
    limpiarAreas() { this.limpiarFiltro('area_id'); },
    colorArea(id) { return (cfg.areaColores && cfg.areaColores[String(id)]) || '#B3B7BF'; },
    nombreArea(id) { return this.nombreDe('area_id', id); },

    // --- Color de las celdas del mapa ---
    rgba(hex, alfa) {
      const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
      return `rgba(${r},${g},${b},${alfa})`;
    },
    // Con una sola área marcada, la rampa toma su color; con ninguna o varias, la rampa cálida.
    hexBase() {
      const sel = this.areasSel();
      return (sel.length === 1 && cfg.areaColores && cfg.areaColores[sel[0]]) || '#D9483A';
    },
    nivel(n) {
      const max = this.mapa.max || 1;
      if (!n) return 0;
      if (max <= 1) return 4;
      return Math.max(1, Math.ceil((n / max) * 4));
    },
    alfaNivel(k) { return [0, 0.22, 0.44, 0.68, 0.92][k] || 0; },
    colorNivel(k) { return this.rgba(this.hexBase(), this.alfaNivel(k)); },
    colorCelda(n) { return this.colorNivel(this.nivel(n)); },
    /**
     * Fondo de un día. Con varias áreas marcadas el cuadrito se parte en franjas del color de cada
     * área, proporcionales a sus eventos, y la opacidad sigue marcando la intensidad del día.
     */
    fondoCelda(c) {
      if (!c || !c.n) return {};
      const sel = this.areasSel();
      if (sel.length > 1 && c.areas) {
        const partes = Object.entries(c.areas).filter(([id]) => sel.includes(String(id)));
        if (partes.length) {
          const alfa = this.alfaNivel(this.nivel(c.n));
          const total = partes.reduce((s, [, v]) => s + v, 0);
          let acc = 0;
          const tramos = partes.map(([id, v]) => {
            const ini = (acc / total) * 100;
            acc += v;
            return `${this.rgba(this.colorArea(id), alfa)} ${ini}% ${(acc / total) * 100}%`;
          });
          return { background: tramos.length === 1 ? tramos[0].split(' ')[0] : `linear-gradient(90deg, ${tramos.join(', ')})` };
        }
      }
      return { background: this.colorCelda(c.n) };
    },
    tituloCelda(c) {
      if (!c.n) return `${c.f} · ${this.txt.sinNada}`;
      return `${c.f} · ${c.n} ${c.n === 1 ? this.txt.evento : this.txt.eventos}\n${c.nombres.join('\n')}`;
    },
    semanaTexto(s) { return s ? this.txt.semana + ' ' + String(s).split('-')[1] : ''; },
    irADia(f) { window.location = CRO.url('calendario', { fecha: f, anio: this.anio }); },
    async cargarProximos() {
      const j = await CRO.fetchJson('api/proximos', this.filtros);
      this.proximos = (j && j.ok) ? j.datos : [];
    },
    async abrir(id) {
      this.detalleAbierto = true; this.cargando = true;
      try {
        const res = await fetch(CRO.url('api/evento', { id }), { headers: { Accept: 'text/html' } });
        this.detalle = await res.text();
      } catch (e) {
        this.cargando = false;
        alert(this.txt.noCargaEvento);
        return;
      }
      this.cargando = false;
    },
    cerrar() { this.detalleAbierto = false; this.detalle = ''; },
    async mover(i) {
      const e = i.event;
      const fin = new Date(e.end || e.start); fin.setDate(fin.getDate() - 1);   // fin exclusivo → inclusivo
      const j = await CRO.fetchJson('api/eventos/mover', {}, { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: e.id, fecha_inicio: CRO.ymd(e.start), fecha_fin: CRO.ymd(fin) }) });
      if (!j || !j.ok) { i.revert(); alert((j && j.error) || this.txt.noMueveEvento); return; }
      if (this.detalleAbierto) this.abrir(e.id);
      this.cargarProximos();
    },
    cambiarVista(v) {
      const volviendo = this.vista === 'anio' && v !== 'anio';
      this.vista = v;
      if (v === 'anio') { this.cargarMapa(); return; }   // el mapa no es una vista de FullCalendar
      this.cal.changeView(v);
      // Al volver del mapa, FullCalendar venía oculto y midió mal: hay que recalcular
      // cuando el contenedor ya está visible, o la rejilla queda descuadrada.
      if (volviendo) {
        this.$nextTick(() => requestAnimationFrame(() => this.cal.updateSize()));
      }
    },
    hoy() { this.cal.today(); }, anterior() { this.cal.prev(); }, siguiente() { this.cal.next(); },
    // Cambiar de año navega a ese año (1-ene) para que la grilla lo muestre de verdad; si el año
    // elegido es el actual, no forzamos fecha y así se conserva el mes que se estaba viendo hoy.
    cambiarAnio(anio) {
      const params = { anio };
      if (String(anio) !== String(new Date().getFullYear())) params.fecha = `${anio}-01-01`;
      window.location = CRO.url('calendario', params);
    },
  }));

  Alpine.data('paleta', (txt = {}) => ({
    abierta: false, q: '', activo: 0, resultados: [], _t: null,
    acciones: [
      { clave: 'nuevo', texto: txt.nuevoEvento, sub: txt.accion, url: CRO.url('eventos/nuevo') },
      { clave: 'cal', texto: txt.irCalendario, sub: txt.accion, url: CRO.url('calendario') },
      { clave: 'lista', texto: txt.verLista, sub: txt.accion, url: CRO.url('eventos') },
      { clave: 'dash', texto: txt.abrirDashboard, sub: txt.accion, url: CRO.url('dashboard') },
    ],
    get visibles() {
      const q = this.q.trim().toLowerCase();
      const acc = q ? this.acciones.filter((a) => a.texto.toLowerCase().includes(q)) : this.acciones;
      return [...this.resultados, ...acc];
    },
    abrirP() { this.abierta = true; this.$nextTick(() => this.$refs.q.focus()); },
    buscar() {
      clearTimeout(this._t);
      const q = this.q.trim();
      if (q.length < 2) { this.resultados = []; return; }
      this._t = setTimeout(async () => {
        const j = await CRO.fetchJson('api/buscar', { q });
        this.resultados = (j && j.ok ? j.datos : []).map((e) => ({
          clave: 'ev' + e.id, texto: e.nombre, sub: `${e.fecha_inicio} · ${e.ciudad}`, color: e.area_color,
          url: CRO.url('calendario', { evento: e.id, fecha: e.fecha_inicio }),
        }));
        this.activo = 0;
      }, 150);
    },
    mover(d) { const n = this.visibles.length; if (n) this.activo = (this.activo + d + n) % n; },
    ir(i) { const it = this.visibles[i ?? this.activo]; if (it) window.location = it.url; },
  }));

  Alpine.data('dashboardApp', (d) => ({
    // --- Matriz de carga área × mes ---
    mxFila: null,   // fila señalada: resalta el nombre del área
    mxCol: null,    // columna señalada: resalta la cabecera del mes
    mxTexto: '',    // lectura en palabras de la casilla señalada
    mxSobre(fila, col, texto) { this.mxFila = fila; this.mxCol = col; this.mxTexto = texto; },
    mxFuera() { this.mxFila = null; this.mxCol = null; this.mxTexto = ''; },
    /** Abre el calendario en ese mes y con esa área. mes 0 = todo el año, área 0 = todas. */
    mxIr(areaId, mes) {
      const params = { anio: d.anio };
      if (areaId) { params.area_id = areaId; }
      if (mes) { params.fecha = `${d.anio}-${String(mes).padStart(2, '0')}-01`; }
      window.location = CRO.url('calendario', params);
    },
    init() {
      const oscuro = document.documentElement.classList.contains('dark');
      const texto = oscuro ? '#9AA0AB' : '#6B7280';
      const base = { textStyle: { fontFamily: 'Manrope' }, color: ['#B3B7BF', '#E0A020', '#2E9E5B', '#8A8F98'] };
      const meses = d.txt.meses;
      const serie = (estado, nombre) => ({ name: nombre, type: 'bar', stack: 'total', barMaxWidth: 28, itemStyle: { borderRadius: 3 },
        data: Object.values(d.por_mes).map((m) => m[estado]) });
      const charts = [];
      const crear = (ref, opt) => { const c = echarts.init(this.$refs[ref], null, { renderer: 'canvas' }); c.setOption({ ...base, ...opt }); charts.push(c); };
      crear('mes', { tooltip: { trigger: 'axis' }, legend: { bottom: 0, textStyle: { color: texto } }, grid: { left: 30, right: 10, top: 10, bottom: 40 },
        xAxis: { type: 'category', data: meses, axisLabel: { color: texto } }, yAxis: { type: 'value', minInterval: 1, axisLabel: { color: texto }, splitLine: { lineStyle: { color: oscuro ? '#2A2F3A' : '#E6E3DB' } } },
        series: [serie('no_realizado', d.txt.estados[0]), serie('en_ejecucion', d.txt.estados[1]), serie('realizado', d.txt.estados[2]), serie('cancelado', d.txt.estados[3])] });
      crear('area', { tooltip: { trigger: 'item' }, series: [{ type: 'pie', radius: ['55%', '80%'], label: { color: texto, fontSize: 11 },
        data: d.por_area.map((a) => ({ name: a.area, value: a.total, itemStyle: { color: a.color || '#B3B7BF' } })) }] });
      crear('tipo', { tooltip: { trigger: 'axis' }, grid: { left: 10, right: 30, top: 10, bottom: 10, containLabel: true }, color: ['#1F3F7A', '#2E9E5B'],
        xAxis: { type: 'value', minInterval: 1, axisLabel: { color: texto } }, yAxis: { type: 'category', data: d.por_tipo.map((t) => t.tipo).reverse(), axisLabel: { color: texto, width: 180, overflow: 'truncate' } },
        series: [{ name: d.txt.total, type: 'bar', barMaxWidth: 18, itemStyle: { borderRadius: 3 }, data: d.por_tipo.map((t) => t.total).reverse() },
                 { name: d.txt.realizadas, type: 'bar', barMaxWidth: 18, itemStyle: { borderRadius: 3 }, data: d.por_tipo.map((t) => t.realizados).reverse() }] });
      crear('segmento', { tooltip: { trigger: 'item' }, color: ['#1F3F7A', '#3A5BD9', '#0E8F8B', '#C2780A', '#7A4BD6', '#C43D6B', '#3E8E3A', '#B3B7BF'],
        series: [{ type: 'pie', radius: ['45%', '75%'], label: { color: texto, fontSize: 11 }, data: d.por_segmento.map((s) => ({ name: s.segmento, value: s.total })) }] });
      window.addEventListener('resize', () => charts.forEach((c) => c.resize()));
    },
  }));
});
