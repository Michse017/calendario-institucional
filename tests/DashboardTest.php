<?php
declare(strict_types=1);

use App\Models\Dashboard;
use App\Models\Evento;

function test_dashboard_indicadores_por_anio(): void
{
    conDb(function (PDO $pdo): void {
        Evento::crear(datosEvento(['nombre' => 'Xd1', 'fecha_inicio' => '2031-03-02', 'fecha_fin' => '2031-03-04', 'estado' => 'realizado', 'tipo_accion' => 'Participación en Ferias', 'segmento' => 'Cultural', 'reuniones' => '5', 'contactos_url' => 'https://x.y/z', 'alianzas' => 'Alguien']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xd2', 'fecha_inicio' => '2031-03-20', 'fecha_fin' => '2031-03-20', 'estado' => 'no_realizado', 'tipo_accion' => 'Workshops/Misión comercial', 'segmento' => 'MICE', 'pais' => 'Xperu', 'ciudad' => 'Xlima', 'reuniones' => 'Pendiente']), 1);
        Evento::crear(datosEvento(['nombre' => 'Xd3', 'fecha_inicio' => '2031-11-01', 'fecha_fin' => '2031-11-01', 'estado' => 'realizado', 'tipo_accion' => 'Ruedas de negocio', 'segmento' => 'MICE', 'reuniones' => '7']), 1);
        // Cancelado: cuenta en el total y en su serie mensual, pero no en KPIs, países, reuniones, contactos ni alianzas
        $x4 = Evento::crear(datosEvento(['nombre' => 'Xd4', 'fecha_inicio' => '2031-05-05', 'fecha_fin' => '2031-05-05', 'estado' => 'realizado', 'tipo_accion' => 'Participación en Ferias', 'segmento' => 'MICE', 'pais' => 'Xchile', 'ciudad' => 'Xsantiago', 'reuniones' => '100', 'contactos_url' => 'https://x.y/w', 'alianzas' => 'Otro']), 1);
        Evento::cancelar($x4, 'Sin presupuesto', 1);

        $d = Dashboard::indicadores(2031);
        $t = $d['totales'];
        assertEq(4, $t['total'], 'el total incluye el cancelado');
        assertEq(2, $t['realizados']);
        assertEq(1, $t['no_realizados']);
        assertEq(1, $t['cancelados']);
        assertEq(2, $t['paises'], 'el país del cancelado no cuenta');
        assertEq(2, $t['ciudades']);
        assertEq(12, $t['reuniones'], 'solo los numéricos y no cancelados');
        assertEq(1, $t['contactos']);
        assertEq(1, $t['alianzas']);
        assertEq(['total' => 1, 'realizados' => 1], $d['kpis']['ferias'], 'la feria cancelada no cuenta');
        assertEq(['total' => 1, 'realizados' => 0], $d['kpis']['workshops']);
        assertEq(['total' => 2, 'realizados' => 1], $d['kpis']['mice'], 'MICE se cuenta por segmento, sin el cancelado');
        assertEq(['no_realizado' => 1, 'en_ejecucion' => 0, 'realizado' => 1, 'cancelado' => 0], $d['por_mes'][3]);
        assertEq(['no_realizado' => 0, 'en_ejecucion' => 0, 'realizado' => 0, 'cancelado' => 1], $d['por_mes'][5]);
        assertEq(0, array_sum($d['por_mes'][7]));
        assertEq(3, array_sum(array_column($d['por_tipo'], 'total')), 'por_tipo excluye cancelados');
        assertEq(0, Dashboard::indicadores(2032)['totales']['total']);
    });
}
