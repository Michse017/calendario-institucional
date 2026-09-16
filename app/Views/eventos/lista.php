<?php
use App\Core\Campos;
use App\Core\View;

$f = static fn(string $k): string => (string) ($filtros[$k] ?? '');
$qs = array_filter(['anio' => $f('anio'), 'area_id' => $f('area_id'), 'tipo_accion_id' => $f('tipo_accion_id'), 'segmento_id' => $f('segmento_id'), 'estado' => $f('estado'), 'q' => $f('q'), 'mios' => $mios ? '1' : '', 'orden' => $orden === 'desc' ? 'desc' : '']);
$u = usuario_actual();
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
  <div>
    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h(t('Eventos')) ?></p>
    <h1 class="titulo"><span id="cro-total"><?= (int) $lista['total'] ?></span> <span class="text-gris"><?= h(t('registros')) ?></span></h1>
  </div>
  <a href="<?= h(url('eventos/exportar', $qs)) ?>" class="btn-secundario">⬇ <?= h(t('Exportar CSV')) ?></a>
</div>

<form method="get" class="card mb-5 grid gap-3 p-4 md:grid-cols-9" x-data="buscadorEventos()">
  <input type="hidden" name="r" value="eventos">
  <input class="input md:col-span-2" name="q" value="<?= h($f('q')) ?>" placeholder="<?= h(t('Buscar por nombre, ciudad, organizador u objetivo…')) ?>" autocomplete="off" @input.debounce.250ms="buscar()" @search="buscar()">
  <select class="input" name="anio"><option value=""><?= h(t('Todos los años')) ?></option><?php foreach ($anios as $a): ?><option value="<?= $a ?>" <?= $f('anio') == $a ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select>
  <select class="input" name="area_id"><option value=""><?= h(t('Todas las áreas')) ?></option><?php foreach ($areas as $a): ?><option value="<?= $a['id'] ?>" <?= $f('area_id') == $a['id'] ? 'selected' : '' ?>><?= h(t($a['valor'])) ?></option><?php endforeach; ?></select>
  <select class="input" name="tipo_accion_id"><option value=""><?= h(t('Todos los tipos')) ?></option><?php foreach ($tipos as $t): ?><option value="<?= $t['id'] ?>" <?= $f('tipo_accion_id') == $t['id'] ? 'selected' : '' ?>><?= h(t($t['valor'])) ?></option><?php endforeach; ?></select>
  <select class="input" name="segmento_id"><option value=""><?= h(t('Todos los segmentos')) ?></option><?php foreach ($segmentos as $s): ?><option value="<?= $s['id'] ?>" <?= $f('segmento_id') == $s['id'] ? 'selected' : '' ?>><?= h(t($s['valor'])) ?></option><?php endforeach; ?></select>
  <select class="input" name="estado"><option value=""><?= h(t('Todos los estados')) ?></option><?php foreach (Campos::ESTADOS as $k => $et): ?><option value="<?= $k ?>" <?= $f('estado') === $k ? 'selected' : '' ?>><?= h(t($et)) ?></option><?php endforeach; ?></select>
  <select class="input" name="orden" aria-label="<?= h(t('Orden')) ?>"><option value="asc" <?= $orden === 'asc' ? 'selected' : '' ?>><?= h(t('Del más cercano al más lejano')) ?></option><option value="desc" <?= $orden === 'desc' ? 'selected' : '' ?>><?= h(t('Del más lejano al más cercano')) ?></option></select>
  <div class="flex items-center gap-2">
    <label class="flex items-center gap-1.5 text-sm<?= $tieneArea ? '' : ' opacity-60' ?>" <?= $tieneArea ? '' : 'title="' . h(t('No tienes un área asignada')) . '"' ?>><input type="checkbox" name="mios" value="1" class="accent-azul" <?= $mios ? 'checked' : '' ?><?= $tieneArea ? '' : ' disabled' ?>> <?= h(t('Solo mi área')) ?></label>
    <button class="btn-primario ml-auto"><?= h(t('Filtrar')) ?></button>
  </div>
</form>

<div class="card overflow-x-auto">
  <table class="tabla">
    <thead><tr><th scope="col"><?= h(t('Fechas')) ?></th><th scope="col"><?= h(t('Evento')) ?></th><th scope="col"><?= h(t('Tipo')) ?></th><th scope="col"><?= h(t('Área')) ?></th><th scope="col"><?= h(t('Lugar')) ?></th><th scope="col"><?= h(t('Estado')) ?></th><th scope="col"><?= h(t('Creado por')) ?></th><th scope="col"></th></tr></thead>
    <tbody id="cro-filas">
    <?= View::parcial('eventos/_filas', ['filas' => $lista['filas']]) ?>
    </tbody>
  </table>
</div>

<?php if ($lista['paginas'] > 1): ?>
<nav class="mt-4 flex items-center justify-between text-sm" id="cro-paginacion">
  <span class="text-gris"><?= h(t('Página :n de :total', ['n' => $lista['pagina'], 'total' => $lista['paginas']])) ?></span>
  <div class="flex gap-2">
    <?php if ($lista['pagina'] > 1): ?><a class="btn-secundario" href="<?= h(url('eventos', $qs + ['pagina' => $lista['pagina'] - 1])) ?>">‹ <?= h(t('Anterior')) ?></a><?php endif; ?>
    <?php if ($lista['pagina'] < $lista['paginas']): ?><a class="btn-secundario" href="<?= h(url('eventos', $qs + ['pagina' => $lista['pagina'] + 1])) ?>"><?= h(t('Siguiente')) ?> ›</a><?php endif; ?>
  </div>
</nav>
<?php endif; ?>
