<?php
$u = usuario_actual();
$r = ruta_actual();
$activo = static fn(string $pref): string => str_starts_with($r, $pref) ? 'nav-link nav-link-activo' : 'nav-link';
$palabras = preg_split('/\s+/u', trim($u['nombre']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$iniciales = mb_strtoupper(mb_substr($palabras[0] ?? '?', 0, 1) . (count($palabras) > 1 ? mb_substr(end($palabras), 0, 1) : ''));
?>
<header class="sticky top-0 z-30 border-b border-borde bg-crema/85 backdrop-blur dark:border-noche-borde dark:bg-noche/85">
  <div class="mx-auto flex h-16 max-w-[1440px] items-center gap-4 px-4 sm:px-6 lg:px-8">
    <a href="<?= h(url('calendario')) ?>" class="flex items-center gap-3">
      <span class="shrink-0 dark:rounded-lg dark:bg-white dark:px-1.5 dark:py-1">
        <img src="<?= h(asset('img/meridiano.svg')) ?>" alt="Centro Cultural Meridiano" class="h-9 w-auto">
      </span>
      <span class="leading-tight">
        <span class="block font-serif text-xl">Calendario</span>
        <span class="block text-[10px] font-bold uppercase tracking-[0.14em] text-gris">Centro Cultural Meridiano</span>
      </span>
    </a>

    <nav class="ml-4 hidden items-center gap-1 md:flex">
      <a href="<?= h(url('calendario')) ?>" class="<?= $activo('calendario') ?>">Calendario</a>
      <a href="<?= h(url('eventos')) ?>" class="<?= $activo('eventos') ?>">Eventos</a>
      <a href="<?= h(url('dashboard')) ?>" class="<?= $activo('dashboard') ?>">Dashboard</a>
      <?php if (es_admin()): ?><a href="<?= h(url('admin/usuarios')) ?>" class="<?= $activo('admin') ?>">Admin</a><?php endif; ?>
    </nav>

    <div class="ml-auto flex items-center gap-2">
      <?php if (App\Core\Auth::puedeCrear($u)): ?>
      <a href="<?= h(url('eventos/nuevo')) ?>" class="btn-primario">
        <span class="text-base leading-none">+</span> Nuevo evento
      </a>
      <?php else: ?>
      <span class="btn-primario cursor-not-allowed opacity-50" title="Pide al administrador que te asigne un área" aria-disabled="true">
        <span class="text-base leading-none">+</span> Nuevo evento
      </span>
      <?php endif; ?>
      <button type="button" class="btn-icono" @click="alternarTema()" :aria-label="oscuro ? 'Tema claro' : 'Tema oscuro'">
        <span x-show="!oscuro">☾</span><span x-show="oscuro" x-cloak>☀</span>
      </button>
      <div class="relative" @click.outside="menuUsuario=false">
        <button type="button" class="flex items-center gap-2 rounded-full border border-borde bg-white py-1 pl-1 pr-3 text-left dark:border-noche-borde dark:bg-noche-2" @click="menuUsuario=!menuUsuario" aria-haspopup="menu" :aria-expanded="menuUsuario ? 'true' : 'false'" aria-label="Menú de usuario">
          <span class="flex h-7 w-7 items-center justify-center rounded-full bg-azul-claro text-xs font-bold text-azul"><?= h($iniciales) ?></span>
          <span class="hidden leading-tight lg:block">
            <span class="block text-xs font-semibold"><?= h($u['nombre']) ?></span>
            <span class="block text-[10px] uppercase tracking-wide text-gris"><?= $u['rol'] === 'admin' ? 'Administrador' : (($u['area_nombre'] ?? '') !== '' ? h($u['area_nombre']) : 'Usuario · sin área') ?></span>
          </span>
        </button>
        <div x-show="menuUsuario" x-cloak x-transition class="card absolute right-0 mt-2 w-48 p-1.5 text-sm">
          <?php if (($u['area_id'] ?? 0) > 0): ?>
          <a href="<?= h(url('eventos', ['mios' => 1])) ?>" class="block rounded-lg px-3 py-2 hover:bg-[#F0EEE8] dark:hover:bg-[#232834]">Mi área</a>
          <?php endif; ?>
          <form method="post" action="<?= h(url('salir')) ?>">
            <?= csrf_campo() ?>
            <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-[#F0EEE8] dark:hover:bg-[#232834]">Cerrar sesión</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <nav class="flex gap-1 overflow-x-auto px-4 pb-2 md:hidden">
    <a href="<?= h(url('calendario')) ?>" class="<?= $activo('calendario') ?>">Calendario</a>
    <a href="<?= h(url('eventos')) ?>" class="<?= $activo('eventos') ?>">Eventos</a>
    <a href="<?= h(url('dashboard')) ?>" class="<?= $activo('dashboard') ?>">Dashboard</a>
    <?php if (es_admin()): ?><a href="<?= h(url('admin/usuarios')) ?>" class="<?= $activo('admin') ?>">Admin</a><?php endif; ?>
  </nav>
</header>
<style>[x-cloak]{display:none!important}</style>
