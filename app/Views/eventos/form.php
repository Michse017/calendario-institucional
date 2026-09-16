<?php
use App\Core\Campos;
use App\Core\Idioma;
use App\Core\View;

$v = static fn(string $k): string => (string) ($valores[$k] ?? '');
// Lo que pinta el JavaScript del formulario viaja ya traducido en su configuración.
$cfgForm = [
    'inicio' => $v('fecha_inicio'), 'fin' => $v('fecha_fin'),
    'id'     => (int) ($valores['id'] ?? 0),
    'idioma' => Idioma::actual(),
    'txt'    => [
        'el'  => t('el'), 'del' => t('del'), 'al' => t('al'),
        'repetidoUno'    => t('Ya existe un evento con este mismo nombre:'),
        'repetidoVarios' => t('Ya existen :n eventos con este mismo nombre:'),
        'sinArea'        => t('Sin área'),
    ],
];
$err = static fn(string $k): string => isset($errores[$k]) ? '<p class="error-campo" id="err-' . $k . '">' . h(t($errores[$k])) . '</p>' : '';
$aria = static fn(string $k): string => isset($errores[$k]) ? ' aria-describedby="err-' . $k . '"' : '';
$desc = static fn(string $k): string => isset(Campos::DESCRIPCION[$k])
    ? '<p class="mb-1.5 text-[11px] leading-snug text-gris">' . h(t(Campos::DESCRIPCION[$k])) . '</p>' : '';
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
        // Múltiples (mercado): lo elegido, principal primero; si no hay lista, el valor simple que hubiera.
        'elegidos'  => in_array($campo, Campos::MULTIPLES, true) ? (array) ($valores['mercados'] ?? array_values(array_filter([(string) ($valores[$campo] ?? '')]))) : [],
    ]);
};
?>
<form method="post" action="<?= h($accion) ?>" class="mx-auto max-w-4xl" x-data="formularioEvento(<?= h(json_encode($cfgForm)) ?>)" @submit="enviando = true">
  <?= csrf_campo() ?>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h($modo === 'crear' ? t('Nuevo evento') : t('Editar evento')) ?></p>
      <h1 class="titulo"><?= $modo === 'crear' ? h(t('Registrar un evento')) : h($v('nombre')) ?></h1>
    </div>
    <div class="flex gap-2">
      <a href="<?= h(url('calendario')) ?>" class="btn-secundario"><?= h(t('Volver')) ?></a>
      <button type="submit" class="btn-primario" :disabled="enviando"><?= h(t('Guardar')) ?></button>
    </div>
  </div>

  <?php if ($sugerencias): ?>
  <div class="mb-6 rounded-xl border border-estado-ambar/40 bg-estado-ambar/10 px-4 py-3 text-sm"><strong><?= h(t('Un momento')) ?>:</strong> <?= h(t('algunos valores se parecen a otros que ya existen. Revisa las sugerencias marcadas; puedes usar el existente o mantener el tuyo y guardar de nuevo.')) ?></div>
  <?php elseif ($errores): ?>
  <div class="mb-6 rounded-xl border border-peligro/30 bg-peligro/10 px-4 py-3 text-sm text-peligro"><?= h(t('Revisa los campos marcados. Todos son obligatorios.')) ?> <b>N/A</b> <?= h(t('solo vale en la sección de seguimiento; en el resto hay que elegir o escribir un valor real.')) ?></div>
  <?php endif; ?>

  <?php if ($cancelado): ?>
  <div class="mb-6 rounded-xl border border-[#8A8F98]/40 bg-[#8A8F98]/10 px-4 py-3 text-sm"><strong><?= h(t('Evento cancelado')) ?></strong><?= $motivo !== '' ? ' · ' . h($motivo) : '' ?>. <?= h(t('Puedes corregir datos; para volver a activarlo usa "Reanudar evento".')) ?></div>
  <?php endif; ?>

  <section class="card p-6">
    <h2 class="mb-4 font-serif text-xl">1 · <?= h(t('Cuándo y qué')) ?></h2>
    <div class="grid gap-4 md:grid-cols-6">
      <div class="md:col-span-6">
        <label class="label mb-0.5" for="nombre"><?= h(t(Campos::ETIQUETA['nombre'])) ?></label>
        <?= $desc('nombre') ?>
        <input class="input" id="nombre" name="nombre" value="<?= h($v('nombre')) ?>" maxlength="200" required autofocus @input.debounce.500ms="mirarRepetidos($event.target.value)"<?= $aria('nombre') ?>>
        <?= $err('nombre') ?>
        <!-- Aviso de nombre repetido: informa, no impide. El porqué está
             explicado en Evento::mismoNombre(). -->
        <div class="cro-repetido" x-show="repetidos.length" x-cloak role="status">
          <p class="cro-repetido-tit" x-text="tituloRepetidos()"></p>
          <ul class="cro-repetido-lista">
            <template x-for="r in repetidos" :key="r.id">
              <li><span x-text="cuando(r)"></span> · <span x-text="r.area || txt.sinArea"></span></li>
            </template>
          </ul>
          <p class="cro-repetido-choque" x-show="chocaFecha" x-cloak><?= h(t('Uno de ellos cae en las mismas fechas que estás poniendo.')) ?></p>
          <p class="cro-repetido-pie"><?= h(t('Si el tuyo es distinto, sigue adelante sin problema. Esto es solo un aviso.')) ?></p>
        </div>
      </div>
      <div class="md:col-span-2">
        <label class="label mb-0.5" for="fecha_inicio"><?= h(t(Campos::ETIQUETA['fecha_inicio'])) ?></label>
        <?= $desc('fecha_inicio') ?>
        <input class="input" type="date" id="fecha_inicio" name="fecha_inicio" x-model="inicio" @change="if (fin < inicio) fin = inicio" required<?= $aria('fecha_inicio') ?>>
        <?= $err('fecha_inicio') ?>
      </div>
      <div class="md:col-span-2">
        <label class="label mb-0.5" for="fecha_fin"><?= h(t(Campos::ETIQUETA['fecha_fin'])) ?></label>
        <?= $desc('fecha_fin') ?>
        <input class="input" type="date" id="fecha_fin" name="fecha_fin" x-model="fin" :min="inicio" required<?= $aria('fecha_fin') ?>>
        <?= $err('fecha_fin') ?>
      </div>
      <div class="md:col-span-2">
        <span class="label mb-0.5"><?= h(t(Campos::ETIQUETA['estado'])) ?></span>
        <?= $desc('estado') ?>
        <?php if ($cancelado): ?>
          <input type="hidden" name="estado" value="cancelado">
          <div class="rounded-xl border border-borde bg-[#F0EEE8] px-3 py-2 text-sm dark:border-noche-borde dark:bg-noche">
            <span class="badge-estado" style="--c:<?= Campos::ESTADO_COLOR['cancelado'] ?>"><?= h(t('Cancelado')) ?></span>
            <span class="ml-2 text-xs text-gris"><?= h(t('Se reanuda desde el botón de abajo.')) ?></span>
          </div>
        <?php else: ?>
          <div class="flex gap-1 rounded-xl border border-borde bg-white p-1 dark:border-noche-borde dark:bg-noche">
            <?php foreach (Campos::ESTADOS_FORMULARIO as $k => $et): ?>
            <label class="flex-1 cursor-pointer rounded-lg px-2 py-1.5 text-center text-xs font-semibold has-checked:bg-azul has-checked:text-white">
              <input type="radio" name="estado" value="<?= $k ?>" class="sr-only" <?= $v('estado') === $k ? 'checked' : '' ?><?= $aria('estado') ?>><?= h(t($et)) ?>
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
    <h2 class="mb-4 font-serif text-xl">2 · <?= h(t('Dónde y quién')) ?></h2>
    <div class="grid gap-4 md:grid-cols-2">
      <?= $catalogo('pais') ?>
      <?= $catalogo('ciudad') ?>
      <?= $lista('mercado') ?>
      <?= $catalogo('organizador') ?>
      <?php if ($esAdmin): ?>
        <?= $lista('area') ?>
      <?php else: ?>
        <div>
          <span class="label mb-0.5"><?= h(t(Campos::ETIQUETA['area'])) ?></span>
          <?= $desc('area') ?>
          <input type="hidden" name="area" value="<?= h($areaUsuario) ?>">
          <div class="input bg-[#F0EEE8] text-gris dark:bg-noche"><?= h($areaUsuario !== '' ? t($areaUsuario) : t('Sin área asignada')) ?></div>
        </div>
      <?php endif; ?>
      <?= $lista('linea_estrategica') ?>
    </div>
  </section>

  <section class="card mt-6 p-6">
    <h2 class="mb-1 font-serif text-xl">3 · <?= h(t('Seguimiento')) ?></h2>
    <p class="mb-4 text-xs text-gris"><?= h(t('Es la única sección donde vale')) ?> <b>N/A</b> <?= h(t('si algo no aplica, o')) ?> <b><?= h(t('Pendiente')) ?></b> <?= h(t('si todavía no se sabe: pulsa los botones que hay junto al nombre de cada campo y se rellena solo.')) ?></p>
    <div class="grid gap-4 md:grid-cols-2">
      <?php foreach (['objetivo' => 3, 'resultados' => 3, 'alianzas' => 2, 'observaciones' => 3] as $t => $filas): ?>
      <?php $autoNa = in_array($t, Campos::AUTO_NA, true); ?>
      <div x-data="{ v: <?= h(json_encode($v($t))) ?> }">
        <div class="flex items-center justify-between">
          <label class="label mb-0.5" for="<?= $t ?>"><?= h(t(Campos::ETIQUETA[$t])) ?></label>
          <span class="mb-0.5 flex gap-2">
            <?php if ($t !== 'objetivo'): ?>
            <button type="button" class="cro-atajo" :class="{ 'cro-atajo-activo': v === 'Pendiente', 'cro-atajo-pista': !v }" title="<?= h(t('Todavía no se sabe')) ?>" @click="v = 'Pendiente'"><?= h(t('Pendiente')) ?></button>
            <?php endif; ?>
            <button type="button" class="cro-atajo<?= $t !== 'objetivo' ? ' cro-atajo-tarde' : '' ?>" :class="{ 'cro-atajo-activo': v === 'N/A', 'cro-atajo-pista': !v }" title="<?= h(t('Marcar este campo como «no aplica»')) ?>" @click="v = 'N/A'">N/A</button>
          </span>
        </div>
        <?= $desc($t) ?>
        <textarea class="input" id="<?= $t ?>" name="<?= $t ?>" rows="<?= $filas ?>" x-model="v" maxlength="5000"<?= $autoNa ? ' @blur="if (v.trim() === \'\') v = \'N/A\'"' : ' required' ?><?= $aria($t) ?>></textarea>
        <?= $err($t) ?>
      </div>
      <?php endforeach; ?>
      <?php
      /**
       * Sugerencias del desplegable. Es un <datalist>: propone, no obliga.
       * Se puede escribir cualquier otra cosa encima.
       * Evidencia no lleva: sigue siendo solo un enlace.
       */
      $SUGERIDO = [
          'contactos_url' => ['N/A', 'Pendiente', t('Pendiente (enlace al listado de contactos)'), t('Pendiente de consolidar al finalizar el evento')],
          'reuniones'     => ['N/A', 'Pendiente', t('Pendiente de consolidar al finalizar el evento'), t('Pendiente de confirmar con el organizador')],
      ];
      $PISTA = [
          'contactos_url' => t('Enlace, los contactos, N/A o en qué va'),
          'evidencia_url' => t('https://…, N/A o Pendiente'),
          'reuniones'     => t('Número, N/A o en qué va'),
      ];
      ?>
      <?php foreach (['contactos_url', 'evidencia_url', 'reuniones'] as $t): ?>
      <div x-data="{ v: <?= h(json_encode($v($t))) ?> }">
        <div class="flex items-center justify-between">
          <label class="label mb-0.5" for="<?= $t ?>"><?= h(t(Campos::ETIQUETA[$t])) ?></label>
          <span class="mb-0.5 flex gap-2">
            <button type="button" class="cro-atajo" :class="{ 'cro-atajo-activo': v === 'Pendiente', 'cro-atajo-pista': !v }" title="<?= h(t('Todavía no se sabe')) ?>" @click="v = 'Pendiente'"><?= h(t('Pendiente')) ?></button>
            <button type="button" class="cro-atajo cro-atajo-tarde" :class="{ 'cro-atajo-activo': v === 'N/A', 'cro-atajo-pista': !v }" title="<?= h(t('Marcar este campo como «no aplica»')) ?>" @click="v = 'N/A'">N/A</button>
          </span>
        </div>
        <?= $desc($t) ?>
        <input class="input" id="<?= $t ?>" name="<?= $t ?>" x-model="v" placeholder="<?= h($PISTA[$t]) ?>" maxlength="<?= $t === 'reuniones' ? 60 : 500 ?>" autocomplete="off"<?= isset($SUGERIDO[$t]) ? ' list="sug-' . $t . '"' : '' ?> required<?= $aria($t) ?>>
        <?php if (isset($SUGERIDO[$t])): ?>
        <datalist id="sug-<?= $t ?>"><?php foreach ($SUGERIDO[$t] as $s): ?><option value="<?= h($s) ?>"></option><?php endforeach; ?></datalist>
        <?php endif; ?>
        <?= $err($t) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
    <?php if ($modo === 'editar'): ?>
      <div class="flex flex-wrap items-center gap-2">
        <?php if ($cancelado): ?>
          <button type="submit" form="reanudar-evento" class="btn-secundario"><?= h(t('Reanudar evento')) ?></button>
        <?php else: ?>
          <button type="button" class="btn-secundario" @click="const d = document.getElementById('cancelar-evento'); d.open = true; d.scrollIntoView({behavior: 'smooth', block: 'center'})"><?= h(t('Cancelar evento…')) ?></button>
        <?php endif; ?>
        <button type="button" class="btn-peligro" @click="const d = document.getElementById('eliminar-evento'); d.open = true; d.scrollIntoView({behavior: 'smooth', block: 'center'})"><?= h(t('Eliminar evento…')) ?></button>
      </div>
    <?php else: ?><span></span><?php endif; ?>
    <div class="flex gap-2">
      <a href="<?= h(url('calendario')) ?>" class="btn-secundario"><?= h(t('Volver')) ?></a>
      <button type="submit" class="btn-primario" :disabled="enviando"><?= h(t('Guardar')) ?></button>
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
