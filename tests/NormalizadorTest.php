<?php
declare(strict_types=1);

use App\Core\Normalizador;

function test_normalizar_quita_tildes_espacios_y_mayusculas(): void
{
    assertEq('gestion de destino', Normalizador::normalizar('  Gestión   de  Destino '));
    assertEq('promocion y mercadeo', Normalizador::normalizar("Promoción\ty Mercadeo"));
    assertEq('peña', Normalizador::normalizar('PEÑA'), 'la ñ se conserva');
    assertEq('', Normalizador::normalizar('   '));
}

function test_limpiar_conserva_forma_visible_y_canoniza_na(): void
{
    assertEq('Feria ITB', Normalizador::limpiar('  Feria   ITB '));
    assertEq('N/A', Normalizador::limpiar(' n.a. '));
    assertEq('N/A', Normalizador::limpiar('No Aplica'));
    assertEq('N/A', Normalizador::limpiar('NA'));
    assertEq('N/A', Normalizador::limpiar('n/a'));
    assertEq('Nacional', Normalizador::limpiar('Nacional'));
}

function test_es_na(): void
{
    assertTrue(Normalizador::esNA('N/A'));
    assertTrue(Normalizador::esNA('no aplica'));
    assertTrue(!Normalizador::esNA('Nacional'));
    assertTrue(!Normalizador::esNA(''));
}

function test_similares_levenshtein(): void
{
    $cands = [
        ['id' => 1, 'valor' => 'México', 'valor_norm' => 'mexico'],
        ['id' => 2, 'valor' => 'Mejico', 'valor_norm' => 'mejico'],
        ['id' => 3, 'valor' => 'Brasil', 'valor_norm' => 'brasil'],
        ['id' => 4, 'valor' => 'Mexicali', 'valor_norm' => 'mexicali'],
        ['id' => 5, 'valor' => 'Mexcio', 'valor_norm' => 'mexcio'],
    ];
    $r = Normalizador::similares('Mexico', $cands);
    assertEq([2, 5], array_column($r, 'id'), 'mejico (1 cambio) y mexcio (2 cambios) entran; mexico exacto se excluye y mexicali (3 cambios) queda fuera');
    assertEq([], Normalizador::similares('Fit', $cands), 'menos de 5 caracteres no sugiere');
    assertEq([], Normalizador::similares('Argentina', $cands));
}

function test_similares_cuenta_caracteres_no_bytes(): void
{
    $cands = [
        ['id' => 1, 'valor' => 'Disena', 'valor_norm' => 'disena'],
        ['id' => 2, 'valor' => 'Peñas', 'valor_norm' => 'peñas'],
        ['id' => 3, 'valor' => 'Diseñador', 'valor_norm' => 'diseñador'],
    ];
    assertEq([1], array_column(Normalizador::similares('Diseño', $cands), 'id'), 'diseño→disena son 2 cambios por carácter (3 por bytes)');
    assertEq([2], array_column(Normalizador::similares('Penas', $cands), 'id'), 'penas→peñas es 1 cambio; 5 caracteres justos sí sugiere');
    assertEq([], Normalizador::similares('Peña', $cands), 'cuatro caracteres no sugiere aunque tenga ñ');
}

function test_normalizar_dieresis(): void
{
    assertEq('bilingue', Normalizador::normalizar('Bilingüe'));
    assertEq('pinguino', Normalizador::normalizar('PINGÜINO'));
}
