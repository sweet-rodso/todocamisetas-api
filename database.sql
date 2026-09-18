-- =====================================================================
-- TodoCamisetas - Base de datos del sistema de gestion de inventario B2B
-- Examen Transversal Final | Desarrollo Backend IF201IINF
-- Instituto Profesional San Sebastian
-- Autor: Rodrigo Alexis Soto Cifuentes
-- =====================================================================
-- Modelo de datos (4 tablas):
--   clientes         Tiendas minoristas B2B que compran al mayorista.
--   camisetas        Productos del inventario (stock).
--   tallas           Catalogo de tallas disponibles.
--   camiseta_tallas  Tabla pivote que resuelve la relacion muchos a muchos
--                    entre camisetas y tallas, almacenando ademas el stock.
-- =====================================================================

DROP DATABASE IF EXISTS todocamisetas;
CREATE DATABASE todocamisetas
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE todocamisetas;

-- ---------------------------------------------------------------------
-- Tabla: clientes
-- ---------------------------------------------------------------------
-- El campo "categoria" es el TIPO de cliente que determina si accede a
-- precios de oferta. Se define como ENUM y no como VARCHAR para que la
-- propia base de datos garantice que solo existan los dos valores validos.
-- "porcentaje_oferta" permite un descuento transversal a todos los
-- productos para ese cliente (ver reglas de precio en el informe).
-- ---------------------------------------------------------------------
CREATE TABLE clientes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    nombre_comercial    VARCHAR(150)    NOT NULL,
    rut                 VARCHAR(20)     NOT NULL,
    direccion           VARCHAR(200)    NOT NULL,
    categoria           ENUM('Regular', 'Preferencial') NOT NULL DEFAULT 'Regular',
    contacto_nombre     VARCHAR(150)    NOT NULL,
    contacto_email      VARCHAR(150)    NOT NULL,
    porcentaje_oferta   DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    creado_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_cliente_rut UNIQUE (rut),
    CONSTRAINT chk_porcentaje_oferta CHECK (porcentaje_oferta >= 0 AND porcentaje_oferta <= 100),
    INDEX idx_cliente_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: camisetas
-- ---------------------------------------------------------------------
-- "precio_oferta" es NULL cuando el producto no tiene oferta vigente.
-- Se usa DECIMAL y no FLOAT porque los valores monetarios requieren
-- precision exacta (FLOAT introduce errores de redondeo).
-- "cliente_id" es la relacion con clientes. Se usa ON DELETE RESTRICT
-- para que la base de datos impida eliminar un cliente que todavia tiene
-- camisetas asignadas (regla de negocio exigida en la Tarea 5).
-- ---------------------------------------------------------------------
CREATE TABLE camisetas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(200)    NOT NULL,
    club                VARCHAR(120)    NOT NULL,
    pais                VARCHAR(80)     NOT NULL,
    tipo                VARCHAR(60)     NOT NULL,
    color               VARCHAR(80)     NOT NULL,
    precio              DECIMAL(10,2)   NOT NULL,
    precio_oferta       DECIMAL(10,2)   NULL DEFAULT NULL,
    detalles            TEXT            NULL,
    codigo_producto     VARCHAR(40)     NOT NULL,
    cliente_id          INT             NULL DEFAULT NULL,
    creado_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_camiseta_codigo UNIQUE (codigo_producto),
    CONSTRAINT chk_camiseta_precio CHECK (precio > 0),
    CONSTRAINT chk_camiseta_precio_oferta CHECK (precio_oferta IS NULL OR precio_oferta > 0),
    CONSTRAINT fk_camiseta_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    INDEX idx_camiseta_cliente (cliente_id),
    INDEX idx_camiseta_club (club),
    INDEX idx_camiseta_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla: tallas
-- ---------------------------------------------------------------------
-- Catalogo independiente. Mantener las tallas en su propia tabla (y no
-- como texto dentro de camisetas) es lo que permite la relacion muchos
-- a muchos y evita duplicar informacion.
-- ---------------------------------------------------------------------
CREATE TABLE tallas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(10)     NOT NULL,
    descripcion     VARCHAR(80)     NOT NULL,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_talla_codigo UNIQUE (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tabla pivote: camiseta_tallas  (relacion MUCHOS A MUCHOS)
-- ---------------------------------------------------------------------
-- Una camiseta esta disponible en varias tallas y una talla pertenece a
-- muchas camisetas. La clave primaria compuesta (camiseta_id, talla_id)
-- impide asignar dos veces la misma talla al mismo producto.
-- ON DELETE CASCADE: al borrar una camiseta o una talla, sus asignaciones
-- se eliminan automaticamente y no quedan filas huerfanas.
-- ---------------------------------------------------------------------
CREATE TABLE camiseta_tallas (
    camiseta_id     INT             NOT NULL,
    talla_id        INT             NOT NULL,
    stock           INT             NOT NULL DEFAULT 0,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (camiseta_id, talla_id),
    CONSTRAINT chk_camiseta_talla_stock CHECK (stock >= 0),
    CONSTRAINT fk_ct_camiseta FOREIGN KEY (camiseta_id)
        REFERENCES camisetas(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_ct_talla FOREIGN KEY (talla_id)
        REFERENCES tallas(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX idx_ct_camiseta (camiseta_id),
    INDEX idx_ct_talla (talla_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DATOS DE EJEMPLO
-- =====================================================================

-- Los dos clientes principales descritos en el caso ------------------
INSERT INTO clientes
    (nombre_comercial, rut, direccion, categoria, contacto_nombre, contacto_email, porcentaje_oferta)
VALUES
    ('90minutos',  '76.543.210-8', 'Providencia, Santiago',  'Preferencial', 'Matias Herrera', 'compras@90minutos.cl',  10.00),
    ('tdeportes',  '77.112.334-5', 'Concepcion, Biobio',     'Regular',      'Camila Rojas',   'compras@tdeportes.cl',   0.00),
    ('GolStore',   '78.998.221-4', 'La Serena, Coquimbo',    'Regular',      'Ignacio Bravo',  'contacto@golstore.cl',   5.00);

-- Catalogo de tallas -------------------------------------------------
INSERT INTO tallas (codigo, descripcion) VALUES
    ('S',   'Small'),
    ('M',   'Medium'),
    ('L',   'Large'),
    ('XL',  'Extra Large'),
    ('XXL', 'Doble Extra Large'),
    ('KID', 'Talla nino');

-- Inventario de camisetas --------------------------------------------
INSERT INTO camisetas
    (titulo, club, pais, tipo, color, precio, precio_oferta, detalles, codigo_producto, cliente_id)
VALUES
    ('Camiseta Local 2025 - Seleccion Chilena', 'Seleccion Chilena', 'Chile',     'Local',           'Rojo y Azul',       45000.00, 38000.00, 'Edicion aniversario 2025',        'SCL2025L',  1),
    ('Camiseta Visita 2025 - Seleccion Chilena','Seleccion Chilena', 'Chile',     'Visita',          'Blanco y Rojo',     45000.00, NULL,     'Tela transpirable Dry-Fit',       'SCL2025V',  1),
    ('Camiseta Local 2025 - Colo Colo',         'Colo Colo',         'Chile',     'Local',           'Blanco y Negro',    42000.00, 35000.00, 'Escudo bordado',                  'CCL2025L',  2),
    ('Camiseta Local 2025 - Universidad Chile', 'Universidad de Chile','Chile',   'Local',           'Azul',              42000.00, NULL,     'Modelo oficial temporada 2025',   'UCH2025L',  2),
    ('Camiseta Local 2025 - FC Barcelona',      'FC Barcelona',      'Espana',    '3era Camiseta',   'Granate y Azul',    58000.00, 49000.00, 'Importada, edicion limitada',     'FCB2025T',  1),
    ('Camiseta Femenino Local 2025 - Colo Colo','Colo Colo',         'Chile',     'Femenino Local',  'Blanco y Negro',    40000.00, NULL,     'Corte femenino',                  'CCL2025FL', 3),
    ('Camiseta Nino Local 2025 - Real Madrid',  'Real Madrid',       'Espana',    'Nino',            'Blanco',            32000.00, 28000.00, 'Tallas infantiles',               'RMA2025N',  NULL);

-- Asignacion de tallas con su stock (relacion muchos a muchos) --------
INSERT INTO camiseta_tallas (camiseta_id, talla_id, stock) VALUES
    (1, 1, 20), (1, 2, 35), (1, 3, 30), (1, 4, 15),
    (2, 2, 18), (2, 3, 22), (2, 4, 10),
    (3, 1, 12), (3, 2, 40), (3, 3, 38), (3, 4, 20), (3, 5, 8),
    (4, 2, 25), (4, 3, 25),
    (5, 2, 14), (5, 3, 16), (5, 4, 9),
    (6, 1, 15), (6, 2, 20), (6, 3, 12),
    (7, 6, 30);

-- =====================================================================
-- Verificacion rapida de la instalacion
-- =====================================================================
-- SELECT COUNT(*) AS clientes        FROM clientes;         -- 3
-- SELECT COUNT(*) AS camisetas       FROM camisetas;        -- 7
-- SELECT COUNT(*) AS tallas          FROM tallas;           -- 6
-- SELECT COUNT(*) AS camiseta_tallas FROM camiseta_tallas;  -- 21
