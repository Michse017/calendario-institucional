<?php
/**
 * Gestión de usuarios.
 *
 * @var array $usuarios       filas de Usuario::listar(), con area_nombre
 * @var array $areasCatalogo  áreas activas del catálogo, sin N/A
 * @var string $tab
 */
$yo = usuario_actual()['id'];
?>
<?php include APP_PATH . '/Views/admin/_tabs.php'; ?>

<div class="card overflow-x-auto">
  <table class="tabla">
    <thead>
      <tr>
        <th scope="col"><?= h(t('Persona')) ?></th>
        <th scope="col"><?= h(t('Rol')) ?></th>
        <th scope="col"><?= h(t('Área')) ?></th>
        <th scope="col"><?= h(t('Estado')) ?></th>
        <th scope="col" class="text-right"><?= h(t('Acciones')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($usuarios as $u): ?>
      <?php $esYo = (int) $u['id'] === $yo; ?>
      <tr class="<?= !$u['activo'] ? 'opacity-60' : '' ?>">
        <td>
          <span class="font-semibold"><?= h($u['nombre']) ?></span>
          <?php if ($esYo): ?><span class="ml-1 text-[10px] font-bold uppercase tracking-wider text-azul"><?= h(t('tú')) ?></span><?php endif; ?>
          <span class="block text-xs text-gris"><?= h($u['correo']) ?></span>
        </td>

        <td colspan="2">
          <form method="post" action="<?= h(url('admin/usuarios')) ?>" class="flex flex-wrap items-center gap-1">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="editar">
            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <input class="input w-40 py-1" name="nombre" value="<?= h($u['nombre']) ?>" aria-label="<?= h(t('Nombre')) ?>" required>
            <select class="input w-auto py-1" name="rol" aria-label="<?= h(t('Rol')) ?>" <?= $esYo ? 'disabled' : '' ?>>
              <option value="usuario" <?= $u['rol'] === 'usuario' ? 'selected' : '' ?>><?= h(t('Usuario')) ?></option>
              <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>><?= h(t('Administrador')) ?></option>
            </select>
            <?php if ($esYo): ?><input type="hidden" name="rol" value="<?= h($u['rol']) ?>"><?php endif; ?>
            <select class="input w-auto py-1" name="area_id" aria-label="<?= h(t('Área')) ?>">
              <option value="0"><?= h(t('Sin área (solo consulta)')) ?></option>
              <?php foreach ($areasCatalogo as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= (int) $u['area_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= h(t($a['valor'])) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn-secundario py-1 text-xs"><?= h(t('Guardar')) ?></button>
          </form>
        </td>

        <td>
          <span class="badge-estado" style="--c:<?= $u['activo'] ? '#2E9E5B' : '#B3B7BF' ?>"><?= h($u['activo'] ? t('activo') : t('de baja')) ?></span>
        </td>

        <td class="text-right">
          <div class="flex items-center justify-end gap-1">
            <form method="post" action="<?= h(url('admin/usuarios')) ?>" class="flex items-center gap-1"
                  x-data="{ abierto: false }">
              <?= csrf_campo() ?>
              <input type="hidden" name="accion" value="contrasena">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <input class="input w-36 py-1" type="password" name="contrasena" placeholder="<?= h(t('Nueva contraseña')) ?>"
                     minlength="8" x-show="abierto" x-cloak aria-label="<?= h(t('Nueva contraseña')) ?>">
              <button type="button" class="btn-secundario py-1 text-xs" x-show="!abierto" @click="abierto = true"><?= h(t('Contraseña')) ?></button>
              <button class="btn-secundario py-1 text-xs" x-show="abierto" x-cloak><?= h(t('Guardar')) ?></button>
            </form>

            <?php if (!$esYo): ?>
            <form method="post" action="<?= h(url('admin/usuarios')) ?>" class="inline"
                  <?= $u['activo'] ? 'data-confirmar="' . h(t('Se dará de baja a :nombre. Podrás reactivarlo después.', ['nombre' => $u['nombre']])) . '"' : '' ?>>
              <?= csrf_campo() ?>
              <input type="hidden" name="accion" value="<?= $u['activo'] ? 'baja' : 'alta' ?>">
              <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <button class="<?= $u['activo'] ? 'btn-peligro' : 'btn-secundario' ?> py-1 text-xs"><?= h($u['activo'] ? t('Dar de baja') : t('Reactivar')) ?></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card mt-6 p-5">
  <p class="label mb-3"><?= h(t('Añadir usuario')) ?></p>
  <form method="post" action="<?= h(url('admin/usuarios')) ?>" class="grid gap-3 md:grid-cols-5" x-data="{ rol: 'usuario' }">
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="crear">

    <div>
      <label class="label mb-0.5" for="nuevo-nombre"><?= h(t('Nombre')) ?></label>
      <input class="input" id="nuevo-nombre" name="nombre" required maxlength="120">
    </div>
    <div>
      <label class="label mb-0.5" for="nuevo-correo"><?= h(t('Correo')) ?></label>
      <input class="input" id="nuevo-correo" name="correo" type="email" required maxlength="150">
    </div>
    <div>
      <label class="label mb-0.5" for="nueva-clave"><?= h(t('Contraseña')) ?></label>
      <input class="input" id="nueva-clave" name="contrasena" type="password" required minlength="8"
             placeholder="<?= h(t('Mínimo 8 caracteres')) ?>">
    </div>
    <div>
      <label class="label mb-0.5" for="nuevo-rol"><?= h(t('Rol')) ?></label>
      <select class="input" id="nuevo-rol" name="rol" x-model="rol">
        <option value="usuario"><?= h(t('Usuario')) ?></option>
        <option value="admin"><?= h(t('Administrador')) ?></option>
      </select>
    </div>
    <div>
      <label class="label mb-0.5" for="nueva-area"><?= h(t('Área')) ?></label>
      <select class="input" id="nueva-area" name="area_id">
        <option value="0"><?= h(t('Sin área (solo consulta)')) ?></option>
        <?php foreach ($areasCatalogo as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= h(t($a['valor'])) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="mt-1 text-[11px] leading-snug text-gris" x-show="rol === 'usuario'" x-cloak>
        <?= h(t('Obligatoria: sin área la persona solo podría consultar.')) ?>
      </p>
    </div>

    <div class="md:col-span-5">
      <button class="btn-primario"><?= h(t('Crear usuario')) ?></button>
    </div>
  </form>
</div>
