#!/usr/bin/env bash
# ============================================================
# SIGRA-ETSR · scripts/preparar-instalacion.sh
# ============================================================
# Deja la carpeta de subidas de fotos escribible por el servidor
# web (Apache corre como www-data dentro del contenedor).
#
# Qué hace:
#   - crea public/uploads/fotos/ si no existe;
#   - la deja con dueño <usuario de la instalación>, grupo www-data
#     y modo 2775 (el 2 activa setgid: los archivos nuevos heredan
#     el grupo y siguen siendo escribibles por www-data).
#
# Qué NO hace: no toca el resto del repositorio, ni los
# contenedores, ni la base de datos.
#
# Uso:
#   sudo scripts/preparar-instalacion.sh
#
# Se pide confirmación antes de aplicar los cambios.
#
# Por qué pide sudo: sólo root puede poner el bit setgid en una
# carpeta cuyo grupo no es uno al que pertenece el usuario. El
# dueño sigue siendo el usuario de la instalación (no root).

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CARPETA="$RAIZ/public/uploads/fotos"
GRUPO="www-data"

# Con sudo, $USER es root: el dueño correcto es quien lo invocó.
DUENIO="${SUDO_USER:-$(id -un)}"

if [ ! -d "$CARPETA" ]; then
    echo "No existe la carpeta $CARPETA."
    echo "Se va a crearla."
    CREAR=si
else
    CREAR=no
fi

echo
echo "Carpeta: $CARPETA"
echo "Dueño:   $DUENIO"
echo "Grupo:   $GRUPO"
echo "Modo:    2775 (drwxrwsr-x)"
echo

if [ "$CREAR" = "no" ]; then
    echo "Estado actual:"
    ls -ld "$CARPETA"
    echo
fi

read -r -p "¿Aplicar los cambios? [s/N] " respuesta
case "$respuesta" in
    [sSyY]*)
        ;;
    *)
        echo "Cancelado. No se cambió nada."
        exit 0
        ;;
esac

if ! getent group "$GRUPO" >/dev/null; then
    echo "ERROR: el grupo '$GRUPO' no existe en este sistema." >&2
    exit 1
fi

mkdir -p "$CARPETA"

# Si el usuario indicado no existe en esta máquina (p. ej. si el
# script corre dentro del contenedor), se deja el dueño actual.
if id -u "$DUENIO" >/dev/null 2>&1; then
    chown "$DUENIO:$GRUPO" "$CARPETA"
else
    echo "AVISO: el usuario '$DUENIO' no existe acá; se conserva el dueño actual." >&2
    chown ":$GRUPO" "$CARPETA"
fi

chmod 2775 "$CARPETA"

echo
echo "Listo. Estado final:"
ls -ld "$CARPETA"

# Aviso si el bit setgid no quedó puesto: sin él, los archivos
# nuevos que suba www-data heredan el grupo del proceso y pueden
# quedar sin permiso de escritura para el usuario de la instalación.
MODO="$(stat -c '%a' "$CARPETA")"
if [ "${MODO:0:1}" != "2" ]; then
    echo
    echo "AVISO: el bit setgid no quedó activo (modo $MODO)." >&2
    echo "Volvé a ejecutarlo con sudo: sólo root puede ponerlo" >&2
    echo "en una carpeta de un grupo al que no pertenecés." >&2
    exit 1
fi
