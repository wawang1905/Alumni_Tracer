-- Migration: Update existing surveys to allow only 1 submission per account
-- Date: 2026-05-08
-- Description: Set allow_multiple_responses to 0 for all existing surveys

USE alumni_tracer;

-- Update all existing surveys to disable multiple responses
UPDATE `surveys` SET `allow_multiple_responses` = 0 WHERE `allow_multiple_responses` = 1;
