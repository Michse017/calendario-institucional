<?php
use App\Core\Campos;
use App\Core\View;
$color = $ev['area_color'] ?: Campos::COLOR_NEUTRO;
$cancelado = $ev['estado'] === 'cancelado';
$enlace = static function (string $v): string {
    if ($v === 'N/A' || $v === 'Pendiente') {
        return '<span class="text-gris">' . h($v) . '</span>';
    }
    // Solo se convierte en enlace lo que DE VERDAD es http(s). Contactos admite
    // texto libre, y construir un href con cualquier cosa dejaría pulsable un
    // 'javascript:...'. Lo que no sea enlace se muestra tal cual.
    if (preg_match('~^https?://[^\s]+$~i', $v) && filter_var($v, FILTER_VALIDATE_URL) !== false) {
        return '<a class="font-semibold text-azul hover:underline" target="_blank" rel="noopener" href="' . h($v) . '">' . h(t('Abrir enlace')) . ' ↗</a>';
    }
    return h($v);
};
?>
<div class="h-1.5" style="background:<?= h($cancelado ? Campos::ESTADO_COLOR['cancelado'] : $color) ?>"></div>
<div class="p-5">
  <div class="mb-3 flex flex-wrap items-center gap-2">
    <span class="chip" style="background:color-mix(in srgb,<?= h($color) ?> 14%,transparent);color:<?= h($color) ?>"><?= h(t($ev['area'])) ?></span>
    <span class="badge-estado" style="--c:<?= estado_color($ev['estado']) ?>"><?= h(t(estado_etiqueta($ev['estado']))) ?></span>
    <?php if (!empty($ev['requiere_cubrimiento'])): ?>
    <span class="badge-estado" style="--c:#1F3F7A" title="<?= h(t('Este evento pide cubrimiento')) ?>">⚑ <?= h(t('Pide cubrimiento')) ?></span>
    <?php else: ?>
    <span class="badge-estado" style="--c:#8A8F98" title="<?= h(t('Este evento no pide cubrimiento')) ?>"><?= h(t('No pide cubrimiento')) ?></span>
    <?php endif; ?>
  </div>
  <h2 class="font-serif text-2xl leading-tight<?= $cancelado ? ' line-through text-gris' : '' ?>"><?= h($ev['nombre']) ?></h2>
  <p class="mt-1 text-sm text-gris"><?= h(dia_semana_corto($ev['fecha_inicio'])) ?> <?= h(rango_fechas($ev['fecha_inicio'], $ev['fecha_fin'])) ?> · <?= h($ev['ciudad']) ?>, <?= h($ev['pais']) ?></p>
  <?php if ($cancelado): ?>
  <div class="mt-3 rounded-lg border border-[#8A8F98]/40 bg-[#8A8F98]/10 px-3 py-2 text-sm"><strong><?= h(t('Cancelado')) ?></strong><?= ($ev['cancelacion_motivo'] ?? '') !== '' ? ' · ' . h($ev['cancelacion_motivo']) : '' ?></div>
  <?php endif; ?>

  <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
    <?php foreach (['tipo_accion', 'segmento', 'mercado', 'organizador', 'linea_estrategica'] as $c): ?>
    <div class="cro-ficha-campo <?= $c === 'linea_estrategica' ? 'col-span-2' : '' ?>">
      <dt class="cro-ficha-rotulo"><?= h(t(Campos::ETIQUETA[$c])) ?></dt>
      <dd class="cro-ficha-dato"><?= h($c === 'mercado' && !empty($ev['mercados']) ? implode(', ', array_map('t', explode(' | ', (string) $ev['mercados']))) : t(catalogo_mostrar($ev, $c))) ?></dd>
    </div>
    <?php endforeach; ?>
  </dl>

  <div class="mt-4 space-y-3 text-sm">
    <?php foreach (['objetivo', 'resultados', 'alianzas', 'observaciones'] as $t): ?>
    <details <?= $t === 'objetivo' ? 'open' : '' ?> class="group rounded-lg border border-borde p-3 dark:border-noche-borde">
      <summary class="cursor-pointer text-[11px] font-bold uppercase tracking-[0.08em] text-gris"><?= h(t(Campos::ETIQUETA[$t])) ?></summary>
      <p class="mt-2 whitespace-pre-line leading-relaxed"><?= h($ev[$t]) ?></p>
    </details>
    <?php endforeach; ?>
  </div>

  <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
    <div class="cro-ficha-campo"><dt class="cro-ficha-rotulo"><?= h(t('Contactos')) ?></dt><dd class="cro-ficha-dato"><?= $enlace($ev['contactos_url']) ?></dd></div>
    <div class="cro-ficha-campo"><dt class="cro-ficha-rotulo"><?= h(t('Aforo estimado')) ?></dt><dd class="cro-ficha-dato"><?= h($ev['reuniones']) ?></dd></div>
    <div class="cro-ficha-campo"><dt class="cro-ficha-rotulo"><?= h(t('Evidencia')) ?></dt><dd class="cro-ficha-dato"><?= $enlace($ev['evidencia_url']) ?></dd></div>
  </dl>

  <dl class="mt-5 text-sm">
    <dt class="cro-ficha-rotulo"><?= h(t('Responsable')) ?></dt>
    <dd class="cro-ficha-dato"><?= h($ev['dueno_nombre'] ?? '—') ?></dd>
  </dl>

  <p class="mt-2 text-xs text-gris"><?= h(t('Creado por')) ?> <?= h($ev['creado_por_nombre']) ?> · <?= h(fecha_humana(substr($ev['creado_en'], 0, 10))) ?><?= $ev['actualizado_en'] !== $ev['creado_en'] ? ' · ' . h(t('editado')) . ' ' . h(fecha_humana(substr($ev['actualizado_en'], 0, 10))) : '' ?></p>

  <?php if ($puedeEditar): ?>
  <div class="mt-4 flex gap-2">
    <a href="<?= h(url('eventos/editar', ['id' => (int) $ev['id']])) ?>" class="btn-primario flex-1"><?= h(t('Editar')) ?></a>
    <?php if ($cancelado): ?>
    <form method="post" action="<?= h(url('eventos/reanudar', ['id' => (int) $ev['id']])) ?>" data-confirmar="<?= h(t('¿Reanudar este evento? Volverá al estado que tenía antes de cancelarse.')) ?>">
      <?= csrf_campo() ?>
      <button type="submit" class="btn-secundario"><?= h(t('Reanudar')) ?></button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!$cancelado): ?>
  <!-- Cambio rápido de cubrimiento, calcado del de estado: un POST y de vuelta al calendario. -->
  <form method="post" action="<?= h(url('eventos/cubrimiento', ['id' => (int) $ev['id']])) ?>" class="mt-4 rounded-xl border border-borde p-3 dark:border-noche-borde">
    <?= csrf_campo() ?>
    <p class="label mb-1.5"><?= h(t('Cubrimiento')) ?></p>
    <div class="flex flex-wrap gap-1.5">
      <?php $pide = !empty($ev['requiere_cubrimiento']); ?>
      <button type="submit" name="valor" value="1" class="badge-estado cro-estado-btn border<?= $pide ? ' font-bold cursor-not-allowed' : ' border-transparent' ?>"
              style="--c:#1F3F7A<?= $pide ? ';border-color:#1F3F7A' : '' ?>"<?= $pide ? ' disabled aria-current="true"' : '' ?>
              title="<?= $pide ? h(t('Ya pide cubrimiento')) : h(t('Marcar que pide cubrimiento')) ?>">⚑ <?= h(t('Pide cubrimiento')) ?></button>
      <button type="submit" name="valor" value="0" class="badge-estado cro-estado-btn border<?= !$pide ? ' font-bold cursor-not-allowed' : ' border-transparent' ?>"
              style="--c:#8A8F98<?= !$pide ? ';border-color:#8A8F98' : '' ?>"<?= !$pide ? ' disabled aria-current="true"' : '' ?>
              title="<?= !$pide ? h(t('No pide cubrimiento')) : h(t('Marcar que no pide cubrimiento')) ?>"><?= h(t('No pide')) ?></button>
    </div>
    <p class="mt-2 text-xs text-gris"><?= h(t('Quién va se decide el día del evento. Queda en el historial.')) ?></p>
  </form>
  <form method="post" action="<?= h(url('eventos/estado', ['id' => (int) $ev['id']])) ?>" class="mt-4 rounded-xl border border-borde p-3 dark:border-noche-borde">
    <?= csrf_campo() ?>
    <p class="label mb-1.5"><?= h(t('Cambiar estado')) ?></p>
    <div class="flex flex-wrap gap-1.5">
      <?php foreach (Campos::ESTADOS_FORMULARIO as $k => $et): ?>
      <?php $actual = $ev['estado'] === $k; ?>
      <button type="submit" name="estado" value="<?= h($k) ?>" class="badge-estado cro-estado-btn border<?= $actual ? ' font-bold cursor-not-allowed' : ' border-transparent' ?>"
              style="--c:<?= Campos::ESTADO_COLOR[$k] ?><?= $actual ? ';border-color:' . Campos::ESTADO_COLOR[$k] : '' ?>"<?= $actual ? ' disabled aria-current="true"' : '' ?>
              title="<?= $actual ? h(t('Estado actual')) : h(t('Marcar como :estado', ['estado' => t($et)])) ?>"><?= h(t($et)) ?></button>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 text-xs text-gris"><?= h(t('Solo cambia el estado; queda en el historial del evento.')) ?></p>
  </form>
  <?php endif; ?>
  <?php if (!$cancelado): ?>
  <div class="mt-3"><?= View::parcial('eventos/_cancelar', ['id' => (int) $ev['id']]) ?></div>
  <?php endif; ?>
  <div class="mt-3"><?= View::parcial('eventos/_eliminar', ['id' => (int) $ev['id']]) ?></div>
  <?php else: ?>
  <p class="mt-4 text-xs text-gris"><?= h(t('Solo el área :area (o un administrador) puede editar este evento.', ['area' => t($ev['area'])])) ?></p>
  <?php endif; ?>
</div>
