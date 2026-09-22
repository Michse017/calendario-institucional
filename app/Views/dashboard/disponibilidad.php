<?php
/**
 * Disponibilidad: quién está comprometido y quién disponible en un rango de fechas, y la
 * agenda de una persona día a día.
 *
 * El nombre importa: esto solo sabe de eventos del calendario. No sabe de vacaciones ni de
 * bajas, así que "disponible" significa "sin evento con cubrimiento a su cargo", no "libre
 * de verdad".
 *
 * @var string $inicio  @var string $fin  @var int $persona
 * @var list<array<string,mixed>> $gente     todos los activos, con su lista de eventos del rango
 * @var list<array<string,mixed>> $agenda    eventos de $persona en el rango (vacío si no hay persona)
 * @var list<array<string,mixed>> $usuarios  para el buscador
 */
use App\Core\Campos;
use App\Core\Idioma;

$ocupados = array_values(array_filter($gente, static fn(array $p): bool => (int) $p['eventos'] > 0));
$libres   = array_values(array_filter($gente, static fn(array $p): bool => (int) $p['eventos'] === 0));

// Días del rango que la persona elegida tiene comprometidos: fecha => eventos de ese día.
$ocupa = [];
foreach ($agenda as $e) {
    $d = new DateTime(max($e['fecha_inicio'], $inicio));
    $tope = new DateTime(min($e['fecha_fin'], $fin));
    while ($d <= $tope) {
        $ocupa[$d->format('Y-m-d')][] = ['id' => (int) $e['id'], 'nombre' => (string) $e['nombre']];
        $d->modify('+1 day');
    }
}
$dias = [];
$d = new DateTime($inicio);
$tope = new DateTime($fin);
while ($d <= $tope && count($dias) < 400) {
    $dias[] = $d->format('Y-m-d');
    $d->modify('+1 day');
}
$quien = '';
foreach ($usuarios as $u) {
    if ((int) $u['id'] === $persona) { $quien = (string) $u['nombre']; }
}
$porId = array_column($agenda, null, 'id');

// Enlaces y pistas. Las pistas van en data-tip (CSS) y no en title: el title del navegador
// tarda medio segundo en salir y no se puede dar formato.
$verEvento = static fn(array $e): string => url('calendario', ['evento' => (int) $e['id'], 'fecha' => $e['fecha_inicio']]);
$verAgenda = static fn(int $id): string => url('disponibilidad', ['inicio' => $inicio, 'fin' => $fin, 'persona' => $id]);
$pistaEvento = static fn(array $e): string => rango_fechas($e['fecha_inicio'], $e['fecha_fin']) . ' · ' . t($e['area']);
$ids = static fn(array $evs): string => h(json_encode(array_values(array_unique(array_map(static fn(array $x): int => (int) $x['id'], $evs)))));

// Lo que pinta el JavaScript del selector de fechas viaja ya traducido.
$cfgRango = [
    'inicio' => $inicio, 'fin' => $fin, 'idioma' => Idioma::actual(),
    'txt' => ['elegir' => t('Elegir…'), 'eligeRango' => t('Elige un rango'), 'dias' => t('días')],
];
$cfgPersona = [
    'id' => $persona,
    'usuarios' => array_map(static fn(array $u): array => ['id' => (string) $u['id'], 'nombre' => $u['nombre'], 'area' => ($u['area_nombre'] ?? '') !== '' ? t($u['area_nombre']) : ''], $usuarios),
];
?>
<div class="mb-5 cro-entra">
  <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h(t('Consulta')) ?></p>
  <h1 class="titulo"><?= h(t('Disponibilidad')) ?></h1>
  <p class="mt-1 max-w-2xl text-sm text-gris"><?= h(t('Quién está comprometido (responsable de un evento que pide cubrimiento) y quién disponible en un rango de fechas. Elige a una persona para ver su agenda día a día. Solo cuenta eventos del calendario: no sabe de vacaciones ni de bajas.')) ?></p>
</div>

<form method="get" class="card cro-form-encima cro-entra cro-entra-2 mb-5 grid gap-4 p-4 md:grid-cols-4">
  <input type="hidden" name="r" value="disponibilidad">
  <div class="relative md:col-span-2" x-data="rangoFechas(<?= h(json_encode($cfgRango)) ?>)" @click.outside="abierto = false" @keydown.escape="abierto = false">
    <input type="hidden" name="inicio" :value="inicio">
    <input type="hidden" name="fin" :value="fin">

    <div class="grid grid-cols-2 gap-3">
      <div>
        <span class="label mb-0.5"><?= h(t('Desde')) ?></span>
        <button type="button" class="input cro-fecha-btn" :class="{ 'cro-fecha-activa': abierto && !ancla }"
                @click="abrir('inicio')" x-text="bonita(inicio)"></button>
      </div>
      <div>
        <span class="label mb-0.5"><?= h(t('Hasta')) ?></span>
        <button type="button" class="input cro-fecha-btn" :class="{ 'cro-fecha-activa': abierto && ancla }"
                @click="abrir('fin')" x-text="bonita(fin)"></button>
      </div>
    </div>

    <div x-show="abierto" x-cloak x-transition.opacity class="cro-rango-pop">
      <div class="cro-rango-atajos">
        <button type="button" class="cro-rango-atajo" @click="atajo('semana')"><?= h(t('7 días')) ?></button>
        <button type="button" class="cro-rango-atajo" @click="atajo('quincena')"><?= h(t('15 días')) ?></button>
        <button type="button" class="cro-rango-atajo" @click="atajo('mes')"><?= h(t('30 días')) ?></button>
        <button type="button" class="cro-rango-atajo" @click="atajo('mesActual')"><?= h(t('Este mes')) ?></button>
        <button type="button" class="cro-rango-atajo" @click="atajo('anio')"><?= h(t('Todo el año')) ?></button>
      </div>

      <div class="cro-rango-cab">
        <button type="button" class="cro-rango-nav" @click="mover(-1)" aria-label="<?= h(t('Mes anterior')) ?>">‹</button>
        <button type="button" class="cro-rango-rotulo" @click="saltar = !saltar" :aria-expanded="saltar" title="<?= h(t('Ir a otro mes')) ?>">
          <span x-text="rotulo"></span><span class="cro-rango-caret">▾</span>
        </button>
        <button type="button" class="cro-rango-nav" @click="mover(1)" aria-label="<?= h(t('Mes siguiente')) ?>">›</button>
      </div>

      <div x-show="saltar" x-cloak class="cro-rango-salto">
        <div class="cro-rango-anio">
          <button type="button" class="cro-rango-nav" @click="moverAnio(-1)" aria-label="<?= h(t('Año anterior')) ?>">‹</button>
          <span x-text="anio"></span>
          <button type="button" class="cro-rango-nav" @click="moverAnio(1)" aria-label="<?= h(t('Año siguiente')) ?>">›</button>
        </div>
        <div class="cro-rango-meses">
          <template x-for="(m, i) in meses" :key="i">
            <button type="button" class="cro-rango-mesbtn" :class="{ 'cro-rango-mesactivo': i === mes }"
                    @click="irA(i)" x-text="m"></button>
          </template>
        </div>
      </div>

      <div class="cro-rango-panes" x-show="!saltar">
        <template x-for="p in panes" :key="p.anio + '-' + p.mes">
          <div>
            <div class="cro-rango-dias cro-rango-cabdias">
              <template x-for="(d, i) in diasSemana" :key="i"><span x-text="d"></span></template>
            </div>
            <div class="cro-rango-dias">
              <template x-for="(f, i) in p.celdas" :key="i">
                <button type="button" class="cro-rango-dia"
                        :class="{ 'cro-rango-hueco': !f, 'cro-rango-punta': f && (esInicio(f) || esFin(f)),
                                  'cro-rango-dentro': f && dentro(f), 'cro-rango-espera': f && esperando(f) }"
                        :disabled="!f" @click="f && pulsar(f)" x-text="f ? Number(f.slice(8)) : ''"></button>
              </template>
            </div>
          </div>
        </template>
      </div>

      <p class="cro-rango-pie">
        <span x-text="resumen"></span>
        <span class="cro-rango-ayuda" x-show="ancla" x-cloak>· <?= h(t('ahora el día de fin')) ?></span>
      </p>
    </div>
  </div>
  <div class="relative" x-data="selectorPersona(<?= h(json_encode($cfgPersona)) ?>)" @click.outside="abierto = false">
    <label class="label mb-0.5" for="persona-q"><?= h(t('Persona')) ?></label>
    <input type="hidden" name="persona" :value="id">
    <div class="relative">
      <input class="input" type="search" id="persona-q" placeholder="<?= h(t('Todas · escribe un nombre…')) ?>"
             x-model="q" @focus="abierto = true" @input="abierto = true; activo = 0; if (q === '') id = ''"
             @keydown="tecla($event)" autocomplete="off">
      <button type="button" class="cro-sel-limpiar" x-show="q" x-cloak @click="limpiar()" aria-label="<?= h(t('Quitar la persona')) ?>">✕</button>
    </div>
    <ul x-show="abierto && candidatos.length" x-cloak x-transition.opacity
        class="card absolute z-20 mt-1 max-h-64 w-full overflow-auto p-1 text-sm">
      <template x-for="(u, i) in candidatos" :key="u.id">
        <li>
          <button type="button" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                  :class="{ 'bg-[#F0EEE8] dark:bg-[#232834]': i === activo }"
                  @click="elegir(u)" @mouseenter="activo = i">
            <span x-text="u.nombre"></span>
            <span class="text-xs text-gris" x-text="u.area ? ' · ' + u.area : ''"></span>
          </button>
        </li>
      </template>
    </ul>
  </div>

  <div class="flex items-end">
    <button class="btn-primario"><?= h(t('Ver')) ?></button>
  </div>
</form>

<?php if ($persona > 0): ?>
<?php $libresPersona = max(0, count($dias) - count($ocupa)); ?>
<!-- Señalar un día ilumina su evento en la lista, y al revés: `foco` guarda los ids del
     evento señalado y el resto se atenúa, para que se vea a qué corresponde cada cosa. -->
<div class="card cro-entra cro-entra-3 mb-6 p-5" x-data="{ foco: [] }" :class="{ 'cro-hay-foco': foco.length > 0 }">
  <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
    <p class="label mb-0"><?= h($quien) ?></p>
    <p class="text-sm text-gris"><?= t(':libres días disponibles de :total · :ocupados comprometidos', ['libres' => '<b class="cro-cifra">' . $libresPersona . '</b>', 'total' => count($dias), 'ocupados' => '<b class="cro-cifra">' . count($ocupa) . '</b>']) ?></p>
  </div>

  <?php
  // Solo los días que esa persona tiene comprometidos, agrupados por mes: los meses sin
  // nada no salen. Debajo de cada mes, sus eventos como chips.
  $porMes = [];
  foreach (array_keys($ocupa) as $f) { $porMes[substr($f, 0, 7)][] = $f; }
  ksort($porMes);
  ?>
  <?php if ($porMes): ?>
  <div class="cro-disp-meses">
    <?php foreach ($porMes as $ym => $fechas): ?>
    <?php
    sort($fechas);
    $evsMes = [];
    foreach ($fechas as $f) { foreach ($ocupa[$f] as $x) { $evsMes[$x['id']] = $x; } }
    ?>
    <div class="cro-disp-fila">
      <p class="cro-disp-mesrot"><?= h(t(Campos::MESES[(int) substr($ym, 5, 2)]) . ' ' . substr($ym, 0, 4)) ?></p>
      <div class="cro-disp-tira">
        <?php foreach ($fechas as $f): ?>
        <?php $j = $ids($ocupa[$f]); ?>
        <span class="cro-disp-dia cro-disp-ocupado cro-tip"
              data-tip="<?= h(fecha_humana($f)) ?> · <?= h(implode(', ', array_column($ocupa[$f], 'nombre'))) ?>"
              @mouseenter="foco = <?= $j ?>" @mouseleave="foco = []"
              :class="{ 'cro-disp-foco': <?= $j ?>.some(i => foco.includes(i)) }"><?= (int) substr($f, 8, 2) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="cro-disp-enque">
        <?php foreach ($evsMes as $x): ?>
        <?php $e = $porId[$x['id']]; ?>
        <a class="cro-ev-chip cro-tip" data-tip="<?= h($pistaEvento($e)) ?> · <?= h(t('ir al calendario')) ?>" href="<?= h($verEvento($e)) ?>"
           @mouseenter="foco = [<?= (int) $x['id'] ?>]" @mouseleave="foco = []"
           :class="{ 'cro-ev-foco': foco.includes(<?= (int) $x['id'] ?>) }"><?= h($x['nombre']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="mt-3 text-[11px] text-gris"><?= h(t('Solo salen los días comprometidos; el resto del rango lo tiene disponible. Señala un día o un evento para ver a qué corresponde.')) ?></p>
  <?php endif; ?>

  <?php if ($agenda): ?>
  <table class="tabla mt-4 text-sm">
    <thead><tr><th scope="col"><?= h(t('Fechas')) ?></th><th scope="col"><?= h(t('Evento')) ?></th><th scope="col"><?= h(t('Área')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($agenda as $e): ?>
      <tr class="cro-fila-viva" @mouseenter="foco = [<?= (int) $e['id'] ?>]" @mouseleave="foco = []"
          :class="{ 'cro-fila-foco': foco.includes(<?= (int) $e['id'] ?>) }">
        <td class="whitespace-nowrap text-gris"><?= h(rango_fechas($e['fecha_inicio'], $e['fecha_fin'])) ?></td>
        <td><a class="cro-enlace-vivo" href="<?= h($verEvento($e)) ?>"><?= h($e['nombre']) ?><span class="cro-enlace-flecha" aria-hidden="true"><?= h(t('ver en el calendario')) ?> →</span></a></td>
        <td class="text-gris"><?= h(t($e['area'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="py-6 text-center text-sm text-gris"><?= h(t('No tiene ningún evento con cubrimiento en esas fechas: está disponible todo el rango.')) ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid gap-6 md:grid-cols-2 cro-entra cro-entra-4">
  <div class="card p-5">
    <p class="label"><?= h(t('Comprometidos')) ?> · <?= count($ocupados) ?></p>
    <p class="mb-3 text-xs text-gris"><?= h(t('Responsables de eventos que piden cubrimiento en esas fechas. Señala un evento para ver cuándo; pulsa el nombre para ver su agenda.')) ?></p>
    <?php if ($ocupados): ?>
    <table class="tabla text-sm">
      <thead><tr><th scope="col"><?= h(t('Persona')) ?></th><th scope="col"><?= h(t('Área')) ?></th><th scope="col"><?= h(t('En')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($ocupados as $p): ?>
        <tr class="cro-fila-viva">
          <td class="font-medium"><a class="cro-enlace-vivo" href="<?= h($verAgenda((int) $p['id'])) ?>"><?= h($p['nombre']) ?><span class="cro-enlace-flecha" aria-hidden="true"><?= h(t('agenda')) ?> →</span></a></td>
          <td class="text-gris"><?= $p['area'] !== null && $p['area'] !== '' ? h(t($p['area'])) : '—' ?></td>
          <td>
            <div class="cro-ev-lista">
              <?php foreach ($p['lista'] as $e): ?>
              <a class="cro-ev-chip cro-tip" data-tip="<?= h($pistaEvento($e)) ?> · <?= h(t('ir al calendario')) ?>" href="<?= h($verEvento($e)) ?>"><?= h($e['nombre']) ?></a>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="py-6 text-center text-sm text-gris"><?= h(t('Nadie está comprometido en esas fechas.')) ?></p>
    <?php endif; ?>
  </div>

  <div class="card p-5">
    <p class="label"><?= h(t('Disponibles')) ?> · <?= count($libres) ?></p>
    <p class="mb-3 text-xs text-gris"><?= h(t('Ojo: esto solo mira el calendario. No sabe de vacaciones, bajas ni de trabajo que no esté aquí.')) ?></p>
    <?php if ($libres): ?>
    <div class="flex flex-wrap gap-1.5">
      <?php foreach ($libres as $p): ?>
      <a class="chip bg-azul-claro text-azul cro-chip-persona cro-tip" data-tip="<?= h(t('Ver su agenda en estas fechas')) ?>" href="<?= h($verAgenda((int) $p['id'])) ?>"><?= h($p['nombre']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="py-6 text-center text-sm text-gris"><?= h(t('Todo el mundo está comprometido en esas fechas.')) ?></p>
    <?php endif; ?>
  </div>
</div>
