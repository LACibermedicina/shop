-- =============================================================
--  shop.m3d.pro — MVP Telemedicina & Cibermedicina com IA
--  SQLite (WAL) | Equivalente direto do schema Prisma/Supabase
--  Tabelas core: users, appointments, medical_records, payments
--  Conformidade: LGPD/HIPAA — isolamento de dados por role aplicado
--  na camada de aplicação (App-Level RLS, ver src/Auth.php)
-- =============================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ---------- Usuários & RBAC ----------
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT    NOT NULL,                -- bcrypt (PASSWORD_DEFAULT)
    role          TEXT    NOT NULL CHECK (role IN ('PACIENTE','MEDICO','ADMIN')),
    specialty     TEXT,                            -- apenas MEDICO
    cpf           TEXT,                            -- PII: acesso restrito por role
    active        INTEGER NOT NULL DEFAULT 1,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id   INTEGER,
    entity     TEXT    NOT NULL,
    entity_id  INTEGER,
    action     TEXT    NOT NULL,
    reason     TEXT,
    ip         TEXT,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ---------- Agenda ----------
CREATE TABLE IF NOT EXISTS appointments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    patient_id  INTEGER NOT NULL REFERENCES users(id),
    doctor_id   INTEGER NOT NULL REFERENCES users(id),
    specialty   TEXT,
    scheduled_at TEXT   NOT NULL,                  -- ISO-8601
    status      TEXT    NOT NULL DEFAULT 'AGENDADO'
                CHECK (status IN ('AGENDADO','PAGO','EM_ANDAMENTO','REALIZADO','CANCELADO')),
    price_cents INTEGER NOT NULL DEFAULT 0,
    room_code   TEXT UNIQUE,                        -- código da sala de teleconsulta
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_appt_patient ON appointments(patient_id);
CREATE INDEX IF NOT EXISTS idx_appt_doctor  ON appointments(doctor_id);

-- ---------- Prontuário Eletrônico do Paciente (PEP) ----------
CREATE TABLE IF NOT EXISTS medical_records (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER NOT NULL UNIQUE REFERENCES appointments(id),
    patient_id     INTEGER NOT NULL REFERENCES users(id),
    doctor_id      INTEGER NOT NULL REFERENCES users(id),
    triage_summary TEXT,                          -- resumo estruturado da IA (anamnese prévia)
    evolution      TEXT,                          -- evolução clínica registrada pelo médico
    cid10          TEXT,                          -- código CID-10
    prescription   TEXT,                          -- texto da receita
    certificate    TEXT,                          -- texto do atestado
    pdf_path       TEXT,                          -- caminho em storage PRIVADO (data/private)
    signed_at      TEXT,                          -- momento da assinatura digital ICP-Brasil (hook)
    created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_mr_patient ON medical_records(patient_id);
CREATE INDEX IF NOT EXISTS idx_mr_doctor  ON medical_records(doctor_id);

-- ---------- Financeiro ----------
CREATE TABLE IF NOT EXISTS payments (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER REFERENCES appointments(id),
    user_id        INTEGER NOT NULL REFERENCES users(id),
    product_code   TEXT    NOT NULL,               -- CONSULTA_AVULSA | MODULO_IA_CLINICA | ...
    gateway        TEXT    NOT NULL CHECK (gateway IN ('STRIPE','MERCADO_PAGO','MOCK')),
    amount_cents   INTEGER NOT NULL,
    status         TEXT    NOT NULL DEFAULT 'PENDENTE'
                   CHECK (status IN ('PENDENTE','PAGO','FALHOU','REEMBOLSADO')),
    external_id    TEXT,                           -- id no gateway (pi_..., pix)
    paid_at        TEXT,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_pay_appt ON payments(appointment_id);

-- ---------- Triagem por IA (mensagens do chatbot) ----------
CREATE TABLE IF NOT EXISTS triage_messages (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    appointment_id INTEGER NOT NULL REFERENCES appointments(id),
    role           TEXT    NOT NULL CHECK (role IN ('user','assistant')),
    content        TEXT    NOT NULL,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ---------- Shop / Marketplace (vitrine) ----------
CREATE TABLE IF NOT EXISTS shop_products (
    code        TEXT PRIMARY KEY,                  -- CONSULTA_AVULSA, MODULO_IA_CLINICA...
    name        TEXT NOT NULL,
    description TEXT NOT NULL,
    audience    TEXT NOT NULL CHECK (audience IN ('PACIENTE','CLINICA','AMBOS')),
    price_cents INTEGER NOT NULL,
    active      INTEGER NOT NULL DEFAULT 1
);
