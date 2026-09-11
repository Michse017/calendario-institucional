<?php
declare(strict_types=1);

use App\Core\Csrf;

function test_csrf_token_estable_y_verificacion(): void
{
    $_SESSION = [];
    $t = Csrf::token();
    assertEq(64, strlen($t));
    assertEq($t, Csrf::token(), 'mismo token en la sesión');
    assertTrue(Csrf::verificar($t));
    assertTrue(!Csrf::verificar('otro'));
    assertTrue(!Csrf::verificar(null));
    assertTrue(str_contains(Csrf::campo(), 'name="_csrf"'));
}
