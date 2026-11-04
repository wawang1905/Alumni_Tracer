-- Create user_accounts table if it doesn't exist
CREATE TABLE IF NOT EXISTS user_accounts (
    Email VARCHAR(255) NOT NULL PRIMARY KEY,
    First_Name VARCHAR(100) NOT NULL,
    Last_Name VARCHAR(100) NOT NULL,
    Role VARCHAR(50) NOT NULL DEFAULT 'alumni',
    College VARCHAR(100),
    Department VARCHAR(100),
    Campus VARCHAR(100),
    account_id VARCHAR(50),
    Password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
