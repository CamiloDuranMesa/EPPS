-- Migration: 003_add_sst_nombre.sql
-- Purpose: Add sst_nombre column to entregas to store nombre del representante SST
-- Date: 2026-06-05

ALTER TABLE entregas ADD COLUMN sst_nombre VARCHAR(150) AFTER sst_id;

-- This column is optional and will default to NULL if not provided.
