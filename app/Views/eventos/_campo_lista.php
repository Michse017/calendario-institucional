<?php
/**
 * Campo de lista cerrada: solo vale un valor de las opciones existentes. Tres formas según el campo:
 *  - Normal: un desplegable. Los de Campos::CON_OTROS añaden "Otros", que revela un detalle obligatorio.
 *  - Buscable (Campos::BUSCABLES, listas largas como mercado): un buscador que filtra al escribir y
 *    devuelve el campo a su último valor válido si lo que quedó escrito no está en la lista.
 *  - Expandible (Campos::EXPANDIBLES, textos muy largos como la línea estratégica): el campo muestra
 *    un resumen con el código y el panel de abajo enseña cada opción completa.
 *  - Múltiple (Campos::MULTIPLES, procedencia del público): buscador + etiquetas; se eligen varios
 *    valores de la lista. El primero viaja como name="<campo>" (principal) y el resto en <campo>s_extra[].
 *
 * @var string   $campo     @var string $valor    @var ?string $error
 * @var string   $detalle   detalle guardado cuando el valor es "Otros"
 * @var string[] $opciones  valores activos del catálogo, sin N/A ni Otros
 * @var string[] $elegidos  (múltiples) valores ya elegidos, el principal primero
 */
use App\Core\Campos;

$multiple   = in_array($campo, Campos::MULTIPLES, true);
$conOtros   = in_array($campo, Campos::CON_OTROS, true);
$buscable   = in_array($campo, Campos::BUSCABLES, true) && !$multiple;   // múltiple gana al buscable simple
$expandible = in_array($campo, Campos::EXPANDIBLES, true);
$colOtro    = Campos::COLUMNA_OTRO[$campo] ?? '';
$esOtros    = $valor === Campos::OTROS;
$fuera      = $valor !== '' && !$esOtros && !in_array($valor, $opciones, true);
$inicial    = $fuera ? '' : $valor;
$aria       = !empty($error) ? ' aria-describedby="err-' . $campo . '"' : '';
$suelto     = $buscable || $expandible || $multiple;   // estos necesitan posicionar su panel
// Solo dejamos como preseleccionados los que siguen existiendo en la lista.
$elegidosOk = $multiple ? array_values(array_intersect((array) ($elegidos ?? []), $opciones)) : [];
?>
<div class="<?= $suelto ? 'relative' : '' ?>"<?php
if ($multiple) {
    echo ' x-data="listaMultiple(' . h(json_encode(['elegidos' => $elegidosOk, 'opciones' => $opciones])) . ')" @click.outside="cerrar()"';
} elseif ($buscable) {
    echo ' x-data="listaCerrada(' . h(json_encode(['valor' => $inicial, 'opciones' => $opciones])) . ')" @click.outside="cerrar()"';
} elseif ($expandible) {
    echo ' x-data="listaExpandible(' . h(json_encode(['valor' => $inicial, 'opciones' => $opciones])) . ')" @click.outside="abierto = false"';
} else {
    echo ' x-data="{ v: ' . h(json_encode($inicial)) . ' }"';
} ?>>
  <label class="label mb-0.5" for="c-<?= $campo ?>"><?= h(t(Campos::ETIQUETA[$campo])) ?></label>
  <?php if (isset(Campos::DESCRIPCION[$campo])): ?>
  <p class="mb-1.5 text-[11px] leading-snug text-gris"><?= h(t(Campos::DESCRIPCION[$campo])) ?></p>
  <?php endif; ?>

  <?php if ($multiple): ?>
  <!-- Varios valores: el primero es el principal (name="<?= $campo ?>"); el resto van en
       <?= $campo ?>s_extra[]. El buscador visible exige al menos uno con :required. -->
  <template x-for="(m, i) in elegidos" :key="m">
    <input type="hidden" :name="i === 0 ? '<?= $campo ?>' : '<?= $campo ?>s_extra[]'" :value="m">
  </template>
  <div class="input flex flex-wrap items-center gap-1.5" @click="$refs.buscar.focus()">
    <template x-for="m in elegidos" :key="m">
      <span class="cro-chip-filtro" @click.stop="quitar(m)" :title="<?= h(json_encode(t('Quitar'))) ?> + ' ' + m">
        <span x-text="m"></span><span class="cro-chip-x" aria-hidden="true">✕</span>
      </span>
    </template>
    <input x-ref="buscar" id="c-<?= $campo ?>" class="min-w-[8rem] flex-1 border-0 bg-transparent p-0 text-sm outline-none"
           x-model="q" @focus="filtrar()" @click.stop="filtrar()" @input="filtrar()" @keydown="tecla($event)"
           @blur="cerrar()" autocomplete="off" maxlength="255"
           :placeholder="elegidos.length ? <?= h(json_encode(t('Añadir otro…'))) ?> : <?= h(json_encode(t('Escribe una procedencia y elígela…'))) ?>"
           :required="!elegidos.length"<?= $aria ?>>
  </div>
  <ul x-show="abierto && items.length" x-cloak x-transition.opacity class="card absolute z-20 mt-1 max-h-64 w-full overflow-auto p-1 text-sm">
    <template x-for="(it, i) in items" :key="it">
      <li>
        <button type="button" class="w-full rounded-lg px-3 py-2 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                :class="{'bg-[#F0EEE8] dark:bg-[#232834]': i === activo}" @mousedown.prevent="agregar(it)" @mousemove="activo = i" x-text="it"></button>
      </li>
    </template>
  </ul>

  <?php elseif ($expandible): ?>
  <input type="hidden" name="<?= $campo ?>" :value="valor">
  <button type="button" id="c-<?= $campo ?>" class="input cro-sel-btn" :class="{ 'cro-sel-vacio': !valor }"
          @click="abierto = !abierto" @keydown.escape="abierto = false" :aria-expanded="abierto ? 'true' : 'false'"<?= $aria ?>>
    <span class="cro-sel-cod" x-show="codigo(valor)" x-cloak x-text="codigo(valor)"></span>
    <span class="cro-sel-txt" x-text="valor ? sinCodigo(valor) : <?= h(json_encode(t('Elige una línea…'))) ?>"></span>
    <span class="cro-sel-flecha" :class="{ 'cro-sel-flecha-abierta': abierto }">⌄</span>
  </button>
  <div class="card cro-sel-panel" x-show="abierto" x-cloak x-transition.opacity>
    <template x-for="(o, i) in opciones" :key="i">
      <button type="button" class="cro-sel-op" :class="{ 'cro-sel-op-activa': o === valor }" @click="elegir(o)">
        <span class="cro-sel-cod" x-show="codigo(o)" x-text="codigo(o)"></span>
        <span x-text="sinCodigo(o)"></span>
      </button>
    </template>
  </div>
  <p class="cro-sel-preview" x-show="valor" x-cloak x-text="valor"></p>

  <?php elseif ($buscable): ?>
  <input class="input" id="c-<?= $campo ?>" name="<?= $campo ?>" x-model="valor" @focus="filtrar()" @click="filtrar()" @input="filtrar()"
         @keydown="tecla($event)" @blur="cerrar()" autocomplete="off" maxlength="255" placeholder="<?= h(t('Escribe para buscar en la lista…')) ?>" required<?= $aria ?>>
  <ul x-show="abierto" x-cloak x-transition.opacity class="card absolute z-20 mt-1 max-h-64 w-full overflow-auto p-1 text-sm">
    <template x-for="(it, i) in items" :key="it">
      <li>
        <button type="button" class="w-full rounded-lg px-3 py-2 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                :class="{'bg-[#F0EEE8] dark:bg-[#232834]': i === activo}" @mousedown.prevent="elegir(it)" @mousemove="activo = i" x-text="it"></button>
      </li>
    </template>
    <li x-show="!items.length" class="px-3 py-2 text-xs text-gris"><?= h(t('Nada coincide. Solo se puede elegir un valor de la lista.')) ?></li>
  </ul>

  <?php else: ?>
  <select class="input" id="c-<?= $campo ?>" name="<?= $campo ?>" x-model="v" required<?= $aria ?>>
    <option value="" disabled><?= h(t('Selecciona…')) ?></option>
    <?php foreach ($opciones as $o): ?>
    <option value="<?= h($o) ?>"><?= h($o) ?></option>
    <?php endforeach; ?>
    <?php if ($conOtros): ?>
    <option value="<?= h(Campos::OTROS) ?>"><?= h(t('Otros (especificar)')) ?></option>
    <?php endif; ?>
  </select>
  <?php endif; ?>

  <?php if ($fuera): ?>
  <p class="mt-1 text-[11px] leading-snug text-estado-ambar"><?= h(t('El valor anterior «:valor» ya no está en la lista. Elige uno para poder guardar.', ['valor' => $valor])) ?></p>
  <?php endif; ?>

  <?php if ($conOtros): ?>
  <div x-show="v === <?= h(json_encode(Campos::OTROS)) ?>" x-cloak class="mt-2">
    <label class="label mb-0.5" for="o-<?= $campo ?>"><?= h(t('¿Cuál?')) ?></label>
    <input class="input" id="o-<?= $campo ?>" name="<?= $colOtro ?>" value="<?= h($detalle) ?>" maxlength="200"
           placeholder="<?= h(t('Escribe cuál…')) ?>" :required="v === <?= h(json_encode(Campos::OTROS)) ?>"<?= !empty($errorOtro) ? ' aria-describedby="err-' . $colOtro . '"' : '' ?>>
    <p class="mt-1 text-[11px] leading-snug text-gris"><?= h(t('Queda registrado en el evento. Un administrador puede convertirlo después en una opción oficial.')) ?></p>
    <?php if (!empty($errorOtro)): ?><p class="error-campo" id="err-<?= $colOtro ?>"><?= h(t($errorOtro)) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?><p class="error-campo" id="err-<?= $campo ?>"><?= h(t($error)) ?></p><?php endif; ?>
</div>
