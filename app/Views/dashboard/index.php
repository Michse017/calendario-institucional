<?php
/** @var array $ind  indicadores del año (Dashboard::indicadores) */
$t = $ind['totales'];
$k = $ind['kpis'];
$mx = $ind['matriz'];
$mesCorto = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mesLargo = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
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
    ['Eventos', $t['total'], $t['realizados'] . ' realizados · ' . $t['cancelados'] . ' cancelados'],
    ['Cancelados', $t['cancelados'], 'no cuentan en los indicadores'],
    ['Conciertos', $k['conciertos']['total'], $k['conciertos']['realizados'] . ' realizados'],
    ['Exposiciones', $k['exposiciones']['total'], $k['exposiciones']['realizados'] . ' realizadas'],
    ['Talleres', $k['talleres']['total'], $k['talleres']['realizados'] . ' realizados'],
    ['Artes escénicas', $k['escenicas']['total'], $k['escenicas']['realizados'] . ' realizadas'],
    ['Festivales', $k['festivales']['total'], $k['festivales']['realizados'] . ' realizados'],
    ['Público escolar e infantil', $k['escolar']['total'], $k['escolar']['realizados'] . ' realizados'],
    ['Sedes', $t['ciudades'], $t['paises'] . ' territorios'],
    ['Aforo estimado', $t['reuniones'], 'suma de los eventos con cifra'],
    ['Alianzas', $t['alianzas'], $t['contactos'] . ' listas de contactos'],
];
?>
<div x-data="dashboardApp(<?= h(json_encode($ind)) ?>)">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris">Dashboard</p>
      <h1 class="titulo">Indicadores <?= $anio ?></h1>
    </div>
    <select class="input w-auto" @change="window.location = CRO.url('dashboard', {anio: $event.target.value})">
      <?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
    </select>
  </div>

  <div class="card mb-6 p-5">
    <div class="mb-1 flex flex-wrap items-start justify-between gap-x-6 gap-y-1">
      <p class="label">Carga por área y mes</p>
      <?php if ($mx['sin_actividad']): ?>
      <p class="text-xs text-gris">Sin eventos en <?= $anio ?>: <?= h(implode(' · ', $mx['sin_actividad'])) ?></p>
      <?php endif; ?>
    </div>
    <p class="cro-mx-lectura" x-text="mxTexto || 'Cada casilla es un mes de un área. Pasa el mouse para ver el detalle; haz clic para abrir ese mes en el calendario.'"></p>

    <div class="cro-mx-scroll">
      <table class="cro-mx">
        <thead>
          <tr>
            <th class="cro-mx-area"></th>
            <?php foreach ($mesCorto as $j => $m): ?>
            <th :class="{ 'cro-mx-viva': mxCol === <?= $j ?> }"><?= $m ?></th>
            <?php endforeach; ?>
            <th class="cro-mx-tot">Año</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mx['areas'] as $i => $a): ?>
          <tr>
            <th class="cro-mx-area" :class="{ 'cro-mx-viva': mxFila === <?= $i ?> }">
              <span class="cro-mx-punto" style="background:<?= h($a['color']) ?>"></span><?= h($a['area']) ?>
            </th>
            <?php foreach ($a['meses'] as $j => $n): ?>
            <?php [$estilo, $claro] = $tono($a['color'], $n); ?>
            <?php $leyenda = $a['area'] . ' · ' . $mesLargo[$j] . ': ' . ($n === 0 ? 'sin eventos' : ($n === 1 ? '1 evento' : $n . ' eventos')); ?>
            <td>
              <button type="button" class="cro-mx-celda<?= $n ? ($claro ? ' cro-mx-claro' : '') : ' cro-mx-cero' ?>"
                      style="<?= $estilo ?>" title="<?= h($leyenda) ?>"
                      @mouseenter="mxSobre(<?= $i ?>, <?= $j ?>, <?= h(json_encode($leyenda)) ?>)" @mouseleave="mxFuera()"
                      @focus="mxSobre(<?= $i ?>, <?= $j ?>, <?= h(json_encode($leyenda)) ?>)" @blur="mxFuera()"
                      @click="mxIr(<?= (int) $a['id'] ?>, <?= $j + 1 ?>)"><?= $n ?: '' ?></button>
            </td>
            <?php endforeach; ?>
            <?php $resAnio = $a['area'] . ' · todo ' . $anio . ': ' . $a['total'] . ' eventos en ' . $a['meses_activos'] . ' meses, el más cargado con ' . $a['pico']; ?>
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
            <th class="cro-mx-area">Todas</th>
            <?php foreach ($mx['meses'] as $j => $n): ?>
            <?php $resMes = ucfirst($mesLargo[$j]) . ' · todas las áreas: ' . $n . ($n === 1 ? ' evento' : ' eventos'); ?>
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
    <div class="card p-5 lg:col-span-2"><p class="label">Eventos por mes y estado</p><div x-ref="mes" class="h-72"></div></div>
    <div class="card p-5"><p class="label">Por área</p><div x-ref="area" class="h-72"></div></div>
    <div class="card p-5 lg:col-span-2"><p class="label">Por tipo de evento</p><div x-ref="tipo" class="h-80"></div></div>
    <div class="card p-5"><p class="label">Por segmento</p><div x-ref="segmento" class="h-80"></div></div>
  </div>
</div>
<?php $scripts = '<script src="https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js"></script>'; ?>
