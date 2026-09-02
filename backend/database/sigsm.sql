-- ============================================
-- S.I.G.S.M. — Base de datos
-- Hospital de Clínicas Dr. Manuel Quintela
-- ============================================

CREATE DATABASE IF NOT EXISTS sigsm 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE sigsm;

-- ============================================
-- DDL — Estructura de tablas
-- ============================================

CREATE TABLE IF NOT EXISTS usuario (
    ci          VARCHAR(10)  NOT NULL,
    nombre      VARCHAR(30)  NOT NULL,
    apellido    VARCHAR(30)  NOT NULL,
    user_name   VARCHAR(30)  NOT NULL,
    pass        VARCHAR(255) NOT NULL,
    rol         ENUM(
                    'root',
                    'Division_Transito',
                    'Division_Enfermeria',
                    'Gestion_General'
                ) NOT NULL,
    -- Restricciones
    CONSTRAINT pk_usuario PRIMARY KEY (ci),
    CONSTRAINT uq_user_name UNIQUE (user_name)
);

-- ============================================
-- DML — Datos de prueba
-- ============================================

-- Root (viene de fábrica, no se registra desde la app)
INSERT INTO usuario (ci, nombre, apellido, user_name, pass, rol)
VALUES ('00000000', 'Root', 'Sistema', 'root', '1234', 'root');

-- Usuarios de prueba
INSERT INTO usuario (ci, nombre, apellido, user_name, pass, rol)
VALUES 
('12345678', 'Juan',   'Pérez',      'jperez',     '1234', 'Gestion_General'),
('23456789', 'María',  'González',   'mgonzalez',  '1234', 'Division_Enfermeria'),
('34567890', 'Carlos', 'Rodríguez',  'crodriguez', '1234', 'Division_Transito');

-- ============================================
-- Restricciones no estructurales
-- ============================================
-- 1. El campo rol solo acepta los valores definidos en el ENUM
-- 2. El campo ci es la clave primaria — no puede repetirse ni ser NULL
-- 3. El campo user_name tiene restricción UNIQUE — no pueden existir dos usuarios con el mismo nombre de usuario
-- 4. Todos los campos son NOT NULL — ningún campo puede quedar vacío
-- 5. El usuario root se inserta manualmente y nunca desde la aplicación