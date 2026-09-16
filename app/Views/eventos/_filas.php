<?php

use App\Core\Auth;
use App\Core\Campos;

/**
 * Filas de la tabla de eventos.
 *
 * Vive aparte porque lo pintan DOS sitios: la página completa (eventos/lista)
 * y el buscador en vivo (api/eventos/lista). Teniendo el marcado en un solo
 * archivo no pueden desincronizarse; si se copiara en JavaScript, al cambiar
 * una columna habría que acordarse de tocar dos lugares.
 *
 * @var array $filas  filas de Evento::listar()
 */
$u = usuario_actual();
?>
<?php foreach ($filas as $e): ?>
  <tr class="hover:bg-[#FAF9F6] dark:hover:bg-[#1F242E]">
    <td class="whitespace-nowrap text-gris"><?= h(rango_fechas($e['fecha_inicio'], $e['fecha_fin'])) ?></td>
    <td><a class="font-semibold hover:text-azul<?= $e['estado'] === 'cancelado' ? ' line-through text-gris' : '' ?>" href="<?= h(url('calendario', ['evento' => $e['id'], 'fecha' => $e['fecha_inicio']])) ?>"><?= h($e['nombre']) ?></a><div class="text-xs text-gris"><?= h(t($e['segmento'])) ?> · <?= h($e['organizador']) ?></div></td>
    <td><?= h(t($e['tipo_accion'])) ?></td>
    <td><span class="chip" style="background:color-mix(in srgb,<?= h($e['area_color'] ?: Campos::COLOR_NEUTRO) ?> 14%,transparent);color:<?= h($e['area_color'] ?: Campos::COLOR_NEUTRO) ?>"><?= h(t($e['area'])) ?></span></td>
    <td><?= h($e['ciudad']) ?>, <?= h($e['pais']) ?></td>
    <td><span class="badge-estado" style="--c:<?= estado_color($e['estado']) ?>"><?= h(t(estado_etiqueta($e['estado']))) ?></span></td>
    <td class="text-gris"><?= h($e['creado_por_nombre']) ?></td>
    <td class="whitespace-nowrap text-right">
      <?php if (Auth::puedeEditar($u, $e)): ?><a class="text-xs font-semibold text-azul hover:underline" href="<?= h(url('eventos/editar', ['id' => $e['id']])) ?>"><?= h(t('Editar')) ?></a><?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
<?php if (!$filas): ?><tr><td colspan="8" class="py-10 text-center text-gris"><?= h(t('No hay eventos con esos filtros.')) ?></td></tr><?php endif; ?>
