--Creating the dataabse
CREATE DATABASE IF NOT EXISTS textile_inventory;
USE textile_inventory;

-- Tabla rollos de tela
CREATE TABLE fabric_rolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fabric_type VARCHAR(100) NOT NULL,
    color VARCHAR(50) NOT NULL,
    original_length DECIMAL(10,2) NOT NULL,
    current_length DECIMAL(10,2) NOT NULL,
    entry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla para le registro de ventas hechas
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roll_id INT NOT NULL,
    meters_sold DECIMAL(10,2) NOT NULL,
    sale_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (roll_id) REFERENCES fabric_rolls(id)
);

-- Indices mejora de rendimiento
CREATE INDEX idx_fabric_type ON fabric_rolls(fabric_type);
CREATE INDEX idx_entry_date ON fabric_rolls(entry_date);
CREATE INDEX idx_current_length ON fabric_rolls(current_length);
CREATE INDEX idx_sale_date ON sales(sale_date);

-- Insertando datos de ejemplo
INSERT INTO fabric_rolls (fabric_type, color, original_length, current_length, entry_date) VALUES
('Algodón', 'Blanco', 50.00, 45.50, '2025-06-01'),
('Lino', 'Beige', 30.00, 28.75, '2025-06-05'),
('Seda', 'Negro', 25.00, 25.00, '2025-06-10'),
('Algodón', 'Azul', 40.00, 35.25, '2025-06-15');