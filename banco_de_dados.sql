-- 1) Cria o banco de dados e seleciona-o
CREATE DATABASE IF NOT EXISTS `sistema`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `sistema`;

-- 2) Tabela de usuários
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome`     VARCHAR(255) NOT NULL,
  `usuario`  VARCHAR(100) NOT NULL UNIQUE,
  `senha`    VARCHAR(255) NOT NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 3) Tabela de itens (agora com a coluna `quantidade`)
CREATE TABLE IF NOT EXISTS `itens` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `nome_item`     VARCHAR(100) NOT NULL,
  `descricao`     TEXT,
  `localizacao`   VARCHAR(255),
  `categoria`     VARCHAR(50) NOT NULL,
  `quantidade`    INT UNSIGNED NOT NULL DEFAULT 1,
  `image1`        LONGBLOB        NULL,
  `image2`        LONGBLOB        NULL,
  `image3`        LONGBLOB        NULL,
  `data_criacao`  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_itens_usuario`
    FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 4) Tabela de trocas
CREATE TABLE IF NOT EXISTS `trocas` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `solicitante_id`      INT UNSIGNED NOT NULL,
  `destinatario_id`     INT UNSIGNED NOT NULL,
  `item_solicitado_id`  INT UNSIGNED NOT NULL,
  `item_ofertado_id`    INT UNSIGNED NOT NULL,
  `status`              ENUM('pendente','aceita','recusada') DEFAULT 'pendente',
  `data_criacao`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unq_solic` (`solicitante_id`,`item_solicitado_id`,`item_ofertado_id`),
  FOREIGN KEY (`solicitante_id`)     REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`destinatario_id`)    REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_solicitado_id`) REFERENCES `itens`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_ofertado_id`)   REFERENCES `itens`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 5) Tabela de avaliações de produtos
CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `produto_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `nota`       TINYINT UNSIGNED NOT NULL CHECK(nota BETWEEN 1 AND 5),
  `comentario` TEXT,
  `criado_em`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`produto_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_avaliacoes_produto`
    FOREIGN KEY (`produto_id`) REFERENCES `itens`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_avaliacoes_usuario`
    FOREIGN KEY (`user_id`)    REFERENCES `usuarios`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 6) Tabela de vídeos (caso utilize feed de vídeo)
CREATE TABLE IF NOT EXISTS `video` (
  `video_id`   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT UNSIGNED NOT NULL,
  `item_id`    INT UNSIGNED NOT NULL,
  `descricao`  TEXT              NULL,
  `categoria`  VARCHAR(50)       NOT NULL DEFAULT '',
  `location`   VARCHAR(255)      NOT NULL,
  `thumbnail`  VARCHAR(255)      NULL,
  `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_video_usuario` (`usuario_id`),
  INDEX `idx_video_item`    (`item_id`),
  CONSTRAINT `fk_video_usuario`
    FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios`(`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_video_item`
    FOREIGN KEY (`item_id`)
    REFERENCES `itens`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 7) Tabela de comentários em vídeos
CREATE TABLE IF NOT EXISTS `video_comentarios` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `video_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `comentario` TEXT              NOT NULL,
  `criado_em`  TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
  INDEX (`video_id`),
  INDEX (`user_id`),
  CONSTRAINT `fk_videocmts_video`
    FOREIGN KEY (`video_id`) REFERENCES `video`(`video_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_videocmts_usuario`
    FOREIGN KEY (`user_id`)  REFERENCES `usuarios`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- 8) Tabela de curtidas em vídeos
CREATE TABLE IF NOT EXISTS `video_likes` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `video_id`  INT UNSIGNED NOT NULL,
  `user_id`   INT UNSIGNED NOT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`video_id`),
  INDEX (`user_id`),
  UNIQUE (`video_id`,`user_id`),
  CONSTRAINT `fk_videolikes_video`
    FOREIGN KEY (`video_id`) REFERENCES `video`(`video_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_videolikes_usuario`
    FOREIGN KEY (`user_id`)  REFERENCES `usuarios`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE IF NOT EXISTS `mensagens` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `remetente_id`  INT UNSIGNED      NOT NULL,
  `destinatario_id` INT UNSIGNED    NOT NULL,
  `mensagem`      TEXT              NOT NULL,
  `created_at`    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`remetente_id`),
  INDEX (`destinatario_id`),
  CONSTRAINT `fk_mensagens_remetente`
    FOREIGN KEY (`remetente_id`)
    REFERENCES `usuarios`(`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_mensagens_destinatario`
    FOREIGN KEY (`destinatario_id`)
    REFERENCES `usuarios`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuario_temas` (
  `usuario_id` INT UNSIGNED NOT NULL PRIMARY KEY,
  `tema`       VARCHAR(50) NOT NULL DEFAULT 'paleta1',
  `atualizado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4;

ALTER TABLE mensagens ADD COLUMN oculto_para JSON DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `mensagens_exclusoes` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `mensagem_id`  INT UNSIGNED NOT NULL,
  `usuario_id`   INT UNSIGNED NOT NULL,
  `data_exclusao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (`mensagem_id`) REFERENCES `mensagens`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`(`id`)  ON DELETE CASCADE,

  INDEX (`mensagem_id`),
  INDEX (`usuario_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
