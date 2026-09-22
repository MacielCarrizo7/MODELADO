-- Migración incremental. Conserva todas las ventas, productos y usuarios.
USE control_stock;

ALTER TABLE ventas
    ADD COLUMN cliente_id INT NULL AFTER usuario_id,
    ADD COLUMN estado ENUM('ACTIVA', 'MODIFICADA', 'CANCELADA') NOT NULL DEFAULT 'ACTIVA' AFTER fecha,
    ADD COLUMN fecha_modificacion DATETIME NULL AFTER estado,
    ADD COLUMN motivo_cancelacion VARCHAR(500) NULL AFTER fecha_modificacion,
    ADD INDEX idx_ventas_cliente_fecha (cliente_id, fecha),
    ADD INDEX idx_ventas_filtros (producto_id, estado, fecha),
    ADD CONSTRAINT fk_ventas_cliente
        FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE SET NULL;

CREATE TABLE venta_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    usuario_id INT NULL,
    tipo VARCHAR(40) NOT NULL,
    cantidad_anterior INT NULL,
    cantidad_nueva INT NULL,
    total_anterior DECIMAL(10,2) NULL,
    total_nuevo DECIMAL(10,2) NULL,
    estado_anterior VARCHAR(20) NOT NULL,
    estado_nuevo VARCHAR(20) NOT NULL,
    motivo VARCHAR(500) NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_historial_venta_fecha (venta_id, fecha),
    CONSTRAINT fk_historial_venta FOREIGN KEY (venta_id) REFERENCES ventas(id),
    CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
