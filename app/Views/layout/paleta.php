<?php $txtPaleta = ['nuevoEvento' => t('Nuevo evento'), 'irCalendario' => t('Ir al calendario'), 'verLista' => t('Ver lista de eventos'), 'abrirDashboard' => t('Abrir dashboard'), 'accion' => t('acción')]; ?>
<div x-data="paleta(<?= h(json_encode($txtPaleta)) ?>)" @keydown.window.prevent.ctrl.k="abrirP()" @keydown.window.prevent.meta.k="abrirP()" @abrir-paleta.window="abrirP()" @keydown.escape.window="abierta=false">
  <div x-show="abierta" x-cloak class="fixed inset-0 z-50 flex items-start justify-center bg-tinta/40 p-4 pt-[12vh] backdrop-blur-sm" @click.self="abierta=false">
    <div class="card w-full max-w-xl overflow-hidden" x-trap.noscroll="abierta">
      <input x-ref="q" x-model="q" @input="buscar()" @keydown.down.prevent="mover(1)" @keydown.up.prevent="mover(-1)" @keydown.enter.prevent="ir()"
             class="w-full border-0 bg-transparent px-5 py-4 text-base focus:outline-none" placeholder="<?= h(t('Buscar eventos o escribe una acción…')) ?>" autocomplete="off">
      <ul class="max-h-80 overflow-auto border-t border-borde p-1.5 dark:border-noche-borde">
        <template x-for="(it, i) in visibles" :key="it.clave">
          <li>
            <button type="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-[#F0EEE8] dark:hover:bg-[#232834]" :class="{'bg-[#F0EEE8] dark:bg-[#232834]': i === activo}" @click="ir(i)" @mousemove="activo = i">
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + (it.color || '#B3B7BF')"></span>
              <span class="truncate" x-text="it.texto"></span>
              <span class="ml-auto shrink-0 text-xs text-gris" x-text="it.sub"></span>
            </button>
          </li>
        </template>
        <li x-show="!visibles.length" class="px-3 py-3 text-sm text-gris"><?= h(t('Sin resultados.')) ?></li>
      </ul>
    </div>
  </div>
</div>
