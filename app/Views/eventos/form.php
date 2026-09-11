<?php
use App\Core\Campos;
use App\Core\View;

$v = static fn(string $k): string => (string) ($valores[$k] ?? '');
$err = static fn(string $k): string => isset($errores[$k]) ? '<p class="error-campo" id="err-' . $k . '">' . h($errores[$k]) . '</p>' : '';
$aria = static fn(string $k): string => isset($errores[$k]) ? ' aria-describedby="err-' . $k . '"' : '';
$desc = static fn(string $k): string => isset(Campos::DESCRIPCION[$k])
    ? '<p class="mb-1.5 text-[11px] leading-snug text-gris">' . h(Campos::DESCRIPCION[$k]) . '</p>' : '';
/** Campo abierto con autocompletado (país, ciudad, organizador). */
$catalogo = static fn(string $campo): string => View::parcial('eventos/_campo_catalogo', [
    'campo' => $campo, 'valor' => (string) ($valores[$campo] ?? ''), 'error' => $errores[$campo] ?? null, 'sugerencias' => $sugerencias[$campo] ?? [],
]);
/** Campo de lista cerrada (tipo de acción, segmento, área, línea estratégica, mercado). */
$lista = static function (string $campo) use ($valores, $errores, $opciones): string {
    $colOtro = Campos::COLUMNA_OTRO[$campo] ?? '';
    return View::parcial('eventos/_campo_lista', [
        'campo'     => $campo,
        'valor'     => (string) ($valores[$campo] ?? ''),
        'error'     => $errores[$campo] ?? null,
        'detalle'   => $colOtro !== '' ? (string) ($valores[$colOtro] ?? '') : '',
        'errorOtro' => $colOtro !== '' ? ($errores[$colOtro] ?? null) : null,
        'opciones'  => $opciones[$campo] ?? [],
    ]);
};
?>
<form method="post" action="<?= h($accion) ?>" class="mx-auto max-w-4xl" x-data="formularioEvento(<?= h(json_encode(['inicio' => $v('fecha_inicio'), 'fin' => $v('fecha_fin')])) ?>)" @submit="enviando = true">
  <?= csrf_campo() ?>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= $modo === 'crear' ? 'Nuevo evento' : 'Editar evento' ?></p>
      <h1 class="titulo"><?= $modo === 'crear' ? 'Registrar un evento' : h($v('nombre')) ?></h1>
    </div>
    <div class="flex gap-2">
      <a href="<?= h(url('calendario')) ?>" class="btn-secundario">Volver</a>
      <button type="submit" class="btn-primario" :disabled="enviando">Guardar</button>
    </div>
  </div>

  <?php if ($sugerencias): ?>
  <div class="mb-6 rounded-xl border border-estado-ambar/40 bg-estado-ambar/10 px-4 py-3 text-sm"><strong>Un momento:</strong> algunos valores se parecen a otros que ya existen. Revisa las sugerencias marcadas; puedes usar el existente o mantener el tuyo y guardar de nuevo.</div>
  <?php elseif ($errores): ?>
  <div class="mb-6 rounded-xl border border-peligro/30 bg-peligro/10 px-4 py-3 text-sm text-peligro">Revisa los campos marcados. Todos son obligatorios. <b>N/A</b> solo vale en la sección de seguimiento; en el resto hay que elegir o escribir un valor real.</div>
  <?php endif; ?>

  <?php if ($cancelado): ?>
  <div class="mb-6 rounded-xl border border-[#8A8F98]/40 bg-[#8A8F98]/10 px-4 py-3 text-sm"><strong>Evento cancelado</strong><?= $motivo !== '' ? ' · ' . h($motivo) : '' ?>. Puedes corregir datos; para volver a activarlo usa "Reanudar evento".</div>
  <?php endif; ?>

  <section class="card p-6">
    <h2 class="mb-4 font-serif text-xl">1 · Cuándo y qué</h2>
    <div class="grid gap-4 md:grid-cols-6">
      <div class="md:col-span-6">
        <label class="label mb-0.5" for="nombre"><?= h(Campos::ETIQUETA['nombre']) ?></label>
        <?= $desc('nombre') ?>
        <input class="input" id="nombre" name="nombre" value="<?= h($v('nombre')) ?>" maxlength="200" required autofocus<?= $aria('nombre') ?>>
        <?= $err('nombre') ?>
      </div>
      <div class="md:col-span-2">
        <label class="label mb-0.5" for="fecha_inicio"><?= h(Campos::ETIQUETA['fecha_inicio']) ?></label>
        <?= $desc('fecha_inicio') ?>
        <input class="input" type="date" id="fecha_inicio" name="fecha_inicio" x-model="inicio" @change="if (fin < inicio) fin = inicio" required<?= $aria('fecha_inicio') ?>>
        <?= $err('fecha_inicio') ?>
      </div>
      <div class="md:col-span-2">
        <label class="label mb-0.5" for="fecha_fin"><?= h(Campos::ETIQUETA['fecha_fin']) ?></label>
        <?= $desc('fecha_fin') ?>
        <input class="input" type="date" id="fecha_fin" name="fecha_fin" x-model="fin" :min="inicio" required<?= $aria('fecha_fin') ?>>
        <?= $err('fecha_fin') ?>
      </div>
      <div class="md:col-span-2">
        <span class="label mb-0.5"><?= h(Campos::ETIQUETA['estado']) ?></span>
        <?= $desc('estado') ?>
        <?php if ($cancelado): ?>
          <input type="hidden" name="estado" value="cancelado">
          <div class="rounded-xl border border-borde bg-[#F0EEE8] px-3 py-2 text-sm dark:border-noche-borde dark:bg-noche">
            <span class="badge-estado" style="--c:<?= Campos::ESTADO_COLOR['cancelado'] ?>">Cancelado</span>
            <span class="ml-2 text-xs text-gris">Se reanuda desde el botón de abajo.</span>
          </div>
        <?php else: ?>
          <div class="flex gap-1 rounded-xl border border-borde bg-white p-1 dark:border-noche-borde dark:bg-noche">
            <?php foreach (Campos::ESTADOS_FORMULARIO as $k => $et): ?>
            <label class="flex-1 cursor-pointer rounded-lg px-2 py-1.5 text-center text-xs font-semibold has-checked:bg-azul has-checked:text-white">
              <input type="radio" name="estado" value="<?= $k ?>" class="sr-only" <?= $v('estado') === $k ? 'checked' : '' ?><?= $aria('estado') ?>><?= h($et) ?>
            </label>
            <?php endforeach; ?>
          </div>
          <?= $err('estado') ?>
        <?php endif; ?>
      </div>
      <div class="md:col-span-3"><?= $lista('tipo_accion') ?></div>
      <div class="md:col-span-3"><?= $lista('segmento') ?></div>
    </div>
  </section>

  <section class="card mt-6 p-6">
    <h2 class="mb-4 font-serif text-xl">2 · Dónde y quién</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <?= $catalogo('pais') ?>
      <?= $catalogo('ciudad') ?>
      <?= $lista('mercado') ?>
      <?= $catalogo('organizador') ?>
      <?php if ($esAdmin): ?>
        <?= $lista('area') ?>
      <?php else: ?>
        <div>
          <span class="label mb-0.5"><?= h(Campos::ETIQUETA['area']) ?></span>
          <?= $desc('area') ?>
          <input type="hidden" name="area" value="<?= h($areaUsuario) ?>">
          <div class="input bg-[#F0EEE8] text-gris dark:bg-noche"><?= h($areaUsuario !== '' ? $areaUsuario : 'Sin área asignada') ?></div>
        </div>
      <?php endif; ?>
      <?= $lista('linea_estrategica') ?>
    </div>
  </section>

  <section class="card mt-6 p-6">
    <h2 class="mb-1 font-serif text-xl">3 · Seguimiento</h2>
    <p class="mb-4 text-xs text-gris">Es la única sección donde vale <b>N/A</b> si algo no aplica, o <b>Pendiente</b> si todavía no se sabe: pulsa los botones que hay junto al nombre de cada campo y se rellena solo.</p>
    <div class="grid gap-4 md:grid-cols-2">
      <?php foreach (['objetivo' => 3, 'resultados' => 3, 'alianzas' => 2, 'observaciones' => 3] as $t => $filas): ?>
      <?php $autoNa = in_array($t, Campos::AUTO_NA, true); ?>
      <div x-data="{ v: <?= h(json_encode($v($t))) ?> }">
        <div class="flex items-center justify-between">
          <label class="label mb-0.5" for="<?= $t ?>"><?= h(Campos::ETIQUETA[$t]) ?></label>
          <button type="button" class="cro-atajo mb-0.5" :class="{ 'cro-atajo-activo': v === 'N/A', 'cro-atajo-pista': !v }" title="Marcar este campo como «no aplica»" @click="v = 'N/A'">N/A</button>
        </div>
        <?= $desc($t) ?>
        <textarea class="input" id="<?= $t ?>" name="<?= $t ?>" rows="<?= $filas ?>" x-model="v" maxlength="5000"<?= $autoNa ? ' @blur="if (v.trim() === \'\') v = \'N/A\'"' : ' required' ?><?= $aria($t) ?>></textarea>
        <?= $err($t) ?>
      </div>
      <?php endforeach; ?>
      <?php foreach (['contactos_url', 'evidencia_url', 'reuniones'] as $t): ?>
      <div x-data="{ v: <?= h(json_encode($v($t))) ?> }">
        <div class="flex items-center justify-between">
          <label class="label mb-0.5" for="<?= $t ?>"><?= h(Campos::ETIQUETA[$t]) ?></label>
          <span class="mb-0.5 flex gap-2">
            <button type="button" class="cro-atajo" :class="{ 'cro-atajo-activo': v === 'Pendiente', 'cro-atajo-pista': !v }" title="Todavía no se sabe" @click="v = 'Pendiente'">Pendiente</button>
            <button type="button" class="cro-atajo cro-atajo-tarde" :class="{ 'cro-atajo-activo': v === 'N/A', 'cro-atajo-pista': !v }" title="Marcar este campo como «no aplica»" @click="v = 'N/A'">N/A</button>
          </span>
        </div>
        <?= $desc($t) ?>
        <input class="input" id="<?= $t ?>" name="<?= $t ?>" x-model="v" placeholder="<?= $t === 'reuniones' ? 'Número, N/A o Pendiente' : 'https://…, N/A o Pendiente' ?>" required<?= $aria($t) ?>>
        <?= $err($t) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
    <?php if ($modo === 'editar'): ?>
      <div class="flex flex-wrap items-center gap-2">
        <?php if ($cancelado): ?>
          <button type="submit" form="reanudar-evento" class="btn-secundario">Reanudar evento</button>
        <?php else: ?>
          <button type="button" class="btn-secundario" @click="const d = document.getElementById('cancelar-evento'); d.open = true; d.scrollIntoView({behavior: 'smooth', block: 'center'})">Cancelar evento…</button>
        <?php endif; ?>
        <button type="button" class="btn-peligro" @click="const d = document.getElementById('eliminar-evento'); d.open = true; d.scrollIntoView({behavior: 'smooth', block: 'center'})">Eliminar evento…</button>
      </div>
    <?php else: ?><span></span><?php endif; ?>
    <div class="flex gap-2">
      <a href="<?= h(url('calendario')) ?>" class="btn-secundario">Volver</a>
      <button type="submit" class="btn-primario" :disabled="enviando">Guardar</button>
    </div>
  </div>
</form>
<?php if ($modo === 'editar'): ?>
<?php $idEv = (int) ($valores['id'] ?? 0); ?>
<div class="mx-auto mt-6 max-w-4xl"><?= View::parcial('eventos/_eliminar', ['id' => $idEv]) ?></div>
<?php if ($cancelado): ?>
<form id="reanudar-evento" method="post" action="<?= h(url('eventos/reanudar', ['id' => $idEv])) ?>"><?= csrf_campo() ?></form>
<?php else: ?>
<div class="mx-auto mt-6 max-w-4xl"><?= View::parcial('eventos/_cancelar', ['id' => $idEv]) ?></div>
<?php endif; ?>
<?php endif; ?>
