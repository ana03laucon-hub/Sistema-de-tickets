#!/bin/bash
# ============================================================
# Script de respaldo de la base de datos del Sistema de Soporte
# Técnico del H. Ayuntamiento de Tehuacán.
#
# Requerimiento no funcional: "El sistema deberá contar con
# mecanismos de respaldo de la información".
#
# USO MANUAL:
#   bash backup.sh
#
# USO AUTOMÁTICO (recomendado, vía cron en el servidor):
#   Ejecutar todos los días a las 2:00 a.m., por ejemplo:
#   0 2 * * * /bin/bash /ruta/completa/al/proyecto/database/backup.sh
#
# El respaldo se guarda comprimido con fecha y hora en el nombre,
# y este script conserva solo los últimos 30 respaldos para no
# llenar el disco del servidor.
# ============================================================

# --- Ajustar estos 4 valores según el servidor real ---
DB_HOST="localhost"
DB_NAME="sistema_tickets"
DB_USER="root"
DB_PASS=""

# Carpeta donde se guardarán los respaldos (se crea si no existe)
DIR_RESPALDOS="$(dirname "$0")/respaldos"
mkdir -p "$DIR_RESPALDOS"

FECHA=$(date +"%Y-%m-%d_%H-%M-%S")
ARCHIVO_SALIDA="$DIR_RESPALDOS/sistema_tickets_$FECHA.sql.gz"

if [ -n "$DB_PASS" ]; then
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --default-character-set=utf8mb4 --single-transaction "$DB_NAME" | gzip > "$ARCHIVO_SALIDA"
else
    mysqldump -h "$DB_HOST" -u "$DB_USER" --default-character-set=utf8mb4 --single-transaction "$DB_NAME" | gzip > "$ARCHIVO_SALIDA"
fi

if [ $? -eq 0 ]; then
    echo "Respaldo generado correctamente: $ARCHIVO_SALIDA"
else
    echo "ERROR: no se pudo generar el respaldo." >&2
    exit 1
fi

# Conservar solo los últimos 30 respaldos
cd "$DIR_RESPALDOS" && ls -1t sistema_tickets_*.sql.gz | tail -n +31 | xargs -r rm --

echo "Respaldos actuales:"
ls -lh "$DIR_RESPALDOS"
