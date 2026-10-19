# Sistema Web para la Gestión, Control y Seguimiento de Solicitudes de Soporte Técnico
### H. Ayuntamiento de Tehuacán

Sistema de tickets de soporte técnico desarrollado en **PHP puro (sin frameworks)**
y **MySQL/MariaDB**, pensado para ejecutarse en cualquier hosting compartido que
soporte PHP 8+ con extensión PDO (cPanel, Apache + mod_php, etc.).

## 1. Requisitos

- PHP 8.0 o superior, con extensiones `pdo_mysql` habilitada.
- MySQL 5.7+ o MariaDB 10.4+.
- Servidor Apache (o Nginx) con soporte para `.htaccess` (si es Apache).

## 2. Instalación local (XAMPP / Laragon / WAMP)

1. Copiar la carpeta `sistema-tickets` dentro de `htdocs` (XAMPP) o `www` (Laragon).
2. Crear la base de datos ejecutando el script `database/sistema_tickets.sql`
   desde phpMyAdmin o por consola:
   ```
   mysql -u root -p < database/sistema_tickets.sql
   ```
3. Editar `config/database.php` con los datos reales de conexión:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'sistema_tickets');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
4. Editar `config/config.php` y ajustar `BASE_URL` según la carpeta donde
   quede instalado el proyecto (ejemplo: `/sistema-tickets` o `/soporte`).
5. Abrir en el navegador: `http://localhost/sistema-tickets/`

## 3. Instalación en el servidor del H. Ayuntamiento

1. Subir todo el contenido de la carpeta al directorio público
   (`public_html` o el que indique el administrador del servidor), vía FTP
   o el administrador de archivos del panel de control.
2. Crear la base de datos y el usuario de MySQL desde el panel de hosting
   (cPanel → MySQL Databases), y otorgar todos los privilegios al usuario
   sobre la base de datos.
3. Importar `database/sistema_tickets.sql` desde phpMyAdmin.
4. Ajustar `config/database.php` con las credenciales otorgadas por el
   hosting (normalmente el host es `localhost`).
5. Ajustar `BASE_URL` en `config/config.php`.
6. Verificar que las carpetas `uploads/` y `config/` tengan los permisos
   correctos (755 para carpetas, 644 para archivos) y que el archivo
   `uploads/.htaccess` se haya subido (evita ejecución de scripts en esa carpeta).

## 4. Usuario administrador por defecto

| Usuario | Contraseña  |
|---------|-------------|
| admin   |   |

**Importante:** cambiar esta contraseña inmediatamente después de la primera
instalación, creando un nuevo usuario administrador y desactivando o
modificando este usuario por defecto.

## 5. Roles del sistema

- **Administrador**: gestiona usuarios, categorías, departamentos, asigna
  tickets a técnicos y consulta reportes/estadísticas globales.
- **Técnico**: da seguimiento a los tickets que le sean asignados, actualiza
  su estatus y responde a los comentarios del solicitante.
- **Usuario (empleado del Ayuntamiento)**: levanta solicitudes de soporte,
  da seguimiento a las propias, comenta y, al resolverse, califica y cierra
  el ticket (o lo reabre si el problema persiste).

## 6. Estructura del proyecto

```
sistema-tickets/
├── config/                # Configuración (conexión BD, constantes)
├── includes/               # Header, footer y funciones auxiliares
├── modules/
│   ├── auth/                # Login / logout
│   ├── tickets/              # Dashboard, crear, listar, detalle, notificaciones
│   └── admin/                # Usuarios, catálogos, reportes
├── assets/css/              # Hoja de estilos
├── uploads/                # Archivos adjuntos de tickets y comentarios
├── database/                # Script SQL de instalación
└── index.php                 # Página de inicio de sesión
```

## 7. Seguridad implementada

- Contraseñas con hash `bcrypt` (`password_hash` / `password_verify`).
- Consultas parametrizadas con PDO (prevención de inyección SQL).
- Tokens CSRF en todos los formularios que modifican datos.
- Escape de salida HTML con `htmlspecialchars` (prevención de XSS).
- Control de acceso por rol en cada módulo (`requerirRol`).
- Restricción de ejecución de scripts dentro de `uploads/`.
- Validación de extensión y tamaño máximo en archivos adjuntos.

## 8. Respaldo y restauración de la base de datos

El sistema incluye dos scripts en `database/` para cumplir con el
requerimiento de contar con mecanismos de respaldo de la información:

- **`backup.sh`**: genera un respaldo comprimido (`.sql.gz`) con fecha y
  hora en el nombre, y conserva automáticamente solo los últimos 30.
  ```
  bash database/backup.sh
  ```
  Para automatizarlo diariamente en el servidor (cron), agregar una línea
  como la siguiente con `crontab -e`:
  ```
  0 2 * * * /bin/bash /ruta/completa/sistema-tickets/database/backup.sh
  ```
- **`restore.sh`**: restaura la base de datos a partir de un respaldo
  generado previamente (pide confirmación antes de sobrescribir datos).
  ```
  bash database/restore.sh database/respaldos/sistema_tickets_2026-08-28_02-00-00.sql.gz
  ```

Antes de usarlos, editar `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` al
inicio de cada script con los datos reales del servidor.

## 9. Estructura de cuentas de usuario y política de contraseñas

- Las cuentas de rol **Usuario** se dan de alta a nombre del departamento
  que representan (por ejemplo, "Tesorería", "Catastro"), no a nombre de
  una persona física. Esto es una convención de uso: el campo
  `nombre_completo` admite cualquier texto libre.
- Un departamento **no está limitado a un solo usuario**: si un área lo
  requiere, el administrador puede registrar más de una cuenta asociada
  al mismo `id_departamento`.
- El sistema ya no solicita ni almacena número de teléfono como dato del
  usuario.
- **Solo el rol Administrador puede establecer o cambiar contraseñas**,
  tanto la propia (desde "Mi perfil") como las de cualquier Técnico o
  Usuario (desde "Usuarios" → columna "Restablecer contraseña"). Los
  roles Técnico y Usuario no tienen esta opción en su perfil; si
  necesitan una contraseña nueva, deben solicitarlo al administrador.

## 10. Nota sobre la extensión mbstring

Las funciones de validación de texto usan `mb_strlen`/`mb_substr` cuando
la extensión `mbstring` de PHP está disponible (lo habitual en cualquier
hosting), y recurren automáticamente a `strlen`/`substr` si no lo está,
para que el sistema no se interrumpa por esta causa en un servidor con
configuración distinta.

## 11. Cobertura de requerimientos

Todos los requerimientos funcionales y no funcionales acordados con el
H. Ayuntamiento de Tehuacán fueron implementados y verificados mediante
pruebas funcionales manuales sobre el sistema en ejecución. La tabla
completa de trazabilidad (requerimiento → módulo donde se implementa)
se encuentra en el capítulo "Requerimientos del sistema" del reporte de
residencia profesional (Reporte_Residencia_Profesional.docx).
