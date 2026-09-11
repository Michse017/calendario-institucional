<?php
declare(strict_types=1);

namespace App\Core;

/** Constantes compartidas por validación, vistas, CSV y catálogos. */
final class Campos
{
    public const CATALOGOS = ['tipo_accion', 'segmento', 'area', 'linea_estrategica', 'pais', 'ciudad', 'mercado', 'organizador'];

    /** campo de catálogo → columna FK en eventos */
    public const COLUMNA = [
        'tipo_accion' => 'tipo_accion_id', 'segmento' => 'segmento_id', 'area' => 'area_id', 'linea_estrategica' => 'linea_id',
        'pais' => 'pais_id', 'ciudad' => 'ciudad_id', 'mercado' => 'mercado_id', 'organizador' => 'organizador_id',
    ];

    /**
     * Catálogos de lista cerrada: en el formulario solo se elige de las opciones existentes. Nadie inventa
     * valores escribiendo; los crea un administrador desde Admin › Catálogos. Los que NO están aquí
     * (país, ciudad, organizador) siguen con autocompletado y sí admiten un valor nuevo.
     */
    public const CERRADOS = ['tipo_accion', 'segmento', 'area', 'linea_estrategica', 'mercado'];

    /** De los cerrados, los que ofrecen la opción "Otros" con un detalle obligatorio. */
    public const CON_OTROS = ['tipo_accion', 'segmento'];

    /** Valor reservado de los catálogos con "Otros". */
    public const OTROS = 'Otros';

    /** campo con "Otros" → columna de `eventos` donde se guarda el detalle escrito. */
    public const COLUMNA_OTRO = ['tipo_accion' => 'tipo_accion_otro', 'segmento' => 'segmento_otro'];

    /**
     * Listas cerradas demasiado largas para un desplegable normal: se pintan como un buscador que filtra
     * mientras se escribe, pero solo aceptan un valor de la lista.
     */
    public const BUSCABLES = ['mercado'];

    /**
     * Listas cerradas cuyos valores son textos muy largos (la línea estratégica pasa de 170 caracteres):
     * el campo enseña solo el código y un resumen, y el panel de abajo muestra cada opción completa.
     */
    public const EXPANDIBLES = ['linea_estrategica'];

    /** Únicos campos donde N/A (o Pendiente) es válido: los de la sección "3 · Seguimiento". */
    public const ADMITEN_NA = ['objetivo', 'resultados', 'alianzas', 'observaciones', 'contactos_url', 'evidencia_url', 'reuniones'];

    /**
     * Campos que se pueden dejar en blanco: al salir del campo (y también al guardar) se rellenan con N/A,
     * de forma que quede a la vista por qué el campo dice N/A y la base siga normalizada.
     */
    public const AUTO_NA = ['resultados', 'observaciones'];

    public const ETIQUETA = [
        'nombre' => 'Nombre del evento', 'fecha_inicio' => 'Fecha inicio', 'fecha_fin' => 'Fecha fin', 'estado' => 'Estado',
        'tipo_accion' => 'Tipo de evento', 'segmento' => 'Público', 'area' => 'Área responsable', 'linea_estrategica' => 'Línea del plan cultural',
        'pais' => 'País', 'ciudad' => 'Ciudad', 'mercado' => 'Procedencia del público', 'organizador' => 'Organizador',
        'objetivo' => 'Objetivo', 'resultados' => 'Resultados', 'contactos_url' => 'Contactos (enlace)', 'reuniones' => 'Aforo estimado',
        'alianzas' => 'Alianzas', 'observaciones' => 'Observaciones', 'evidencia_url' => 'Evidencia (enlace)',
        'tipo_accion_otro' => 'Tipo de evento · cuál', 'segmento_otro' => 'Público · cuál',
    ];

    /** Ayuda breve que se muestra bajo la etiqueta de cada campo del formulario. */
    public const DESCRIPCION = [
        'nombre'            => 'Como se conoce el evento.',
        'fecha_inicio'      => 'Primer día del evento.',
        'fecha_fin'         => 'Último día. Si dura un solo día, repite la fecha de inicio.',
        'estado'            => 'Cómo va hoy. Se puede cambiar después desde la ficha.',
        'tipo_accion'       => 'Qué clase de evento es. Si no está en la lista, elige Otros.',
        'segmento'          => 'Público al que se dirige. Si no está en la lista, elige Otros.',
        'pais'              => 'País donde ocurre el evento.',
        'ciudad'            => 'Ciudad donde ocurre el evento.',
        'mercado'           => 'De dónde viene principalmente el público del evento.',
        'organizador'       => 'Quién organiza. Si no aparece en la lista, escríbelo y queda disponible.',
        'area'              => 'Área responsable del evento. Solo esa área podrá editarlo.',
        'linea_estrategica' => 'Línea del plan cultural a la que aporta este evento.',
        'objetivo'          => 'Para qué se hace y qué se busca lograr.',
        'resultados'        => 'Resultados esperados o los que ya se obtuvieron, según en qué va el evento. Si lo dejas vacío se guarda como N/A.',
        'alianzas'          => 'Con quién se hizo alianza para este evento. Si no hubo ninguna, escribe N/A con el botón.',
        'observaciones'     => 'Cualquier nota útil sobre el evento. Si lo dejas vacío se guarda como N/A.',
        'contactos_url'     => 'Enlace al listado de contactos conseguidos.',
        'reuniones'         => 'Número aproximado de asistentes.',
        'evidencia_url'     => 'Enlace a fotos, informes o soportes.',
    ];

    public const TEXTOS = ['objetivo', 'resultados', 'alianzas', 'observaciones'];
    public const ENLACES = ['contactos_url', 'evidencia_url'];

    /** Estados manuales que se eligen en el formulario. */
    public const ESTADOS_FORMULARIO = ['no_realizado' => 'No realizado', 'en_ejecucion' => 'En ejecución', 'realizado' => 'Realizado'];
    /** Todos los estados (el cancelado solo se pone con la acción "Cancelar evento"). */
    public const ESTADOS = ['no_realizado' => 'No realizado', 'en_ejecucion' => 'En ejecución', 'realizado' => 'Realizado', 'cancelado' => 'Cancelado'];
    public const ESTADO_COLOR = ['no_realizado' => '#B3B7BF', 'en_ejecucion' => '#E0A020', 'realizado' => '#2E9E5B', 'cancelado' => '#8A8F98'];

    /** Colores que se asignan en orden a las áreas nuevas. */
    public const PALETA_AREAS = ['#3A5BD9', '#0E8F8B', '#C2780A', '#7A4BD6', '#C43D6B', '#3E8E3A', '#D9483A', '#2A7FB8', '#8A6D1F', '#5B5BD6'];
    public const COLOR_NEUTRO = '#B3B7BF';

    public const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    public const MESES_CORTO = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    public const DIAS_CORTO = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    /** Cabeceras del CSV, en el orden exacto del Excel. */
    public const CSV = ['N°', 'Mes', 'Fecha Inicio', 'Fecha Fin', 'Ciudad', 'País', 'Tipo de acción', 'Segmento', 'Nombre del evento', 'Mercado', 'Organizador', 'Área Responsable', 'Objetivo', 'Línea Estratégica', 'Resultados', 'Contactos', 'Reuniones', 'Alianzas', 'Estado', 'Observaciones', 'Evidencia'];
}
