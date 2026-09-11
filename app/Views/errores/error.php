<section class="mx-auto mt-[10vh] max-w-md text-center">
  <div class="card p-10">
    <p class="font-serif text-6xl text-azul"><?= h((string) ($codigo ?? 500)) ?></p>
    <p class="mt-3 text-sm text-gris"><?= h(t($mensaje ?? 'Ocurrió un error.')) ?></p>
    <a href="<?= h(url('calendario')) ?>" class="btn-primario mt-6"><?= h(t('Ir al calendario')) ?></a>
  </div>
</section>
