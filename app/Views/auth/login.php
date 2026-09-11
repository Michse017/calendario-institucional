<?php
/**
 * Pantalla de acceso.
 *
 * @var bool   $esDemo        si mostrar las cuentas de ejemplo
 * @var array  $cuentas       cuentas de ejemplo (correo, rol, pista)
 * @var string $clave         contraseña compartida de las cuentas de ejemplo
 * @var string $correoPrevio  correo escrito en el intento anterior
 */
?>
<div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-md flex-col justify-center py-8"
     x-data="{ rellenar(correo) { this.$refs.correo.value = correo; this.$refs.clave.value = <?= h(json_encode($clave)) ?>; this.$refs.correo.focus(); } }">

  <div class="mb-7 text-center">
    <img src="<?= h(asset('img/meridiano.svg')) ?>" alt="" class="mx-auto mb-3 h-12 w-12">
    <h1 class="font-serif text-3xl">Centro Cultural Meridiano</h1>
    <p class="mt-1 text-sm text-gris">Calendario institucional de eventos</p>
  </div>

  <form method="post" action="<?= h(url('acceso/entrar')) ?>" class="card p-6">
    <?= csrf_campo() ?>

    <div class="mb-4">
      <label class="label mb-0.5" for="correo">Correo</label>
      <input class="input" type="email" id="correo" name="correo" x-ref="correo"
             value="<?= h($correoPrevio) ?>" autocomplete="username"
             required autofocus placeholder="nombre@meridiano.demo">
    </div>

    <div class="mb-5">
      <label class="label mb-0.5" for="contrasena">Contraseña</label>
      <input class="input" type="password" id="contrasena" name="contrasena" x-ref="clave"
             autocomplete="current-password" required placeholder="Tu contraseña">
    </div>

    <button type="submit" class="btn-primario w-full justify-center">Entrar</button>
  </form>

  <?php if ($esDemo): ?>
  <div class="card mt-5 p-5">
    <p class="label mb-1">Cuentas de prueba</p>
    <p class="mb-3 text-xs leading-snug text-gris">
      Esto es una demostración con datos ficticios. Pulsa una cuenta para rellenar el formulario
      y comprueba cómo cambia lo que cada persona puede editar.
    </p>

    <div class="grid gap-2">
      <?php foreach ($cuentas as $c): ?>
      <button type="button" class="cro-cuenta-demo" @click="rellenar(<?= h(json_encode($c['correo'])) ?>)">
        <span class="cro-cuenta-info">
          <span class="cro-cuenta-rol"><?= h($c['rol']) ?></span>
          <span class="cro-cuenta-correo"><?= h($c['correo']) ?></span>
        </span>
        <span class="cro-cuenta-pista"><?= h($c['pista']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>

    <p class="mt-3 text-[11px] text-gris">
      Todas usan la contraseña <b class="font-mono"><?= h($clave) ?></b>.
      Los datos se reinician cada noche, así que puedes crear, editar y borrar sin miedo.
    </p>
  </div>
  <?php endif; ?>
</div>
