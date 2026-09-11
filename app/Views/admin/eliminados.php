<?php include APP_PATH . '/Views/admin/_tabs.php'; ?>
<?php use App\Core\Campos; ?>
<div class="card overflow-x-auto">
  <table class="tabla">
    <thead><tr><th scope="col">Eliminado</th><th scope="col">Por</th><th scope="col">Evento</th><th scope="col">Fechas</th><th scope="col">Área</th><th scope="col">Motivo</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($eliminados as $e): ?>
      <?php $colorE = $e['area_color'] ?: Campos::COLOR_NEUTRO; ?>
      <tr>
        <td class="whitespace-nowrap text-gris"><?= h(substr((string) $e['eliminado_en'], 0, 16)) ?></td>
        <td class="font-semibold"><?= h($e['eliminado_por_nombre']) ?></td>
        <td><span class="font-semibold"><?= h($e['nombre']) ?></span><span class="block text-xs text-gris"><?= h($e['ciudad']) ?> · creado por <?= h($e['creado_por_nombre']) ?> · estaba <?= h(mb_strtolower(estado_etiqueta($e['estado']))) ?></span></td>
        <td class="whitespace-nowrap text-gris"><?= h(rango_fechas($e['fecha_inicio'], $e['fecha_fin'])) ?></td>
        <td><span class="chip" style="background:color-mix(in srgb,<?= h($colorE) ?> 14%,transparent);color:<?= h($colorE) ?>"><?= h($e['area']) ?></span></td>
        <td class="max-w-md text-sm"><?= h((string) (($e['eliminacion_motivo'] ?? '') !== '' ? $e['eliminacion_motivo'] : '—')) ?></td>
        <td class="text-right">
          <form method="post" action="<?= h(url('admin/restaurar')) ?>" data-confirmar="<?= h('¿Restaurar "' . $e['nombre'] . '"? Volverá al calendario con los datos y el estado que tenía.') ?>"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>"><input type="hidden" name="volver" value="eliminados"><button class="btn-secundario py-1 text-xs">Restaurar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$eliminados): ?><tr><td colspan="7" class="py-8 text-center text-sm text-gris">La papelera está vacía.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<p class="mt-3 text-xs text-gris">Eliminar es para errores de carga y siempre exige un motivo. Si un evento no se hará, lo correcto es cancelarlo (queda visible, tachado). Al restaurar, el evento vuelve al calendario con el estado y los datos que tenía; la eliminación y su motivo quedan en el historial.</p>
