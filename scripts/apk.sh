#!/bin/sh
# Empacota o APK com os assets do momento: o native:package usa o
# public/build como está, e um build velho já gerou um APK com o
# JavaScript vazio (o timer não existia no aparelho).
#
# Uso (release assinada):
#   scripts/apk.sh --android \
#       --keystore="$HOME/diario-release.keystore" \
#       --keystore-password=SUA_SENHA \
#       --key-alias=diario \
#       --key-password=SUA_SENHA
#
# O PHP tem que ser o do host, não o do lerd: o wrapper do lerd roda o
# artisan dentro do container, onde não há Android SDK, JDK nem adb, e o
# build morre sem dizer por quê. Override com PHP_BIN se preciso.
set -eu

export JAVA_HOME="${JAVA_HOME:-$HOME/.local/jdks/jdk-21.0.12.1+1}"
export ANDROID_HOME="${ANDROID_HOME:-$HOME/android-sdk}"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/platform-tools:$PATH"

if [ ! -d nativephp/android ]; then
    echo "Falta o nativephp/android: rode 'php artisan native:install android' antes." >&2
    exit 1
fi

npm run build

# shellcheck disable=SC2086
${PHP_BIN:-/usr/bin/php} -d memory_limit=2G artisan native:package --no-tty "$@"
