# Plica

Gestión de clubes de pesca deportiva: mangas, pesajes, clasificaciones y
rankings de temporada calculados automáticamente. El admin del club mete
los datos de las plicas al llegar a casa; el club lo ve todo al momento.

> Nombre provisional del proyecto. "Plica": la papeleta donde el pescador
> apunta sus capturas.

## Stack

- Laravel 12 · PHP 8.5 · SQLite (dev) — MySQL/PostgreSQL en producción
- Filament v5 (panel de admin en `/admin` y panel de socio en `/app`)
- Tailwind CSS v4 vía Vite (páginas públicas)
- PHPUnit (suite de humo + motor de puntuación)

## Arranque en local

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder   # datos de ejemplo (solo local)
php artisan serve
```

Credenciales de demo: `admin@plica.test` / `plica2026` (panel `/admin`)
y `socio@plica.test` / `plica2026` (panel `/app`). Club público de demo:
`/c/cd-pesca-piloto`.

**Producción arranca vacía**: `DatabaseSeeder` no siembra nada. Los clubes
del piloto se dan de alta a mano.

## Modelo: hechos vs. puntuación

Regla de oro del proyecto: los **hechos** (participaciones y capturas: piezas,
gramos, milímetros) se guardan puros y nunca se tocan. Toda la **puntuación**
vive en `app/Services/Scoring.php` y se calcula al vuelo — editar un dato o
cambiar una regla recalcula todo sin corromper nada. Pesos en **gramos
enteros** y medidas en **milímetros enteros**: nada de floats.

Configuración por **sección** (cada sección = una modalidad con sus normas):
criterio de manga (peso / medida / piezas), sistema de ranking (acumulado /
por puestos), puntos por participación y descartes. Los rankings son SIEMPRE
por sección; no existe ranking general (decisión de producto, no un hueco).

## Tests

```bash
php artisan test
```

Cubren: páginas públicas, panel admin completo, clasificación por secciones,
panel de socio, control de acceso, flujo de invitación completo y el motor
de puntuación (puestos, descartes, puntos de participación) con casos
calculados a mano.

## Decisiones tomadas (y por qué)

- **Invitaciones por link** (WhatsApp), no por email: cero dependencia de
  SMTP en el piloto y encaja con cómo se comunica un club real.
- **El sistema no interviene el día de la manga**: el ritual del agua no se
  toca (plicas en papel); se digitaliza el después. El pesaje en vivo /
  videopesaje es fase futura, y por eso las capturas ya guardan medida.
- **Multi-tenant desde el día 1**: todo scoped por `club_id`; meter el club
  n.º 2 es dar de alta una fila.

## PENDIENTES — leer antes de desplegar

Aparcado a propósito, no olvidado:

1. **Reglas reales del club piloto**: validar que los botones de sección
   cubren su reglamento; si no, añadir el botón que falte (nunca un motor
   de fórmulas genérico sin reglamentos reales delante).
2. **Datos reales**: nombre del club, socios y calendario de mangas de la
   temporada (hoy hay placeholders de demo).
3. **Deploy a VPS**: MySQL/PostgreSQL, HTTPS, backups de BD automatizados,
   `APP_ENV=production`, colas si algún día hay emails.
4. **SMTP + recuperación de contraseña**: los paneles no tienen "olvidé mi
   contraseña" porque no hay correo saliente. Configurar mailer y activar
   `->passwordReset()` en los dos panel providers.
5. **Cambio de reglas con historial**: la config vive en la sección (nivel
   club); cambiarla recalcula también temporadas pasadas. Cuando haya
   clubes con historial: config por temporada×sección o snapshot al cerrar
   temporada.
6. **Alta self-service de clubes**: hoy la landing solo captura solicitudes
   (tabla `solicituds`); el alta la hace el desarrollador. Automatizar
   cuando haya demanda real.
7. **Ranking general absoluto**: descartado salvo que un reglamento real lo
   pida (necesitaría puntos por puesto comparables entre secciones).
8. **Videopesaje / captura en vivo**: fase futura; el modelo de datos ya
   guarda medidas por captura para no cerrar la puerta.
