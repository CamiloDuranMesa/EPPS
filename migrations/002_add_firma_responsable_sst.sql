-- Migration: 002_add_firma_responsable_sst.sql
-- Purpose: Add firma_responsable and firma_sst columns to entregas table
-- Date: 2026-01-10

-- Add firma_responsable column if it doesn't exist
ALTER TABLE entregas ADD COLUMN firma_responsable VARCHAR(255) AFTER firma_empleado;

-- Add firma_sst column if it doesn't exist
ALTER TABLE entregas ADD COLUMN firma_sst VARCHAR(255) AFTER firma_responsable;

-- Note: These columns will now be available in the application for storing digital signatures
-- The application already has logic to handle these columns if they exist (checked via columnaExiste function)
