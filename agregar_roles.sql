USE control_stock;

ALTER TABLE usuarios
ADD COLUMN rol ENUM('admin', 'vendedor', 'cliente') NOT NULL DEFAULT 'cliente';
