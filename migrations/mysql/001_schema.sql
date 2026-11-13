CREATE TABLE users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(40)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    role           VARCHAR(20)  NOT NULL,
    totp_secret    VARCHAR(64)  NULL,
    totp_last_step BIGINT       NULL,
    active         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at     CHAR(19)     NOT NULL,
    last_login_at  CHAR(19)     NULL,
    CONSTRAINT users_role CHECK (role IN ('admin', 'moderador', 'suporte'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(40) NOT NULL,
    ip           VARCHAR(45) NOT NULL,
    success      TINYINT(1)  NOT NULL,
    attempted_at CHAR(19)    NOT NULL,
    INDEX login_attempts_user (username, attempted_at),
    INDEX login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE whitelist (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    steam_id    CHAR(17)     NOT NULL UNIQUE,
    player_name VARCHAR(64)  NOT NULL,
    note        VARCHAR(200) NOT NULL DEFAULT '',
    added_by    INT UNSIGNED NOT NULL,
    created_at  CHAR(19)     NOT NULL,
    CONSTRAINT whitelist_user FOREIGN KEY (added_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE bans (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    steam_id      CHAR(17)     NOT NULL,
    player_name   VARCHAR(64)  NOT NULL,
    reason        VARCHAR(200) NOT NULL,
    created_by    INT UNSIGNED NOT NULL,
    created_at    CHAR(19)     NOT NULL,
    expires_at    CHAR(19)     NULL,
    revoked_at    CHAR(19)     NULL,
    revoked_by    INT UNSIGNED NULL,
    revoke_reason VARCHAR(200) NULL,
    INDEX bans_steam (steam_id),
    INDEX bans_active (revoked_at, expires_at),
    CONSTRAINT bans_creator FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT bans_revoker FOREIGN KEY (revoked_by) REFERENCES users (id),
    CONSTRAINT bans_revoke_pair CHECK ((revoked_at IS NULL) = (revoked_by IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(40)  NOT NULL,
    target_type VARCHAR(20)  NOT NULL DEFAULT '',
    target_id   VARCHAR(40)  NOT NULL DEFAULT '',
    details     TEXT         NOT NULL,
    ip          VARCHAR(45)  NOT NULL,
    created_at  CHAR(19)     NOT NULL,
    INDEX audit_created (created_at),
    INDEX audit_user (user_id, created_at),
    CONSTRAINT audit_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
