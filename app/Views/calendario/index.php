<?php
use App\Core\Campos;

$cfg = [
    'fecha' => $fecha,
    'vistaInicial' => $vistaInicial,
    'filtros' => array_intersect_key($filtros, array_flip(['area_id', 'tipo_accion_id', 'segmento_id', 'estado'])) + ['mios' => $mios ? '1' : ''],
    'abrir' => $eventoAbrir ?: null,
    'nuevoUrl' => url('eventos/nuevo'),
    'puedeCrear' => $puedeCrear,
    'proximos' => $proximos,
    'anio' => $anio,
    'areaColores' => array_column(array_map(static fn(array $a): array => ['id' => (string) $a['id'], 'color' => $a['color'] ?: Campos::COLOR_NEUTRO], $areas), 'color', 'id'),
    'areaNombres' => array_column(array_map(static fn(array $a): array => ['id' => (string) $a['id'], 'valor' => $a['valor']], $areas), 'valor', 'id'),
    'tipoNombres' => array_column(array_map(static fn(array $t): array => ['id' => (string) $t['id'], 'valor' => $t['valor']], $tipos), 'valor', 'id'),
    'segmentoNombres' => array_column(array_map(static fn(array $s): array => ['id' => (string) $s['id'], 'valor' => $s['valor']], $segmentos), 'valor', 'id'),
];
$estadosConteo = $conteos['estados'];
$total = array_sum($estadosConteo);
$conteoPorArea = array_column($conteos['areas'], 'n', 'id');
?>
<div class="grid gap-6 xl:grid-cols-[264px_minmax(0,1fr)_352px]" x-data="calendarioApp(<?= h(json_encode($cfg)) ?>)">

  <!-- Barra lateral -->
  <aside class="cro-col space-y-5">
    <div class="card p-4">
      <div class="mb-3 flex items-center justify-between">
        <span class="label mb-0">Año</span>
        <select class="input w-auto py-1" @change="cambiarAnio($event.target.value)">
          <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
        </select>
      </div>
      <p class="font-serif text-3xl"><?= $total ?> <span class="text-base text-gris">eventos en <?= $anio ?></span></p>
    </div>

    <div class="card p-4">
      <p class="label">Áreas</p>
      <ul class="space-y-1">
        <?php foreach ($areas as $a): ?>
        <?php if ($a['valor_norm'] === 'n/a') continue; ?>
        <li>
          <button type="button" class="cro-area-btn flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                  :class="{'bg-azul-claro dark:bg-[#232834]': areaActiva(<?= $a['id'] ?>)}" @click="alternarArea(<?= $a['id'] ?>)">
            <span class="cro-area-punto h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= h($a['color'] ?: Campos::COLOR_NEUTRO) ?>"></span>
            <span class="truncate"><?= h($a['valor']) ?></span>
            <span class="ml-auto text-xs font-semibold text-gris"><?= (int) ($conteoPorArea[$a['id']] ?? 0) ?></span>
          </button>
        </li>
        <?php endforeach; ?>
        <?php if (!$areas): ?><li class="px-2 py-1.5 text-xs text-gris">Sin áreas activas.</li><?php endif; ?>
      </ul>
      <p class="mt-2 text-[11px] leading-snug text-gris">Puedes marcar varias a la vez.</p>
      <button type="button" class="cro-btn-limpiar mt-2" x-show="areasSel().length" x-cloak @click="limpiarAreas()">
        <span class="cro-btn-x">✕</span>
        <span x-text="areasSel().length === 1 ? 'Quitar el área' : 'Quitar las ' + areasSel().length + ' áreas'"></span>
      </button>
    </div>

    <div class="card p-4">
      <p class="label">Estado</p>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach (Campos::ESTADOS as $k => $et): ?>
        <button type="button" class="badge-estado cro-chip-estado border border-transparent" style="--c:<?= Campos::ESTADO_COLOR[$k] ?>"
                :class="{'!border-[var(--c)]': activo('estado', '<?= $k ?>')}" @click="alternarMulti('estado', '<?= $k ?>')">
          <?= h($et) ?> · <?= (int) ($estadosConteo[$k] ?? 0) ?>
        </button>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 grid gap-3">
        <div>
          <select class="input py-1.5" @change="agregar('tipo_accion_id', $event.target.value); $event.target.value = ''">
            <option value="" x-text="sel('tipo_accion_id').length ? 'Añadir otro tipo…' : 'Todos los tipos'">Todos los tipos</option>
            <?php foreach ($tipos as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['valor']) ?></option><?php endforeach; ?>
          </select>
          <div class="cro-chips" x-show="sel('tipo_accion_id').length" x-cloak>
            <template x-for="id in sel('tipo_accion_id')" :key="id">
              <button type="button" class="cro-chip-filtro" :title="'Quitar ' + nombreDe('tipo_accion_id', id)" @click="alternarMulti('tipo_accion_id', id)">
                <span x-text="nombreDe('tipo_accion_id', id)"></span><span class="cro-chip-x">✕</span>
              </button>
            </template>
          </div>
        </div>
        <div>
          <select class="input py-1.5" @change="agregar('segmento_id', $event.target.value); $event.target.value = ''">
            <option value="" x-text="sel('segmento_id').length ? 'Añadir otro segmento…' : 'Todos los segmentos'">Todos los segmentos</option>
            <?php foreach ($segmentos as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['valor']) ?></option><?php endforeach; ?>
          </select>
          <div class="cro-chips" x-show="sel('segmento_id').length" x-cloak>
            <template x-for="id in sel('segmento_id')" :key="id">
              <button type="button" class="cro-chip-filtro" :title="'Quitar ' + nombreDe('segmento_id', id)" @click="alternarMulti('segmento_id', id)">
                <span x-text="nombreDe('segmento_id', id)"></span><span class="cro-chip-x">✕</span>
              </button>
            </template>
          </div>
        </div>
      </div>
      <label class="mt-4 flex cursor-pointer items-center justify-between text-sm<?= $tieneArea ? '' : ' opacity-60' ?>" <?= $tieneArea ? '' : 'title="No tienes un área asignada"' ?>>
        <span>Solo mi área</span>
        <input type="checkbox" class="h-4 w-4 accent-azul" :checked="!!filtros.mios" @change="filtros.mios = $event.target.checked ? '1' : ''; if (filtros.mios) filtros.area_id = ''; aplicar()"<?= $tieneArea ? '' : ' disabled' ?>>
      </label>
      <button type="button" x-show="hayFiltros" x-cloak class="cro-btn-limpiar mt-4" @click="limpiar()">
        <span class="cro-btn-x">✕</span><span>Limpiar filtros</span>
      </button>
    </div>

    <div class="card p-4">
      <p class="label">Próximos 30 días</p>
      <ul class="space-y-2">
        <template x-for="p in proximos" :key="p.id">
          <li>
            <button type="button" class="cro-prox-btn w-full rounded-lg border-l-[3px] px-2.5 py-1.5 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]" :style="'border-color:' + p.area_color" @click="abrir(p.id)">
              <span class="block truncate text-sm font-semibold" x-text="p.nombre"></span>
              <span class="block text-xs text-gris" x-text="p.rango + ' · ' + (p.ciudad || p.area)"></span>
            </button>
          </li>
        </template>
        <li x-show="!proximos.length" class="text-xs text-gris">Nada programado en los próximos 30 días.</li>
      </ul>
    </div>
  </aside>

  <!-- Calendario -->
  <section class="cro-col card p-4 md:p-5">
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <button type="button" class="btn-secundario" x-show="vista !== 'anio'" @click="hoy()">Hoy</button>
      <div class="flex overflow-hidden rounded-full border border-borde dark:border-noche-borde" x-show="vista !== 'anio'">
        <button type="button" class="px-3 py-1.5 hover:bg-[#F0EEE8] dark:hover:bg-[#232834]" @click="anterior()" aria-label="Anterior">‹</button>
        <button type="button" class="border-l border-borde px-3 py-1.5 hover:bg-[#F0EEE8] dark:border-noche-borde dark:hover:bg-[#232834]" @click="siguiente()" aria-label="Siguiente">›</button>
      </div>
      <h1 class="ml-2 font-serif text-2xl cro-cal-titulo" x-text="vista === 'anio' ? 'Mapa de calor ' + anio : titulo"></h1>
      <div class="ml-auto flex gap-1 rounded-full border border-borde p-1 dark:border-noche-borde">
        <template x-for="v in [['anio','Año'],['dayGridMonth','Mes'],['timeGridWeek','Semana'],['listMonth','Lista']]" :key="v[0]">
          <button type="button" class="rounded-full px-3 py-1 text-xs font-semibold" :class="vista === v[0] ? 'bg-azul text-white' : 'text-gris hover:text-tinta dark:hover:text-white'" @click="cambiarVista(v[0])" x-text="v[1]"></button>
        </template>
      </div>
    </div>
    <div x-ref="cal" x-show="vista !== 'anio'"></div>

    <!-- Vista "Año": mapa de calor por día, con los mismos filtros de la barra lateral -->
    <div x-show="vista === 'anio'" x-cloak>
      <div class="cro-hm-resumen">
        <span>Eventos <b x-text="mapa.resumen.acciones ?? 0"></b></span>
        <button type="button" class="cro-hm-cifra" :class="{ 'cro-hm-cifra-activa': foco === 'activos' }"
                title="Resaltar en el mapa todos los días que tienen algún evento"
                @click="resaltar('activos', mapa.resumen.dias_activos)">Días con eventos <b x-text="mapa.resumen.dias_con_algo ?? 0"></b></button>
        <button type="button" class="cro-hm-cifra" x-show="mapa.resumen.dia_pico" :class="{ 'cro-hm-cifra-activa': foco === 'dia' }"
                title="Llevarme a ese día en el mapa"
                @click="resaltar('dia', [mapa.resumen.dia_pico])">Día más cargado <b x-text="mapa.resumen.dia_pico"></b> con <b x-text="mapa.resumen.dia_pico_n"></b></button>
        <button type="button" class="cro-hm-cifra" x-show="mapa.resumen.semana_pico" :class="{ 'cro-hm-cifra-activa': foco === 'semana' }"
                title="Llevarme a esa semana en el mapa"
                @click="resaltar('semana', mapa.resumen.semana_pico_dias)">Semana más cargada <b x-text="semanaTexto(mapa.resumen.semana_pico)"></b> con <b x-text="mapa.resumen.semana_pico_n"></b></button>
        <button type="button" class="cro-hm-quitar" x-show="foco" x-cloak @click="resaltar('', [])">Quitar resaltado</button>
        <label class="cro-hm-modo"><input type="checkbox" class="accent-azul" @change="modoMapa = $event.target.checked ? 'inicio' : 'activos'; cargarMapa()"> Contar solo el día de inicio</label>
      </div>

      <div class="cro-hm-grid">
        <template x-for="m in mapa.meses" :key="mapa.sello + '-' + m.mes">
          <div>
            <p class="cro-hm-titulo" x-text="m.nombre"></p>
            <div class="cro-hm-dias cro-hm-cab">
              <template x-for="(d, i) in ['L','M','X','J','V','S','D']" :key="i"><span x-text="d"></span></template>
            </div>
            <div class="cro-hm-dias">
              <template x-for="(c, i) in m.celdas" :key="i">
                <button type="button" class="cro-hm"
                        :class="{ 'cro-hm-vacio': !c, 'cro-hm-0': c && !c.n, 'cro-hm-blanco': c && c.n && nivel(c.n) >= 3,
                                  'cro-hm-foco': c && resaltado.includes(c.f), 'cro-hm-atenuado': foco && c && !resaltado.includes(c.f) }"
                        :data-f="c ? c.f : ''"
                        :style="fondoCelda(c)"
                        :title="c ? tituloCelda(c) : ''" :disabled="!c" @click="c && irADia(c.f)" x-text="c ? c.d : ''"></button>
              </template>
            </div>
          </div>
        </template>
        <p x-show="!mapa.meses.length" class="text-sm text-gris">Cargando…</p>
      </div>

      <div class="cro-hm-leyenda">
        <span class="cro-hm-leyenda-rampa" x-show="areasSel().length <= 1">
          <span>Menos</span>
          <template x-for="k in [1,2,3,4]" :key="k"><span class="cro-hm" :style="'background:' + colorNivel(k)"></span></template>
          <span>Más</span>
        </span>
        <span class="cro-hm-leyenda-areas" x-show="areasSel().length > 1" x-cloak>
          <template x-for="id in areasSel()" :key="id">
            <span class="cro-hm-area"><span class="cro-hm-punto" :style="'background:' + colorArea(id)"></span><span x-text="nombreArea(id)"></span></span>
          </template>
        </span>
        <span class="cro-hm-nota" x-show="mapa.max">Hasta <b x-text="mapa.max"></b> por día. Haz clic en un día para abrirlo en el calendario.</span>
      </div>
    </div>
  </section>

  <!-- Panel lateral de detalle -->
  <aside class="cro-col">
    <div class="card min-h-[320px] overflow-hidden" x-show="detalleAbierto" x-cloak x-transition.opacity @keydown.escape.window="cerrar()">
      <div class="flex items-center justify-between border-b border-borde px-4 py-2.5 dark:border-noche-borde">
        <span class="label mb-0">Detalle</span>
        <button type="button" class="btn-icono h-7 w-7" @click="cerrar()" aria-label="Cerrar">✕</button>
      </div>
      <div x-show="cargando" class="p-6 text-sm text-gris">Cargando…</div>
      <div x-show="!cargando" x-html="detalle"></div>
    </div>
    <div class="card flex min-h-[320px] flex-col items-center justify-center p-8 text-center text-sm text-gris" x-show="!detalleAbierto">
      <p class="font-serif text-2xl text-tinta dark:text-[#E9EAEE]">Selecciona un evento</p>
      <p class="mt-2">Haz clic en un chip del calendario para ver toda la ficha. <?= $puedeCrear ? 'Clic en un día vacío crea un evento en esa fecha.' : 'Para crear eventos pide al administrador que te asigne un área.' ?></p>
    </div>
  </aside>
</div>
<?php
$scripts = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" integrity="sha384-B1OFx8Gy9GjPu8UbUyXbGQpzll9ubAUQ9agInFJ8NnD7nYG1u/CLR+Sqr5yifl4q" crossorigin="anonymous"></script>'
    . '<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js" integrity="sha384-yvtDoBsejAOiZwybj79N2ImCrUqwpcD1EyVnqVU+BCvFUi85WVXj0tP9gRQyW5KF" crossorigin="anonymous"></script>';
