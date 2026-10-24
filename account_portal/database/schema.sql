CREATE DATABASE IF NOT EXISTS account_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE account_portal;

-- PARENT ENTITY: one user can own many account records (and make many offers)
CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- LOOKUP: one game has many account records
CREATE TABLE IF NOT EXISTS games (
    game_id   INT AUTO_INCREMENT PRIMARY KEY,
    game_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO games (game_name) VALUES
    ('Genshin Impact'), ('Zenless Zone Zero'), ('Mobile Legends');

-- CHILD ENTITY: bookkeeping record that becomes a market listing when published
CREATE TABLE IF NOT EXISTS accounts (
    account_id       INT AUTO_INCREMENT PRIMARY KEY,
    seller_id        INT NOT NULL,                       -- the trader (FK users)
    game_id          INT NOT NULL,                       -- FK games
    pseudonym        VARCHAR(100) NOT NULL,
    selling_price    DECIMAL(10,2) NOT NULL,             -- Philippine peso
    account_level    INT NOT NULL,                       -- AR 1-60 / Interknot 1-60 / ML 1-300
    rank_tier        ENUM('Mythic','Legend','Epic') NULL,-- Mobile Legends only
    featured_items   TEXT NULL,                          -- 5* characters, or valuable skins (ML)
    description      TEXT NULL,
    birthday_binded  TINYINT(1) NOT NULL DEFAULT 0,      -- Genshin / ZZZ
    username_binded  TINYINT(1) NOT NULL DEFAULT 0,      -- Genshin / ZZZ
    email_binded     TINYINT(1) NOT NULL DEFAULT 0,      -- Mobile Legends
    -- SELLER-EXCLUSIVE (never selected by market queries)
    account_password VARCHAR(255) NOT NULL,              -- AES-256 encrypted
    user_notes       TEXT NULL,
    last_updated     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- MARKET
    status           ENUM('draft','available','sold') NOT NULL DEFAULT 'draft',
    trade_posted     DATETIME NULL,                      -- set when published
    CONSTRAINT fk_acc_seller FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_acc_game   FOREIGN KEY (game_id)   REFERENCES games(game_id),
    INDEX idx_market (status, selling_price)
) ENGINE=InnoDB;

-- one listing has many offers, one buyer has many offers
CREATE TABLE IF NOT EXISTS offers (
    offer_id   INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    buyer_id   INT NOT NULL,
    status     ENUM('pending','cancelled','accepted','declined') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_off_acc   FOREIGN KEY (account_id) REFERENCES accounts(account_id) ON DELETE CASCADE,
    CONSTRAINT fk_off_buyer FOREIGN KEY (buyer_id)   REFERENCES users(user_id)       ON DELETE CASCADE
) ENGINE=InnoDB;
