<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris">Administración</p>
    <h1 class="titulo"><?= h($titulo) ?></h1>
  </div>
  <nav class="flex gap-1 rounded-full border border-borde p-1 dark:border-noche-borde">
    <?php foreach (['usuarios' => 'Usuarios', 'catalogos' => 'Catálogos', 'historial' => 'Historial', 'eliminados' => 'Eliminados'] as $k => $et): ?>
      <a href="<?= h(url('admin/' . $k)) ?>" class="rounded-full px-3.5 py-1 text-xs font-semibold <?= $tab === $k ? 'bg-azul text-white' : 'text-gris hover:text-tinta dark:hover:text-white' ?>"><?= $et ?></a>
    <?php endforeach; ?>
  </nav>
</div>
