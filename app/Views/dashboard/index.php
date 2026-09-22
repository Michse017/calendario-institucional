<?php
/** @var array $ind  indicadores del año (Dashboard::indicadores) */
$t = $ind['totales'];
$k = $ind['kpis'];
$mx = $ind['matriz'];
$mesCorto = array_map('t', ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']);
$mesLargo = array_map('t', ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']);
/**
 * Tono de una casilla: el color propio del área, con más cuerpo cuanto más cargado esté el mes.
 * Devuelve [estilo, textoClaro]; el segundo dice si el fondo ya pide letra blanca.
 */
$tono = static function (string $hex, int $n) use ($mx): array {
    if ($n <= 0) {
        return ['', false];
    }
    if (!preg_match('/^#[0-9a-f]{6}$/i', $hex)) {
        $hex = '#B3B7BF';
    }
    $r = $mx['max'] > 0 ? $n / $mx['max'] : 1.0;
    $alfa = 0.18 + 0.68 * ($r ** 0.7);
    [$rr, $gg, $bb] = sscanf($hex, '#%02x%02x%02x');
    return [sprintf('background:rgba(%d,%d,%d,%.3f)', $rr, $gg, $bb, $alfa), $alfa >= 0.58];
};
$tarjetas = [
    [t('Eventos'), $t['total'], t(':n realizados · :c cancelados', ['n' => $t['realizados'], 'c' => $t['cancelados']])],
    [t('Cancelados'), $t['cancelados'], t('no cuentan en los indicadores')],
    [t('Conciertos'), $k['conciertos']['total'], t(':n realizados', ['n' => $k['conciertos']['realizados']])],
    [t('Exposiciones'), $k['exposiciones']['total'], t(':n realizadas', ['n' => $k['exposiciones']['realizados']])],
    [t('Talleres'), $k['talleres']['total'], t(':n realizados', ['n' => $k['talleres']['realizados']])],
    [t('Artes escénicas'), $k['escenicas']['total'], t(':n realizadas', ['n' => $k['escenicas']['realizados']])],
    [t('Festivales'), $k['festivales']['total'], t(':n realizados', ['n' => $k['festivales']['realizados']])],
    [t('Público escolar e infantil'), $k['escolar']['total'], t(':n realizados', ['n' => $k['escolar']['realizados']])],
    [t('Sedes'), $t['ciudades'], t(':n territorios', ['n' => $t['paises']])],
    [t('Aforo estimado'), $t['reuniones'], t('suma de los eventos con cifra')],
    [t('Alianzas'), $t['alianzas'], t(':n listas de contactos', ['n' => $t['contactos']])],
];
?>
<?php
// Las graficas las pinta ECharts desde el JavaScript, asi que las etiquetas que
// salen de los datos se traducen aqui, antes de mandarselas.
$indJs = $ind;
foreach ($indJs['por_tipo'] as $i => $f)      { $indJs['por_tipo'][$i]['tipo'] = t($f['tipo']); }
foreach ($indJs['por_segmento'] as $i => $f)  { $indJs['por_segmento'][$i]['segmento'] = t($f['segmento']); }
foreach ($indJs['por_area'] as $i => $f)      { $indJs['por_area'][$i]['area'] = t($f['area']); }
// Informe de cubrimiento: las áreas vienen sembradas y se traducen; los nombres de la gente, no.
$cubJs = $cub;
foreach ($cubJs['carga'] as $i => $f)        { $cubJs['carga'][$i]['area'] = $f['area'] !== null ? t($f['area']) : null; }
foreach ($cubJs['responsables'] as $i => $f) { $cubJs['responsables'][$i]['area'] = $f['area'] !== null ? t($f['area']) : null; }
foreach ($cubJs['por_area'] as $i => $f)     { $cubJs['por_area'][$i]['area'] = t($f['area']); }
$indJs['cub'] = $cubJs;
$indJs['txt'] = [
    'meses'   => $mesCorto,
    'eventos'      => t('Eventos'),
    'dias'         => t('Días'),
    'piden'        => t('Piden cubrimiento'),
    'noPiden'      => t('No lo piden'),
    'lidera'       => t('Eventos que lidera'),
    'conCub'       => t('De ellos, con cubrimiento'),
    'semana'       => t('Semana'),
    'personasDia'  => t('personas-día'),
    'vacioPiden'   => t('Ningún evento de este año pide cubrimiento todavía.'),
    'vacioEventos' => t('Sin eventos este año.'),
    'vacioSemanas' => t('Nadie comprometido este año todavía: marca qué eventos piden cubrimiento.'),
    'estados' => array_map('t', ['No realizado', 'En ejecución', 'Realizado', 'Cancelado']),
    'total'      => t('Total'),
    'realizadas' => t('Realizadas'),
];
?>
<div x-data="dashboardApp(<?= h(json_encode($indJs)) ?>)">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris">Dashboard</p>
      <h1 class="titulo"><?= h(t('Indicadores')) ?> <?= $anio ?></h1>
    </div>
    <select class="input w-auto" @change="window.location = CRO.url('dashboard', {anio: $event.target.value})">
      <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="card mb-6 p-5">
    <div class="mb-1 flex flex-wrap items-start justify-between gap-x-6 gap-y-1">
      <p class="label"><?= h(t('Carga por área y mes')) ?></p>
      <?php if ($mx['sin_actividad']): ?>
      <p class="text-xs text-gris"><?= h(t('Sin eventos en :anio', ['anio' => $anio])) ?>: <?= h(implode(' · ', array_map('t', $mx['sin_actividad']))) ?></p>
      <?php endif; ?>
    </div>
    <p class="cro-mx-lectura" x-text="mxTexto || <?= h(json_encode(t('Cada casilla es un mes de un área. Pasa el mouse para ver el detalle; haz clic para abrir ese mes en el calendario.'))) ?>"></p>

    <div class="cro-mx-scroll">
      <table class="cro-mx">
        <thead>
          <tr>
            <th class="cro-mx-area"></th>
            <?php foreach ($mesCorto as $j => $m): ?>
            <th :class="{ 'cro-mx-viva': mxCol === <?= $j ?> }"><?= $m ?></th>
            <?php endforeach; ?>
            <th class="cro-mx-tot"><?= h(t('Año')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mx['areas'] as $i => $a): ?>
          <tr>
            <th class="cro-mx-area" :class="{ 'cro-mx-viva': mxFila === <?= $i ?> }">
              <span class="cro-mx-punto" style="background:<?= h($a['color']) ?>"></span><?= h(t($a['area'])) ?>
            </th>
            <?php foreach ($a['meses'] as $j => $n): ?>
            <?php [$estilo, $claro] = $tono($a['color'], $n); ?>
            <?php $leyenda = t($a['area']) . ' · ' . $mesLargo[$j] . ': ' . ($n === 0 ? t('sin eventos') : ($n === 1 ? t('1 evento') : t(':n eventos', ['n' => $n]))); ?>
            <td>
              <button type="button" class="cro-mx-celda<?= $n ? ($claro ? ' cro-mx-claro' : '') : ' cro-mx-cero' ?>"
                      style="<?= $estilo ?>" title="<?= h($leyenda) ?>"
                      @mouseenter="mxSobre(<?= $i ?>, <?= $j ?>, <?= h(json_encode($leyenda)) ?>)" @mouseleave="mxFuera()"
                      @focus="mxSobre(<?= $i ?>, <?= $j ?>, <?= h(json_encode($leyenda)) ?>)" @blur="mxFuera()"
                      @click="mxIr(<?= (int) $a['id'] ?>, <?= $j + 1 ?>)"><?= $n ?: '' ?></button>
            </td>
            <?php endforeach; ?>
            <?php $resAnio = t(':area · todo :anio: :total eventos en :meses meses, el más cargado con :pico', ['area' => t($a['area']), 'anio' => $anio, 'total' => $a['total'], 'meses' => $a['meses_activos'], 'pico' => $a['pico']]); ?>
            <td>
              <button type="button" class="cro-mx-celda cro-mx-tot" title="<?= h($resAnio) ?>"
                      @mouseenter="mxSobre(<?= $i ?>, null, <?= h(json_encode($resAnio)) ?>)" @mouseleave="mxFuera()"
                      @click="mxIr(<?= (int) $a['id'] ?>, 0)"><?= $a['total'] ?></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th class="cro-mx-area"><?= h(t('Todas')) ?></th>
            <?php foreach ($mx['meses'] as $j => $n): ?>
            <?php $resMes = mb_convert_case(mb_substr($mesLargo[$j], 0, 1), MB_CASE_UPPER) . mb_substr($mesLargo[$j], 1) . ' · ' . t('todas las áreas') . ': ' . ($n === 1 ? t('1 evento') : t(':n eventos', ['n' => $n])); ?>
            <td>
              <button type="button" class="cro-mx-celda cro-mx-pie" :class="{ 'cro-mx-viva': mxCol === <?= $j ?> }"
                      title="<?= h($resMes) ?>"
                      @mouseenter="mxSobre(null, <?= $j ?>, <?= h(json_encode($resMes)) ?>)" @mouseleave="mxFuera()"
                      @click="mxIr(0, <?= $j + 1 ?>)"><?= $n ?: '·' ?></button>
            </td>
            <?php endforeach; ?>
            <td><span class="cro-mx-celda cro-mx-tot cro-mx-pie"><?= $mx['total'] ?></span></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
    <?php foreach ($tarjetas as [$titulo, $valor, $sub]): ?>
    <div class="card p-4">
      <p class="label"><?= h($titulo) ?></p>
      <p class="font-serif text-4xl"><?= (int) $valor ?></p>
      <p class="text-xs text-gris"><?= h($sub) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="card p-5 lg:col-span-2"><p class="label"><?= h(t('Eventos por mes y estado')) ?></p><div x-ref="mes" class="h-72"></div></div>
    <div class="card p-5"><p class="label"><?= h(t('Por área')) ?></p><div x-ref="area" class="h-72"></div></div>
    <div class="card p-5 lg:col-span-2"><p class="label"><?= h(t('Por tipo de evento')) ?></p><div x-ref="tipo" class="h-80"></div></div>
    <div class="card p-5"><p class="label"><?= h(t('Por segmento')) ?></p><div x-ref="segmento" class="h-80"></div></div>
  </div>

  <div class="mt-8 mb-3 flex flex-wrap items-end justify-between gap-2">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h(t('Cubrimiento')) ?></p>
      <p class="mt-1 max-w-2xl text-sm text-gris"><?= h(t('Quién lidera y en qué semanas se aprieta. Cuentan los eventos que piden cubrimiento, con su responsable.')) ?></p>
    </div>
    <a class="text-xs font-semibold text-azul hover:underline" href="<?= h(url('calendario', ['anio' => $anio, 'vista' => 'linea'])) ?>"><?= h(t('Ver la línea de tiempo')) ?> ↗</a>
  </div>
  <div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-5 lg:col-span-2"><p class="label"><?= h(t('Responsables con más eventos que piden cubrimiento')) ?></p><div x-ref="cubPersonas" class="h-80"></div></div>
    <div class="card p-5"><p class="label"><?= h(t('Cubrimiento por área')) ?></p><div x-ref="cubAreas" class="h-80"></div></div>
    <div class="card p-5 lg:col-span-2"><p class="label"><?= h(t('Responsables que más eventos lideran')) ?></p><div x-ref="cubResp" class="h-80"></div></div>
    <div class="card p-5">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="label mb-0"><?= h(t('Carga por responsable')) ?></p>
        <a class="text-xs font-semibold text-azul hover:underline" href="<?= h(url('disponibilidad')) ?>"><?= h(t('Ver quién está disponible')) ?> ↗</a>
      </div>
      <?php if ($cub['carga']): ?>
      <table class="tabla text-sm">
        <thead><tr><th scope="col"><?= h(t('Persona')) ?></th><th scope="col"><?= h(t('Eventos')) ?></th><th scope="col"><?= h(t('Días')) ?></th></tr></thead>
        <tbody>
        <?php foreach (array_slice($cub['carga'], 0, 12) as $p): ?>
          <tr>
            <td class="font-medium"><a class="hover:text-azul" href="<?= h(url('eventos', ['persona' => (int) ($p['id'] ?? 0), 'anio' => $anio])) ?>"><?= h($p['nombre']) ?></a><span class="block text-xs text-gris"><?= ($p['area'] ?? '') !== '' && $p['area'] !== null ? h(t($p['area'])) : '—' ?></span></td>
            <td class="font-semibold"><?= (int) $p['eventos'] ?></td>
            <td class="text-gris"><?= (int) $p['dias'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="py-6 text-center text-sm text-gris"><?= h(t('Ningún evento de :anio pide cubrimiento todavía.', ['anio' => $anio])) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mt-6 p-5">
    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
      <p class="label mb-0"><?= h(t('Personas-día por semana')) ?></p>
      <p class="text-xs text-gris"><?= h(t('Cuánta gente está comprometida cada semana del año, sumando sus días. El pico va en ámbar: es la semana más cargada.')) ?></p>
    </div>
    <div x-ref="cubSemanas" class="h-72"></div>
  </div>
</div>
<?php $scripts = '<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js" integrity="sha384-Mx5lkUEQPM1pOJCwFtUICyX45KNojXbkWdYhkKUKsbv391mavbfoAmONbzkgYPzR" crossorigin="anonymous"></script>'; ?>
