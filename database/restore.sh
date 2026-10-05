#!/bin/bash
# ============================================================
# Restaura la base de datos a partir de un respaldo generado
# por backup.sh
#
# USO:
#   bash restore.sh respaldos/sistema_tickets_2026-08-28_02-00-00.sql.gz
# ============================================================

DB_HOST="localhost"
DB_NAME="sistema_tickets"
DB_USER="root"
DB_PASS=""

ARCHIVO="$1"
if [ -z "$ARCHIVO" ]; then
    echo "Uso: bash restore.sh <archivo_respaldo.sql.gz>"
    exit 1
fi
if [ ! -f "$ARCHIVO" ]; then
    echo "ERROR: el archivo '$ARCHIVO' no existe."
    exit 1
fi

echo "Esto SOBRESCRIBIRÁ todos los datos actuales de la base '$DB_NAME'."
read -p "¿Desea continuar? (escriba 'si' para confirmar): " CONFIRMAR
if [ "$CONFIRMAR" != "si" ]; then
    echo "Operación cancelada."
    exit 0
fi

if [ -n "$DB_PASS" ]; then
    gunzip -c "$ARCHIVO" | mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --default-character-set=utf8mb4 "$DB_NAME"
else
    gunzip -c "$ARCHIVO" | mysql -h "$DB_HOST" -u "$DB_USER" --default-character-set=utf8mb4 "$DB_NAME"
fi

echo "Restauración completada."
