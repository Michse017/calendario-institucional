-- Calendario Institucional · esquema completo e idempotente.
-- Probado en MariaDB 10.4+ / 11 y MySQL 8.
--
-- Un único archivo en lugar de una cadena de migraciones: el proyecto arranca
-- desde cero, así que no hay bases antiguas que ir actualizando paso a paso.

CREATE DATABASE IF NOT EXISTS `calendario_demo`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `calendario_demo`;

-- ---------------------------------------------------------------------------
-- Catálogos
--
-- Una sola tabla para todos los campos de lista. `valor_norm` guarda el valor
-- en minúsculas, sin tildes y con los espacios colapsados, que es la clave por
-- la que se detecta que "Teatro " y "teatro" son el mismo valor.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `catalogo_valores` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `campo`      ENUM('tipo_accion','segmento','area','linea_estrategica','pais','ciudad','mercado','organizador') NOT NULL,
  `valor`      VARCHAR(255) NOT NULL COMMENT 'como se muestra',
  `valor_norm` VARCHAR(255) NOT NULL COMMENT 'minúsculas, sin tildes, espacios colapsados',
  `usos`       INT NOT NULL DEFAULT 0,
  `activo`     TINYINT(1) NOT NULL DEFAULT 1,
  `color`      VARCHAR(7) DEFAULT NULL COMMENT 'solo para campo=area',
  `creado_por` INT DEFAULT NULL,
  `creado_en`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campo_norm` (`campo`,`valor_norm`),
  KEY `idx_campo_activo_usos` (`campo`,`activo`,`usos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Usuarios
--
-- La contraseña se guarda siempre como hash de password_hash(). Nunca en claro.
-- `area_id` apunta al área que la persona representa: sin área solo consulta,
-- con área puede crear y editar los eventos de esa área.
-- La baja es lógica (`activo` = 0); nunca se borra una fila, para que el
-- historial de auditoría siga apuntando a alguien.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `nombre`         VARCHAR(120) NOT NULL,
  `correo`         VARCHAR(150) NOT NULL,
  `contrasena`     VARCHAR(255) NOT NULL COMMENT 'hash de password_hash(), nunca texto plano',
  `rol`            ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  `area_id`        INT DEFAULT NULL COMMENT 'catalogo_valores.id con campo=area; NULL = solo consulta',
  `activo`         TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'baja lógica',
  `creado_en`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_correo` (`correo`),
  KEY `idx_usuarios_area` (`area_id`),
  CONSTRAINT `fk_usuarios_area` FOREIGN KEY (`area_id`) REFERENCES `catalogo_valores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Intentos de acceso
--
-- Sostiene el límite de intentos fallidos. Se registran aciertos y fallos para
-- poder auditar, y los antiguos se purgan al consultar.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intentos_acceso` (
  `id`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `correo`    VARCHAR(150) NOT NULL,
  `ip`        VARCHAR(45) NOT NULL COMMENT 'cabe una IPv6 completa',
  `exito`     TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intentos_correo` (`correo`,`creado_en`),
  KEY `idx_intentos_ip` (`ip`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Eventos
--
-- `eliminado_en` a NULL significa vivo: la eliminación es lógica y exige un
-- motivo, de modo que la papelera conserva el contexto de por qué se borró.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventos` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(200) NOT NULL,
  `fecha_inicio`       DATE NOT NULL,
  `fecha_fin`          DATE NOT NULL,
  `estado`             ENUM('no_realizado','en_ejecucion','realizado','cancelado') NOT NULL DEFAULT 'no_realizado',
  `cancelacion_motivo` VARCHAR(500) DEFAULT NULL COMMENT 'solo cuando estado=cancelado',
  `tipo_accion_id`     INT NOT NULL,
  `tipo_accion_otro`   VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'detalle escrito cuando el tipo es Otros',
  `segmento_id`        INT NOT NULL,
  `segmento_otro`      VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'detalle escrito cuando el público es Otros',
  `area_id`            INT NOT NULL,
  `linea_id`           INT NOT NULL,
  `pais_id`            INT NOT NULL,
  `ciudad_id`          INT NOT NULL,
  `mercado_id`         INT NOT NULL,
  `organizador_id`     INT NOT NULL,
  `objetivo`           TEXT NOT NULL,
  `resultados`         TEXT NOT NULL,
  `alianzas`           TEXT NOT NULL,
  `observaciones`      TEXT NOT NULL,
  `contactos_url`      VARCHAR(500) NOT NULL COMMENT 'URL, N/A o Pendiente',
  `evidencia_url`      VARCHAR(500) NOT NULL COMMENT 'URL, N/A o Pendiente',
  `reuniones`          VARCHAR(60) NOT NULL COMMENT 'entero, N/A o Pendiente',
  `creado_por`         INT NOT NULL COMMENT 'usuarios.id',
  `actualizado_por`    INT NOT NULL COMMENT 'usuarios.id',
  `creado_en`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en`       DATETIME DEFAULT NULL COMMENT 'NULL = vivo',
  `eliminacion_motivo` VARCHAR(500) DEFAULT NULL COMMENT 'obligatorio al eliminar',
  `eliminado_por`      INT DEFAULT NULL COMMENT 'usuarios.id; vuelve a NULL al restaurar',
  PRIMARY KEY (`id`),
  KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`),
  KEY `idx_eliminado` (`eliminado_en`),
  KEY `idx_creado_por` (`creado_por`),
  KEY `idx_area_evento` (`area_id`),
  CONSTRAINT `fk_ev_tipo`   FOREIGN KEY (`tipo_accion_id`) REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_seg`    FOREIGN KEY (`segmento_id`)    REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_area`   FOREIGN KEY (`area_id`)        REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_linea`  FOREIGN KEY (`linea_id`)       REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_pais`   FOREIGN KEY (`pais_id`)        REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_ciudad` FOREIGN KEY (`ciudad_id`)      REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_merc`   FOREIGN KEY (`mercado_id`)     REFERENCES `catalogo_valores` (`id`),
  CONSTRAINT `fk_ev_org`    FOREIGN KEY (`organizador_id`) REFERENCES `catalogo_valores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Historial
--
-- Registro de auditoría: quién hizo qué y qué cambió exactamente. `cambios`
-- guarda el antes y el después de cada campo tocado, en JSON.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventos_historial` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `evento_id`  INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `accion`     ENUM('crear','editar','eliminar','restaurar','cancelar','reanudar') NOT NULL,
  `cambios`    JSON DEFAULT NULL,
  `fecha`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evento` (`evento_id`),
  KEY `idx_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Uso del asistente de ayuda
--
-- Solo cuenta peticiones para poder limitarlas por IP. NO guarda lo que la
-- gente pregunta: un asistente que archiva conversaciones es un problema de
-- privacidad que nadie pidió, y para el límite basta con la marca de tiempo.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `asistente_uso` (
  `id`        BIGINT NOT NULL AUTO_INCREMENT,
  `ip`        VARCHAR(45) NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_fecha` (`ip`,`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Procedencias del público (varias por evento)
--
-- `eventos.mercado_id` sigue guardando la principal (todas las consultas hacen
-- JOIN con ella); esta tabla añade las demás. `activo` es baja lógica: aquí no
-- se borra, se desactiva, igual que en el resto del esquema.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evento_mercados` (
  `evento_id`  INT NOT NULL,
  `mercado_id` INT NOT NULL,
  `activo`     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`evento_id`, `mercado_id`),
  KEY `idx_em_mercado` (`mercado_id`),
  CONSTRAINT `fk_em_evento`  FOREIGN KEY (`evento_id`)  REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_em_mercado` FOREIGN KEY (`mercado_id`) REFERENCES `catalogo_valores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relleno idempotente: los eventos que ya existían quedan con su principal en la
-- tabla nueva (INSERT IGNORE: no toca lo que ya esté).
INSERT IGNORE INTO `evento_mercados` (`evento_id`, `mercado_id`, `activo`)
SELECT `id`, `mercado_id`, 1 FROM `eventos`;
