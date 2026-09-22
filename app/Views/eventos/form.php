<?php
use App\Core\Campos;
use App\Core\Idioma;
use App\Core\View;

$v = static fn(string $k): string => (string) ($valores[$k] ?? '');
/**
 * Alta por pasos.
 *
 * Editar sigue mostrando el formulario entero: quien edita viene a cambiar un
 * campo concreto, no a recorrer cuatro pantallas. De ahí que el paso 0
 * signifique "enséñalo todo".
 */
$PASOS   = 4;
$TITULOS = [t('Cuándo y qué'), t('Dónde y quién'), t('Seguimiento'), t('Contactos y evidencia')];
$campoPaso = [
    'nombre' => 1, 'fecha_inicio' => 1, 'fecha_fin' => 1, 'estado' => 1,
    'tipo_accion' => 1, 'tipo_accion_otro' => 1, 'segmento' => 1, 'segmento_otro' => 1,
    'pais' => 2, 'ciudad' => 2, 'mercado' => 2, 'organizador' => 2,
    'area' => 2, 'linea_estrategica' => 2, 'dueno_id' => 2, 'requiere_cubrimiento' => 2,
    'objetivo' => 3, 'resultados' => 3, 'alianzas' => 3, 'observaciones' => 3,
    'contactos_url' => 4, 'evidencia_url' => 4, 'reuniones' => 4,
];
$pasoInicial = $modo === 'crear' ? 1 : 0;
if ($pasoInicial === 1) {
    // Si el servidor devolvió pegas, se abre en el primer paso que las tenga:
    // dejar a alguien en el paso 1 con el error en el 4 es mandarlo a buscar.
    $conPega = array_intersect_key($campoPaso, ($errores ?: []) + ($sugerencias ?: []));
    if ($conPega) {
        $pasoInicial = min($conPega);
    }
}
// Lo que pinta el JavaScript del formulario viaja ya traducido en su configuración.
$cfgForm = [
    'inicio'  => $v('fecha_inicio'), 'fin' => $v('fecha_fin'),
    'id'      => (int) ($valores['id'] ?? 0),
    'idioma'  => Idioma::actual(),
    'paso'    => $pasoInicial, 'pasos' => $PASOS, 'titulos' => $TITULOS,
    'txt'     => [
        'el'  => t('el'), 'del' => t('del'), 'al' => t('al'),
        'repetidoUno'    => t('Ya existe un evento con este mismo nombre:'),
        'repetidoVarios' => t('Ya existen :n eventos con este mismo nombre:'),
        'sinArea'        => t('Sin área'),
        'faltaRellenar'  => t('Falta rellenar «:campo».'),
        'consejoNA'      => t('Si no aplica, márcalo con el botón N/A.'),
        'faltanCampos'   => t('Faltan campos obligatorios por rellenar.'),
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
<form method="post" action="<?= h($accion) ?>" class="mx-auto max-w-4xl" x-data="formularioEvento(<?= h(json_encode($cfgForm)) ?>)" @submit="enviar($event)"
      @keydown.enter="if (paso > 0 && paso < pasos && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); siguiente(); }"<?= $modo === 'crear' ? ' novalidate' : '' ?>>
  <?= csrf_campo() ?>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h($modo === 'crear' ? t('Nuevo evento') : t('Editar evento')) ?></p>
      <h1 class="titulo"><?= $modo === 'crear' ? h(t('Registrar un evento')) : h($v('nombre')) ?></h1>
    </div>
    <div class="flex gap-2">
      <a href="<?= h(url('calendario')) ?>" class="btn-secundario"><?= h(t('Volver')) ?></a>
      <button type="submit" class="btn-primario" x-show="paso === 0 || paso === pasos" @click="enviar($event)" :disabled="enviando"><?= h(t('Guardar')) ?></button>
    </div>
  </div>

  <!-- Barra de pasos: es SOLO una guía visual. No se puede pulsar a propósito;
       se avanza y se retrocede con Siguiente y Atrás, que son los que validan.
       Por eso son <li> y no <button>: lo que no se puede usar, no debe
       parecer que se puede. -->
  <ol class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4" x-show="paso > 0" aria-label="<?= h(t('Progreso del formulario')) ?>">
    <template x-for="n in pasos" :key="n">
      <li class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-left text-xs font-semibold transition"
          :class="n === paso ? 'border-azul bg-azul text-white'
                 : (n < paso ? 'border-azul/40 bg-azul-claro text-azul'
                 : 'border-borde bg-white text-gris dark:border-noche-borde dark:bg-noche-2')"
          :aria-current="n === paso ? 'step' : false">
        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px]"
              :class="n === paso ? 'bg-white/20' : (n < paso ? 'bg-azul/15' : 'bg-[#EEECE6] dark:bg-[#232834]')"
              x-text="n < paso ? '✓' : n"></span>
        <span class="truncate" x-text="titulos[n - 1]"></span>
      </li>
    </template>
  </ol>

  <!-- Lo que falta, dicho en la página. El globo del navegador se pierde al
       cambiar de paso y entonces parece que el botón no hace nada. -->
  <div x-show="aviso" x-cloak role="alert"
       class="mb-6 rounded-xl border border-peligro/30 bg-peligro/10 px-4 py-3 text-sm text-peligro"
       x-text="aviso"></div>

  <?php if ($sugerencias): ?>
  <div class="mb-6 rounded-xl border border-estado-ambar/40 bg-estado-ambar/10 px-4 py-3 text-sm"><strong><?= h(t('Un momento')) ?>:</strong> <?= h(t('algunos valores se parecen a otros que ya existen. Revisa las sugerencias marcadas; puedes usar el existente o mantener el tuyo y guardar de nuevo.')) ?></div>
  <?php elseif ($errores): ?>
  <div class="mb-6 rounded-xl border border-peligro/30 bg-peligro/10 px-4 py-3 text-sm text-peligro"><?= h(t('Revisa los campos marcados. Todos son obligatorios.')) ?> <b>N/A</b> <?= h(t('solo vale en la sección de seguimiento; en el resto hay que elegir o escribir un valor real.')) ?></div>
  <?php endif; ?>

  <?php if ($cancelado): ?>
  <div class="mb-6 rounded-xl border border-[#8A8F98]/40 bg-[#8A8F98]/10 px-4 py-3 text-sm"><strong><?= h(t('Evento cancelado')) ?></strong><?= $motivo !== '' ? ' · ' . h($motivo) : '' ?>. <?= h(t('Puedes corregir datos; para volver a activarlo usa "Reanudar evento".')) ?></div>
  <?php endif; ?>

  <section class="card p-6" data-paso="1" x-show="paso === 0 || paso === 1">
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

  <section class="card mt-6 p-6" data-paso="2" x-show="paso === 0 || paso === 2">
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
      <div>
        <label class="label mb-0.5" for="dueno_id"><?= h(t('Responsable del evento')) ?></label>
        <p class="mb-1.5 text-[11px] leading-snug text-gris"><?= h(t('Quién responde por este evento. Puede ser otra persona del área, no necesariamente quien lo crea.')) ?></p>
        <select class="input" id="dueno_id" name="dueno_id" required<?= $aria('dueno_id') ?>>
          <?php foreach ($duenos as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $duenoActual ? 'selected' : '' ?>><?= h($d['nombre']) ?><?= ($d['area_nombre'] ?? '') !== '' ? ' · ' . h(t($d['area_nombre'])) : ($d['rol'] === 'admin' ? ' · ' . h(t('Administrador')) : '') ?></option>
          <?php endforeach; ?>
          <?php if (!$duenos): ?><option value=""><?= h(t('No hay usuarios en esta área')) ?></option><?php endif; ?>
        </select>
        <?= $err('dueno_id') ?>
      </div>
      <?= $lista('linea_estrategica') ?>
      <div class="md:col-span-2" x-data="{ pide: <?= $cubrimiento ? 'true' : 'false' ?> }">
        <span class="label mb-0.5"><?= h(t('¿Necesita cubrimiento?')) ?></span>
        <p class="mb-1.5 text-[11px] leading-snug text-gris"><?= h(t('Márcalo si hace falta que alguien del área esté presente. Quién va se decide el día del evento.')) ?></p>
        <input type="hidden" name="requiere_cubrimiento" :value="pide ? '1' : '0'">
        <div class="flex flex-wrap gap-1.5">
          <button type="button" class="badge-estado cro-estado-btn border" :class="pide ? 'font-bold' : 'border-transparent'"
                  :style="'--c:#1F3F7A' + (pide ? ';border-color:#1F3F7A' : '')" :aria-pressed="pide" @click="pide = true">⚑ <?= h(t('Sí, pide cubrimiento')) ?></button>
          <button type="button" class="badge-estado cro-estado-btn border" :class="!pide ? 'font-bold' : 'border-transparent'"
                  :style="'--c:#8A8F98' + (!pide ? ';border-color:#8A8F98' : '')" :aria-pressed="!pide" @click="pide = false"><?= h(t('No')) ?></button>
        </div>
      </div>
    </div>
  </section>

  <section class="card mt-6 p-6" data-paso="3" x-show="paso === 0 || paso === 3">
    <h2 class="mb-1 font-serif text-xl">3 · <?= h(t('Seguimiento')) ?></h2>
    <p class="mb-4 text-xs text-gris"><?= h(t('Esta sección y la siguiente son las únicas donde vale')) ?> <b>N/A</b> <?= h(t('si algo no aplica, o')) ?> <b><?= h(t('Pendiente')) ?></b> <?= h(t('si todavía no se sabe: pulsa los botones que hay junto al nombre de cada campo y se rellena solo.')) ?></p>
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
    </div>
  </section>

  <section class="card mt-6 p-6" data-paso="4" x-show="paso === 0 || paso === 4">
    <h2 class="mb-1 font-serif text-xl">4 · <?= h(t('Contactos y evidencia')) ?></h2>
    <p class="mb-4 text-xs text-gris"><?= h(t('Aquí también valen')) ?> <b>N/A</b> <?= h(t('y')) ?> <b><?= h(t('Pendiente')) ?></b> <?= h(t('con los botones de cada campo. En Contactos y Aforo el campo propone opciones al escribir, pero puedes poner lo que necesites. Evidencia sigue siendo un enlace.')) ?></p>
    <div class="grid gap-4 md:grid-cols-2">
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
      <!-- "Volver" solo al editar: junto a "Atrás" las dos se confunden, y arriba
           ya hay un "Volver" para salirse del formulario. -->
      <a href="<?= h(url('calendario')) ?>" class="btn-secundario" x-show="paso === 0"><?= h(t('Volver')) ?></a>
      <button type="button" class="btn-secundario" x-show="paso > 1" @click="atras()">← <?= h(t('Atrás')) ?></button>
      <button type="button" class="btn-primario" x-show="paso > 0 && paso < pasos" @click="siguiente()"><?= h(t('Siguiente')) ?> →</button>
      <button type="submit" class="btn-primario" x-show="paso === 0 || paso === pasos" @click="enviar($event)" :disabled="enviando"><?= h(t('Guardar')) ?></button>
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
