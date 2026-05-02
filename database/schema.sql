CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    default_markup_percent REAL NOT NULL,
    default_club_fee_percent REAL NOT NULL,
    default_distribution_method TEXT NOT NULL,
    default_spa_tax_per_person REAL NOT NULL,
    default_spa_tax_age_threshold INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS trips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    start_date TEXT NOT NULL,
    markup_percent REAL NOT NULL,
    club_fee_percent REAL NOT NULL,
    distribution_method TEXT NOT NULL,
    spa_tax_per_person REAL NOT NULL,
    spa_tax_age_threshold INTEGER NOT NULL,
    planned_total_costs REAL NOT NULL,
    planned_total_revenue REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS trip_bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id INTEGER NOT NULL,
    category_type TEXT NOT NULL,
    participant_count INTEGER NOT NULL,
    base_price_per_person REAL NOT NULL,
    FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS trip_group_expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    amount REAL NOT NULL,
    FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id INTEGER NOT NULL,
    room_category TEXT NOT NULL,
    received_at TEXT,
    comment TEXT NOT NULL DEFAULT '',
    billing_calculated_at TEXT,
    billing_total REAL,
    FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS registration_participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    registration_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    birth_date TEXT NOT NULL,
    FOREIGN KEY(registration_id) REFERENCES registrations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS registration_billing_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    registration_id INTEGER NOT NULL,
    participant_name TEXT NOT NULL,
    category_type TEXT NOT NULL,
    price REAL NOT NULL,
    FOREIGN KEY(registration_id) REFERENCES registrations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS actual_expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trip_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    amount REAL NOT NULL,
    FOREIGN KEY(trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT,
    role TEXT NOT NULL DEFAULT 'user',
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    purpose TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_auth_tokens_email ON auth_tokens(email);
