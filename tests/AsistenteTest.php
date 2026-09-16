<?php
declare(strict_types=1);

use App\Core\Env;
use App\Models\Asistente;

/**
 * El asistente de ayuda, sin llamar al proveedor.
 *
 * Todo lo que se comprueba aquí ocurre ANTES de salir a la red: la validación
 * de la pregunta, el límite de uso y la purga. Lo que responde el modelo no se
 * puede afirmar en una prueba automática (ni debería: cambia con el modelo),
 * así que eso se verifica a mano contra la API.
 */

function test_asistente_solo_se_activa_con_clave(): void
{
    $antes = (string) Env::get('GEMINI_API_KEY', '');

    Env::set('GEMINI_API_KEY', '');
    assertEq(false, Asistente::configurado(), 'sin clave, el asistente no se ofrece');

    Env::set('GEMINI_API_KEY', 'clave-de-prueba');
    assertEq(true, Asistente::configurado());

    Env::set('GEMINI_API_KEY', $antes);
}

function test_asistente_rechaza_preguntas_imposibles_sin_gastar_cuota(): void
{
    $antes = (string) Env::get('GEMINI_API_KEY', '');
    Env::set('GEMINI_API_KEY', 'clave-de-prueba');
    $usuario = ['rol' => 'usuario', 'area_nombre' => 'Programación', 'id' => 2];

    // Una pregunta vacía o desmedida no debe llegar siquiera a la red.
    assertLanza(fn() => Asistente::responder('   ', [], $usuario, '203.0.113.1'), 'la pregunta vacía se corta aquí');
    assertLanza(fn() => Asistente::responder(str_repeat('a', 501), [], $usuario, '203.0.113.1'), 'máximo 500 caracteres');

    Env::set('GEMINI_API_KEY', '');
    assertLanza(fn() => Asistente::responder('¿Cuántos eventos hay?', [], $usuario, '203.0.113.1'), 'sin clave configurada, no se intenta');

    Env::set('GEMINI_API_KEY', $antes);
}

function test_asistente_limita_por_ip_y_purga_lo_viejo(): void
{
    conDb(function (PDO $pdo): void {
        $antes = (string) Env::get('GEMINI_API_KEY', '');
        Env::set('GEMINI_API_KEY', 'clave-de-prueba');
        $ip = '203.0.113.77';
        $usuario = ['rol' => 'usuario', 'area_nombre' => 'Programación', 'id' => 2];

        $pdo->prepare('DELETE FROM asistente_uso WHERE ip = ?')->execute([$ip]);
        $ins = $pdo->prepare('INSERT INTO asistente_uso (ip, creado_en) VALUES (?, NOW())');
        for ($i = 0; $i < 60; $i++) {
            $ins->execute([$ip]);   // justo en el tope por hora
        }
        assertLanza(
            fn() => Asistente::responder('¿Cuántos eventos hay?', [], $usuario, $ip),
            'pasado el tope por IP no se llama al proveedor'
        );

        // Las mismas filas, pero antiguas, ya no cuentan: el límite es por hora.
        $pdo->prepare('UPDATE asistente_uso SET creado_en = DATE_SUB(NOW(), INTERVAL 3 HOUR) WHERE ip = ?')->execute([$ip]);
        $st = $pdo->prepare('SELECT COUNT(*) FROM asistente_uso WHERE ip = ? AND creado_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $st->execute([$ip]);
        assertEq(0, (int) $st->fetchColumn(), 'las filas viejas salen de la ventana');

        Asistente::purgar(1);
        $st = $pdo->prepare('SELECT COUNT(*) FROM asistente_uso WHERE ip = ?');
        $st->execute([$ip]);
        assertEq(0, (int) $st->fetchColumn(), 'purgar borra lo que ya no sirve para el límite');

        Env::set('GEMINI_API_KEY', $antes);
    });
}
