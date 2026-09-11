<?php
/**
 * Campo de catálogo abierto (país, ciudad, organizador): autocompletado que muestra las opciones al
 * enfocar y permite escribir un valor nuevo. No admite N/A: solo la sección de seguimiento lo acepta.
 *
 * @var string $campo  @var string $valor  @var ?string $error  @var string[] $sugerencias
 */
use App\Core\Campos;
?>
<div class="relative" x-data="autocompletar(<?= h(json_encode(['campo' => $campo, 'valor' => $valor])) ?>)" @click.outside="abierto=false">
  <label class="label mb-0.5" for="c-<?= $campo ?>"><?= h(t(Campos::ETIQUETA[$campo])) ?></label>
  <?php if (isset(Campos::DESCRIPCION[$campo])): ?>
  <p class="mb-1.5 text-[11px] leading-snug text-gris"><?= h(t(Campos::DESCRIPCION[$campo])) ?></p>
  <?php endif; ?>
  <input class="input" id="c-<?= $campo ?>" name="<?= $campo ?>" x-model="valor" @input="buscar()" @focus="buscar()" @click="buscar()" @keydown="tecla($event)" autocomplete="off" maxlength="255" required<?= !empty($error) ? ' aria-describedby="err-' . $campo . '"' : '' ?>>
  <ul x-show="abierto" x-cloak x-transition.opacity class="card absolute z-20 mt-1 max-h-64 w-full overflow-auto p-1 text-sm">
    <template x-for="(it, i) in items" :key="it.id">
      <li>
        <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]"
                :class="{'bg-[#F0EEE8] dark:bg-[#232834]': i === activo}" @click="elegir(it.valor)" @mousemove="activo = i">
          <span x-text="it.valor"></span>
          <span class="text-[10px] text-gris" x-text="it.usos ? it.usos + ' ' + <?= h(json_encode(t('usos'))) ?> : <?= h(json_encode(t('sin usos'))) ?>"></span>
        </button>
      </li>
    </template>
  </ul>
  <?php if ($sugerencias): ?>
  <div class="mt-2 rounded-lg border border-estado-ambar/40 bg-estado-ambar/10 p-2.5 text-xs">
    <p class="mb-1.5 font-semibold"><?= h(t('¿Quisiste decir…?')) ?></p>
    <div class="flex flex-wrap items-center gap-1.5">
      <?php foreach ($sugerencias as $s): ?>
      <button type="button" class="chip border border-borde bg-white text-tinta hover:border-azul dark:bg-noche-2 dark:text-[#E9EAEE]" @click="elegir(<?= h(json_encode($s)) ?>)"><?= h(t($s)) ?></button>
      <?php endforeach; ?>
      <label class="chip cursor-pointer border border-borde bg-white dark:bg-noche-2">
        <input type="checkbox" name="confirmar[<?= $campo ?>]" value="1" class="mr-1"> <?= h(t('Mantener «:valor» como valor nuevo', ['valor' => $valor])) ?>
      </label>
    </div>
  </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?><p class="error-campo" id="err-<?= $campo ?>"><?= h(t($error)) ?></p><?php endif; ?>
</div>
