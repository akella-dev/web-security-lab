CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS students (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    group_name TEXT NOT NULL,
    session_token_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_seen_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY,
    owner_id INTEGER NOT NULL REFERENCES users(id),
    status TEXT NOT NULL,
    total INTEGER NOT NULL,
    address TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS reviews (
    id BIGSERIAL PRIMARY KEY,
    author TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE reviews ADD COLUMN IF NOT EXISTS student_id TEXT;

CREATE TABLE IF NOT EXISTS coupon_applications (
    id BIGSERIAL PRIMARY KEY,
    cart_id TEXT NOT NULL,
    code TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS submissions (
    id BIGSERIAL PRIMARY KEY,
    student_id TEXT NOT NULL,
    challenge TEXT NOT NULL,
    proof JSONB NOT NULL,
    submitted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (student_id, challenge)
);

CREATE TABLE IF NOT EXISTS lab_events (
    id BIGSERIAL PRIMARY KEY,
    type TEXT NOT NULL,
    summary TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO users (id, email, name) VALUES
    (1, 'student@example.test', 'Алексей Морозов'),
    (2, 'alice@example.test', 'Алиса Воронова')
ON CONFLICT (id) DO NOTHING;

INSERT INTO orders (id, owner_id, status, total, address) VALUES
    (1001, 1, 'processing', 12490, 'г. Екатеринбург, ул. Мира, д. 19'),
    (1002, 2, 'delivered', 4990, 'г. Москва, ул. Пчелиная, д. 10, кв. 25')
ON CONFLICT (id) DO NOTHING;

DELETE FROM reviews
WHERE student_id IS NULL
  AND NOT (author = 'Марина' AND content = 'Мёд отличный, доставка вовремя!');

INSERT INTO reviews (author, content)
SELECT 'Марина', 'Мёд отличный, доставка вовремя!'
WHERE NOT EXISTS (SELECT 1 FROM reviews WHERE student_id IS NULL);
