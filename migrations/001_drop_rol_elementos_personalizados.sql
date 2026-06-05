-- Migration: 001_drop_rol_elementos_personalizados.sql
-- Purpose: Remove unused role-based system and personalized elements table
-- Status: Completed - rol column and elementos_personalizados table removed
-- Date: 2026-01-10

-- Create backup of elementos_personalizados if data exists
CREATE TABLE IF NOT EXISTS elementos_personalizados_backup LIKE elementos_personalizados;
INSERT IGNORE INTO elementos_personalizados_backup SELECT * FROM elementos_personalizados;

-- Drop rol column from usuarios table (no longer used for authorization)
ALTER TABLE usuarios DROP COLUMN IF EXISTS rol;

-- Drop the unused table
DROP TABLE IF EXISTS elementos_personalizados;

-- Safety Notes:
-- 1. Uses IF EXISTS / DROP COLUMN IF EXISTS for idempotency
-- 2. No PHP code references the 'rol' column
-- 3. No PHP code references the 'elementos_personalizados' table  
-- 4. Authorization is handled at application level only
-- 5. Data backed up in elementos_personalizados_backup before deletion
