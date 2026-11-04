-- Add Campus column to user_accounts table
ALTER TABLE user_accounts ADD COLUMN Campus VARCHAR(100) AFTER Department;
