-- Migration: 003_add_sst_nombre.sql
-- Purpose: Add sst_nombre column to entregas to store nombre del representante SST
-- Date: 2026-06-05

ALTER TABLE entregas ADD COLUMN IF NOT EXISTS sst_nombre VARCHAR(150)
    COMMENT 'Nombre del representante SST en el momento de la entrega' AFTER sst_id;

-- This column is optional and will default to NULL if not provided.
