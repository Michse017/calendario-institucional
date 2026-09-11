<?php
declare(strict_types=1);

use App\Core\Seguridad;

function test_nonce_es_estable_dentro_de_la_misma_peticion(): void
{
    $a = Seguridad::nonce();
    $b = Seguridad::nonce();

    // La cabecera y la etiqueta <script> se generan en momentos distintos del
    // mismo ciclo. Si no coincidieran, el navegador bloquearía el script.
    assertEq($a, $b);
}

function test_nonce_tiene_entropia_suficiente(): void
{
    $n = Seguridad::nonce();
    $bytes = base64_decode($n, true);

    assertTrue($bytes !== false, 'el nonce debe ser base64 válido');
    assertTrue(strlen($bytes) >= 16, 'con menos de 16 bytes se podría adivinar');
}

function test_nonce_no_contiene_caracteres_que_rompan_la_cabecera(): void
{
    // Un ';' o un espacio partirían la directiva de la política y la dejarían
    // abierta. base64 no los produce, pero conviene que quede afirmado.
    assertTrue((bool) preg_match('~^[A-Za-z0-9+/=]+$~', Seguridad::nonce()));
}
