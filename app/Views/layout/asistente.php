<?php
/**
 * Asistente de ayuda: botón flotante y panel de conversación.
 *
 * Solo se incluye si hay clave configurada (lo decide base.php), así que la
 * instalación sin clave no enseña un botón que no haría nada.
 *
 * Los textos que pinta el JavaScript viajan en la configuración del componente,
 * como en el resto de la aplicación.
 */
$txt = [
    'error'     => t('No se pudo enviar. Inténtalo de nuevo.'),
    'pensando'  => t('Pensando…'),
];
?>
<div class="cro-asis" x-data="asistente(<?= h(json_encode($txt)) ?>)" x-cloak>

  <button type="button" class="cro-asis-btn" @click="alternar()"
          :aria-expanded="abierto ? 'true' : 'false'" aria-controls="cro-asis-panel"
          :title="abierto ? <?= h(json_encode(t('Cerrar la ayuda'))) ?> : <?= h(json_encode(t('Abrir la ayuda'))) ?>">
    <span x-show="!abierto" aria-hidden="true">?</span>
    <span x-show="abierto" x-cloak aria-hidden="true">✕</span>
    <span class="sr-only"><?= h(t('Ayuda')) ?></span>
  </button>

  <div id="cro-asis-panel" class="cro-asis-panel card" x-show="abierto" x-cloak
       x-transition.opacity.duration.150ms role="dialog" aria-labelledby="cro-asis-tit">
    <div class="cro-asis-cab">
      <div>
        <p class="cro-asis-tit" id="cro-asis-tit"><?= h(t('Ayuda del calendario')) ?></p>
        <p class="cro-asis-sub"><?= h(t('Pregunta cómo se usa la aplicación')) ?></p>
      </div>
      <button type="button" class="cro-asis-limpiar" @click="limpiar()" x-show="mensajes.length"
              title="<?= h(t('Empezar de nuevo')) ?>"><?= h(t('Borrar')) ?></button>
    </div>

    <div class="cro-asis-hilo" x-ref="hilo">
      <!-- Estado inicial: en vez de un panel vacío, tres ejemplos de lo que sí sabe responder. -->
      <div class="cro-asis-vacio" x-show="!mensajes.length">
        <p><?= h(t('Puedo explicarte cómo funciona el calendario y contarte qué hay registrado. Por ejemplo:')) ?></p>
        <?php foreach ([
            t('¿Cómo registro un evento nuevo?'),
            t('¿Qué significa Pendiente en el seguimiento?'),
            t('¿Cuántos eventos hay este año?'),
        ] as $ej): ?>
        <button type="button" class="cro-asis-ejemplo" @click="usarEjemplo(<?= h(json_encode($ej)) ?>)"><?= h($ej) ?></button>
        <?php endforeach; ?>
      </div>

      <template x-for="(m, i) in mensajes" :key="i">
        <div class="cro-asis-msg" :class="m.rol === 'persona' ? 'cro-asis-mia' : 'cro-asis-suya'">
          <p x-text="m.texto"></p>
        </div>
      </template>

      <div class="cro-asis-msg cro-asis-suya cro-asis-cargando" x-show="enviando" x-cloak>
        <span></span><span></span><span></span>
      </div>

      <p class="cro-asis-error" x-show="error" x-cloak x-text="error" role="alert"></p>
    </div>

    <form class="cro-asis-pie" @submit.prevent="enviar()">
      <input x-ref="campo" class="cro-asis-campo" x-model="texto" maxlength="500" autocomplete="off"
             :disabled="enviando" placeholder="<?= h(t('Escribe tu pregunta…')) ?>"
             aria-label="<?= h(t('Escribe tu pregunta…')) ?>">
      <button type="submit" class="cro-asis-enviar" :disabled="enviando || !texto.trim()"
              title="<?= h(t('Enviar')) ?>">→<span class="sr-only"><?= h(t('Enviar')) ?></span></button>
    </form>
    <p class="cro-asis-nota"><?= h(t('Respuestas generadas por IA: pueden equivocarse.')) ?></p>
  </div>
</div>
