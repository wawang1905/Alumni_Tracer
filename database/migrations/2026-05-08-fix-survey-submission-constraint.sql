-- Migration: Fix survey submission constraint to allow only 1 submission per account per survey
-- Date: 2026-05-08
-- Description: Remove survey_number from unique constraint to prevent multiple submissions per user per survey

USE alumni_tracer;

-- Drop the old constraint that allowed multiple submissions per user with different survey_number
ALTER TABLE `responses` DROP INDEX `unique_response_per_batch`;

-- Add new constraint that ensures only 1 submission per user per survey
ALTER TABLE `responses` ADD UNIQUE KEY `unique_response_per_survey` (`survey_id`, `user_email`);
