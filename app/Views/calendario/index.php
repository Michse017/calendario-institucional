<?php
use App\Core\Campos;
use App\Core\Idioma;

$cfg = [
    'fecha' => $fecha,
    'vistaInicial' => $vistaInicial,
    'filtros' => array_intersect_key($filtros, array_flip(['area_id', 'tipo_accion_id', 'segmento_id', 'estado', 'persona'])) + ['mios' => $mios ? '1' : ''],
    'abrir' => $eventoAbrir ?: null,
    'nuevoUrl' => url('eventos/nuevo'),
    'puedeCrear' => $puedeCrear,
    'proximos' => $proximos,
    'anio' => $anio,
    'idioma' => Idioma::actual(),
    // Gente activa (filtro de personas) y lo que lidera cada uno este año (panel al señalar su chip).
    'usuarios' => array_map(static fn(array $u): array => ['id' => (string) $u['id'], 'nombre' => $u['nombre'], 'area' => ($u['area_nombre'] ?? '') !== '' ? t($u['area_nombre']) : ''], $usuarios),
    'agendas' => $agendas,
    // Los textos que pinta el JavaScript viajan traducidos dentro de la
    // configuración. Meterlos en las expresiones de Alpine obligaría a pelear
    // con las comillas y a escapar a mano; así llegan ya en su idioma.
    'txt' => [
        'mas'              => t('más'),
        'sinEventosPeriodo' => t('Sin eventos en este periodo'),
        'sinNada'          => t('sin nada'),
        'evento'           => t('evento'),
        'eventos'          => t('eventos'),
        'semana'           => t('semana'),
        'noCargaEvento'    => t('No se pudo cargar el evento.'),
        'noMueveEvento'    => t('No se pudo mover el evento.'),
        'nuevoEvento'      => t('Nuevo evento'),
        'verLista'         => t('Ver lista de eventos'),
        'mapaDeCalor'      => t('Mapa de calor'),
        'quitarArea'       => t('Quitar el área'),
        'quitarAreasN'     => t('Quitar las :n áreas'),
        'quitar'           => t('Quitar'),
        'anadirOtroTipo'   => t('Añadir otro tipo…'),
        'todosLosTipos'    => t('Todos los tipos'),
        'anadirOtroSeg'    => t('Añadir otro segmento…'),
        'todosLosSeg'      => t('Todos los segmentos'),
        'vistaAnio'        => t('Año'),
        'vistaMes'         => t('Mes'),
        'vistaSemana'      => t('Semana'),
        'vistaLista'       => t('Lista'),
        'todoElDia'        => t('Todo el día'),
        'diasSemana'       => explode(' ', t('L M X J V S D')),
        // Filtro de personas, panel del día, vistazo, modos del mapa y línea de tiempo.
        'vistaLinea'       => t('Línea de tiempo'),
        'persona'          => t('persona'),
        'personas'         => t('personas'),
        'personaFallback'  => t('Persona'),
        'quitarA'          => t('Quitar a'),
        'yMas'             => t('y :n más'),
        'responsable'      => t('Responsable'),
        'pideCubrimiento'  => t('Pide cubrimiento'),
        'noPideCubrimiento' => t('No pide cubrimiento'),
        'clicFicha'        => t('Haz clic para ver la ficha'),
        'van'              => t('Van:'),
        'en'               => t('en'),
        'modoTodo'         => t('Todo el evento'),
        'modoTodoAyuda'    => t('Pinta todos los días que dura cada evento, no solo el primero.'),
        'modoInicio'       => t('Solo el inicio'),
        'modoInicioAyuda'  => t('Pinta únicamente el día en que arranca cada evento.'),
        'modoPersonas'     => t('Disponibilidad'),
        'modoPersonasAyuda' => t('Cuánta gente está comprometida cada día. Solo cuenta eventos del calendario: no sabe de vacaciones ni de bajas.'),
        'modoCubre'        => t('Piden cubrimiento'),
        'modoCubreAyuda'   => t('Días con eventos que piden cubrimiento.'),
        'personasComprometidas' => t('Personas comprometidas'),
        'eventosPiden'     => t('Eventos que piden cubrimiento'),
        'diasConGente'     => t('Días con gente comprometida'),
        'diasConEventosPiden' => t('Días con eventos que piden cubrimiento'),
        'diasConEventos'   => t('Días con eventos'),
        'eventoSing'       => t('evento'),
        'eventosRot'       => t('Eventos'),
        'cancelado'        => t('cancelado'),
    ],
    'areaColores' => array_column(array_map(static fn(array $a): array => ['id' => (string) $a['id'], 'color' => $a['color'] ?: Campos::COLOR_NEUTRO], $areas), 'color', 'id'),
    'areaNombres' => array_column(array_map(static fn(array $a): array => ['id' => (string) $a['id'], 'valor' => t($a['valor'])], $areas), 'valor', 'id'),
    'tipoNombres' => array_column(array_map(static fn(array $t): array => ['id' => (string) $t['id'], 'valor' => t($t['valor'])], $tipos), 'valor', 'id'),
    'segmentoNombres' => array_column(array_map(static fn(array $s): array => ['id' => (string) $s['id'], 'valor' => t($s['valor'])], $segmentos), 'valor', 'id'),
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
        <span class="label mb-0"><?= h(t('Año')) ?></span>
        <select class="input w-auto py-1" @change="cambiarAnio($event.target.value)" aria-label="<?= h(t('Año')) ?>">
          <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
        </select>
      </div>
      <p class="font-serif text-3xl"><?= $total ?> <span class="text-base text-gris"><?= h(t('eventos en :anio', ['anio' => $anio])) ?></span></p>
    </div>

    <div class="card p-4">
      <p class="label"><?= h(t('Áreas')) ?></p>
      <ul class="space-y-1">
        <?php foreach ($areas as $a): ?>
        <?php if ($a['valor_norm'] === 'n/a') continue; ?>
        <li>
          <button type="button" class="cro-area-btn flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                  :class="{'bg-azul-claro dark:bg-[#232834]': areaActiva(<?= $a['id'] ?>)}" @click="alternarArea(<?= $a['id'] ?>)">
            <span class="cro-area-punto h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= h($a['color'] ?: Campos::COLOR_NEUTRO) ?>"></span>
            <span class="truncate"><?= h(t($a['valor'])) ?></span>
            <span class="ml-auto text-xs font-semibold text-gris"><?= (int) ($conteoPorArea[$a['id']] ?? 0) ?></span>
          </button>
        </li>
        <?php endforeach; ?>
        <?php if (!$areas): ?><li class="px-2 py-1.5 text-xs text-gris"><?= h(t('Sin áreas activas.')) ?></li><?php endif; ?>
      </ul>
      <p class="mt-2 text-[11px] leading-snug text-gris"><?= h(t('Puedes marcar varias a la vez.')) ?></p>
      <button type="button" class="cro-btn-limpiar mt-2" x-show="areasSel().length" x-cloak @click="limpiarAreas()">
        <span class="cro-btn-x">✕</span>
        <span x-text="areasSel().length === 1 ? txt.quitarArea : txt.quitarAreasN.replace(':n', areasSel().length)"></span>
      </button>
    </div>

    <div class="card p-4">
      <p class="label"><?= h(t('Estado')) ?></p>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach (Campos::ESTADOS as $k => $et): ?>
        <button type="button" class="badge-estado cro-chip-estado border border-transparent" style="--c:<?= Campos::ESTADO_COLOR[$k] ?>"
                :class="{'!border-[var(--c)]': activo('estado', '<?= $k ?>')}" @click="alternarMulti('estado', '<?= $k ?>')">
          <?= h(t($et)) ?> · <?= (int) ($estadosConteo[$k] ?? 0) ?>
        </button>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 grid gap-3">
        <div>
          <select class="input py-1.5" @change="agregar('tipo_accion_id', $event.target.value); $event.target.value = ''" aria-label="<?= h(t('Todos los tipos')) ?>">
            <option value="" x-text="sel('tipo_accion_id').length ? txt.anadirOtroTipo : txt.todosLosTipos"><?= h(t('Todos los tipos')) ?></option>
            <?php foreach ($tipos as $t): ?><option value="<?= $t['id'] ?>"><?= h(t($t['valor'])) ?></option><?php endforeach; ?>
          </select>
          <div class="cro-chips" x-show="sel('tipo_accion_id').length" x-cloak>
            <template x-for="id in sel('tipo_accion_id')" :key="id">
              <button type="button" class="cro-chip-filtro" :title="txt.quitar + ' ' + nombreDe('tipo_accion_id', id)" @click="alternarMulti('tipo_accion_id', id)">
                <span x-text="nombreDe('tipo_accion_id', id)"></span><span class="cro-chip-x">✕</span>
              </button>
            </template>
          </div>
        </div>
        <div>
          <select class="input py-1.5" @change="agregar('segmento_id', $event.target.value); $event.target.value = ''" aria-label="<?= h(t('Todos los segmentos')) ?>">
            <option value="" x-text="sel('segmento_id').length ? txt.anadirOtroSeg : txt.todosLosSeg"><?= h(t('Todos los segmentos')) ?></option>
            <?php foreach ($segmentos as $s): ?><option value="<?= $s['id'] ?>"><?= h(t($s['valor'])) ?></option><?php endforeach; ?>
          </select>
          <div class="cro-chips" x-show="sel('segmento_id').length" x-cloak>
            <template x-for="id in sel('segmento_id')" :key="id">
              <button type="button" class="cro-chip-filtro" :title="txt.quitar + ' ' + nombreDe('segmento_id', id)" @click="alternarMulti('segmento_id', id)">
                <span x-text="nombreDe('segmento_id', id)"></span><span class="cro-chip-x">✕</span>
              </button>
            </template>
          </div>
        </div>
      </div>
      <div class="mt-4">
        <p class="label"><?= h(t('Persona')) ?></p>
        <p class="mb-1.5 text-[11px] leading-snug text-gris"><?= h(t('Añade una o varias. Salen los eventos de los que alguna de ellas es responsable. En el modo «Disponibilidad», los días en blanco son los que tienen libres.')) ?></p>
        <div class="relative" @click.outside="abiertoPersona = false">
          <input class="input py-1.5" type="search" placeholder="<?= h(t('Escribe un nombre y pulsa Enter…')) ?>"
                 x-model="qPersona" @focus="abiertoPersona = true" @input="abiertoPersona = true; activoPersona = 0"
                 @keydown="teclaPersona($event)" autocomplete="off" aria-label="<?= h(t('Buscar persona')) ?>">
          <ul x-show="abiertoPersona && candidatosPersona.length" x-cloak x-transition.opacity
              class="card absolute z-20 mt-1 max-h-64 w-full overflow-auto p-1 text-sm">
            <template x-for="(u, i) in candidatosPersona" :key="u.id">
              <li>
                <button type="button" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                        :class="{ 'bg-[#F0EEE8] dark:bg-[#232834]': i === activoPersona }"
                        @click="anadirPersona(u.id)" @mouseenter="activoPersona = i">
                  <span x-text="u.nombre"></span>
                  <span class="text-xs text-gris" x-text="u.area ? ' · ' + u.area : ''"></span>
                </button>
              </li>
            </template>
          </ul>
        </div>
        <div class="cro-chips" x-show="sel('persona').length" x-cloak>
          <template x-for="id in sel('persona')" :key="id">
            <button type="button" class="cro-chip-filtro" :class="{ 'cro-chip-porquitar': personaPorQuitar === id }" :title="txt.quitarA + ' ' + nombrePersona(id)"
                    @click="verAgenda = ''; alternarMulti('persona', id)"
                    @mouseenter="verAgenda = id" @mouseleave="verAgenda = ''"
                    @focus="verAgenda = id" @blur="verAgenda = ''">
              <span x-text="nombrePersona(id)"></span><span class="cro-chip-x">✕</span>
            </button>
          </template>
        </div>

        <p class="mt-1 text-[11px] leading-snug text-peligro" x-show="personaPorQuitar" x-cloak><?= h(t('Pulsa Retroceso otra vez para quitar a')) ?> <b x-text="nombrePersona(personaPorQuitar)"></b>.</p>

        <!-- Qué lleva esa persona este año. Sale al señalar su chip, sin tener que pinchar. -->
        <div x-show="verAgenda && sel('persona').includes(verAgenda)" x-cloak x-transition.opacity class="cro-agenda-pop">
          <p class="cro-agenda-quien" x-text="nombrePersona(verAgenda)"></p>
          <template x-if="agendaDe(verAgenda).length">
            <ul class="cro-agenda-lista">
              <template x-for="(e, i) in agendaDe(verAgenda).slice(0, 8)" :key="i">
                <li><span class="cro-agenda-fecha" x-text="e.fechas"></span> <span x-text="e.nombre"></span></li>
              </template>
            </ul>
          </template>
          <p class="cro-agenda-mas" x-show="agendaDe(verAgenda).length > 8"
             x-text="txt.yMas.replace(':n', agendaDe(verAgenda).length - 8)"></p>
          <p class="cro-agenda-mas" x-show="!agendaDe(verAgenda).length"><?= h(t('Sin eventos este año.')) ?></p>
        </div>
      </div>
      <label class="mt-4 flex cursor-pointer items-center justify-between text-sm<?= $tieneArea ? '' : ' opacity-60' ?>" <?= $tieneArea ? '' : 'title="' . h(t('No tienes un área asignada')) . '"' ?>>
        <span><?= h(t('Solo mi área')) ?></span>
        <input type="checkbox" class="h-4 w-4 accent-azul" :checked="!!filtros.mios" @change="filtros.mios = $event.target.checked ? '1' : ''; if (filtros.mios) filtros.area_id = ''; aplicar()"<?= $tieneArea ? '' : ' disabled' ?>>
      </label>
      <button type="button" x-show="hayFiltros" x-cloak class="cro-btn-limpiar mt-4" @click="limpiar()">
        <span class="cro-btn-x">✕</span><span><?= h(t('Limpiar filtros')) ?></span>
      </button>
    </div>

    <div class="card p-4">
      <p class="label"><?= h(t('Próximos 30 días')) ?></p>
      <ul class="space-y-2">
        <template x-for="p in proximos" :key="p.id">
          <li>
            <button type="button" class="cro-prox-btn w-full rounded-lg border-l-[3px] px-2.5 py-1.5 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]" :style="'border-color:' + p.area_color" @click="irAEvento(p)">
              <span class="block truncate text-sm font-semibold" x-text="p.nombre"></span>
              <span class="block text-xs text-gris" x-text="p.rango + ' · ' + (p.ciudad || p.area)"></span>
            </button>
          </li>
        </template>
        <li x-show="!proximos.length" class="text-xs text-gris"><?= h(t('Nada programado en los próximos 30 días.')) ?></li>
      </ul>
    </div>
  </aside>

  <!-- Calendario -->
  <section class="cro-col card p-4 md:p-5">
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <button type="button" class="btn-secundario" x-show="vista !== 'anio'" @click="hoy()"><?= h(t('Hoy')) ?></button>
      <div class="flex overflow-hidden rounded-full border border-borde dark:border-noche-borde" x-show="vista !== 'anio'">
        <button type="button" class="px-3 py-1.5 hover:bg-[#F0EEE8] dark:hover:bg-[#232834]" @click="anterior()" aria-label="<?= h(t('Anterior')) ?>">‹</button>
        <button type="button" class="border-l border-borde px-3 py-1.5 hover:bg-[#F0EEE8] dark:border-noche-borde dark:hover:bg-[#232834]" @click="siguiente()" aria-label="<?= h(t('Siguiente')) ?>">›</button>
      </div>
      <h1 class="ml-2 font-serif text-2xl cro-cal-titulo" x-text="vista === 'anio' ? txt.mapaDeCalor + ' ' + anio : (vista === 'linea' ? txt.vistaLinea + ' · ' + titulo : titulo)"></h1>
      <div class="ml-auto flex gap-1 rounded-full border border-borde p-1 dark:border-noche-borde">
        <template x-for="v in [['anio',txt.vistaAnio],['dayGridMonth',txt.vistaMes],['timeGridWeek',txt.vistaSemana],['listMonth',txt.vistaLista],['linea',txt.vistaLinea]]" :key="v[0]">
          <button type="button" class="rounded-full px-3 py-1 text-xs font-semibold" :class="vista === v[0] ? 'bg-azul text-white' : 'text-gris hover:text-tinta dark:hover:text-white'" @click="cambiarVista(v[0])" x-text="v[1]"></button>
        </template>
      </div>
    </div>
    <div x-ref="cal" x-show="vista !== 'anio' && vista !== 'linea'"></div>

    <!-- Vista "Año": mapa de calor por día, con los mismos filtros de la barra lateral -->
    <div x-show="vista === 'anio'" x-cloak>
      <!-- Qué pinta el mapa: es el control, va primero y se ve como control, no como texto. -->
      <div class="cro-hm-barra">
        <p class="cro-hm-barra-rot"><?= h(t('Qué pinta el mapa')) ?></p>
        <div class="cro-hm-segm" role="group" aria-label="<?= h(t('Qué pinta el mapa')) ?>">
          <template x-for="m in modosMapa" :key="m.id">
            <button type="button" class="cro-hm-seg" :class="{ 'cro-hm-seg-activo': modoMapa === m.id }"
                    :title="m.ayuda" :aria-pressed="modoMapa === m.id"
                    @click="modoMapa = m.id; cargarMapa()" x-text="m.rotulo"></button>
          </template>
        </div>
        <button type="button" class="cro-hm-quitar" x-show="foco" x-cloak @click="resaltar('', [])"><?= h(t('Quitar resaltado')) ?></button>
      </div>

      <!-- Las cifras, en tarjetas. Las que se pueden pulsar resaltan esos días en el mapa. -->
      <div class="cro-hm-cifras">
        <div class="cro-hm-dato">
          <b class="cro-hm-num" x-text="mapa.resumen.acciones ?? 0"></b>
          <span class="cro-hm-rot" x-text="tituloTotal"></span>
          <span class="cro-hm-pie" x-text="txt.en + ' ' + anio"></span>
        </div>

        <button type="button" class="cro-hm-dato cro-hm-dato-btn" :class="{ 'cro-hm-dato-activo': foco === 'activos' }"
                title="<?= h(t('Resaltar en el mapa todos esos días')) ?>"
                @click="resaltar('activos', mapa.resumen.dias_activos)">
          <b class="cro-hm-num" x-text="mapa.resumen.dias_con_algo ?? 0"></b>
          <span class="cro-hm-rot" x-text="tituloDias"></span>
          <span class="cro-hm-pie"><?= h(t('pulsa para resaltarlos')) ?></span>
        </button>

        <button type="button" class="cro-hm-dato cro-hm-dato-btn" x-show="mapa.resumen.dia_pico" x-cloak
                :class="{ 'cro-hm-dato-activo': foco === 'dia' }"
                title="<?= h(t('Llevarme a ese día en el mapa')) ?>"
                @click="resaltar('dia', [mapa.resumen.dia_pico])">
          <span class="cro-hm-linea">
            <b class="cro-hm-num" x-text="mapa.resumen.dia_pico_n"></b>
            <span class="cro-hm-uni" x-text="plural(mapa.resumen.dia_pico_n)"></span>
          </span>
          <span class="cro-hm-rot"><?= h(t('Día más cargado')) ?></span>
          <span class="cro-hm-pie" x-text="fechaCorta(mapa.resumen.dia_pico)"></span>
        </button>

        <button type="button" class="cro-hm-dato cro-hm-dato-btn" x-show="mapa.resumen.semana_pico" x-cloak
                :class="{ 'cro-hm-dato-activo': foco === 'semana' }"
                title="<?= h(t('Llevarme a esa semana en el mapa')) ?>"
                @click="resaltar('semana', mapa.resumen.semana_pico_dias)">
          <span class="cro-hm-linea">
            <b class="cro-hm-num" x-text="mapa.resumen.semana_pico_n"></b>
            <span class="cro-hm-uni" x-text="plural(mapa.resumen.semana_pico_n)"></span>
          </span>
          <span class="cro-hm-rot"><?= h(t('Semana más cargada')) ?></span>
          <span class="cro-hm-pie" x-text="semanaTexto(mapa.resumen.semana_pico)"></span>
        </button>
      </div>

      <div class="cro-hm-grid">
        <template x-for="m in mapa.meses" :key="mapa.sello + '-' + m.mes">
          <div>
            <p class="cro-hm-titulo" x-text="m.nombre"></p>
            <div class="cro-hm-dias cro-hm-cab">
              <template x-for="(d, i) in txt.diasSemana" :key="i"><span x-text="d"></span></template>
            </div>
            <div class="cro-hm-dias">
              <template x-for="(c, i) in m.celdas" :key="i">
                <button type="button" class="cro-hm"
                        :class="{ 'cro-hm-vacio': !c, 'cro-hm-0': c && !c.n, 'cro-hm-blanco': c && c.n && nivel(c.n) >= 3,
                                  'cro-hm-foco': c && resaltado.includes(c.f), 'cro-hm-atenuado': foco && c && !resaltado.includes(c.f),
                                  'cro-hm-hoy': c && c.f === fechaHoy }"
                        :data-f="c ? c.f : ''"
                        :style="fondoCelda(c)"
                        :disabled="!c"
                        @mouseenter="c && verVistazo(c, $event.currentTarget)" @mouseleave="vistazo = null"
                        @click.stop="c && pulsarDia(c, $event.currentTarget)" x-text="c ? c.d : ''"></button>
              </template>
            </div>
          </div>
        </template>
        <p x-show="!mapa.meses.length" class="text-sm text-gris"><?= h(t('Cargando…')) ?></p>

      <!-- Panel del día: se abre al pulsar una casilla y se queda hasta cerrarlo, para poder
           pinchar un evento y ver su ficha sin salir del mapa. -->
      <div x-show="dia" x-cloak class="cro-dia-pop" :style="dia ? dia.pos : ''"
           @click.outside="dia = null; diaEvento = null" @keydown.escape.window="dia = null; diaEvento = null">
        <template x-if="dia">
          <div>
            <div class="cro-dia-cab">
              <span x-text="dia.fecha"></span>
              <button type="button" class="cro-dia-x" @click="dia = null" aria-label="<?= h(t('Cerrar')) ?>">✕</button>
            </div>

            <!-- Lista de eventos del día -->
            <template x-if="!diaEvento">
              <div>
                <template x-for="e in dia.eventos" :key="e.id">
                  <button type="button" class="cro-dia-item" @click="diaEvento = e">
                    <span class="cro-dia-punto" :style="'background:' + e.color"></span>
                    <span class="cro-dia-nombre" x-text="e.nombre"></span>
                    <span class="cro-dia-marca cro-marca-cubre" x-show="e.cubre" title="<?= h(t('Pide cubrimiento')) ?>">⚑</span>
                    <span class="cro-dia-flecha">›</span>
                  </button>
                </template>
                <p class="cro-dia-vacio" x-show="!dia.eventos.length"><?= h(t('Sin eventos este día.')) ?></p>
                <button type="button" class="cro-dia-ir cro-dia-ir-suave" @click="irADia(dia.fecha)"><?= h(t('Ver este día en el calendario')) ?> →</button>
              </div>
            </template>

            <!-- Ficha del evento elegido -->
            <template x-if="diaEvento">
              <div>
                <button type="button" class="cro-dia-volver" @click="diaEvento = null">‹ <?= h(t('Todos los del día')) ?></button>
                <p class="cro-dia-titulo" x-text="diaEvento.nombre"></p>
                <dl class="cro-dia-datos">
                  <div><dt><?= h(t('Área')) ?></dt><dd x-text="diaEvento.area"></dd></div>
                  <div><dt><?= h(t('Tipo')) ?></dt><dd x-text="diaEvento.tipo"></dd></div>
                  <div><dt><?= h(t('Fechas')) ?></dt><dd x-text="diaEvento.inicio === diaEvento.fin ? diaEvento.inicio : diaEvento.inicio + ' → ' + diaEvento.fin"></dd></div>
                  <div><dt><?= h(t('Lugar')) ?></dt><dd x-text="diaEvento.lugar"></dd></div>
                  <div><dt><?= h(t('Responsable')) ?></dt><dd x-text="diaEvento.dueno || '—'"></dd></div>
                  <div class="cro-dia-ancho"><dt><?= h(t('Cubrimiento')) ?></dt>
                    <dd x-text="diaEvento.cubre ? '⚑ ' + txt.pideCubrimiento : txt.noPideCubrimiento"></dd>
                  </div>
                </dl>
                <button type="button" class="cro-dia-ir" @click="irAlEvento(diaEvento)"><?= h(t('Ir al evento')) ?> →</button>
              </div>
            </template>
          </div>
        </template>
      </div>
      </div>

      <div class="cro-hm-leyenda">
        <span class="cro-hm-leyenda-rampa" x-show="areasSel().length <= 1">
          <span><?= h(t('Menos')) ?></span>
          <template x-for="k in [1,2,3,4]" :key="k"><span class="cro-hm" :style="'background:' + colorNivel(k)"></span></template>
          <span><?= h(t('Más')) ?></span>
        </span>
        <span class="cro-hm-leyenda-areas" x-show="areasSel().length > 1" x-cloak>
          <template x-for="id in areasSel()" :key="id">
            <span class="cro-hm-area"><span class="cro-hm-punto" :style="'background:' + colorArea(id)"></span><span x-text="nombreArea(id)"></span></span>
          </template>
        </span>
        <span class="cro-hm-nota" x-show="mapa.max"><?= h(t('Hasta')) ?> <b x-text="mapa.max"></b> <?= h(t('por día. Haz clic en un día para ver sus eventos y quién responde por ellos.')) ?></span>
      </div>
    </div>

    <!-- Vista "Línea de tiempo": por área, sus eventos y su gente día a día. En la fila de cada
         persona van, sin texto, los eventos que lidera; el nombre solo en la fila de eventos. -->
    <div x-show="vista === 'linea'" x-cloak class="cro-lt">
      <div class="cro-lt-leyenda">
        <span><i class="cro-lt-mues cro-lt-mues-ev"></i> <?= h(t('Evento del área')) ?></span>
        <span><i class="cro-lt-mues cro-lt-mues-r"></i> <?= h(t('Responsable')) ?></span>
        <span><i class="cro-lt-mues cro-lt-mues-cubre">⚑</i> <?= h(t('Pide cubrimiento')) ?></span>
      </div>
      <p x-show="linea.dias.length && !linea.areas.length" class="py-10 text-center text-sm text-gris"><?= h(t('Nada en este mes con esos filtros.')) ?></p>
      <div class="cro-lt-scroll" x-show="linea.areas.length">
        <div class="cro-lt-cuerpo" :style="'min-width:' + (170 + linea.n * 18) + 'px'">
          <div class="cro-lt-fila cro-lt-cab" :style="'grid-template-columns: var(--lt-rot) repeat(' + linea.n + ', minmax(18px,1fr))'">
            <div class="cro-lt-rot"></div>
            <template x-for="(d, i) in linea.dias" :key="d.f">
              <div class="cro-lt-dia" :class="{ 'cro-lt-finde': d.finde, 'cro-lt-hoy': d.hoy }" :style="'grid-column:' + (i + 2)"><span x-text="d.l"></span><b x-text="d.d"></b></div>
            </template>
          </div>

          <template x-for="a in linea.areas" :key="a.id">
            <div class="cro-lt-area">
              <div class="cro-lt-area-cab">
                <span class="cro-lt-punto" :style="'background:' + a.color"></span>
                <span x-text="a.nombre"></span>
                <span class="cro-lt-area-n" x-text="a.eventos.length + ' ' + (a.eventos.length === 1 ? txt.eventoSing : txt.eventos) + ' · ' + a.personas.length + ' ' + (a.personas.length === 1 ? txt.persona : txt.personas)"></span>
              </div>

              <div class="cro-lt-fila" x-show="a.eventos.length"
                   :style="'grid-template-columns: var(--lt-rot) repeat(' + linea.n + ', minmax(18px,1fr)); grid-template-rows: repeat(' + a.carriles + ', 26px)'">
                <div class="cro-lt-rot cro-lt-rot-ev"><?= h(t('Eventos')) ?></div>
                <template x-for="(d, i) in linea.dias" :key="'c' + d.f"><div class="cro-lt-celda" :class="{ 'cro-lt-finde': d.finde, 'cro-lt-hoy': d.hoy }" :style="'grid-column:' + (i + 2) + '; grid-row: 1 / -1'"></div></template>
                <template x-for="e in a.eventos" :key="'e' + e.id">
                  <button type="button" class="cro-lt-barra cro-lt-ev"
                          :class="{ 'cro-lt-cancelado': e.estado === 'cancelado', 'cro-lt-corta': e.c2 === e.c1, 'cro-lt-foco': focoLinea === e.id, 'cro-lt-tenue': focoLinea && focoLinea !== e.id }"
                          :style="'grid-column:' + (e.c1 + 1) + ' / ' + (e.c2 + 2) + '; grid-row:' + e.carril + '; --c:' + a.color"
                          @mouseenter="vistazoLinea(e, $event.currentTarget)" @mouseleave="salirLinea()" @click="vistazo = null; abrir(e.id)">
                    <span class="cro-lt-ico" x-text="e.cubre ? '⚑' : ''"></span><span class="cro-lt-txt" x-text="e.nombre"></span>
                  </button>
                </template>
              </div>

              <template x-for="p in a.personas" :key="'p' + p.id">
                <div class="cro-lt-fila cro-lt-persona" :class="{ 'cro-lt-fila-foco': focoLinea && p.barras.some(b => b.id === focoLinea) }"
                     :style="'grid-template-columns: var(--lt-rot) repeat(' + linea.n + ', minmax(18px,1fr)); grid-template-rows: repeat(' + p.carriles + ', 26px)'">
                  <div class="cro-lt-rot"><span class="cro-lt-nombre" x-text="p.nombre"></span><span class="cro-lt-cuenta" x-show="p.en" x-text="p.en"></span></div>
                  <div class="cro-lt-pista" :style="'grid-column: 2 / ' + (linea.n + 2) + '; grid-row: 1 / -1'"></div>
                  <template x-for="(d, i) in linea.dias" :key="'c' + d.f"><div class="cro-lt-celda" :class="{ 'cro-lt-finde': d.finde, 'cro-lt-hoy': d.hoy }" :style="'grid-column:' + (i + 2) + '; grid-row: 1 / -1'"></div></template>
                  <template x-for="b in p.barras" :key="'b' + b.id">
                    <button type="button" class="cro-lt-barra cro-lt-muda cro-lt-r" :aria-label="b.nombre + ' · ' + txt.responsable"
                            :class="{ 'cro-lt-cancelado': b.estado === 'cancelado', 'cro-lt-corta': b.c2 === b.c1, 'cro-lt-foco': focoLinea === b.id, 'cro-lt-tenue': focoLinea && focoLinea !== b.id }"
                            :style="'grid-column:' + (b.c1 + 1) + ' / ' + (b.c2 + 2) + '; grid-row:' + b.carril + '; --c:' + a.color"
                            @mouseenter="vistazoLinea(b, $event.currentTarget)" @mouseleave="salirLinea()" @click="vistazo = null; abrir(b.id)">
                      <span class="cro-lt-txt" aria-hidden="true"></span>
                    </button>
                  </template>
                </div>
              </template>
              <p class="cro-lt-vacio" x-show="!a.personas.length"><?= h(t('Nadie activo en esta área.')) ?></p>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Vistazo al señalar: solo informa, no se puede pulsar (pointer-events:none), así
         que nunca se interpone entre el ratón y la casilla. El detalle va en el panel. -->
    <div x-show="vistazo && !dia" x-cloak class="cro-vistazo" :style="vistazo ? vistazo.pos : ''">
      <template x-if="vistazo">
        <div>
          <p class="cro-vistazo-cab"><span x-text="vistazo.fecha"></span> · <span x-text="vistazo.cuenta"></span></p>
          <ul class="cro-vistazo-lista">
            <template x-for="(n, i) in vistazo.lineas" :key="i"><li x-text="n"></li></template>
          </ul>
          <p class="cro-vistazo-gente" x-show="vistazo.gente" x-text="vistazo.gente"></p>
          <p class="cro-vistazo-pie" x-text="vistazo.pie || txt.clicFicha"></p>
        </div>
      </template>
    </div>
  </section>

  <!-- Panel lateral de detalle -->
  <aside class="cro-col">
    <div class="card min-h-[320px] overflow-hidden" x-show="detalleAbierto" x-cloak x-transition.opacity @keydown.escape.window="cerrar()">
      <div class="flex items-center justify-between border-b border-borde px-4 py-2.5 dark:border-noche-borde">
        <span class="label mb-0"><?= h(t('Detalle')) ?></span>
        <button type="button" class="btn-icono h-7 w-7" @click="cerrar()" aria-label="<?= h(t('Cerrar')) ?>">✕</button>
      </div>
      <div x-show="cargando" class="p-6 text-sm text-gris"><?= h(t('Cargando…')) ?></div>
      <div x-show="!cargando" x-html="detalle"></div>
    </div>
    <div class="card flex min-h-[320px] flex-col items-center justify-center p-8 text-center text-sm text-gris" x-show="!detalleAbierto">
      <p class="font-serif text-2xl text-tinta dark:text-[#E9EAEE]"><?= h(t('Selecciona un evento')) ?></p>
      <p class="mt-2"><?= h(t('Haz clic en un chip del calendario para ver toda la ficha.')) ?> <?= $puedeCrear ? h(t('Clic en un día vacío crea un evento en esa fecha.')) : h(t('Para crear eventos pide al administrador que te asigne un área.')) ?></p>
    </div>
  </aside>
</div>
<?php
$scripts = '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" integrity="sha384-B1OFx8Gy9GjPu8UbUyXbGQpzll9ubAUQ9agInFJ8NnD7nYG1u/CLR+Sqr5yifl4q" crossorigin="anonymous"></script>'
    . '<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js" integrity="sha384-yvtDoBsejAOiZwybj79N2ImCrUqwpcD1EyVnqVU+BCvFUi85WVXj0tP9gRQyW5KF" crossorigin="anonymous"></script>';
