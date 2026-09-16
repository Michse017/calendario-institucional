<?php
declare(strict_types=1);

namespace App\Demo;

use App\Core\Database;
use App\Core\Normalizador;
use App\Models\Evento;
use App\Models\Usuario;
use PDO;
use RuntimeException;

/**
 * Genera los datos de demostración del Centro Cultural Meridiano.
 *
 * Todo lo que produce es ficticio. La generación es determinista: con la misma
 * semilla salen siempre los mismos eventos, de modo que la demo recién desplegada
 * y la recién reiniciada son idénticas, y las capturas del README no cambian.
 *
 * Es una clase y no un guion suelto porque la usan tres sitios: la línea de
 * comandos, el arranque del contenedor y el botón de reinicio del panel. Un solo
 * camino para generar los datos significa que los tres dan el mismo resultado.
 */
final class Sembrador
{
    /** Fija la secuencia aleatoria: mismo número, mismos eventos. */
    private const SEMILLA = 20260911;

    /** Áreas del centro, cada una con el color con que se pinta en el calendario. */
    private const AREAS = [
        ['Programación',   '#3A5BD9'],
        ['Producción',     '#0E8F8B'],
        ['Comunicaciones', '#C2780A'],
        ['Educación',      '#7A4BD6'],
        ['Administración', '#3E8E3A'],
    ];

    /**
     * Bolsa ponderada de áreas. Repetir un nombre aumenta su probabilidad, de
     * modo que la agenda se parezca a la de un centro real: casi la mitad de lo
     * que pasa es programación, y administración apenas asoma.
     */
    private const PESOS_AREA = [
        'Programación', 'Programación', 'Programación', 'Programación', 'Programación', 'Programación', 'Programación',
        'Educación', 'Educación', 'Educación', 'Educación',
        'Comunicaciones', 'Comunicaciones', 'Comunicaciones',
        'Producción', 'Producción', 'Producción',
        'Administración',
    ];

    private const CATALOGOS = [
        'tipo_accion' => [
            'Concierto', 'Exposición', 'Taller', 'Función de teatro', 'Conferencia',
            'Festival', 'Residencia artística', 'Visita guiada', 'Proyección', 'Reunión',
        ],
        'segmento' => [
            'Familiar', 'Infantil', 'Juvenil', 'Adulto', 'Adulto mayor',
            'Comunidad educativa', 'Público general', 'Profesional',
        ],
        'mercado'     => ['Barrio', 'Municipal', 'Regional', 'Nacional', 'Internacional'],
        'pais'        => ['Andalia'],
        'ciudad'      => ['Puerto Sereno', 'Valdecanto', 'Ribalta'],
        'organizador' => ['Centro Cultural Meridiano', 'Red de Bibliotecas', 'Asociación Vecinal Ribalta', 'Compañía La Veleta'],
        'linea_estrategica' => [
            'C1. Ampliar el acceso de la ciudadanía a la programación cultural, con especial atención a los barrios con menos oferta.',
            'C2. Acompañar la creación local ofreciendo espacios de ensayo, residencia y exhibición a artistas del territorio.',
            'C3. Convertir el centro en un lugar de encuentro cotidiano, más allá de la asistencia puntual a un espectáculo.',
            'P1. Construir públicos nuevos desde la educación, trabajando con centros escolares durante todo el curso.',
            'P2. Cuidar y difundir la memoria cultural del municipio mediante archivo, exposición y publicación.',
        ],
    ];

    /**
     * Plantillas de evento por área: nombre, tipo, público y objetivo. Con esto
     * los datos generados se leen como los de una institución real y no como
     * relleno.
     *
     * @var array<string,array<int,array{0:string,1:string,2:string,3:string}>>
     */
    private const PLANTILLAS = [
        'Programación' => [
            ['Ciclo de jazz en el patio', 'Concierto', 'Público general', 'Ocupar el patio en las noches de verano con formaciones pequeñas.'],
            ['Temporada de teatro contemporáneo', 'Función de teatro', 'Adulto', 'Traer tres compañías de fuera del circuito habitual.'],
            ['Noche de cine mudo con música en directo', 'Proyección', 'Público general', 'Recuperar el repertorio silente con acompañamiento en vivo.'],
            ['Festival de músicas del mundo', 'Festival', 'Familiar', 'Reunir durante un fin de semana a grupos de cinco tradiciones distintas.'],
            ['Residencia de danza contemporánea', 'Residencia artística', 'Profesional', 'Ceder la sala grande a una compañía durante tres semanas.'],
            ['Recital de piano de invierno', 'Concierto', 'Adulto mayor', 'Programar repertorio clásico en horario de tarde.'],
            ['Muestra de teatro de calle', 'Función de teatro', 'Familiar', 'Sacar la programación a la plaza durante las fiestas.'],
            ['Ciclo de cine documental', 'Proyección', 'Juvenil', 'Proyectar seis documentales con coloquio posterior.'],
        ],
        'Producción' => [
            ['Montaje de la exposición de otoño', 'Exposición', 'Público general', 'Preparar sala, iluminación y seguros de las obras.'],
            ['Revisión técnica del equipo de sonido', 'Reunión', 'Profesional', 'Comprobar el estado del equipo antes de la temporada.'],
            ['Exposición de fotografía del archivo municipal', 'Exposición', 'Público general', 'Mostrar cien fotografías inéditas del archivo.'],
            ['Instalación sonora en la sala pequeña', 'Exposición', 'Juvenil', 'Acoger una pieza de arte sonoro durante un mes.'],
            ['Exposición colectiva de artistas locales', 'Exposición', 'Público general', 'Dar sala a doce creadores del municipio.'],
        ],
        'Comunicaciones' => [
            ['Presentación de la temporada', 'Conferencia', 'Profesional', 'Presentar la programación del año a prensa y colaboradores.'],
            ['Campaña de abonos de temporada', 'Conferencia', 'Adulto', 'Explicar las ventajas del abono y captar suscriptores.'],
            ['Jornada de puertas abiertas', 'Visita guiada', 'Familiar', 'Abrir bambalinas, camerinos y taller al público.'],
            ['Encuentro con medios locales', 'Reunión', 'Profesional', 'Mantener la relación con la prensa del municipio.'],
            ['Lanzamiento del boletín cultural', 'Conferencia', 'Público general', 'Estrenar el boletín mensual de actividades.'],
        ],
        'Educación' => [
            ['Taller de iniciación al teatro', 'Taller', 'Infantil', 'Acercar la escena a niñas y niños de ocho a doce años.'],
            ['Programa escolar de visitas', 'Visita guiada', 'Comunidad educativa', 'Recibir a los centros del municipio durante el curso.'],
            ['Taller de escritura creativa', 'Taller', 'Juvenil', 'Trabajar la escritura breve con adolescentes.'],
            ['Curso de introducción a la historia del arte', 'Conferencia', 'Adulto mayor', 'Ofrecer ocho sesiones abiertas los jueves por la tarde.'],
            ['Taller de radio para adolescentes', 'Taller', 'Juvenil', 'Producir un programa mensual hecho por el alumnado.'],
            ['Laboratorio de creación en familia', 'Taller', 'Familiar', 'Crear en común entre adultos y menores un sábado al mes.'],
        ],
        'Administración' => [
            ['Consejo de dirección', 'Reunión', 'Profesional', 'Revisar la marcha del centro y aprobar decisiones.'],
            ['Auditoría anual de cuentas', 'Reunión', 'Profesional', 'Someter las cuentas del ejercicio a revisión externa.'],
            ['Planificación presupuestaria', 'Reunión', 'Profesional', 'Preparar el presupuesto del ejercicio siguiente.'],
        ],
    ];

    private const CUENTAS = [
        ['Ana Torres',   'ana.torres@meridiano.demo',   'admin',   null],
        ['Carlos Mena',  'carlos.mena@meridiano.demo',  'usuario', 'Programación'],
        ['Lucía Ferrer', 'lucia.ferrer@meridiano.demo', 'usuario', 'Comunicaciones'],
    ];

    public const CONTRASENA_DEMO = 'demo1234';

    private const MESES = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /**
     * Siembra la demostración.
     *
     * @param bool $forzar      vacía el contenido anterior antes de sembrar
     * @param bool $conUsuarios recrea también las cuentas de ejemplo
     * @return array{catalogos:int,usuarios:int,eventos:int,cancelados:int,omitido:bool}
     */
    public static function sembrar(bool $forzar = false, bool $conUsuarios = false): array
    {
        $pdo = Database::pdo();
        $resumen = ['catalogos' => 0, 'usuarios' => 0, 'eventos' => 0, 'cancelados' => 0, 'omitido' => false];

        $hay = (int) $pdo->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
        if ($hay > 0 && !$forzar) {
            $resumen['omitido'] = true;
            return $resumen;
        }

        if ($forzar) {
            self::vaciar($pdo, $conUsuarios);
        }

        $resumen['catalogos'] = self::sembrarCatalogos($pdo);
        $resumen['usuarios'] = self::sembrarUsuarios($pdo, $conUsuarios);

        $idAdmin = (int) $pdo->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        if ($idAdmin <= 0) {
            throw new RuntimeException('No hay ningún administrador. Siembra con la opción de usuarios.');
        }

        [$creados, $cancelados] = self::sembrarEventos($idAdmin);
        $resumen['eventos'] = $creados;
        $resumen['cancelados'] = $cancelados;

        return $resumen;
    }

    /**
     * Vacía el contenido.
     *
     * El orden importa por las claves foráneas; se desactivan un momento para no
     * pelear con el grafo de dependencias.
     */
    private static function vaciar(PDO $pdo, bool $incluirUsuarios): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['eventos_historial', 'eventos', 'intentos_acceso'] as $t) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
        if ($incluirUsuarios) {
            $pdo->exec('TRUNCATE TABLE `usuarios`');
            $pdo->exec('TRUNCATE TABLE `catalogo_valores`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private static function sembrarCatalogos(PDO $pdo): int
    {
        // Si el valor ya existe no se pisa el color que un administrador haya
        // elegido: solo se usa el de la semilla cuando aún no tiene ninguno.
        $ins = $pdo->prepare(
            'INSERT INTO catalogo_valores (campo, valor, valor_norm, usos, activo, color) VALUES (?, ?, ?, 0, 1, ?)
             ON DUPLICATE KEY UPDATE color = COALESCE(color, VALUES(color))'
        );

        $n = 0;
        foreach (self::AREAS as [$valor, $color]) {
            $ins->execute(['area', $valor, Normalizador::normalizar($valor), $color]);
            $n++;
        }
        $ins->execute(['area', 'N/A', 'n/a', '#B3B7BF']);
        $n++;

        foreach (self::CATALOGOS as $campo => $valores) {
            foreach ([...$valores, 'N/A'] as $v) {
                $ins->execute([$campo, Normalizador::limpiar($v), Normalizador::normalizar($v), null]);
                $n++;
            }
        }
        return $n;
    }

    private static function sembrarUsuarios(PDO $pdo, bool $forzar): int
    {
        $vacio = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0;
        if (!$forzar && !$vacio) {
            return 0;
        }
        $n = 0;
        foreach (self::CUENTAS as [$nombre, $correo, $rol, $area]) {
            if (Usuario::correoExiste($correo)) {
                continue;
            }
            Usuario::crear($nombre, $correo, self::CONTRASENA_DEMO, $rol, $area ? self::idArea($pdo, $area) : null);
            $n++;
        }
        return $n;
    }

    private static function idArea(PDO $pdo, string $nombre): int
    {
        $st = $pdo->prepare("SELECT id FROM catalogo_valores WHERE campo = 'area' AND valor_norm = ?");
        $st->execute([Normalizador::normalizar($nombre)]);
        return (int) $st->fetchColumn();
    }

    /** @return array{0:int,1:int} creados y cancelados */
    private static function sembrarEventos(int $idAdmin): array
    {
        mt_srand(self::SEMILLA);

        $anio = (int) date('Y');
        $hoy = date('Y-m-d');
        $finAnio = sprintf('%04d-12-31', $anio);
        $creados = 0;
        $cancelados = 0;

        $motivos = [
            'Aplazado a la próxima temporada por agenda de la compañía.',
            'La sala quedó ocupada por obras de mantenimiento.',
            'No se alcanzó el mínimo de inscripciones.',
        ];

        // Se recorren los doce meses y en cada uno se programan entre cuatro y
        // ocho eventos, para que el mapa de calor tenga relieve en todo el año.
        for ($mes = 1; $mes <= 12; $mes++) {
            $cuantos = mt_rand(4, 8);
            $diasDelMes = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));

            for ($i = 0; $i < $cuantos; $i++) {
                // Una de cada cinco veces se reutiliza un día cercano, para que
                // haya jornadas con dos o tres eventos y se vea la intensidad.
                $dia = mt_rand(1, $diasDelMes);
                if ($i > 0 && mt_rand(1, 5) === 1) {
                    $dia = min($diasDelMes, max(1, $dia - mt_rand(0, 2)));
                }

                $area = self::PESOS_AREA[array_rand(self::PESOS_AREA)];
                [$nombre, $tipo, $publico, $objetivo] = self::PLANTILLAS[$area][array_rand(self::PLANTILLAS[$area])];

                $duracion = match (true) {
                    $tipo === 'Exposición'           => mt_rand(8, 18),
                    $tipo === 'Festival'             => mt_rand(2, 4),
                    $tipo === 'Residencia artística' => mt_rand(6, 12),
                    default                          => mt_rand(0, 1),
                };

                $inicio = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
                $fin = date('Y-m-d', strtotime($inicio . ' +' . $duracion . ' days'));
                // Un evento no se sale del año: la vista anual lo recortaría y confunde.
                if ($fin > $finAnio) {
                    $fin = $finAnio;
                }

                $estado = match (true) {
                    $fin < $hoy     => 'realizado',
                    $inicio <= $hoy => 'en_ejecucion',
                    default         => 'no_realizado',
                };

                // Tres cancelados en todo el año, para que el filtro de estado
                // tenga algo que mostrar y se vea el motivo en la ficha.
                $motivo = null;
                if ($cancelados < 3 && $estado === 'no_realizado' && mt_rand(1, 14) === 1) {
                    $motivo = $motivos[$cancelados];
                    $cancelados++;
                }

                $id = Evento::crear([
                    'nombre'            => $nombre . ' · ' . ucfirst(self::MESES[$mes]),
                    'fecha_inicio'      => $inicio,
                    'fecha_fin'         => $fin,
                    'estado'            => $estado,
                    'tipo_accion'       => $tipo,
                    'tipo_accion_otro'  => '',
                    'segmento'          => $publico,
                    'segmento_otro'     => '',
                    'area'              => $area,
                    'linea_estrategica' => self::CATALOGOS['linea_estrategica'][array_rand(self::CATALOGOS['linea_estrategica'])],
                    'pais'              => 'Andalia',
                    'ciudad'            => self::CATALOGOS['ciudad'][array_rand(self::CATALOGOS['ciudad'])],
                    'mercado'           => $procedencia = self::CATALOGOS['mercado'][array_rand(self::CATALOGOS['mercado'])],
                    // Uno de cada tres eventos llega a más de una procedencia: la siguiente de la
                    // lista, en ciclo. Se decide con $i y no con mt_rand para no mover la secuencia
                    // aleatoria del resto de la semilla.
                    'mercados_extra'    => $i % 3 === 0
                        ? [self::CATALOGOS['mercado'][(array_search($procedencia, self::CATALOGOS['mercado'], true) + 1) % count(self::CATALOGOS['mercado'])]]
                        : [],
                    'organizador'       => self::CATALOGOS['organizador'][array_rand(self::CATALOGOS['organizador'])],
                    'objetivo'          => $objetivo,
                    'resultados'        => $estado === 'realizado'
                        ? 'Se cumplió el aforo previsto y se recogieron valoraciones del público.'
                        : 'Pendiente',
                    'alianzas'          => mt_rand(1, 3) === 1 ? 'Red de Bibliotecas del municipio' : 'N/A',
                    'observaciones'     => 'N/A',
                    'contactos_url'     => 'N/A',
                    'evidencia_url'     => $estado === 'realizado' ? 'https://archivo.meridiano.demo/' . $mes . '-' . $i : 'Pendiente',
                    'reuniones'         => (string) (mt_rand(1, 10) === 1 ? 'N/A' : mt_rand(20, 400)),
                ], $idAdmin);
                $creados++;

                if ($motivo !== null) {
                    Evento::cancelar($id, $motivo, $idAdmin);
                }
            }
        }

        return [$creados, $cancelados];
    }
}
