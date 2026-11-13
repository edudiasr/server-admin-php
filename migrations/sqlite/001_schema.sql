CREATE TABLE users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    username       TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash  TEXT    NOT NULL,
    role           TEXT    NOT NULL CHECK (role IN ('admin', 'moderador', 'suporte')),
    totp_secret    TEXT    NULL,
    totp_last_step INTEGER NULL,
    active         INTEGER NOT NULL DEFAULT 1,
    created_at     TEXT    NOT NULL,
    last_login_at  TEXT    NULL
);

CREATE TABLE login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    username     TEXT    NOT NULL,
    ip           TEXT    NOT NULL,
    success      INTEGER NOT NULL,
    attempted_at TEXT    NOT NULL
);
CREATE INDEX login_attempts_user ON login_attempts (username, attempted_at);
CREATE INDEX login_attempts_ip ON login_attempts (ip, attempted_at);

CREATE TABLE whitelist (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    steam_id    TEXT    NOT NULL UNIQUE,
    player_name TEXT    NOT NULL,
    note        TEXT    NOT NULL DEFAULT '',
    added_by    INTEGER NOT NULL REFERENCES users (id),
    created_at  TEXT    NOT NULL
);

CREATE TABLE bans (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    steam_id      TEXT    NOT NULL,
    player_name   TEXT    NOT NULL,
    reason        TEXT    NOT NULL,
    created_by    INTEGER NOT NULL REFERENCES users (id),
    created_at    TEXT    NOT NULL,
    expires_at    TEXT    NULL,
    revoked_at    TEXT    NULL,
    revoked_by    INTEGER NULL REFERENCES users (id),
    revoke_reason TEXT    NULL,
    CHECK ((revoked_at IS NULL) = (revoked_by IS NULL))
);
CREATE INDEX bans_steam ON bans (steam_id);
CREATE INDEX bans_active ON bans (revoked_at, expires_at);

CREATE TABLE audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NULL REFERENCES users (id),
    action      TEXT    NOT NULL,
    target_type TEXT    NOT NULL DEFAULT '',
    target_id   TEXT    NOT NULL DEFAULT '',
    details     TEXT    NOT NULL,
    ip          TEXT    NOT NULL,
    created_at  TEXT    NOT NULL
);
CREATE INDEX audit_created ON audit_log (created_at);
CREATE INDEX audit_user ON audit_log (user_id, created_at);
