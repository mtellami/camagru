CREATE TABLE users (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  username VARCHAR(30) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  verified_at TIMESTAMPTZ,
  notify_comments BOOLEAN NOT NULL DEFAULT TRUE,
  auth_version INTEGER NOT NULL DEFAULT 1,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX users_username_unique ON users (LOWER(username));

CREATE UNIQUE INDEX users_email_unique ON users (LOWER(email));

CREATE TABLE account_tokens (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  purpose VARCHAR(20) NOT NULL CHECK (purpose IN ('verify', 'reset', 'email')),
  token_hash CHAR(64) NOT NULL UNIQUE,
  target_email VARCHAR(254),
  expires_at TIMESTAMPTZ NOT NULL,
  consumed_at TIMESTAMPTZ
);

CREATE INDEX tokens_user_purpose ON account_tokens (user_id, purpose);

CREATE TABLE photos (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users (id),
  kind VARCHAR(8) NOT NULL CHECK (kind IN ('photo', 'gif')),
  state VARCHAR(10) NOT NULL CHECK (state IN ('staging', 'ready', 'deleting')),
  media_key CHAR(32) NOT NULL UNIQUE,
  width INTEGER NOT NULL,
  height INTEGER NOT NULL,
  frame_count INTEGER NOT NULL DEFAULT 1,
  submission_key CHAR(32) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (user_id, submission_key)
);

CREATE INDEX photos_gallery ON photos (created_at DESC, id DESC)
WHERE
  state = 'ready';

CREATE INDEX photos_owner ON photos (user_id, created_at DESC, id DESC);

CREATE TABLE likes (
  photo_id BIGINT NOT NULL REFERENCES photos (id) ON DELETE CASCADE,
  user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  PRIMARY KEY (photo_id, user_id)
);

CREATE TABLE comments (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  photo_id BIGINT NOT NULL REFERENCES photos (id) ON DELETE CASCADE,
  user_id BIGINT NOT NULL REFERENCES users (id),
  body VARCHAR(1000) NOT NULL CHECK (LENGTH(TRIM(body)) > 0),
  submission_key CHAR(32) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (user_id, submission_key)
);

CREATE INDEX comments_photo ON comments (photo_id, created_at, id);

CREATE TABLE email_outbox (
  id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  event_key VARCHAR(120) NOT NULL UNIQUE,
  user_id BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  comment_id BIGINT REFERENCES comments (id) ON DELETE CASCADE,
  payload TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  lease_until TIMESTAMPTZ,
  sent_at TIMESTAMPTZ,
  failed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX outbox_pending ON email_outbox (next_attempt_at)
WHERE
  sent_at IS NULL
  AND failed_at IS NULL;

CREATE TABLE rate_limits (
  key CHAR(64) PRIMARY KEY,
  count INTEGER NOT NULL DEFAULT 1,
  expires_at TIMESTAMPTZ NOT NULL
);
