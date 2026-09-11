<?php
declare(strict_types=1);

use App\Core\Campos;
use App\Core\Idioma;

function test_idioma_por_defecto_es_espanol(): void
{
    Idioma::fijar('es');

    assertEq('es', Idioma::actual());
    // En español la clave ya es su propia traducción.
    assertEq('Nuevo evento', Idioma::t('Nuevo evento'));
}

function test_idioma_traduce_al_ingles(): void
{
    Idioma::fijar('en');

    assertEq('en', Idioma::actual());
    assertEq('New event', Idioma::t('Nuevo evento'));

    Idioma::fijar('es');
}

function test_una_cadena_sin_traducir_se_muestra_en_espanol(): void
{
    Idioma::fijar('en');

    // Es la garantía que sostiene todo el diseño: si algo se escapa, la
    // pantalla enseña español, nunca una clave cruda ni un hueco en blanco.
    assertEq('Esto no está en el diccionario', Idioma::t('Esto no está en el diccionario'));

    Idioma::fijar('es');
}

function test_los_parametros_se_sustituyen_por_nombre(): void
{
    Idioma::fijar('en');

    // Por nombre y no por posición, para que el orden de las palabras pueda
    // cambiar entre idiomas sin romper la frase.
    assertEq('Page 3 of 7', Idioma::t('Página :n de :total', ['n' => 3, 'total' => 7]));

    Idioma::fijar('es');
}

function test_un_idioma_desconocido_cae_al_de_por_defecto(): void
{
    Idioma::fijar('klingon');

    assertEq('es', Idioma::actual());
}

/**
 * El guardián de verdad: recorre el código buscando llamadas a t() y comprueba
 * que todas están en el diccionario. Sin esto, cada pantalla nueva se queda a
 * medio traducir sin que nadie se entere, porque la cadena sin traducir se ve
 * en español y no rompe nada.
 */
function test_no_queda_ninguna_cadena_sin_traducir(): void
{
    $diccionario = require BASE_PATH . '/lang/en.php';
    $usadas = [];

    $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_PATH));
    foreach ($archivos as $archivo) {
        if (!$archivo->isFile() || $archivo->getExtension() !== 'php') {
            continue;
        }
        $fuente = (string) file_get_contents($archivo->getPathname());
        if (preg_match_all("/\\bt\\(\\s*'([^']*)'/", $fuente, $m)) {
            foreach ($m[1] as $clave) {
                $usadas[$clave] = $archivo->getFilename();
            }
        }
    }

    // Los catálogos fijos del código también se muestran traducidos.
    $reflexion = new ReflectionClass(Campos::class);
    foreach (['ETIQUETA', 'DESCRIPCION'] as $constante) {
        foreach ((array) $reflexion->getConstant($constante) as $valor) {
            if (is_string($valor) && $valor !== '') {
                $usadas[$valor] = 'Campos::' . $constante;
            }
        }
    }

    $faltan = [];
    foreach ($usadas as $clave => $donde) {
        if ($clave !== '' && !isset($diccionario[$clave])) {
            $faltan[] = "$donde: \"$clave\"";
        }
    }

    assertEq([], $faltan, 'faltan traducciones al inglés');
}
