-- Migración incremental: conserva todos los usuarios y relaciones existentes.
USE control_stock;

ALTER TABLE usuarios
    ADD COLUMN apellido VARCHAR(100) NULL AFTER nombre;

-- Las cuentas existentes quedan con apellido NULL. El sistema lo completa
-- únicamente después de validar sus credenciales anteriores correctamente.
