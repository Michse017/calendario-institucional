<?php /** @var int $id  Bloque plegable con el formulario de eliminación (motivo obligatorio; papelera restaurable por admin). */ ?>
<details id="eliminar-evento" class="rounded-xl border border-peligro/30 p-3">
  <summary class="cursor-pointer text-sm font-semibold text-peligro">Eliminar evento…</summary>
  <form method="post" action="<?= h(url('eventos/eliminar', ['id' => (int) $id])) ?>" class="mt-3 space-y-2">
    <?= csrf_campo() ?>
    <label class="label" for="motivo-elim-<?= (int) $id ?>">Motivo de la eliminación</label>
    <textarea class="input" id="motivo-elim-<?= (int) $id ?>" name="motivo" rows="3" minlength="3" maxlength="500" required placeholder="Por qué se elimina (p. ej. registro duplicado o error de carga). Queda en el historial."></textarea>
    <p class="text-xs text-gris">Eliminar es para errores de carga; si el evento no se hará, usa "Cancelar evento". Si hace falta, un administrador podrá restaurarlo.</p>
    <button type="submit" class="btn-peligro w-full">Confirmar eliminación</button>
  </form>
</details>
