<?php use App\Core\Campos; ?>
<?php include APP_PATH . '/Views/admin/_tabs.php'; ?>
<div class="mb-4 flex flex-wrap items-center gap-2">
  <?php foreach (Campos::CATALOGOS as $c): ?>
    <a href="<?= h(url('admin/catalogos', ['campo' => $c])) ?>" class="chip border <?= $c === $campo ? 'border-azul bg-azul text-white' : 'border-borde bg-white text-tinta dark:bg-noche-2 dark:text-[#E9EAEE]' ?>"><?= h(t(Campos::ETIQUETA[$c])) ?></a>
  <?php endforeach; ?>
  <form method="post" action="<?= h(url('admin/catalogos')) ?>" class="ml-auto"><?= csrf_campo() ?><input type="hidden" name="campo" value="<?= h($campo) ?>"><input type="hidden" name="accion" value="recalcular"><button class="btn-secundario text-xs"><?= h(t('Recalcular usos')) ?></button></form>
</div>
<div class="card overflow-x-auto">
  <table class="tabla">
    <thead><tr><th scope="col"><?= h(t('Valor')) ?></th><th scope="col"><?= h(t('Usos')) ?></th><th scope="col"><?= h(t('Estado')) ?></th><?php if ($campo === 'area'): ?><th scope="col"><?= h(t('Color')) ?></th><?php endif; ?><th scope="col" class="text-right"><?= h(t('Acciones')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($valores as $v): ?>
      <tr class="<?= !$v['activo'] ? 'opacity-60' : '' ?>">
        <td>
          <form method="post" action="<?= h(url('admin/catalogos')) ?>" class="flex items-center gap-1">
            <?= csrf_campo() ?><input type="hidden" name="campo" value="<?= h($campo) ?>"><input type="hidden" name="accion" value="renombrar"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <input class="input py-1" name="valor" value="<?= h($v['valor']) ?>" <?= $v['valor'] === 'N/A' ? 'readonly' : '' ?>>
            <?php if ($v['valor'] !== 'N/A'): ?><button class="btn-secundario py-1 text-xs"><?= h(t('Renombrar')) ?></button><?php endif; ?>
          </form>
        </td>
        <td class="text-gris"><?= (int) $v['usos'] ?></td>
        <td><span class="badge-estado" style="--c:<?= $v['activo'] ? '#2E9E5B' : '#B3B7BF' ?>"><?= h($v['activo'] ? t('activo') : t('inactivo')) ?></span></td>
        <?php if ($campo === 'area'): ?>
        <td>
          <form method="post" action="<?= h(url('admin/catalogos')) ?>" class="flex items-center gap-1">
            <?= csrf_campo() ?><input type="hidden" name="campo" value="area"><input type="hidden" name="accion" value="color"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <input type="color" name="color" value="<?= h($v['color'] ?: Campos::COLOR_NEUTRO) ?>" class="h-8 w-10 cursor-pointer rounded border border-borde" onchange="this.form.submit()">
          </form>
        </td>
        <?php endif; ?>
        <td class="text-right">
          <?php if ($v['valor'] !== 'N/A'): ?>
          <form method="post" action="<?= h(url('admin/catalogos')) ?>" class="inline-flex items-center gap-1">
            <?= csrf_campo() ?><input type="hidden" name="campo" value="<?= h($campo) ?>"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <select name="destino" class="input w-auto py-1 text-xs"><option value=""><?= h(t('Unir con…')) ?></option>
              <?php foreach ($valores as $d): if ($d['id'] !== $v['id'] && $d['activo']): ?><option value="<?= (int) $d['id'] ?>"><?= h(t($d['valor'])) ?></option><?php endif; endforeach; ?>
            </select>
            <button name="accion" value="unir" class="btn-secundario py-1 text-xs" data-confirmar="<?= h(t('Los eventos de «:valor» pasarán al valor elegido y este quedará inactivo. ¿Continuar?', ['valor' => $v['valor']])) ?>" data-requiere="destino"><?= h(t('Unir')) ?></button>
            <button name="accion" value="<?= $v['activo'] ? 'desactivar' : 'activar' ?>" class="btn-secundario py-1 text-xs"><?= h($v['activo'] ? t('Desactivar') : t('Activar')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="mt-3 text-xs text-gris"><?= h(t('Desactivar oculta el valor del autocompletado sin tocar los eventos que ya lo usan; si alguien lo vuelve a escribir tal cual, se reactiva solo (usa "Unir" si quieres redirigirlo para siempre). Unir mueve los eventos y desactiva el origen. "Recalcular usos" cuenta los eventos vivos reales.')) ?></p>
