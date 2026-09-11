<?php
use App\Core\Campos;

$f = static fn(string $k): string => (string) ($filtros[$k] ?? '');
$qs = array_filter(['anio' => $f('anio'), 'area_id' => $f('area_id'), 'tipo_accion_id' => $f('tipo_accion_id'), 'segmento_id' => $f('segmento_id'), 'estado' => $f('estado'), 'q' => $f('q'), 'mios' => $mios ? '1' : '', 'orden' => $orden === 'desc' ? 'desc' : '']);
$u = usuario_actual();
?>
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
  <div>
    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h(t('Eventos')) ?></p>
    <h1 class="titulo"><?= (int) $lista['total'] ?> <span class="text-gris"><?= h(t('registros')) ?></span></h1>
  </div>
  <a href="<?= h(url('eventos/exportar', $qs)) ?>" class="btn-secundario">⬇ <?= h(t('Exportar CSV')) ?></a>
</div>

<form method="get" class="card mb-5 grid gap-3 p-4 md:grid-cols-9">
  <input type="hidden" name="r" value="eventos">
  <input class="input md:col-span-2" name="q" value="<?= h($f('q')) ?>" placeholder="<?= h(t('Buscar por nombre, ciudad, organizador u objetivo…')) ?>">
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
    <tbody>
    <?php foreach ($lista['filas'] as $e): ?>
      <tr class="hover:bg-[#FAF9F6] dark:hover:bg-[#1F242E]">
        <td class="whitespace-nowrap text-gris"><?= h(rango_fechas($e['fecha_inicio'], $e['fecha_fin'])) ?></td>
        <td><a class="font-semibold hover:text-azul<?= $e['estado'] === 'cancelado' ? ' line-through text-gris' : '' ?>" href="<?= h(url('calendario', ['evento' => $e['id'], 'fecha' => $e['fecha_inicio']])) ?>"><?= h($e['nombre']) ?></a><div class="text-xs text-gris"><?= h(t($e['segmento'])) ?> · <?= h($e['organizador']) ?></div></td>
        <td><?= h(t($e['tipo_accion'])) ?></td>
        <td><span class="chip" style="background:color-mix(in srgb,<?= h($e['area_color'] ?: Campos::COLOR_NEUTRO) ?> 14%,transparent);color:<?= h($e['area_color'] ?: Campos::COLOR_NEUTRO) ?>"><?= h(t($e['area'])) ?></span></td>
        <td><?= h($e['ciudad']) ?>, <?= h($e['pais']) ?></td>
        <td><span class="badge-estado" style="--c:<?= estado_color($e['estado']) ?>"><?= h(t(estado_etiqueta($e['estado']))) ?></span></td>
        <td class="text-gris"><?= h($e['creado_por_nombre']) ?></td>
        <td class="whitespace-nowrap text-right">
          <?php if (App\Core\Auth::puedeEditar($u, $e)): ?><a class="text-xs font-semibold text-azul hover:underline" href="<?= h(url('eventos/editar', ['id' => $e['id']])) ?>"><?= h(t('Editar')) ?></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$lista['filas']): ?><tr><td colspan="8" class="py-10 text-center text-gris"><?= h(t('No hay eventos con esos filtros.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($lista['paginas'] > 1): ?>
<nav class="mt-4 flex items-center justify-between text-sm">
  <span class="text-gris"><?= h(t('Página :n de :total', ['n' => $lista['pagina'], 'total' => $lista['paginas']])) ?></span>
  <div class="flex gap-2">
    <?php if ($lista['pagina'] > 1): ?><a class="btn-secundario" href="<?= h(url('eventos', $qs + ['pagina' => $lista['pagina'] - 1])) ?>">‹ <?= h(t('Anterior')) ?></a><?php endif; ?>
    <?php if ($lista['pagina'] < $lista['paginas']): ?><a class="btn-secundario" href="<?= h(url('eventos', $qs + ['pagina' => $lista['pagina'] + 1])) ?>"><?= h(t('Siguiente')) ?> ›</a><?php endif; ?>
  </div>
</nav>
<?php endif; ?>
