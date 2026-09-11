<?php /** @var int $id  Bloque plegable con el formulario de cancelación (sin JS: <details>). */ ?>
<details id="cancelar-evento" class="rounded-xl border border-borde p-3 dark:border-noche-borde">
  <summary class="cursor-pointer text-sm font-semibold text-peligro">Cancelar evento…</summary>
  <form method="post" action="<?= h(url('eventos/cancelar', ['id' => (int) $id])) ?>" class="mt-3 space-y-2">
    <?= csrf_campo() ?>
    <label class="label" for="motivo-<?= (int) $id ?>">Motivo de la cancelación</label>
    <textarea class="input" id="motivo-<?= (int) $id ?>" name="motivo" rows="3" minlength="3" maxlength="500" required placeholder="Por qué no se hará (queda en el historial)"></textarea>
    <p class="text-xs text-gris">El evento seguirá visible, tachado, y se podrá reanudar. Si fue un error de carga, usa "Eliminar".</p>
    <button type="submit" class="btn-peligro w-full">Confirmar cancelación</button>
  </form>
</details>
