# S.I.G.S.M. — Sistema Informático de Gestión de Servicios Médicos

Hospital de Clínicas Dr. Manuel Quintela · Proyecto de egreso 2026 · Equipo **ROKA** (I.S.B.O., 3MN)

Repositorio: https://github.com/benja424/proyectoROKA

## Qué es

Sistema web con dos módulos:

- **Documentación**: los funcionarios cargan documentos PDF por categoría y crean encuestas de satisfacción. Los pacientes consultan los documentos (sin poder descargarlos) y responden las encuestas en forma anónima desde el portal público, también con el celular mediante código QR.
- **Ambulancias**: alta, edición y seguimiento de traslados de pacientes y de elementos (órganos, muestras, insumos, documentos, equipamiento), con disponibilidad de vehículos e historial filtrable.

Además: login con roles, gestión de usuarios (activar / inhabilitar) y registro de actividad (logs) visible solo para el administrador.

## Tecnologías

PHP 8.2 · MariaDB 10.4 / MySQL · HTML5, CSS3 y JavaScript sin frameworks · XAMPP · Git y GitHub.
Librerías incluidas en el proyecto (funcionan sin internet): PDF.js (visor de PDF) y qrcode-generator (códigos QR).

## Instalación

1. **Instalar XAMPP** (Apache + MySQL/MariaDB + PHP 8.2 o superior) e iniciar Apache y MySQL.
2. **Copiar el proyecto** en `C:\xampp\htdocs\sigsm\` (la carpeta tiene que llamarse `sigsm`).
3. **Crear la base de datos**: en phpMyAdmin (`http://localhost/phpmyadmin`), pestaña **SQL**, ejecutar en este orden:
   1. `backend/database/sigsm.sql` — crea la base completa (tablas, vistas y datos de prueba). Si la base ya existía, la borra y la vuelve a crear.
   2. `backend/database/usuarios_bd.sql` — crea los usuarios de base de datos y sus permisos.
4. **Abrir el sistema**:
   - Portal de pacientes: `http://localhost/sigsm/frontend/index.html`
   - Funcionarios: `http://localhost/sigsm/frontend/login.html`

Requisitos de Apache (vienen así en XAMPP): `mod_rewrite` activo y `AllowOverride All` en `htdocs`, porque el proyecto usa archivos `.htaccess`.

Para usar los códigos QR desde un celular, entrar al panel con la IP o el dominio del servidor (no con `localhost`), con el celular en la misma red.

## Credenciales de prueba

Clave de todos los usuarios de prueba: **1234**

| Usuario | Rol | Accede a |
|---|---|---|
| `root` | root | Todo + Gestión de usuarios + Registro de actividad |
| `jperez` | Gestion_General | Documentación y Ambulancias |
| `mgonzalez` | Division_Enfermeria | Documentación |
| `crodriguez` | Division_Transito | Ambulancias |

Usuarios de base de datos (creados por `usuarios_bd.sql`):

| Usuario | Permisos | Uso |
|---|---|---|
| `sigsm_admin` / `Admin.Sigsm#2026` | Todos, solo sobre la base `sigsm` | Mantenimiento (DTI) |
| `sigsm_app` / `App.Sigsm#2026` | Solo leer y escribir datos en las tablas que usa | Lo usa la aplicación (`backend/config/Database.php`) |

**Antes de instalar en el servidor del hospital** hay que cambiar estas contraseñas en `usuarios_bd.sql` y en `Database.php`, y las claves de los usuarios de prueba.

## Estructura

```
sigsm/
├── .htaccess              redirige /sigsm/api/* al router
├── api/                   endpoints REST (JSON); index.php es el router
├── backend/
│   ├── config/            conexión a la base (Singleton) y zona horaria
│   ├── models/            capa de datos: una clase por entidad, consultas preparadas (PDO)
│   ├── negocio/           capa de negocio: reglas, validaciones, permisos y logs
│   └── database/          scripts SQL de instalación
├── frontend/              capa de presentación: HTML, CSS y JS de cada página
└── uploads/documentos/    PDF subidos (no accesibles por URL; se entregan por api/pdf)
```

Arquitectura en tres capas: **presentación** (`frontend/`) → **negocio** (`api/` + `backend/negocio/`) → **datos** (`backend/models/` + `backend/config/`).

## Seguridad implementada

- Consultas preparadas (PDO) en todas las operaciones: sin inyección SQL.
- Contraseñas con `password_hash()` / `password_verify()`; nunca en texto plano.
- Control de acceso por rol en cada endpoint, no solo en la interfaz.
- Validación en JavaScript y en PHP de todos los formularios.
- La aplicación se conecta con un usuario de base de datos restringido (sin DROP, CREATE ni ALTER).
- Cookie de sesión `httponly` y nuevo id de sesión al iniciar sesión.
- Los usuarios inhabilitados pierden el acceso aunque tengan la sesión abierta.
- Los PDF se validan por su contenido, se guardan con nombre aleatorio y no son accesibles por URL.
- Las carpetas `backend/` y `uploads/` están bloqueadas para el navegador.
- Los logs solo se pueden agregar y leer; la aplicación no puede modificarlos ni borrarlos.
