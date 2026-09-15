# Banco de pruebas en el navegador

La app en `http://127.0.0.1:8123` con una base de datos de demo **aparte**
(`demo.sqlite`, aquí mismo), para mirarla y tocarla con Playwright sin ensuciar
la de desarrollo. Playwright se instala aquí, no en el `package.json` del proyecto.

```bash
pruebas-navegador/banco.sh instalar    # una vez: Playwright + Chromium
pruebas-navegador/banco.sh encender    # levanta la app con la demo (la siembra si no existe)
pruebas-navegador/banco.sh apagar
pruebas-navegador/banco.sh estado
pruebas-navegador/banco.sh reiniciar   # demo limpia otra vez
```

Usuarios de la demo: `admin@plica.test` (panel `/admin`) y `socio@plica.test`
(panel `/app`), contraseña `plica2026`. Club público: `/c/cd-pesca-piloto`.
El ranking del panel va por id de sección (`/admin/ranking/1`).

```bash
# Una captura entera (móvil por defecto), con login si hace falta
node pruebas-navegador/captura.mjs /admin/seccions/1/edit --admin --movil
node pruebas-navegador/captura.mjs /c/cd-pesca-piloto/orilla --ambos
# Pulsar algo antes de capturar (p. ej. ver el formulario en «por puestos»)
node pruebas-navegador/captura.mjs /admin/seccions/create --admin --clic "text=Suma los puestos" --nombre puestos

# Auditoría completa: 21 páginas × móvil y escritorio, axe-core, desbordes,
# objetivos táctiles, textos pequeños, errores de consola
node pruebas-navegador/auditar.mjs
```

Las capturas van a `capturas/` (ignorado por git), y `auditar.mjs` deja además
`capturas/resultados.json`.
