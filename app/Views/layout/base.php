<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo ?? 'Calendario') ?> · Centro Cultural Meridiano</title>
<meta name="csrf-token" content="<?= h(App\Core\Csrf::token()) ?>">
<meta name="base-url" content="<?= h(base_path()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(asset('css/app.css')) ?>">
<script>
(function(){try{var t=localStorage.getItem('cro-tema');if(t==='oscuro'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');}}catch(e){}})();
</script>
</head>
<body class="min-h-screen" x-data="app()">
<?php if (empty($sinNav)) { include APP_PATH . '/Views/layout/nav.php'; } ?>
<?php if ($f = flash()): ?>
  <div class="mx-auto max-w-[1440px] px-4 pt-4 sm:px-6 lg:px-8" x-data="avisoFlash(6)" x-show="ver" x-cloak
       @mouseenter="pausar()" @mouseleave="seguir()"
       x-transition:enter="cro-aviso-anim" x-transition:enter-start="cro-aviso-fuera" x-transition:enter-end="cro-aviso-dentro"
       x-transition:leave="cro-aviso-anim" x-transition:leave-start="cro-aviso-dentro" x-transition:leave-end="cro-aviso-fuera">
    <div class="cro-aviso flex items-center gap-3 rounded-xl border px-4 py-2.5 text-sm font-medium <?= $f['tipo'] === 'ok' ? 'border-estado-verde/30 bg-estado-verde/10 text-[#1E6B3E]' : 'border-peligro/30 bg-peligro/10 text-peligro' ?>" role="status" aria-live="polite">
      <span class="cro-aviso-icono"><?= $f['tipo'] === 'ok' ? '✓' : '!' ?></span>
      <span class="flex-1"><?= h($f['mensaje']) ?></span>
      <button type="button" class="btn-icono h-7 w-7" @click="cerrar()" aria-label="Cerrar aviso">✕</button>
      <span class="cro-aviso-barra" :style="'width:' + Math.max(restante, 0) + '%'"></span>
    </div>
  </div>
<?php endif; ?>
<main class="<?= ($ancho ?? '') === 'completo' ? 'w-full px-4 sm:px-6 lg:px-8' : 'mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8' ?> py-6">
<?= $contenido ?>
</main>
<?php if (empty($sinNav)) { include APP_PATH . '/Views/layout/paleta.php'; } ?>
<script src="<?= h(asset('js/app.js')) ?>"></script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.14.9/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
<?= $scripts ?? '' ?>
</body>
</html>
