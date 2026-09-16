<?php

use App\Core\Campos;
use App\Core\Idioma;
use App\Core\Seguridad;

/**
 * Informe de usuarios, pensado para el papel.
 *
 * Se sirve sin la plantilla normal: lo que se ve en pantalla ya es la hoja.
 * No se genera un PDF en el servidor a propósito. El navegador lo hace mejor
 * (texto seleccionable, enlaces vivos, tipografías nítidas) y así no hay que
 * meter una librería de PDF en el servidor.
 *
 * @var array $usuarios       filas de Usuario::listar() (id, nombre, rol, area_id, area_nombre, activo)
 * @var array $areasEventos   [{id, valor, color, n}] de Evento::conteos()
 * @var array $coloresArea    id de área → color
 * @var int   $anio
 * @var DateTimeImmutable $generado
 */

$total   = count($usuarios);
$activos = 0;
$admins  = [];
$porArea = [];

foreach ($usuarios as $u) {
    if (!empty($u['activo'])) {
        $activos++;
    }
    if (($u['rol'] ?? '') === 'admin') {
        $admins[] = $u;
        continue;
    }
    $k = (int) ($u['area_id'] ?? 0);
    if (!isset($porArea[$k])) {
        $porArea[$k] = [
            'nombre' => $u['area_nombre'] ? t($u['area_nombre']) : t('Sin área'),
            'color'  => $coloresArea[$k] ?? Campos::COLOR_NEUTRO,
            'gente'  => [],
        ];
    }
    $porArea[$k]['gente'][] = $u;
}

// Un área puede tener eventos y no tener a nadie asignado. Si la escondiéramos,
// el informe daría a entender que no existe.
$eventosPorArea = [];
foreach ($areasEventos as $ae) {
    $k = (int) $ae['id'];
    $eventosPorArea[$k] = (int) $ae['n'];
    if ($k && !isset($porArea[$k])) {
        $porArea[$k] = [
            'nombre' => $ae['valor'] ? t($ae['valor']) : t('Sin área'),
            'color'  => $ae['color'] ?: Campos::COLOR_NEUTRO,
            'gente'  => [],
        ];
    }
}

uasort($porArea, static function (array $x, array $y): int {
    return (count($y['gente']) <=> count($x['gente'])) ?: strcmp($x['nombre'], $y['nombre']);
});

$inactivos   = $total - $activos;
$areasConAcc = count(array_filter(array_keys($porArea)));
$totalEv     = array_sum($eventosPorArea);
$fecha       = Idioma::actual() === 'en'
    ? $generado->format('F j, Y, H:i')
    : $generado->format('j') . ' de ' . mb_strtolower(Campos::MESES[(int) $generado->format('n')]) . ' de ' . $generado->format('Y') . ', ' . $generado->format('H:i');

/** Media de eventos por persona, con el separador decimal del idioma. */
$porPersona = static function (int $ev, int $gente): string {
    if ($gente === 0) {
        return '—';
    }
    return Idioma::actual() === 'en' ? number_format($ev / $gente, 1, '.', ',') : number_format($ev / $gente, 1, ',', '.');
};
$entidad = 'Centro Cultural Meridiano';
?>
<!DOCTYPE html>
<html lang="<?= h(Idioma::actual()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(t('Usuarios de la plataforma')) ?> · <?= h($entidad) ?></title>
<style>
  @page { size: letter; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: "Segoe UI", Calibri, Arial, sans-serif;
    color: #1f2937; font-size: 9.6pt; line-height: 1.3;
    background: #f3f1ec;
  }
  .hoja {
    width: 215.9mm; min-height: 279.4mm; margin: 8mm auto; background: #fff;
    padding: 11mm 16mm 8mm 16mm; box-shadow: 0 2mm 8mm rgba(0,0,0,.12);
  }

  /* Barra de acciones: solo pantalla */
  .barra {
    position: sticky; top: 0; z-index: 10;
    display: flex; gap: 8px; align-items: center; justify-content: center;
    padding: 10px; background: #12384f;
  }
  .barra button, .barra a {
    display: inline-block; padding: 7px 14px; border: 0; border-radius: 999px;
    font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
  }
  .barra button { background: #fff; color: #12384f; }
  .barra a { background: transparent; color: #cfe0ea; border: 1px solid #3c6379; }
  .barra .aviso { color: #9fbccd; font-size: 12px; margin-left: 6px; }

  /* Encabezado */
  .kicker { font-size: 8.4pt; font-weight: 700; text-transform: uppercase; letter-spacing: 1.6px; color: #6b7280; }
  h1 { font-size: 20pt; font-weight: 700; color: #12384f; letter-spacing: -0.2px; margin-top: 1.5mm; }
  .meta { font-size: 9pt; color: #6b7280; margin-top: 1.5mm; }
  .meta b { color: #12384f; font-weight: 600; }
  .regla { height: 2px; background: #12384f; margin-top: 4mm; }

  /* Cifras */
  .cifras { display: flex; gap: 3.5mm; margin: 4mm 0 4.5mm 0; }
  .cifra { flex: 1; border: 1px solid #e3e0d8; border-radius: 3mm; padding: 2.4mm 3mm; background: #fbfaf7; }
  .cifra .n { font-size: 17pt; font-weight: 700; color: #12384f; line-height: 1; }
  .cifra .t { font-size: 8pt; color: #6b7280; margin-top: 1.4mm; text-transform: uppercase; letter-spacing: 0.7px; font-weight: 600; }

  h2 {
    font-size: 10pt; text-transform: uppercase; letter-spacing: 1.4px; color: #12384f;
    border-bottom: 1.2px solid #12384f; padding-bottom: 1mm; margin: 0 0 2mm 0;
  }
  .nota { font-size: 8.4pt; color: #6b7280; margin: 1.5mm 0 4mm 0; }

  /* Tabla de áreas */
  table { width: 100%; border-collapse: collapse; }
  th {
    font-size: 8pt; text-transform: uppercase; letter-spacing: 0.7px; color: #6b7280;
    text-align: left; font-weight: 700; padding: 0 2mm 1.2mm 2mm; border-bottom: 1px solid #d8d4ca;
  }
  td { padding: 1.1mm 2mm; border-bottom: 1px solid #efece5; }
  .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .punto { display: inline-block; width: 2.4mm; height: 2.4mm; border-radius: 50%; margin-right: 2mm; vertical-align: -0.2mm; }
  .area-nom { font-weight: 600; color: #1f2937; }
  tr.total td { border-bottom: 0; border-top: 1.6px solid #12384f; font-weight: 700; color: #12384f; padding-top: 2mm; }

  /* Listado en tres columnas */
  .listado { column-count: 3; column-gap: 4mm; font-size: 8pt; }
  .bloque { break-inside: avoid; margin-bottom: 2.6mm; }
  .bloque h3 {
    font-size: 8.4pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #12384f;
    background: #f4f2ed; border-bottom: 1px solid #d8d4ca; padding: 1.3mm 0.6mm; margin-bottom: 0.5mm;
  }
  .bloque h3 .cuenta { font-weight: 600; color: #6b7280; letter-spacing: 0; text-transform: none; }
  .fila { display: flex; gap: 1.5mm; align-items: baseline; padding: 0.8mm 0.6mm; border-bottom: 1px solid #f2efe9; }
  .fila .id { color: #9ca3af; font-variant-numeric: tabular-nums; width: 5.2mm; text-align: right; flex: none; font-size: 7.6pt; }
  .fila .baja { color: #b91c1c; font-size: 7.2pt; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

  .pie { margin-top: 4.5mm; padding-top: 2.2mm; border-top: 1px solid #e3e0d8; font-size: 8pt; color: #9ca3af; line-height: 1.45; }

  @media print {
    body { background: #fff; }
    .barra { display: none !important; }
    .hoja { margin: 0; box-shadow: none; width: auto; min-height: 0; }
  }
</style>
</head>
<body>

<div class="barra">
  <button type="button" id="imprimir"><?= h(t('Imprimir o guardar como PDF')) ?></button>
  <a href="<?= h(url('admin/usuarios')) ?>"><?= h(t('Volver a Usuarios')) ?></a>
  <span class="aviso"><?= h(t('En el diálogo de impresión elige «Guardar como PDF».')) ?></span>
</div>

<div class="hoja">

  <div class="kicker"><?= h(t('Calendario institucional')) ?></div>
  <h1><?= h(t('Usuarios de la plataforma')) ?></h1>
  <div class="meta">
    <?= h($entidad) ?><br>
    <?= str_replace(':fecha', '<b>' . h($fecha) . '</b>', h(t('Datos tomados el :fecha'))) ?>
  </div>
  <div class="regla"></div>

  <div class="cifras">
    <div class="cifra"><div class="n"><?= $total ?></div><div class="t"><?= h(t('Usuarios')) ?></div></div>
    <div class="cifra"><div class="n"><?= $activos ?></div><div class="t"><?= h(t('Activos')) ?></div></div>
    <div class="cifra"><div class="n"><?= $inactivos ?></div><div class="t"><?= h(t('Inactivos')) ?></div></div>
    <div class="cifra"><div class="n"><?= count($admins) ?></div><div class="t"><?= h(t('Administradores')) ?></div></div>
    <div class="cifra"><div class="n"><?= $areasConAcc ?></div><div class="t"><?= h(t('Áreas con acceso')) ?></div></div>
  </div>

  <h2><?= h(t('Reparto por área')) ?></h2>
  <table>
    <thead>
      <tr>
        <th scope="col"><?= h(t('Área')) ?></th>
        <th scope="col" class="num"><?= h(t('Usuarios')) ?></th>
        <th scope="col" class="num"><?= h(t('Eventos :anio', ['anio' => $anio])) ?></th>
        <th scope="col" class="num"><?= h(t('Eventos por usuario')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($porArea as $idArea => $g): ?>
        <?php $ev = $eventosPorArea[$idArea] ?? 0; $n = count($g['gente']); ?>
        <tr>
          <td><span class="punto" style="background:<?= h($g['color']) ?>"></span><span class="area-nom"><?= h($g['nombre']) ?></span></td>
          <td class="num"><?= $n ?></td>
          <td class="num"><?= $ev ?></td>
          <td class="num"><?= $porPersona($ev, $n) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($admins): ?>
      <tr>
        <td><span class="punto" style="background:<?= h(Campos::COLOR_NEUTRO) ?>"></span><span class="area-nom"><?= h(t('Sin área (administradores)')) ?></span></td>
        <td class="num"><?= count($admins) ?></td>
        <td class="num">—</td>
        <td class="num">—</td>
      </tr>
      <?php endif; ?>
      <tr class="total">
        <td><?= h(t('Total')) ?></td>
        <td class="num"><?= $total ?></td>
        <td class="num"><?= $totalEv ?></td>
        <td class="num"><?= $porPersona($totalEv, $total) ?></td>
      </tr>
    </tbody>
  </table>
  <div class="nota">
    <?= h(t('Los administradores no tienen área asignada porque ven y editan todas. El conteo de eventos corresponde al calendario :anio y no incluye los eventos eliminados.', ['anio' => $anio])) ?>
  </div>

  <h2><?= h(t('Listado completo')) ?></h2>
  <div class="listado">
    <?php if ($admins): ?>
    <div class="bloque">
      <h3><span class="punto" style="background:<?= h(Campos::COLOR_NEUTRO) ?>"></span><?= h(t('Administradores')) ?> <span class="cuenta">· <?= count($admins) ?></span></h3>
      <?php foreach ($admins as $u): ?>
        <div class="fila">
          <span class="id"><?= (int) $u['id'] ?></span>
          <span><?= h($u['nombre'] ?: '—') ?><?= empty($u['activo']) ? ' <span class="baja">' . h(t('baja')) . '</span>' : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($porArea as $g): ?>
      <?php if (!$g['gente']) { continue; } ?>
      <div class="bloque">
        <h3><span class="punto" style="background:<?= h($g['color']) ?>"></span><?= h($g['nombre']) ?> <span class="cuenta">· <?= count($g['gente']) ?></span></h3>
        <?php foreach ($g['gente'] as $u): ?>
          <div class="fila">
            <span class="id"><?= (int) $u['id'] ?></span>
            <span><?= h($u['nombre'] ?: '—') ?><?= empty($u['activo']) ? ' <span class="baja">' . h(t('baja')) . '</span>' : '' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="pie">
    <?= h(t('El ID es el identificador interno del usuario. Quien aparece marcado como «baja» conserva su ficha pero no puede entrar.')) ?><br>
    <?= h(t('Generado desde Administración › Usuarios. No se incluyen correos ni datos personales.')) ?>
  </div>

</div>
<!-- Sin onclick en línea: la política de seguridad de contenido solo admite scripts con nonce. -->
<script nonce="<?= h(Seguridad::nonce()) ?>">
  document.getElementById('imprimir').addEventListener('click', function () { window.print(); });
</script>
</body>
</html>
