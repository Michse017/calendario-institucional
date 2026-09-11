<?php
declare(strict_types=1);

use App\Models\IntentoAcceso;

function test_intento_acceso_bloquea_al_quinto_fallo(): void
{
    conDb(function (PDO $pdo): void {
        $correo = 'xia.uno@prueba.local';
        $ip = '203.0.113.10';

        for ($i = 1; $i < IntentoAcceso::MAXIMO; $i++) {
            IntentoAcceso::registrar($correo, $ip, false);
            assertTrue(!IntentoAcceso::bloqueado($correo, $ip), "con $i fallos todavía no debe bloquear");
        }

        IntentoAcceso::registrar($correo, $ip, false);
        assertTrue(IntentoAcceso::bloqueado($correo, $ip), 'al llegar al máximo debe bloquear');
    });
}

function test_intento_acceso_un_acierto_borra_la_cuenta_de_fallos(): void
{
    conDb(function (PDO $pdo): void {
        $correo = 'xia.dos@prueba.local';
        $ip = '203.0.113.11';

        for ($i = 0; $i < IntentoAcceso::MAXIMO; $i++) {
            IntentoAcceso::registrar($correo, $ip, false);
        }
        assertTrue(IntentoAcceso::bloqueado($correo, $ip), 'bloqueado tras los fallos');

        // Solo cuentan los fallos posteriores al último acierto.
        IntentoAcceso::registrar($correo, $ip, true);
        assertTrue(!IntentoAcceso::bloqueado($correo, $ip), 'entrar bien deja el contador a cero');
    });
}

function test_intento_acceso_no_confunde_dos_correos(): void
{
    conDb(function (PDO $pdo): void {
        $ip = '203.0.113.12';
        for ($i = 0; $i < IntentoAcceso::MAXIMO; $i++) {
            IntentoAcceso::registrar('xia.tres@prueba.local', $ip, false);
        }

        assertTrue(IntentoAcceso::bloqueado('xia.tres@prueba.local', $ip), 'el correo atacado queda bloqueado');
        // La otra cuenta comparte IP pero aún no llega al umbral por dirección.
        assertTrue(!IntentoAcceso::bloqueado('xia.cuatro@prueba.local', $ip), 'otro correo desde la misma IP sigue pudiendo entrar');
    });
}

function test_intento_acceso_limpiar_y_purgar(): void
{
    conDb(function (PDO $pdo): void {
        $correo = 'xia.cinco@prueba.local';
        $ip = '203.0.113.13';

        for ($i = 0; $i < IntentoAcceso::MAXIMO; $i++) {
            IntentoAcceso::registrar($correo, $ip, false);
        }
        assertTrue(IntentoAcceso::bloqueado($correo, $ip), 'bloqueado antes de limpiar');

        IntentoAcceso::limpiar($correo);
        assertTrue(!IntentoAcceso::bloqueado($correo, $ip), 'limpiar libera la cuenta');
    });
}

function test_intento_acceso_ip_ignora_cabeceras_falsificables(): void
{
    $remotaOriginal = $_SERVER['REMOTE_ADDR'] ?? null;
    $reenviadaOriginal = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;

    $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

    // Sin TRUST_PROXY activo, la cabecera se ignora: si no, cualquiera burlaría
    // el límite enviando una dirección distinta en cada intento.
    assertEq('198.51.100.7', IntentoAcceso::ipCliente(), 'sin proxy declarado manda REMOTE_ADDR');

    $remotaOriginal === null ? ($_SERVER['REMOTE_ADDR'] = '') : ($_SERVER['REMOTE_ADDR'] = $remotaOriginal);
    if ($reenviadaOriginal === null) {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    } else {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $reenviadaOriginal;
    }
}
