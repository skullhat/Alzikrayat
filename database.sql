
CREATE DATABASE IF NOT EXISTS alzikrayat
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE alzikrayat;

-- Drop child tables first, because they depend on parent tables.
DROP TABLE IF EXISTS photo_tags;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS photos;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id          INT          NOT NULL AUTO_INCREMENT,
    first_name  VARCHAR(50)  NOT NULL,
    last_name   VARCHAR(50)  NOT NULL,
    email       VARCHAR(100) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    location    VARCHAR(100) NULL DEFAULT NULL,
    description TEXT         NULL,
    occupation  VARCHAR(100) NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photos (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    description TEXT         NULL,
    date_time   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_photos_file_name (file_name),
    KEY idx_photos_user_id (user_id),
    KEY idx_photos_date_time (date_time),
    CONSTRAINT fk_photos_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id        INT       NOT NULL AUTO_INCREMENT,
    photo_id  INT       NOT NULL,
    user_id   INT       NOT NULL,
    comment   TEXT      NOT NULL,
    date_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_photo_date (photo_id, date_time),
    KEY idx_comments_user_id (user_id),
    KEY idx_comments_date_time (date_time),
    CONSTRAINT fk_comments_photo
        FOREIGN KEY (photo_id) REFERENCES photos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_comments_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE photo_tags (
    id        INT       NOT NULL AUTO_INCREMENT,
    photo_id  INT       NOT NULL,
    user_id   INT       NOT NULL,
    date_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_photo_tags_photo_user (photo_id, user_id),
    KEY idx_photo_tags_user_id (user_id),
    CONSTRAINT fk_photo_tags_photo
        FOREIGN KEY (photo_id) REFERENCES photos (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_photo_tags_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
