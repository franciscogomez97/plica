#!/bin/bash
# Banco de pruebas de Plica en el navegador: la app en http://127.0.0.1:8123 con
# una base de datos de demo APARTE (pruebas-navegador/demo.sqlite), para mirar y
# tocar sin ensuciar la de desarrollo. Playwright vive aquí, no en el package.json
# del proyecto.
#
#   pruebas-navegador/banco.sh encender    levanta la app (crea y siembra la demo si no existe)
#   pruebas-navegador/banco.sh apagar      la para
#   pruebas-navegador/banco.sh estado      ¿está encendida?
#   pruebas-navegador/banco.sh reiniciar   borra la demo, la vuelve a sembrar y la levanta
#   pruebas-navegador/banco.sh instalar    instala Playwright y Chromium (una vez)
#
# Luego: node pruebas-navegador/captura.mjs /admin/seccions/1/edit --admin --movil
#        node pruebas-navegador/auditar.mjs
set -euo pipefail
AQUI="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$AQUI/.." && pwd)"
PUERTO=8123
BD="$AQUI/demo.sqlite"
PID="$AQUI/servidor.pid"
LOG="$AQUI/servidor.log"
export DB_CONNECTION=sqlite DB_DATABASE="$BD" APP_URL="http://127.0.0.1:$PUERTO" APP_ENV=local

encendida() { [[ -f "$PID" ]] && kill -0 "$(cat "$PID")" 2>/dev/null; }

sembrar() {
  rm -f "$BD" "$BD-wal" "$BD-shm"
  touch "$BD"
  (cd "$RAIZ" && php artisan migrate --force --seed --seeder=DemoSeeder --no-interaction >/dev/null)
  echo "Demo sembrada en $BD (admin@plica.test / socio@plica.test · plica2026)"
}

case "${1:-estado}" in
  instalar)
    (cd "$AQUI" && npm install --no-fund --no-audit && npx playwright install chromium)
    ;;
  encender)
    if encendida; then echo "Ya está encendida en http://127.0.0.1:$PUERTO"; exit 0; fi
    [[ -s "$BD" ]] || sembrar
    (cd "$RAIZ" && nohup php artisan serve --host=127.0.0.1 --port=$PUERTO >"$LOG" 2>&1 & echo $! >"$PID")
    for _ in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PUERTO/salud" && break; sleep 0.3; done
    echo "Encendida: http://127.0.0.1:$PUERTO  (admin: /admin/login · socio: /app/login · club: /c/cd-pesca-piloto)"
    ;;
  apagar)
    if encendida; then kill "$(cat "$PID")" && rm -f "$PID" && echo "Apagada"; else rm -f "$PID"; echo "Ya estaba apagada"; fi
    ;;
  reiniciar)
    "$0" apagar >/dev/null
    sembrar
    "$0" encender
    ;;
  estado)
    if encendida; then echo "Encendida en http://127.0.0.1:$PUERTO (pid $(cat "$PID"))"; else echo "Apagada"; fi
    [[ -s "$BD" ]] && echo "Demo: $BD" || echo "Demo: sin sembrar"
    ;;
  *)
    echo "Uso: $0 encender|apagar|estado|reiniciar|instalar"; exit 1 ;;
esac
