<?php
declare(strict_types=1);

use App\Core\Database;

function test_db_tiene_las_tablas(): void
{
    conDb(function (PDO $pdo): void {
        $st = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('accesos','catalogo_valores','eventos','eventos_historial')");
        assertEq(4, (int) $st->fetchColumn());
    });
}

function test_db_catalogos_sembrados(): void
{
    conDb(function (PDO $pdo): void {
        $st = $pdo->query("SELECT campo, COUNT(*) n FROM catalogo_valores GROUP BY campo");
        $n = array_column($st->fetchAll(), 'n', 'campo');
        assertTrue((int) ($n['tipo_accion'] ?? 0) >= 9, 'tipo_accion: al menos la semilla (los catálogos aprenden)');
        assertTrue((int) ($n['segmento'] ?? 0) >= 8, 'segmento: al menos la semilla');
        assertTrue((int) ($n['area'] ?? 0) >= 7, 'area: al menos la semilla');
        assertTrue((int) ($n['linea_estrategica'] ?? 0) >= 14, 'linea_estrategica: al menos la semilla');
        $c = $pdo->query("SELECT color FROM catalogo_valores WHERE campo='area' AND valor_norm='gestion de destino'")->fetchColumn();
        assertEq('#3A5BD9', $c);
    });
}

function test_db_unicidad_por_campo_y_norm(): void
{
    conDb(function (PDO $pdo): void {
        $pdo->exec("INSERT INTO catalogo_valores (campo, valor, valor_norm) VALUES ('pais','Perú','peru')");
        assertLanza(fn() => $pdo->exec("INSERT INTO catalogo_valores (campo, valor, valor_norm) VALUES ('pais','PERU','peru')"), 'duplicado');
    });
}

function test_db_transaccion_anidada_reutiliza(): void
{
    conDb(function (PDO $pdo): void {
        $r = Database::transaccion(fn(PDO $p) => $p->inTransaction());
        assertEq(true, $r);
        assertTrue($pdo->inTransaction(), 'la transacción externa sigue viva');
    });
}
