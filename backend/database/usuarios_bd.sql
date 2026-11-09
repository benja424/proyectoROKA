-- ============================================
-- S.I.G.S.M. — Usuarios de la base de datos y sus privilegios
-- Ejecutar con root, DESPUÉS de sigsm.sql (las tablas tienen que existir).
-- Al instalar en el servidor, cambiar ambas contraseñas (y la de la app en Database.php).
-- ============================================

DROP USER IF EXISTS 'sigsm_admin'@'localhost';
DROP USER IF EXISTS 'sigsm_app'@'localhost';

-- --------------------------------------------
-- 1) Administrador del sistema
--    Lo usa una persona (DTI) para mantenimiento: crear/modificar tablas,
--    respaldos, cargar vehículos, etc. Control total, pero SOLO sobre la base sigsm.
-- --------------------------------------------
CREATE USER 'sigsm_admin'@'localhost' IDENTIFIED BY 'Admin.Sigsm#2026';
GRANT ALL PRIVILEGES ON sigsm.* TO 'sigsm_admin'@'localhost';

-- --------------------------------------------
-- 2) Usuario de la aplicación
--    Lo usa el código PHP (Database.php). Solo puede leer y escribir datos
--    en las tablas que necesita; no puede crear, borrar ni modificar tablas.
-- --------------------------------------------
CREATE USER 'sigsm_app'@'localhost' IDENTIFIED BY 'App.Sigsm#2026';

-- Usuarios del sistema: login, alta, cambio de estado y actualización del hash.
-- Sin DELETE: los usuarios no se borran, se inhabilitan.
GRANT SELECT, INSERT, UPDATE         ON sigsm.usuario           TO 'sigsm_app'@'localhost';

-- Traslados
GRANT SELECT                         ON sigsm.vehiculo          TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT                 ON sigsm.paciente          TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT, DELETE         ON sigsm.item              TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT, UPDATE         ON sigsm.peticion          TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT, DELETE         ON sigsm.traslado_paciente TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT, DELETE         ON sigsm.traslado_item     TO 'sigsm_app'@'localhost';

-- Documentación (las categorías no se borran ni se renombran desde la app)
GRANT SELECT, INSERT                 ON sigsm.categoria         TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON sigsm.documento         TO 'sigsm_app'@'localhost';

-- Encuestas (las respuestas son anónimas y no se modifican ni se borran)
GRANT SELECT, INSERT, UPDATE, DELETE ON sigsm.encuesta          TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT, DELETE         ON sigsm.pregunta          TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT                 ON sigsm.envio             TO 'sigsm_app'@'localhost';
GRANT SELECT, INSERT                 ON sigsm.respuesta         TO 'sigsm_app'@'localhost';

-- Logs: solo se agregan y se leen; la app NO puede modificarlos ni borrarlos
GRANT SELECT, INSERT                 ON sigsm.logs              TO 'sigsm_app'@'localhost';

-- Vistas (solo lectura)
GRANT SELECT ON sigsm.v_logs                TO 'sigsm_app'@'localhost';
GRANT SELECT ON sigsm.v_documentos          TO 'sigsm_app'@'localhost';
GRANT SELECT ON sigsm.v_encuestas           TO 'sigsm_app'@'localhost';
GRANT SELECT ON sigsm.v_resultados_escala   TO 'sigsm_app'@'localhost';
GRANT SELECT ON sigsm.v_vehiculo_estado     TO 'sigsm_app'@'localhost';
GRANT SELECT ON sigsm.v_historial_traslados TO 'sigsm_app'@'localhost';

FLUSH PRIVILEGES;

-- ============================================
-- Resumen de roles y permisos
-- ============================================
-- sigsm_admin : ALL PRIVILEGES sobre sigsm.* (estructura y datos). Uso humano, mantenimiento.
-- sigsm_app   : solo DML (SELECT/INSERT/UPDATE/DELETE) y solo en las tablas que usa la app:
--   usuario            SELECT, INSERT, UPDATE (no se borran: se inhabilitan)
--   vehiculo           SELECT                (los vehículos los carga el administrador)
--   paciente           SELECT, INSERT        (los pacientes no se modifican ni se borran)
--   item               SELECT, INSERT, DELETE
--   peticion           SELECT, INSERT, UPDATE (las peticiones nunca se borran: se cancelan)
--   traslado_paciente  SELECT, INSERT, DELETE
--   traslado_item      SELECT, INSERT, DELETE
--   categoria          SELECT, INSERT
--   documento          SELECT, INSERT, UPDATE, DELETE
--   encuesta           SELECT, INSERT, UPDATE, DELETE
--   pregunta           SELECT, INSERT, DELETE
--   envio, respuesta   SELECT, INSERT        (respuestas anónimas e inalterables)
--   logs               SELECT, INSERT        (inalterables desde la app)
--   v_documentos, v_encuestas, v_resultados_escala, v_logs, v_vehiculo_estado, v_historial_traslados: SELECT
-- Sin DROP, CREATE, ALTER ni GRANT: si alguien lograra una inyección SQL no podría
-- borrar tablas ni crear usuarios.
-- Al agregar tablas nuevas se suman sus GRANT acá.
