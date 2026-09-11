<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-gris"><?= h(t('Administración')) ?></p>
    <h1 class="titulo"><?= h(t($titulo)) ?></h1>
  </div>
  <nav class="flex gap-1 rounded-full border border-borde p-1 dark:border-noche-borde">
    <?php foreach (['usuarios' => t('Usuarios'), 'catalogos' => t('Catálogos'), 'historial' => t('Historial'), 'eliminados' => t('Eliminados')] as $k => $et): ?>
      <a href="<?= h(url('admin/' . $k)) ?>" class="rounded-full px-3.5 py-1 text-xs font-semibold <?= $tab === $k ? 'bg-azul text-white' : 'text-gris hover:text-tinta dark:hover:text-white' ?>"><?= $et ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<?php if (App\Core\Env::bool('APP_DEMO')): ?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-borde bg-white px-4 py-3 dark:border-noche-borde dark:bg-noche-2">
  <p class="text-xs leading-snug text-gris">
    <?= h(t('Esto es una demostración con datos ficticios. Se reinician solos cada noche; aquí puedes devolverlos a su estado inicial ahora mismo.')) ?>
  </p>
  <form method="post" action="<?= h(url('demo/reiniciar')) ?>"
        data-confirmar="<?= h(t('Se borrarán los eventos actuales y se volverán a sembrar los de ejemplo. ¿Seguir?')) ?>">
    <?= csrf_campo() ?>
    <button class="btn-secundario text-xs"><?= h(t('Reiniciar la demostración')) ?></button>
  </form>
</div>
<?php endif; ?>
