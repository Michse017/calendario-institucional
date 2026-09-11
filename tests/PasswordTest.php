<?php
declare(strict_types=1);

use App\Core\Password;

function test_password_nunca_guarda_texto_plano(): void
{
    $hash = Password::cifrar('demo1234');

    assertTrue($hash !== 'demo1234', 'la contraseña no puede guardarse tal cual');
    assertTrue(!str_contains($hash, 'demo1234'), 'el texto plano no puede aparecer dentro del hash');
    assertTrue(strlen($hash) >= 50, 'un hash de password_hash ocupa bastante más que la contraseña');
}

function test_password_verifica_correcta_e_incorrecta(): void
{
    $hash = Password::cifrar('unaClaveLarga123');

    assertTrue(Password::verificar('unaClaveLarga123', $hash), 'la contraseña correcta debe pasar');
    assertTrue(!Password::verificar('unaClaveLarga124', $hash), 'una contraseña distinta debe fallar');
    assertTrue(!Password::verificar('', $hash), 'la contraseña vacía debe fallar');
}

function test_password_dos_hashes_de_la_misma_clave_son_distintos(): void
{
    // password_hash añade una sal aleatoria distinta cada vez. Si los dos hashes
    // salieran iguales, una tabla precalculada podría romperlos en bloque.
    $a = Password::cifrar('misma');
    $b = Password::cifrar('misma');

    assertTrue($a !== $b, 'dos cifrados de la misma clave deben diferir por la sal');
    assertTrue(Password::verificar('misma', $a), 'aun así el primero verifica');
    assertTrue(Password::verificar('misma', $b), 'y el segundo también');
}

function test_password_detecta_hash_obsoleto(): void
{
    $actual = Password::cifrar('clave');
    assertTrue(!Password::necesitaRecifrado($actual), 'un hash recién creado no necesita recifrado');

    // Un hash creado con un coste menor que el vigente sí debe marcarse.
    $barato = password_hash('clave', PASSWORD_BCRYPT, ['cost' => 4]);
    assertTrue(Password::necesitaRecifrado($barato), 'un hash con coste bajo debe pedir recifrado');
}
