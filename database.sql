CREATE TABLE IF NOT EXISTS upyogkarta (
    pehchan SERIAL PRIMARY KEY,
    naam VARCHAR(50) NOT NULL UNIQUE,
    vipatra VARCHAR(100) NOT NULL UNIQUE,
    gupt_sanket VARCHAR(255) NOT NULL,
    shesh DECIMAL(10, 2) DEFAULT 100.00,
    poora_naam VARCHAR(100),
    parichay VARCHAR(500),
    chitra VARCHAR(255) DEFAULT 'default.png',
    satyapit BOOLEAN DEFAULT FALSE,
    nirmit TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lenden (
    pehchan SERIAL PRIMARY KEY,
    bhejne_wala INT NOT NULL,
    pane_wala INT NOT NULL,
    rashi DECIMAL(10, 2) NOT NULL,
    tippani VARCHAR(200),
    samay TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bhejne_wala) REFERENCES upyogkarta(pehchan),
    FOREIGN KEY (pane_wala) REFERENCES upyogkarta(pehchan)
);

CREATE TABLE IF NOT EXISTS abhilekh (
    pehchan SERIAL PRIMARY KEY,
    upyogkarta_pehchan INT,
    karya VARCHAR(255) NOT NULL,
    prishtha VARCHAR(255),
    ip_pata VARCHAR(45) NOT NULL,
    samay TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vivaran TEXT,
    FOREIGN KEY (upyogkarta_pehchan) REFERENCES upyogkarta(pehchan) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pravesh_prayaas (
    pehchan SERIAL PRIMARY KEY,
    ip_pata VARCHAR(45) NOT NULL,
    naam VARCHAR(50) NOT NULL,
    prayaas_samay TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert a test user (password: password123)
-- Hash generated via password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO upyogkarta (naam, vipatra, gupt_sanket, shesh, poora_naam, parichay, satyapit) VALUES
('testuser', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1000.00, 'Test User', 'This is a test user account.', TRUE)
ON CONFLICT (naam) DO NOTHING;

INSERT INTO upyogkarta (naam, vipatra, gupt_sanket, shesh, poora_naam, parichay, satyapit) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5000.00, 'Admin User', 'System Administrator', TRUE)
ON CONFLICT (naam) DO NOTHING;
