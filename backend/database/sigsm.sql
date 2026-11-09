-- ============================================
-- S.I.G.S.M. — Script de instalación completo (DDL + vistas + DML)
-- Hospital de Clínicas Dr. Manuel Quintela — Equipo ROKA
--
-- Crea la base desde cero. ATENCIÓN: si la base sigsm ya existe, la BORRA.
-- Orden de instalación:
--   1) Ejecutar este script como root (phpMyAdmin > SQL, o: mysql -u root -p < sigsm.sql)
--   2) Ejecutar usuarios_bd.sql (usuarios de base de datos y permisos)
-- ============================================

SET NAMES utf8mb4;   -- los tildes se guardan bien aunque se ejecute desde la consola

DROP DATABASE IF EXISTS sigsm;
CREATE DATABASE sigsm
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sigsm;

-- ############################################
-- 1. USUARIOS DEL SISTEMA
-- ############################################

CREATE TABLE usuario (
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
    estado      ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    -- Restricciones
    CONSTRAINT pk_usuario PRIMARY KEY (ci),
    CONSTRAINT uq_user_name UNIQUE (user_name)
);

-- Las contraseñas se guardan con password_hash() (bcrypt), nunca en texto plano.
-- Clave de todos los usuarios de prueba: 1234

-- Root (viene de fábrica, no se registra desde la app)
INSERT INTO usuario (ci, nombre, apellido, user_name, pass, rol)
VALUES ('00000000', 'Root', 'Sistema', 'root', '$2y$10$JpAELa2pqYbU8P2TXs24gOe1/142keZGiKNcto7lbI.Jb5qsxNbKG', 'root');

-- Usuarios de prueba
INSERT INTO usuario (ci, nombre, apellido, user_name, pass, rol)
VALUES 
('12345678', 'Juan',   'Pérez',      'jperez',     '$2y$10$JpAELa2pqYbU8P2TXs24gOe1/142keZGiKNcto7lbI.Jb5qsxNbKG', 'Gestion_General'),
('23456789', 'María',  'González',   'mgonzalez',  '$2y$10$JpAELa2pqYbU8P2TXs24gOe1/142keZGiKNcto7lbI.Jb5qsxNbKG', 'Division_Enfermeria'),
('34567890', 'Carlos', 'Rodríguez',  'crodriguez', '$2y$10$JpAELa2pqYbU8P2TXs24gOe1/142keZGiKNcto7lbI.Jb5qsxNbKG', 'Division_Transito');

-- Restricciones no estructurales (usuarios)
-- 1. El campo rol solo acepta los valores definidos en el ENUM
-- 2. El campo ci es la clave primaria — no puede repetirse ni ser NULL
-- 3. El campo user_name tiene restricción UNIQUE — no pueden existir dos usuarios con el mismo nombre de usuario
-- 4. Todos los campos son NOT NULL — ningún campo puede quedar vacío
-- 5. El usuario root se inserta manualmente y nunca desde la aplicación
-- 6. El campo pass guarda solo el hash de la contraseña (password_hash); se valida con password_verify
-- 7. Los usuarios no se borran: se pasan a Inactivo (no pueden iniciar sesión, pero su historial queda)
-- 8. El root no puede inhabilitarse

-- ############################################
-- 2. TRASLADOS (Módulo Ambulancias)
-- ############################################

CREATE TABLE vehiculo (
    matricula  VARCHAR(10) NOT NULL,
    tipo       ENUM('Ambulancia','Auto') NOT NULL,
    estado     ENUM('Operativo','Mantenimiento') NOT NULL DEFAULT 'Operativo',
    CONSTRAINT pk_vehiculo PRIMARY KEY (matricula)
);

-- El paciente guarda solo su identidad.
-- Los requisitos cambian de un traslado a otro, por eso van en traslado_paciente.
CREATE TABLE paciente (
    id_paciente  INT AUTO_INCREMENT,
    documento    VARCHAR(10) NOT NULL,
    CONSTRAINT pk_paciente PRIMARY KEY (id_paciente),
    CONSTRAINT uq_paciente_documento UNIQUE (documento)
);

CREATE TABLE item (
    id_item         INT AUTO_INCREMENT,
    nombre          VARCHAR(80)  NOT NULL,
    caracteristicas VARCHAR(255) NULL,
    tipo_objeto     ENUM('Organo','Muestra_biologica','Insumo_medico','Documento','Equipamiento') NOT NULL,
    CONSTRAINT pk_item PRIMARY KEY (id_item)
);

-- Datos comunes a toda petición de traslado
CREATE TABLE peticion (
    id_peticion    INT AUTO_INCREMENT,
    usuario_ci     VARCHAR(10)  NOT NULL,
    matricula      VARCHAR(10)  NOT NULL,
    chofer         VARCHAR(60)  NOT NULL,
    enfermero      VARCHAR(60)  NOT NULL,
    razon          VARCHAR(150) NOT NULL,
    destino        VARCHAR(150) NOT NULL,
    fecha_peticion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hora_salida    DATETIME     NOT NULL,
    estado         ENUM('Solicitado','En_curso','En_destino','Retornado','Cancelado') NOT NULL DEFAULT 'Solicitado',
    observaciones  VARCHAR(255) NULL,
    CONSTRAINT pk_peticion PRIMARY KEY (id_peticion),
    CONSTRAINT fk_peticion_usuario  FOREIGN KEY (usuario_ci) REFERENCES usuario(ci),
    CONSTRAINT fk_peticion_vehiculo FOREIGN KEY (matricula)  REFERENCES vehiculo(matricula)
);

-- Relación Traslado_Pacientes del MER (con los requisitos de ESE viaje)
CREATE TABLE traslado_paciente (
    id_peticion          INT        NOT NULL,
    id_paciente          INT        NOT NULL,
    requiere_oxigeno     TINYINT(1) NOT NULL DEFAULT 0,
    requiere_aislamiento TINYINT(1) NOT NULL DEFAULT 0,
    requiere_camilla     TINYINT(1) NOT NULL DEFAULT 0,
    autonomo             TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT pk_traslado_paciente PRIMARY KEY (id_peticion),
    CONSTRAINT fk_tp_peticion FOREIGN KEY (id_peticion) REFERENCES peticion(id_peticion) ON DELETE CASCADE,
    CONSTRAINT fk_tp_paciente FOREIGN KEY (id_paciente) REFERENCES paciente(id_paciente)
);

-- Relación Traslado_items del MER
CREATE TABLE traslado_item (
    id_peticion INT NOT NULL,
    id_item     INT NOT NULL,
    CONSTRAINT pk_traslado_item PRIMARY KEY (id_peticion),
    CONSTRAINT fk_ti_peticion FOREIGN KEY (id_peticion) REFERENCES peticion(id_peticion) ON DELETE CASCADE,
    CONSTRAINT fk_ti_item     FOREIGN KEY (id_item)     REFERENCES item(id_item)
);

-- ============================================
-- VISTAS
-- ============================================

-- Disponibilidad calculada de cada vehículo (no se guarda, se deduce de los traslados)
CREATE VIEW v_vehiculo_estado AS
SELECT v.matricula,
       v.tipo,
       v.estado AS estado_fisico,
       CASE
           WHEN v.estado = 'Mantenimiento' THEN 'No_disponible'
           WHEN EXISTS (SELECT 1 FROM peticion p
                        WHERE p.matricula = v.matricula
                          AND p.estado IN ('En_curso','En_destino')) THEN 'En_traslado'
           ELSE 'Disponible'
       END AS disponibilidad,
       (SELECT p.destino FROM peticion p
         WHERE p.matricula = v.matricula AND p.estado IN ('En_curso','En_destino')
         ORDER BY p.hora_salida DESC LIMIT 1) AS destino_actual,
       (SELECT p.hora_salida FROM peticion p
         WHERE p.matricula = v.matricula AND p.estado IN ('En_curso','En_destino')
         ORDER BY p.hora_salida DESC LIMIT 1) AS salida_actual
FROM vehiculo v;

-- Historial completo de traslados (se filtra por documento del paciente o por vehículo)
CREATE VIEW v_historial_traslados AS
SELECT p.id_peticion,
       p.fecha_peticion,
       p.hora_salida,
       p.estado,
       p.razon,
       p.destino,
       p.chofer,
       p.enfermero,
       p.observaciones,
       p.matricula,
       v.tipo AS tipo_vehiculo,
       p.usuario_ci,
       CONCAT(u.nombre, ' ', u.apellido) AS solicitante,
       CASE WHEN tp.id_peticion IS NOT NULL THEN 'Paciente' ELSE i.tipo_objeto END AS tipo_elemento,
       pa.documento,
       tp.requiere_oxigeno,
       tp.requiere_aislamiento,
       tp.requiere_camilla,
       tp.autonomo,
       i.nombre AS item_nombre,
       i.caracteristicas AS item_caracteristicas
FROM peticion p
JOIN vehiculo v ON v.matricula = p.matricula
JOIN usuario  u ON u.ci = p.usuario_ci
LEFT JOIN traslado_paciente tp ON tp.id_peticion = p.id_peticion
LEFT JOIN paciente          pa ON pa.id_paciente = tp.id_paciente
LEFT JOIN traslado_item     ti ON ti.id_peticion = p.id_peticion
LEFT JOIN item              i  ON i.id_item = ti.id_item;

-- ============================================
-- DML — Datos de prueba
-- ============================================

INSERT INTO vehiculo (matricula, tipo, estado) VALUES
('SBQ 1234', 'Ambulancia', 'Operativo'),
('SBQ 5678', 'Ambulancia', 'Operativo'),
('SBQ 9012', 'Ambulancia', 'Mantenimiento'),
('SBA 4455', 'Auto',       'Operativo');

INSERT INTO paciente (documento) VALUES ('45678901'), ('56789012'), ('67890123');

-- Solicitante de las peticiones de prueba: un usuario de Division_Transito si existe, si no cualquiera
SET @solicitante := (SELECT ci FROM usuario ORDER BY (rol = 'Division_Transito') DESC, ci LIMIT 1);

-- 1) Paciente con oxígeno, en curso ahora mismo (ambulancia SBQ 5678)
INSERT INTO peticion (usuario_ci, matricula, chofer, enfermero, razon, destino, hora_salida, estado, observaciones)
VALUES (@solicitante, 'SBQ 5678', 'Luis Pérez', 'Julia Méndez',
        'Falta de camas en el servicio', 'Hospital Maciel', NOW() - INTERVAL 1 HOUR, 'En_curso', 'Paciente con oxígeno portátil');
SET @p := LAST_INSERT_ID();
INSERT INTO traslado_paciente (id_peticion, id_paciente, requiere_oxigeno, requiere_aislamiento, requiere_camilla, autonomo)
VALUES (@p, (SELECT id_paciente FROM paciente WHERE documento = '45678901'), 1, 0, 1, 0);

-- 2) Órgano para trasplante, solicitado para mañana (solo ambulancia)
INSERT INTO item (nombre, caracteristicas, tipo_objeto)
VALUES ('Riñón para trasplante', 'Traslado en contenedor refrigerado', 'Organo');
SET @i := LAST_INSERT_ID();
INSERT INTO peticion (usuario_ci, matricula, chofer, enfermero, razon, destino, hora_salida, estado, observaciones)
VALUES (@solicitante, 'SBQ 1234', 'Roberto Silva', 'Valentina Herrera',
        'Trasplante renal', 'Hospital Maciel', NOW() + INTERVAL 1 DAY, 'Solicitado', 'Horario estipulado por el equipo de trasplante');
SET @p := LAST_INSERT_ID();
INSERT INTO traslado_item (id_peticion, id_item) VALUES (@p, @i);

-- 3) Paciente en camilla, traslado ya finalizado (ayer)
INSERT INTO peticion (usuario_ci, matricula, chofer, enfermero, razon, destino, hora_salida, estado, observaciones)
VALUES (@solicitante, 'SBQ 1234', 'Ana Martínez', 'Sebastián Torres',
        'Estudio programado', 'Hospital Pereira Rossell',
        NOW() - INTERVAL 1 DAY, 'Retornado', 'Estudio programado');
SET @p := LAST_INSERT_ID();
INSERT INTO traslado_paciente (id_peticion, id_paciente, requiere_oxigeno, requiere_aislamiento, requiere_camilla, autonomo)
VALUES (@p, (SELECT id_paciente FROM paciente WHERE documento = '67890123'), 0, 0, 1, 0);

-- 4) Paciente autónomo en auto, solicitado para hoy más tarde
INSERT INTO peticion (usuario_ci, matricula, chofer, enfermero, razon, destino, hora_salida, estado, observaciones)
VALUES (@solicitante, 'SBA 4455', 'Diego López', 'Andrés Morales',
        'Consulta de control', 'Policlínica Barrio Sur', NOW() + INTERVAL 3 HOUR, 'Solicitado', 'Paciente autónomo, consulta de control');
SET @p := LAST_INSERT_ID();
INSERT INTO traslado_paciente (id_peticion, id_paciente, requiere_oxigeno, requiere_aislamiento, requiere_camilla, autonomo)
VALUES (@p, (SELECT id_paciente FROM paciente WHERE documento = '56789012'), 0, 0, 0, 1);

-- 5) Documentos en auto, cancelado (elección libre de vehículo)
INSERT INTO item (nombre, caracteristicas, tipo_objeto)
VALUES ('Historias clínicas', 'Carpeta sellada', 'Documento');
SET @i := LAST_INSERT_ID();
INSERT INTO peticion (usuario_ci, matricula, chofer, enfermero, razon, destino, hora_salida, estado, observaciones)
VALUES (@solicitante, 'SBA 4455', 'Patricia Díaz', 'Julia Méndez',
        'Archivo de historias clínicas', 'Archivo general', NOW() - INTERVAL 2 DAY, 'Cancelado', 'Se resolvió por otro medio');
SET @p := LAST_INSERT_ID();
INSERT INTO traslado_item (id_peticion, id_item) VALUES (@p, @i);

-- ============================================
-- Restricciones no estructurales (traslados)
-- ============================================
-- 1. Una petición traslada UN paciente o UN item, nunca los dos ni ninguno:
--    existe una fila en traslado_paciente o en traslado_item, no en ambas (validado en PHP, ServicioTraslado).
-- 2. Solo ambulancia: pacientes con oxígeno, aislamiento, camilla o no autónomos, y órganos o muestras biológicas
--    (ReglasTraslado::tipoVehiculoRequerido).
-- 3. Documentos, insumos médicos y equipamiento: elección libre de vehículo (el hospital no lo definió).
-- 4. El enfermero es obligatorio en todo traslado.
-- 5. Un vehículo en mantenimiento no se puede asignar.
-- 6. Un vehículo no puede tener dos traslados activos con salidas a menos de 120 minutos
--    (ReglasTraslado::VENTANA_MINUTOS).
-- 7. Estados: Solicitado -> En_curso -> En_destino -> Retornado; se puede Cancelar mientras no llegó a destino.
-- 8. Solo se puede editar un traslado en estado Solicitado.
-- 9. Solo se registra la hora de salida; no se guarda hora de llegada ni de retorno.
-- 10. No se puede borrar un usuario que tenga peticiones (FK fk_peticion_usuario).
-- 11. La disponibilidad del vehículo no se guarda: se calcula en v_vehiculo_estado (evita datos inconsistentes).

-- ############################################
-- 3. REGISTRO DE ACTIVIDAD (logs)
-- ############################################

CREATE TABLE logs (
    id_log      INT AUTO_INCREMENT,
    usuario_ci  VARCHAR(10) NOT NULL,                   -- quién hizo la acción
    accion      VARCHAR(40) NOT NULL,                   -- Crea, Modifica, Elimina, Cambia estado...
    modulo      VARCHAR(30) NOT NULL,                   -- Traslados, Usuarios, Sesion, Documentos...
    id_registro VARCHAR(20) NULL,                       -- id de lo que se tocó (sin FK, ver abajo)
    fecha       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_logs PRIMARY KEY (id_log),
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_ci) REFERENCES usuario(ci),
    INDEX idx_logs_fecha (fecha),
    INDEX idx_logs_modulo (modulo)
);

-- Vista para la pantalla de logs: agrega nombre y rol de quien hizo la acción
CREATE VIEW v_logs AS
SELECT l.id_log,
       l.fecha,
       l.usuario_ci,
       CONCAT(u.nombre, ' ', u.apellido) AS usuario,
       u.rol,
       l.modulo,
       l.accion,
       l.id_registro
FROM logs l
JOIN usuario u ON u.ci = l.usuario_ci;

-- ============================================
-- Restricciones no estructurales (logs)
-- ============================================
-- 1. id_registro NO es clave foránea a propósito: apunta a tablas distintas según el módulo,
--    y el registro de un borrado tiene que seguir existiendo aunque lo borrado ya no exista.
-- 2. Los logs no se modifican ni se borran desde la aplicación: el usuario sigsm_app
--    solo tiene SELECT e INSERT sobre esta tabla (ver usuarios_bd.sql).
-- 3. Cada operación que modifica datos (alta, edición, baja, cambio de estado) y cada
--    inicio/cierre de sesión genera un log. Las consultas no se registran.
-- 4. Los usuarios no se borran (se inhabilitan), así que todo log conserva a su autor.

-- ############################################
-- 4. DOCUMENTACIÓN: categorías y documentos
-- ############################################

-- La collation utf8mb4_unicode_ci no distingue mayúsculas ni tildes:
-- "Nefrología" y "nefrologia" cuentan como el mismo nombre (UNIQUE).
CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT,
    nombre       VARCHAR(60) NOT NULL,
    CONSTRAINT pk_categoria PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre)
);

CREATE TABLE documento (
    id_documento INT AUTO_INCREMENT,
    nombre       VARCHAR(150) NOT NULL,
    descripcion  VARCHAR(255) NULL,
    subcategoria VARCHAR(60)  NULL,                    -- texto libre y opcional
    archivo      VARCHAR(64)  NOT NULL,                -- nombre del PDF en uploads/documentos/
    id_categoria INT          NOT NULL,
    usuario_ci   VARCHAR(10)  NOT NULL,                -- quién lo subió (relación Crea del MER)
    fecha_subida DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_documento PRIMARY KEY (id_documento),
    CONSTRAINT uq_documento_archivo UNIQUE (archivo),
    CONSTRAINT fk_documento_categoria FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
    CONSTRAINT fk_documento_usuario   FOREIGN KEY (usuario_ci)   REFERENCES usuario(ci)
);

-- ============================================
-- VISTA — listado de documentos (la usan el panel y el portal público)
-- ============================================
CREATE VIEW v_documentos AS
SELECT d.id_documento,
       d.nombre,
       d.descripcion,
       d.subcategoria,
       d.archivo,
       d.fecha_subida,
       d.id_categoria,
       c.nombre AS categoria,
       d.usuario_ci,
       CONCAT(u.nombre, ' ', u.apellido) AS subido_por
FROM documento d
JOIN categoria c ON c.id_categoria = d.id_categoria
JOIN usuario   u ON u.ci = d.usuario_ci;

-- ============================================
-- DML — Categorías iniciales
-- (los documentos se cargan desde la aplicación, porque necesitan su PDF)
-- ============================================
INSERT INTO categoria (nombre) VALUES
('Estudios y diagnóstico'),
('Cirugía y procedimientos'),
('Tratamientos y medicación'),
('Nefrología y trasplante'),
('Otras pautas');

-- ============================================
-- Restricciones no estructurales (documentos)
-- ============================================
-- 1. Cada documento pertenece a UNA sola categoría.
-- 2. Si al crear una categoría el nombre ya existe (sin importar mayúsculas ni tildes),
--    se usa la existente en lugar de crear otra.
-- 3. Solo se aceptan archivos PDF reales (se verifica el contenido, no solo la extensión)
--    de hasta 10 MB.
-- 4. El PDF se guarda en uploads/documentos/ con un nombre aleatorio; en la base solo queda ese nombre.
--    La carpeta no es accesible por URL: los PDF solo se entregan por api/pdf.php.
-- 8. El portal de pacientes no ofrece descargar ni imprimir: el PDF se dibuja con PDF.js, sin barra de herramientas.
-- 5. Al eliminar un documento se borra también su archivo PDF.
-- 6. Solo root, Gestion_General y Division_Enfermeria pueden crear, editar o eliminar documentos;
--    el listado y la descarga son públicos (portal de pacientes).
-- 7. La subcategoría es texto libre y opcional; no es una tabla.

-- ############################################
-- 5. ENCUESTAS DE SATISFACCIÓN (Módulo Documentación)
-- ############################################

CREATE TABLE encuesta (
    id_encuesta    INT AUTO_INCREMENT,
    titulo         VARCHAR(120) NOT NULL,
    descripcion    VARCHAR(255) NULL,
    estado         ENUM('Publica','Privada') NOT NULL DEFAULT 'Privada',
    id_categoria   INT          NOT NULL,                -- segmentación de la encuesta
    usuario_ci     VARCHAR(10)  NOT NULL,                -- quién la creó
    fecha_creacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_encuesta PRIMARY KEY (id_encuesta),
    CONSTRAINT fk_encuesta_categoria FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
    CONSTRAINT fk_encuesta_usuario   FOREIGN KEY (usuario_ci)   REFERENCES usuario(ci)
);

CREATE TABLE pregunta (
    id_pregunta INT AUTO_INCREMENT,
    id_encuesta INT          NOT NULL,
    enunciado   VARCHAR(200) NOT NULL,
    tipo        ENUM('Escala','Texto') NOT NULL,        -- Escala: 1 a 5 / Texto: respuesta libre
    orden       TINYINT      NOT NULL,
    CONSTRAINT pk_pregunta PRIMARY KEY (id_pregunta),
    CONSTRAINT fk_pregunta_encuesta FOREIGN KEY (id_encuesta) REFERENCES encuesta(id_encuesta) ON DELETE CASCADE
);

-- Un envío es una persona que completó la encuesta una vez.
-- ANÓNIMO: no guarda usuario, CI, paciente ni IP.
CREATE TABLE envio (
    id_envio    INT AUTO_INCREMENT,
    id_encuesta INT      NOT NULL,
    fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_envio PRIMARY KEY (id_envio),
    CONSTRAINT fk_envio_encuesta FOREIGN KEY (id_encuesta) REFERENCES encuesta(id_encuesta)
);

CREATE TABLE respuesta (
    id_respuesta INT AUTO_INCREMENT,
    id_envio     INT          NOT NULL,
    id_pregunta  INT          NOT NULL,
    valor_escala TINYINT      NULL,
    valor_texto  VARCHAR(500) NULL,
    CONSTRAINT pk_respuesta PRIMARY KEY (id_respuesta),
    CONSTRAINT uq_respuesta UNIQUE (id_envio, id_pregunta),              -- una respuesta por pregunta y envío
    CONSTRAINT fk_respuesta_envio    FOREIGN KEY (id_envio)    REFERENCES envio(id_envio),
    CONSTRAINT fk_respuesta_pregunta FOREIGN KEY (id_pregunta) REFERENCES pregunta(id_pregunta),
    CONSTRAINT ck_respuesta_escala   CHECK (valor_escala IS NULL OR valor_escala BETWEEN 1 AND 5),
    CONSTRAINT ck_respuesta_valor    CHECK ((valor_escala IS NULL) <> (valor_texto IS NULL))  -- exactamente uno de los dos
);

-- ============================================
-- VISTAS
-- ============================================

-- Listado de encuestas con su categoría y cantidad de respuestas
CREATE VIEW v_encuestas AS
SELECT e.id_encuesta,
       e.titulo,
       e.descripcion,
       e.estado,
       e.id_categoria,
       c.nombre AS categoria,
       e.fecha_creacion,
       CONCAT(u.nombre, ' ', u.apellido) AS creada_por,
       (SELECT COUNT(*) FROM pregunta p WHERE p.id_encuesta = e.id_encuesta) AS preguntas,
       (SELECT COUNT(*) FROM envio    v WHERE v.id_encuesta = e.id_encuesta) AS respuestas
FROM encuesta e
JOIN categoria c ON c.id_categoria = e.id_categoria
JOIN usuario   u ON u.ci = e.usuario_ci;

-- Resultados de las preguntas de escala: promedio y cuántas veces se eligió cada valor
CREATE VIEW v_resultados_escala AS
SELECT p.id_encuesta,
       p.id_pregunta,
       p.orden,
       p.enunciado,
       COUNT(r.valor_escala)          AS cantidad,
       ROUND(AVG(r.valor_escala), 2)  AS promedio,
       SUM(r.valor_escala = 1)        AS v1,
       SUM(r.valor_escala = 2)        AS v2,
       SUM(r.valor_escala = 3)        AS v3,
       SUM(r.valor_escala = 4)        AS v4,
       SUM(r.valor_escala = 5)        AS v5
FROM pregunta p
LEFT JOIN respuesta r ON r.id_pregunta = p.id_pregunta
WHERE p.tipo = 'Escala'
GROUP BY p.id_encuesta, p.id_pregunta, p.orden, p.enunciado;

-- ============================================
-- DML — Encuesta de ejemplo
-- ============================================
SET @cat := (SELECT id_categoria FROM categoria ORDER BY id_categoria LIMIT 1);
SET @creador := (SELECT ci FROM usuario WHERE rol = 'root' LIMIT 1);

INSERT INTO encuesta (titulo, descripcion, estado, id_categoria, usuario_ci)
VALUES ('Encuesta estándar de satisfacción',
        'Contanos cómo fue tu atención. La encuesta es anónima.',
        'Publica', @cat, @creador);
SET @e := LAST_INSERT_ID();

INSERT INTO pregunta (id_encuesta, enunciado, tipo, orden) VALUES
(@e, '¿Cómo calificás la atención recibida?',               'Escala', 1),
(@e, '¿Qué tan clara fue la información que te dieron?',    'Escala', 2),
(@e, '¿Cómo calificás los tiempos de espera?',              'Escala', 3),
(@e, '¿Querés dejarnos algún comentario o sugerencia?',     'Texto',  4);

-- ============================================
-- Restricciones no estructurales (encuestas)
-- ============================================
-- 1. Las encuestas son ANÓNIMAS: envio y respuesta no guardan ningún dato de quien responde.
--    Por eso no se puede impedir que una misma persona responda más de una vez.
-- 2. Solo las encuestas en estado Publica se muestran y aceptan respuestas en el portal.
-- 3. Una encuesta con respuestas no se puede editar ni eliminar (se romperían sus resultados):
--    solo se puede cambiar su estado.
-- 4. Toda encuesta tiene al menos una pregunta; las preguntas de escala son obligatorias
--    al responder y las de texto son opcionales.
-- 5. Los resultados no se guardan: se calculan con la vista v_resultados_escala.
-- 6. Solo root, Gestion_General y Division_Enfermeria crean y administran encuestas.
