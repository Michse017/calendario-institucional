<?php include APP_PATH . '/Views/admin/_tabs.php'; ?>
<?php use App\Core\Campos; ?>
<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
  <div class="card overflow-x-auto">
    <table class="tabla">
      <thead><tr><th scope="col">Id SSO</th><th scope="col">Nombre</th><th scope="col">Rol</th><th scope="col">Área</th><th scope="col">Estado</th><th scope="col" class="text-right">Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($accesos as $a): ?>
        <?php $idA = (int) $a['id']; $colorA = $a['area_color'] ?: Campos::COLOR_NEUTRO; ?>
        <tr class="<?= $a['estado'] === 'inactivo' ? 'opacity-60' : '' ?>">
          <td class="text-gris"><?= $idA ?></td>
          <td class="font-semibold"><?= h($a['nombre'] ?: '—') ?></td>
          <td>
            <form method="post" action="<?= h(url('admin/accesos')) ?>" class="flex items-center gap-1">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="rol"><input type="hidden" name="id" value="<?= $idA ?>">
              <label class="sr-only" for="rol-<?= $idA ?>">Rol</label>
              <select id="rol-<?= $idA ?>" name="rol" class="input w-auto py-1" onchange="this.form.submit()">
                <option value="admin" <?= $a['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                <option value="usuario" <?= $a['rol'] !== 'admin' ? 'selected' : '' ?>>Usuario</option>
              </select>
            </form>
          </td>
          <td>
            <form method="post" action="<?= h(url('admin/accesos')) ?>" class="flex items-center gap-2" data-confirmar="<?= h('¿Cambiar el área de ' . $a['nombre'] . '? Solo podrá editar los eventos de la nueva área.') ?>">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="area"><input type="hidden" name="id" value="<?= $idA ?>">
              <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= h($a['area_id'] ? $colorA : Campos::COLOR_NEUTRO) ?>"></span>
              <label class="sr-only" for="area-<?= $idA ?>">Área</label>
              <select id="area-<?= $idA ?>" name="area_id" class="input w-auto py-1" @change="$el.form.requestSubmit()">
                <option value="" <?= !$a['area_id'] ? 'selected' : '' ?>>Sin área · solo consulta</option>
                <?php foreach ($areasCatalogo as $ac): ?>
                <option value="<?= (int) $ac['id'] ?>" <?= (int) $a['area_id'] === (int) $ac['id'] ? 'selected' : '' ?>><?= h($ac['valor']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><span class="badge-estado" style="--c:<?= $a['estado'] === 'activo' ? '#2E9E5B' : '#B3B7BF' ?>"><?= h($a['estado']) ?></span></td>
          <td class="text-right">
            <?php $confirmarRevocar = $a['estado'] === 'activo' ? ' data-confirmar="' . h('¿Revocar el acceso de ' . $a['nombre'] . '?') . '"' : ''; ?>
            <form method="post" action="<?= h(url('admin/accesos')) ?>" class="inline"<?= $confirmarRevocar ?>>
              <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $idA ?>">
              <?php if ($a['estado'] === 'activo'): ?>
                <input type="hidden" name="accion" value="revocar"><button class="btn-peligro py-1 text-xs">Revocar</button>
              <?php else: ?>
                <input type="hidden" name="accion" value="reactivar"><button class="btn-secundario py-1 text-xs">Reactivar</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card p-5">
    <p class="label">Otorgar acceso</p>
    <form method="post" action="<?= h(url('admin/accesos')) ?>" class="space-y-3" x-data="{ sel: '', rol: 'usuario' }">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="otorgar">
      <?php if ($candidatos): ?>
        <label class="sr-only" for="acc-usuario">Usuario del SSO</label>
        <select id="acc-usuario" class="input" x-model="sel" @change="$refs.id.value = $event.target.value; $refs.nombre.value = $event.target.selectedOptions[0] ? $event.target.selectedOptions[0].dataset.nombre : ''" required>
          <option value="">Elige un usuario del SSO…</option>
          <?php foreach ($candidatos as $c): ?><option value="<?= (int) $c['id'] ?>" data-nombre="<?= h($c['nombre']) ?>"><?= h($c['nombre']) ?> (<?= h($c['usuario']) ?>)</option><?php endforeach; ?>
        </select>
        <input type="hidden" name="id" x-ref="id"><input type="hidden" name="nombre" x-ref="nombre">
      <?php else: ?>
        <p class="text-xs text-gris">No se pudo leer la lista del SSO; escribe el id manualmente.</p>
        <label class="sr-only" for="acc-id-manual">Id del usuario en el SSO</label>
        <input id="acc-id-manual" class="input" name="id" type="number" min="1" placeholder="Id del usuario en el SSO" required>
        <label class="sr-only" for="acc-nombre-manual">Nombre</label>
        <input id="acc-nombre-manual" class="input" name="nombre" placeholder="Nombre" required>
      <?php endif; ?>
      <label class="label" for="acc-rol">Rol</label>
      <select id="acc-rol" class="input" name="rol" x-model="rol"><option value="usuario">Usuario</option><option value="admin">Administrador</option></select>
      <label class="label" for="acc-area">Área <span class="font-normal text-gris" x-show="rol === 'admin'">(opcional para administradores)</span></label>
      <select id="acc-area" class="input" name="area_id" :required="rol === 'usuario'">
        <option value="">Elige el área…</option>
        <?php foreach ($areasCatalogo as $ac): ?><option value="<?= (int) $ac['id'] ?>"><?= h($ac['valor']) ?></option><?php endforeach; ?>
      </select>
      <button class="btn-primario w-full">Otorgar</button>
      <p class="text-xs text-gris">El usuario verá la tarjeta "Calendario Corpoturismo" en el Panel General en su próximo ingreso y solo podrá editar los eventos de su área.</p>
    </form>
  </div>
</div>
