CREATE TABLE IF NOT EXISTS lab_requests (
    id BIGSERIAL PRIMARY KEY,
    student_id TEXT NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    method TEXT NOT NULL,
    path TEXT NOT NULL,
    headers JSONB NOT NULL DEFAULT '{}'::jsonb,
    body JSONB NOT NULL DEFAULT '{}'::jsonb,
    mode TEXT NOT NULL DEFAULT 'single',
    response_status INTEGER NOT NULL,
    response_body JSONB NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS lab_requests_student_id_id_idx ON lab_requests (student_id, id DESC);
