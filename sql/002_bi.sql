-- ---------------------------------------------------------------------------
-- Capa de análisis (BI)
--
-- Vistas en esquema de estrella para conectar Power BI, Metabase, Excel o
-- cualquier cliente que lea MySQL. No tocan ninguna tabla: son solo lectura,
-- así que se pueden crear y borrar sin riesgo para la aplicación.
--
-- Por qué vistas y no tablas nuevas: el volumen es pequeño (decenas de miles de
-- filas como mucho) y así el análisis nunca se desincroniza del dato real. Si
-- algún día la base creciera, estas mismas consultas se materializan sin
-- cambiar ni una medida del informe.
--
-- Grano de cada hecho:
--   bi_hechos_eventos     → una fila por evento      (¿cuántos eventos?)
--   bi_hechos_dias        → una fila por evento y día (¿qué días hay carga?)
--   bi_puente_procedencia → una fila por evento y procedencia (varios a varios)
--
-- Aplicar:  mysql -u root calendario_demo < sql/002_bi.sql
-- ---------------------------------------------------------------------------

-- ----------------------------------------------------------------- Dimensiones

-- Un catálogo por vista, para que en el informe cada lista sea su propia tabla.
CREATE OR REPLACE VIEW `bi_dim_area` AS
SELECT c.id AS area_id, c.valor AS area, c.color AS area_color, c.activo AS area_activa
FROM catalogo_valores c WHERE c.campo = 'area';

CREATE OR REPLACE VIEW `bi_dim_tipo` AS
SELECT c.id AS tipo_id, c.valor AS tipo, c.activo AS tipo_activo
FROM catalogo_valores c WHERE c.campo = 'tipo_accion';

CREATE OR REPLACE VIEW `bi_dim_publico` AS
SELECT c.id AS publico_id, c.valor AS publico, c.activo AS publico_activo
FROM catalogo_valores c WHERE c.campo = 'segmento';

CREATE OR REPLACE VIEW `bi_dim_procedencia` AS
SELECT c.id AS procedencia_id, c.valor AS procedencia, c.activo AS procedencia_activa
FROM catalogo_valores c WHERE c.campo = 'mercado';

CREATE OR REPLACE VIEW `bi_dim_linea` AS
SELECT c.id AS linea_id,
       -- "C1. Ampliar el acceso..." → código y texto en columnas aparte, para
       -- que el eje del gráfico quepa y el detalle siga disponible.
       TRIM(SUBSTRING_INDEX(c.valor, '.', 1)) AS linea_codigo,
       c.valor AS linea, c.activo AS linea_activa
FROM catalogo_valores c WHERE c.campo = 'linea_estrategica';

CREATE OR REPLACE VIEW `bi_dim_lugar` AS
SELECT c.id AS lugar_id, c.valor AS lugar, c.campo AS tipo_lugar
FROM catalogo_valores c WHERE c.campo IN ('pais', 'ciudad');

CREATE OR REPLACE VIEW `bi_dim_organizador` AS
SELECT c.id AS organizador_id, c.valor AS organizador, c.activo AS organizador_activo
FROM catalogo_valores c WHERE c.campo = 'organizador';

CREATE OR REPLACE VIEW `bi_dim_estado` AS
SELECT 'no_realizado' AS estado, 'No realizado' AS estado_etiqueta, 1 AS estado_orden, 0 AS cuenta_como_hecho UNION ALL
SELECT 'en_ejecucion', 'En ejecución', 2, 0 UNION ALL
SELECT 'realizado',    'Realizado',    3, 1 UNION ALL
SELECT 'cancelado',    'Cancelado',    4, 0;

/**
 * Calendario. Se genera a partir de los propios eventos, así que cubre
 * exactamente los años que hay y ni uno más. Una dimensión de fecha de verdad
 * es lo que permite decir "trimestre", "fin de semana" o "semana ISO" sin
 * escribir la misma fórmula en cada medida.
 */
CREATE OR REPLACE VIEW `bi_dim_fecha` AS
SELECT d.fecha,
       YEAR(d.fecha)                        AS anio,
       QUARTER(d.fecha)                     AS trimestre,
       CONCAT(YEAR(d.fecha), '-T', QUARTER(d.fecha)) AS anio_trimestre,
       MONTH(d.fecha)                       AS mes,
       DATE_FORMAT(d.fecha, '%Y-%m')        AS anio_mes,
       MONTHNAME(d.fecha)                   AS mes_nombre,
       DAY(d.fecha)                         AS dia,
       WEEKDAY(d.fecha) + 1                 AS dia_semana,          -- 1 = lunes
       DAYNAME(d.fecha)                     AS dia_nombre,
       WEEKOFYEAR(d.fecha)                  AS semana_iso,
       (WEEKDAY(d.fecha) >= 5)              AS es_fin_de_semana
FROM (
    SELECT DATE_ADD(r.inicio, INTERVAL n.n DAY) AS fecha
    FROM (SELECT MIN(fecha_inicio) AS inicio, DATEDIFF(MAX(fecha_fin), MIN(fecha_inicio)) AS dias FROM eventos) r
    JOIN (
        SELECT (u.i + 10 * d.i + 100 * c.i + 1000 * m.i) AS n
        FROM (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
              UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) u
        CROSS JOIN (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
              UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) d
        CROSS JOIN (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
              UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
        CROSS JOIN (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) m
    ) n ON n.n <= r.dias
) d;

-- --------------------------------------------------------------------- Hechos

/**
 * Un evento por fila. Es la tabla de hechos principal.
 *
 * `aforo` sale de la columna `reuniones`, que admite un número, N/A, Pendiente
 * o un texto que dice en qué va. Aquí solo se convierte lo que es un número de
 * verdad; el resto queda en NULL para que los promedios no se ensucien con
 * ceros que no son ceros. `aforo_declarado` dice si ese dato existe, que en sí
 * mismo es un indicador de cómo de bien se está reportando.
 */
CREATE OR REPLACE VIEW `bi_hechos_eventos` AS
SELECT e.id                                   AS evento_id,
       e.nombre                               AS evento,
       e.fecha_inicio,
       e.fecha_fin,
       DATEDIFF(e.fecha_fin, e.fecha_inicio) + 1 AS duracion_dias,
       e.estado,
       e.area_id, e.tipo_accion_id AS tipo_id, e.segmento_id AS publico_id,
       e.linea_id, e.mercado_id AS procedencia_id, e.organizador_id,
       e.pais_id, e.ciudad_id,
       CASE WHEN e.reuniones REGEXP '^[0-9]+$' THEN CAST(e.reuniones AS UNSIGNED) END AS aforo,
       (e.reuniones REGEXP '^[0-9]+$')        AS aforo_declarado,
       (e.evidencia_url LIKE 'http%')         AS tiene_evidencia,
       (e.contactos_url NOT IN ('N/A', 'Pendiente')) AS tiene_contactos,
       (e.alianzas <> 'N/A')                  AS tiene_alianzas,
       e.creado_por, e.creado_en,
       -- Cuánto se planeó con antelación: del alta al primer día del evento.
       DATEDIFF(e.fecha_inicio, DATE(e.creado_en)) AS dias_de_antelacion
FROM eventos e
WHERE e.eliminado_en IS NULL;

/**
 * Un evento y un día por fila: "explota" cada evento en los días que ocupa.
 *
 * Sin esto, un festival de dos semanas cuenta igual que una charla de una
 * tarde y la carga real del equipo queda invisible. Con esto se puede medir
 * ocupación por día, solapes y cuántos frentes abiertos hay a la vez.
 */
CREATE OR REPLACE VIEW `bi_hechos_dias` AS
SELECT h.evento_id, h.evento, f.fecha, h.estado, h.area_id, h.tipo_id,
       h.publico_id, h.linea_id, h.procedencia_id,
       (f.fecha = h.fecha_inicio) AS es_dia_inicio,
       h.aforo / h.duracion_dias  AS aforo_por_dia
FROM bi_hechos_eventos h
JOIN bi_dim_fecha f ON f.fecha BETWEEN h.fecha_inicio AND h.fecha_fin;

/**
 * Puente de procedencias: un evento puede apuntar a varias.
 *
 * Va aparte porque es una relación de varios a varios; mezclarla con los
 * hechos duplicaría los eventos y todas las cuentas saldrían infladas. En el
 * informe se filtra por aquí y se cuentan los eventos distintos.
 */
CREATE OR REPLACE VIEW `bi_puente_procedencia` AS
SELECT em.evento_id, em.mercado_id AS procedencia_id,
       (em.mercado_id = e.mercado_id) AS es_principal
FROM evento_mercados em
JOIN eventos e ON e.id = em.evento_id
WHERE em.activo = 1 AND e.eliminado_en IS NULL;

/**
 * Trazabilidad: quién cambió qué y cuándo. Sirve para medir adopción real de
 * la herramienta, no solo el contenido del calendario.
 */
CREATE OR REPLACE VIEW `bi_hechos_actividad` AS
SELECT hi.id AS actividad_id, hi.evento_id, hi.usuario_id, hi.accion,
       DATE(hi.fecha) AS fecha, hi.fecha AS momento,
       u.nombre AS usuario, u.rol, u.area_id AS usuario_area_id
FROM eventos_historial hi
LEFT JOIN usuarios u ON u.id = hi.usuario_id;

/** Personas, para cruzar actividad con área. Sin correo ni contraseña. */
CREATE OR REPLACE VIEW `bi_dim_usuario` AS
SELECT u.id AS usuario_id, u.nombre AS usuario, u.rol, u.area_id, u.activo AS usuario_activo
FROM usuarios u;

-- ------------------------------------------------------- Vista plana (Excel)
-- Todo ya unido y con nombres legibles. Para quien solo quiere una tabla,
-- abrirla en Excel y hacer una tabla dinámica sin montar el modelo.
CREATE OR REPLACE VIEW `bi_plano_eventos` AS
SELECT h.evento_id, h.evento, h.fecha_inicio, h.fecha_fin, h.duracion_dias,
       es.estado_etiqueta AS estado,
       a.area, t.tipo, p.publico, li.linea_codigo, li.linea,
       me.procedencia AS procedencia_principal,
       org.organizador, ci.lugar AS ciudad, pa.lugar AS pais,
       h.aforo, h.aforo_declarado, h.tiene_evidencia, h.tiene_alianzas,
       h.dias_de_antelacion,
       YEAR(h.fecha_inicio) AS anio, QUARTER(h.fecha_inicio) AS trimestre,
       DATE_FORMAT(h.fecha_inicio, '%Y-%m') AS anio_mes
FROM bi_hechos_eventos h
JOIN bi_dim_estado es       ON es.estado = h.estado
JOIN bi_dim_area a          ON a.area_id = h.area_id
JOIN bi_dim_tipo t          ON t.tipo_id = h.tipo_id
JOIN bi_dim_publico p       ON p.publico_id = h.publico_id
JOIN bi_dim_linea li        ON li.linea_id = h.linea_id
JOIN bi_dim_procedencia me  ON me.procedencia_id = h.procedencia_id
JOIN bi_dim_organizador org ON org.organizador_id = h.organizador_id
JOIN bi_dim_lugar ci        ON ci.lugar_id = h.ciudad_id
JOIN bi_dim_lugar pa        ON pa.lugar_id = h.pais_id;
