<?php
declare(strict_types=1);

use App\Core\Validator;
use App\Models\Catalogo;
use App\Models\Evento;
use App\Models\Usuario;

/**
 * Responsable del evento.
 *
 * Es quien RESPONDE por el evento y no tiene por qué ser quien lo creó. La forma del campo
 * la valida Validator; que la persona exista y sea del área lo comprueba
 * Usuario::errorDueno(), que sí consulta la base. Cuentas de la semilla: 1 Ana Torres
 * (admin, sin área), 2 Carlos Mena (Programación), 3 Lucía Ferrer (Comunicaciones).
 */

function test_responsable_se_guarda_y_se_lee_separado_del_creador(): void
{
    conDb(function (): void {
        // Creado por la 1 (Ana, admin) pero con responsable el 2 (Carlos).
        $id = Evento::crear(datosEvento(), 1);
        $ev = (array) Evento::porId($id);
        assertEq(2, (int) $ev['dueno_id'], 'se guarda el responsable elegido, no el creador');
        assertEq(1, (int) $ev['creado_por'], 'el creador sigue siendo quien lo creó');
        assertEq('Carlos Mena', $ev['dueno_nombre'], 'la consulta trae el nombre del responsable');
        assertTrue($ev['dueno_nombre'] !== $ev['creado_por_nombre'], 'responsable y creador son campos distintos');
    });
}

function test_responsable_es_obligatorio_y_debe_ser_un_id(): void
{
    $base = datosEvento();
    foreach (['', '   ', 'abc', '0', '-3', '2.5'] as $malo) {
        $r = Validator::evento(['dueno_id' => $malo] + $base);
        assertTrue(isset($r['errores']['dueno_id']), 'se rechaza un responsable inválido: ' . var_export($malo, true));
    }
    $r = Validator::evento($base);
    assertTrue(!isset($r['errores']['dueno_id']), 'un id válido pasa la validación de forma');
}

function test_responsables_posibles_son_los_del_area_mas_los_admins(): void
{
    conDb(function (): void {
        $areaId = Catalogo::idArea('Programación');
        assertTrue($areaId !== null, 'el área del fixture existe');
        $ids = array_map(static fn(array $d): int => (int) $d['id'], Usuario::duenosPosibles($areaId));

        assertTrue(in_array(2, $ids, true), 'aparece la persona de esa área');
        assertTrue(in_array(1, $ids, true), 'aparece la administradora, que no tiene área');
        assertTrue(!in_array(3, $ids, true), 'no aparece alguien de otra área');
    });
}

function test_error_responsable_detecta_a_quien_no_es_del_area(): void
{
    conDb(function (): void {
        $programacion = Catalogo::idArea('Programación');
        assertEq([], Usuario::errorDueno(2, $programacion), 'la persona de esa área es responsable válida');
        assertEq([], Usuario::errorDueno(1, $programacion), 'un admin vale para cualquier área');

        // La 3 (Lucía) es de Comunicaciones: no puede responder por un evento de Programación.
        $malo = Usuario::errorDueno(3, $programacion);
        assertTrue(isset($malo['dueno_id']), 'se rechaza a alguien de otra área');

        assertTrue(isset(Usuario::errorDueno(999999, $programacion)['dueno_id']), 'se rechaza un id inexistente');
    });
}

function test_cambiar_el_responsable_queda_en_el_historial(): void
{
    conDb(function (PDO $pdo): void {
        $id = Evento::crear(datosEvento(), 1);
        Evento::actualizar($id, datosEvento(['dueno_id' => '1']), 1);

        $st = $pdo->prepare("SELECT cambios FROM eventos_historial WHERE evento_id = ? AND accion = 'editar' ORDER BY id DESC LIMIT 1");
        $st->execute([$id]);
        $cambios = (string) $st->fetchColumn();
        assertTrue(str_contains($cambios, 'dueno_nombre'), 'el cambio de responsable se registra en el historial');

        assertEq(1, (int) ((array) Evento::porId($id))['dueno_id'], 'el responsable quedó actualizado');
    });
}
