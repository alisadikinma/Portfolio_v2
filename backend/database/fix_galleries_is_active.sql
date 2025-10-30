-- Fix existing galleries that have is_active = 0 or NULL
UPDATE galleries SET is_active = 1 WHERE is_active IS NULL OR is_active = 0;

-- Verify
SELECT id, title, is_active, award_id FROM galleries;
