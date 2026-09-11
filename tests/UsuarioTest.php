<?php
declare(strict_types=1);

use App\Models\Usuario;

function test_usuario_crear_y_leer_nunca_devuelve_el_hash(): void
{
    conDb(function (PDO $pdo): void {
        $id = Usuario::crear('Xus Uno', 'xus.uno@prueba.local', 'clave12345', 'usuario', null);
        assertTrue($id > 0, 'crear devuelve el identificador nuevo');

        $u = Usuario::porId($id);
        assertEq('Xus Uno', $u['nombre']);
        assertEq('xus.uno@prueba.local', $u['correo'], 'el correo se guarda en minúsculas');
        assertEq('usuario', $u['rol']);
        assertTrue(!array_key_exists('contrasena', $u), 'la fila devuelta NO puede traer el hash');
    });
}

function test_usuario_correo_se_normaliza_a_minusculas(): void
{
    conDb(function (PDO $pdo): void {
        $id = Usuario::crear('Xus Dos', '  XUS.DOS@Prueba.Local  ', 'clave12345', 'usuario', null);
        assertEq('xus.dos@prueba.local', Usuario::porId($id)['correo']);
        assertTrue(Usuario::correoExiste('xus.DOS@prueba.local'), 'la comprobación no distingue mayúsculas');
    });
}

function test_usuario_autenticar_acierta_y_falla(): void
{
    conDb(function (PDO $pdo): void {
        Usuario::crear('Xus Tres', 'xus.tres@prueba.local', 'clave12345', 'usuario', null);

        $ok = Usuario::autenticar('xus.tres@prueba.local', 'clave12345');
        assertTrue($ok !== null, 'con la contraseña correcta entra');
        assertTrue(!array_key_exists('contrasena', $ok), 'tampoco aquí puede viajar el hash');

        assertEq(null, Usuario::autenticar('xus.tres@prueba.local', 'otra'), 'contraseña equivocada no entra');
        assertEq(null, Usuario::autenticar('no.existe@prueba.local', 'clave12345'), 'correo inexistente no entra');
    });
}

function test_usuario_dado_de_baja_no_puede_entrar(): void
{
    conDb(function (PDO $pdo): void {
        $id = Usuario::crear('Xus Cuatro', 'xus.cuatro@prueba.local', 'clave12345', 'usuario', null);
        assertTrue(Usuario::autenticar('xus.cuatro@prueba.local', 'clave12345') !== null, 'activo sí entra');

        Usuario::activar($id, false);
        assertEq(null, Usuario::autenticar('xus.cuatro@prueba.local', 'clave12345'), 'de baja no entra');

        // La baja es lógica: la fila tiene que seguir existiendo para el historial.
        assertTrue(Usuario::porId($id) !== null, 'la fila permanece tras la baja');

        Usuario::activar($id, true);
        assertTrue(Usuario::autenticar('xus.cuatro@prueba.local', 'clave12345') !== null, 'reactivado vuelve a entrar');
    });
}

function test_usuario_cambiar_contrasena_invalida_la_anterior(): void
{
    conDb(function (PDO $pdo): void {
        $id = Usuario::crear('Xus Cinco', 'xus.cinco@prueba.local', 'clave12345', 'usuario', null);
        Usuario::cambiarContrasena($id, 'nuevaClave678');

        assertEq(null, Usuario::autenticar('xus.cinco@prueba.local', 'clave12345'), 'la vieja deja de servir');
        assertTrue(Usuario::autenticar('xus.cinco@prueba.local', 'nuevaClave678') !== null, 'la nueva sirve');
    });
}

function test_usuario_cuenta_administradores_activos(): void
{
    conDb(function (PDO $pdo): void {
        $antes = Usuario::administradoresActivos();
        $id = Usuario::crear('Xus Admin', 'xus.admin@prueba.local', 'clave12345', 'admin', null);
        assertEq($antes + 1, Usuario::administradoresActivos(), 'un admin nuevo suma');

        Usuario::activar($id, false);
        assertEq($antes, Usuario::administradoresActivos(), 'de baja deja de contar');
    });
}
