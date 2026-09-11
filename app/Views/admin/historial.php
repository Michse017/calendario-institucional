<?php include APP_PATH . '/Views/admin/_tabs.php'; ?>
<div class="card overflow-x-auto">
  <table class="tabla">
    <thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Evento</th><th scope="col">Cambios</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($movimientos as $m): ?>
      <tr>
        <td class="whitespace-nowrap text-gris"><?= h(substr($m['fecha'], 0, 16)) ?></td>
        <td><?= h($m['usuario_nombre']) ?></td>
        <td><span class="badge-estado" style="--c:<?= ['crear' => '#2E9E5B', 'editar' => '#E0A020', 'eliminar' => '#B42318', 'restaurar' => '#3A5BD9', 'cancelar' => '#8A8F98', 'reanudar' => '#3A5BD9'][$m['accion']] ?? '#B3B7BF' ?>"><?= h($m['accion']) ?></span></td>
        <td><a class="font-semibold hover:text-azul" href="<?= h(url('calendario', ['evento' => $m['evento_id']])) ?>"><?= h($m['evento_nombre']) ?></a><?= $m['eliminado_en'] ? ' <span class="text-xs text-peligro">(eliminado)</span>' : '' ?></td>
        <td class="max-w-md text-xs text-gris">
          <?php if ($m['accion'] === 'editar'): ?>
            <?php foreach ($m['cambios'] as $campo => $c): ?>
            <?php $fmt = static fn($v): string => $campo === 'estado' ? estado_etiqueta((string) $v) : mb_strimwidth((string) $v, 0, 40, '…'); ?>
            <div><b><?= h(App\Core\Campos::ETIQUETA[$campo] ?? $campo) ?>:</b> <?= h($fmt($c['antes'] ?? '')) ?> → <?= h($fmt($c['despues'] ?? '')) ?></div>
            <?php endforeach; ?>
          <?php elseif ($m['accion'] === 'eliminar'): ?>
            <div><b>Motivo:</b> <?= h((string) ($m['cambios']['motivo'] ?? '—')) ?></div>
          <?php elseif ($m['accion'] === 'restaurar' && ($m['cambios']['motivo_eliminacion'] ?? '') !== ''): ?>
            <div><b>Se había eliminado por:</b> <?= h((string) $m['cambios']['motivo_eliminacion']) ?></div>
          <?php elseif ($m['accion'] === 'cancelar'): ?>
            <div><b>Motivo:</b> <?= h((string) ($m['cambios']['motivo'] ?? '')) ?></div>
            <div><b>Estado previo:</b> <?= h(estado_etiqueta((string) ($m['cambios']['estado_previo'] ?? ''))) ?></div>
          <?php elseif ($m['accion'] === 'reanudar'): ?>
            <div><b>Vuelve a:</b> <?= h(estado_etiqueta((string) ($m['cambios']['estado'] ?? ''))) ?></div>
          <?php endif; ?>
        </td>
        <td class="text-right">
          <?php if ($m['accion'] === 'eliminar' && $m['eliminado_en']): ?>
            <form method="post" action="<?= h(url('admin/restaurar')) ?>"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $m['evento_id'] ?>"><button class="btn-secundario py-1 text-xs">Restaurar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
