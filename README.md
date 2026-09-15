# Plica

Gestión de clubes de pesca deportiva: mangas, pesajes, clasificaciones y
rankings de temporada calculados automáticamente. El admin del club mete
los datos de las plicas al llegar a casa; el club lo ve todo al momento.

> Nombre provisional del proyecto. "Plica": la papeleta donde el pescador
> apunta sus capturas.

## Stack

- Laravel 13 · PHP 8.5 · SQLite (dev) — MySQL/PostgreSQL en producción
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

**Cada socio es de una o varias secciones** (tabla `seccion_socio`, 14 de
septiembre de 2026): se aprende sola (quien pesa en una manga de Orilla pasa
a ser de Orilla) y se corrige en la ficha del socio o en la sección. El
ranking de una sección lista a todos sus socios, hayan ido o no a alguna
manga; quien no ha ido a ninguna sale al final con sus puntos por ausencia,
y un socio de baja sigue con la etiqueta «Baja». Socios y Mangas se filtran
por sección con pestañas.

**Toda manga es de una sección** (decisión de septiembre de 2026: el club
siempre compite por secciones, cada sección tiene su calendario). La sección
se elige al crear la manga y todo lo demás la hereda: la asistencia apunta a
los socios a esa sección sin preguntar nada, el pesaje sabe su criterio y el
ranking se calcula por sección. En base de datos `mangas.seccion_id` es
obligatorio y una sección con mangas no se puede borrar. El modo «jornada de
todo el club» (varias secciones en una manga) se retiró; la migración
`mangas_siempre_de_una_seccion` reparte las que hubiera en una manga por sección.

## Tests

```bash
php artisan test
```

**El motor es el producto, y se prueba como tal.** Además de los casos a mano
(reglamentos reales, matrices calculadas aparte), `tests/Feature/MotorMatrizTest`
recorre el producto cartesiano de las reglas de una sección (criterio ×
sistema × asistencia × ausencia × descartes × descartes de ausencias × bolo ×
desempate × desempate general: 1.210 configuraciones en la matriz completa, 450 en la reducida)
sobre varios escenarios (temporada normal, empates exactos, todos bolo, uno
solo en la manga, quien entra a mitad, quien no va nunca, guardado en otro
orden, equipos de una y de dos personas) y compara ranking, cuadro y
clasificación de cada manga con `tests/Support/MotorDeReferencia`, un segundo
motor escrito aparte y a lo simple a partir de la frase de reglas. Si discrepan,
uno de los dos lee mal la regla. Los escenarios se escriben con
`tests/Support/Escenario` (una línea por socio, una columna por manga; `bolo`,
`—`, o el detalle de piezas y pieza mayor) y la sección se reconfigura sin
reconstruir nada, así que una combinación nueva son tres líneas. Límite
honesto: el motor de referencia lo escribió la misma cabeza que el real, así
que caza regresiones y errores de orden, no una lectura equivocada de la regla.
La lectura la prueban los datos reales: la general de Bass Extremadura
calculada por el club (`BassExtremaduraTemporadaTest`) y, **norma: cada club
nuevo aporta su hoja de la temporada pasada como matriz** antes de fiarse del
motor con su reglamento.

Cubren: páginas públicas, panel admin completo, clasificación por secciones,
panel de socio, control de acceso, flujo de invitación completo, el motor
de puntuación (puestos, descartes, puntos por asistencia) con casos
calculados a mano, el pesaje rápido (guardado por casilla, medidas pez a
pez, aislamiento entre clubes), el alta de socios en bloque, el resumen de
reglas en formulario, rankings y página pública, el cuadro manga a manga por
sección, la navegación «Atrás» jerárquica y el encadenado sección → mangas.

## Producción

Plica vive en **https://plicapesca.es**, un VPS (Ubuntu 24.04, Nginx,
PHP-FPM 8.4, PostgreSQL 16) con el código en `/var/www/plica`, desplegado el
11 de septiembre de 2026. El dominio está en IONOS (registros A de `@` y
`www` a la IP del VPS, sin AAAA) y el certificado es de Let's Encrypt:
`certbot certonly --webroot -w /var/www/letsencrypt`, renovación automática
por `certbot.timer` con un hook que recarga Nginx. La config de Nginx del
servidor es `deploy/nginx-plica.conf` (copiarla a
`/etc/nginx/sites-available/plica` si cambia): HTTP, `www` y la IP desnuda
redirigen con 301 a `https://plicapesca.es`, con HSTS de un año, para que
ningún enlace compartido lleve otra cosa. En el `.env`,
`APP_URL=https://plicapesca.es` y `SESSION_SECURE_COOKIE=true`. Se despliega
desde el Mac, sin git en el servidor:

```bash
deploy/desplegar.sh          # tests → npm run build → rsync → composer install → composer deploy
```

El script compila los assets aquí, sube el código por rsync (sin `.env`,
`vendor`, `storage/app` ni logs), instala dependencias sin dev, pone la app
en mantenimiento unos segundos mientras migra y cachea (`composer deploy` =
`migrate --force` + `optimize` + `filament:optimize`), enlaza `storage`,
deja permisos (código de root, `storage` y `bootstrap/cache` de `www-data`)
y comprueba que la web responde. Con OPcache activado el panel responde en
decenas de milisegundos; sin las cachés, Filament descubre recursos y
componentes en cada petición y se nota.

**Disponibilidad y correo:** `/salud` responde 200 con JSON si la aplicación
y la base de datos van, y 503 si no; es lo que vigila el monitor externo
(UptimeRobot, gratis, cada 5 minutos, avisa a plica.contacto@gmail.com).
`php artisan plica:avisar "Asunto" "Texto"` manda un correo al email de
notificaciones con el mailer configurado: sirve para probar el SMTP (Gmail
con contraseña de aplicación: `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`,
`MAIL_PORT=587`, usuario y contraseña de aplicación, y `php artisan
config:cache` después de tocar el `.env`).

**En el servidor, fuera del repo:** el `.env` de producción (clave de
Postgres en `/root/.plica_db_pass`), el sitio de Nginx en
`/etc/nginx/sites-available/plica` (raíz `public/`, subidas hasta 12 MB) y
la copia nocturna: `deploy/plica-backup` copiado a `/usr/local/bin` y una
línea de cron de root a las 3:30 (volcado de Postgres + archivos subidos,
logos y fotos, 14 días, en `/var/backups/plica`, de root pero legible por el
grupo `postgres`). El mismo script deja una réplica idéntica en el
Google Drive de `plica.contacto@gmail.com` (carpeta «Backups-Plica») con
`rclone sync`: remoto `gdrive` en `/root/.config/rclone/rclone.conf`,
autorizado una vez con `rclone authorize "drive"` desde el Mac y con permiso
`drive.file` (rclone solo ve lo que él sube). Si el token dejara de
renovarse, repetir esa autorización y volver a escribir el `token` del
remoto. La copia lleva también un `config-FECHA.tar.gz` (solo root) con el
`.env`, el sitio de Nginx, `rclone.conf`, la contraseña de Postgres y el
crontab: con él y el volcado se reconstruye el servidor desde cero. Si el
script falla o Drive no responde, avisa por correo (`php artisan
plica:avisar`, al email de notificaciones). Restaurar (comprobado el 14 de
septiembre de 2026 restaurando la
copia de esa noche en una base temporal, con los mismos recuentos que la
base en vivo): `sudo -u postgres pg_restore -d plica --clean
/var/backups/plica/plica-FECHA.dump`.

**Correo de las solicitudes:** el formulario de la landing guarda la
solicitud (menú «Solicitudes» del superadmin) y avisa por email a
`PLICA_NOTIFICACIONES_EMAIL` o, si falta, a `PLICA_SUPERADMIN_EMAIL`. Con
`MAIL_MAILER=log` el aviso solo va al log: para recibirlo en Gmail, activar
la verificación en dos pasos, crear una «contraseña de aplicación» y poner
`MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, `MAIL_HOST=smtp.gmail.com`,
`MAIL_PORT=587`, `MAIL_USERNAME` y `MAIL_PASSWORD` en el `.env`, y después
`php artisan config:cache`.

**Primer acceso:** `php artisan plica:club "Nombre" email@club.es` crea el
club, su temporada y el admin con contraseña generada. El usuario cuyo email
es `PLICA_SUPERADMIN_EMAIL` ve además las solicitudes.

**Club de pruebas en producción** (`ClubPruebaSeeder`, cargado el 11 de
septiembre de 2026): «Club de Pruebas» (`/c/club-de-pruebas`, con perfil
público, así que es el «club de ejemplo» de la landing) para que la gente
toquetee sin miedo. Admin `club@club.com` y socio `socio@club.com` (Mario
López), los dos con contraseña `club1234`. Tres secciones con reglas
distintas (Orilla por peso con 500 puntos por asistir y desempate por pieza
mayor; Embarcación por peso con un descarte; Pato — Lucio por medida), 16
socios (uno de baja), 9 mangas pesadas con ranking, 2 pasadas sin pesar
(salen como pendientes) y 3 próximas con ubicación y «Asistiré». Para
dejarlo como nuevo después de que lo destrocen:

```bash
php artisan db:seed --class=ClubPruebaSeeder --force   # borra el club de pruebas y lo recrea igual
```

Los resultados son deterministas (semilla fija); solo las fechas son
relativas al día en que se ejecuta.

## Decisiones tomadas (y por qué)

- **Antispam sin captcha** (`App\Services\AntiSpam`, septiembre de 2026): el
  formulario de solicitud lleva un campo trampa que las personas no ven y un
  sello de tiempo cifrado; se descarta lo que rellena la trampa, lo que llega
  en menos de cuatro segundos, lo que trae enlaces en el mensaje y el mismo
  email dos veces en 24 h. Al bot se le dice «recibido» igual (para que no
  insista), pero ni se guarda ni se avisa; queda en el log como aviso. Límite
  de 5 envíos por IP cada 10 minutos. El WhatsApp de Plica no va en el HTML:
  `/whatsapp` redirige al pulsar y `robots.txt` lo excluye (también
  `/acceso/`, los paneles y Livewire). Si algún día pasa spam de verdad, el
  siguiente paso es Cloudflare Turnstile (gratis, sin puzzles).
- **Páginas de error y cabeceras** (septiembre de 2026): 403, 404, 419, 429,
  500 y 503 en `resources/views/errors`, en castellano, con la marca y un
  botón a la portada (o «Volver atrás» en la 419). El middleware
  `CabecerasSeguridad` pone `X-Frame-Options`, `nosniff` y `Referrer-Policy`
  en todas las respuestas; HSTS lo pone Nginx.
- **Sistema «por puestos» completo** (septiembre de 2026, para Bass
  Extremadura): vuelve al formulario de sección («El ranking de la temporada:
  suma lo pescado / suma los puestos»). Cada manga da tantos puntos como tu
  puesto y gana quien menos suma. Los empates van en la misma regla que el
  desempate (`seccions.desempate`, ver abajo): con «promedio», los empatados
  se reparten el promedio de sus puestos (18,5 cada uno; ocho con cero en el
  22 → 25,5), el sistema de federación. En este
  sistema, «Puntos por no ir» es lo que se lleva un ausente por manga (el
  club pone socios + 1, p. ej. 48); a 0, el último de esa manga + 1. Los
  puntos pueden llevar decimales (`Scoring::formatPuntos`: «18,5»). El cuadro
  manga a manga enseña en cada celda los puntos de esa manga y en las de
  «no fue» lo que costó. `BassExtremaduraSeeder` carga su Orilla (47 socios,
  sin pesajes) y `PorPuestosTest` comprueba su hoja al decimal.
- **Cierre del sistema de la federación** (11 de septiembre de 2026): nuevo
  ajuste por sección «quien va y no pesca (el bolo) se lleva…»
  (`seccions.bolo`, `puntos_bolo`): la media de los puestos que quedan
  (((C + 1) + N) / 2, la fórmula oficial y la de Bass Extremadura), el primer
  puesto libre (C + 1), el último (N), un número fijo, o lo mismo que una
  ausencia. Los bolos siguen ocupando los últimos puestos de la manga; solo
  cambian sus puntos. El Club de Pruebas lleva una **matriz de 30 secciones**
  de federación (peso, medida y piezas × diez combinaciones de desempate,
  ausencia automática o fija, las cinco opciones de bolo y los tres modos de
  descarte) con datos con dos y tres bolos por manga; `MatrizFederacionTest`
  fija sus resultados, calculados aparte con una implementación independiente
  (`oraculo_federacion.py`, fuera del repo), y comprueba que el cuadro
  coincide con el ranking.
- **Cierre de «suma lo pescado»** (11 de septiembre de 2026): `puntos_no_asistencia`
  vuelve al formulario como **«Puntos por ausencia»** en los dos sistemas (en
  suma lo pescado admite negativos: castigo por cada manga a la que no se va;
  por puestos es lo que se lleva quien no va). Nuevo desempate
  `menos_piezas` («quien menos piezas haya sacado», el segundo criterio
  oficial de la FEPyC tras la pieza mayor; no se ofrece en las secciones por
  piezas). El Club de Pruebas lleva una **matriz de 24 secciones** de suma lo
  pescado (peso, medida y piezas × ocho combinaciones de asistencia, empates,
  descartes y ausencia) con datos que fuerzan empates, ceros y ausencias;
  `MatrizAcumuladoTest` fija sus resultados, calculados aparte del motor con
  una implementación independiente, y comprueba que el cuadro coincide con
  el ranking.
- **Página pública de sección, limpia** (14 de septiembre de 2026, a
  imagen del leaderboard de Bassmaster): cabecera sobria (título, temporada
  y mangas celebradas; las reglas plegadas en «Cómo puntúa esta sección»),
  dos tarjetas arriba (líder y pieza mayor de la temporada), y el cuadro
  manga a manga como clasificación general única (antes había una lista y
  luego el cuadro con lo mismo). Tabla sobria: cabecera gris con
  mayúsculas pequeñas, nombres normales, números tabulares, líneas finas;
  una franja «Sin ninguna manga esta temporada» separa a los socios de la
  sección que no han pescado. La lista pública (última manga, club, manga)
  pierde las barras y los nombres grandes: puesto, pescador y dato.
- **Todo en claro** (14 de septiembre de 2026): la web pública (landing,
  páginas del club, sección y manga, acceso, legales y errores) deja el tema
  oscuro y pasa a fondo claro (`slate-50`, tarjetas blancas, verde
  `emerald-600`); los paneles de admin y socio van siempre en claro
  (`->darkMode(false)`), aunque el móvil esté en modo oscuro. Motivo: el
  oscuro no convencía y hacía que la misma clasificación pareciera otra
  cosa según por dónde se entrara.
- **Foto y licencia federativa del socio** (`socios.foto`, `socios.licencia`,
  14 de septiembre de 2026): la ficha del socio (admin) y el perfil del socio
  (`/app/profile`, página `App/Auth/Perfil`, que extiende la de Filament)
  llevan foto y número de licencia. La foto se recorta al centro, se reduce
  a 400 px y se guarda en WebP en el disco «public» (`Services/FotoSocio`,
  con la conversión compartida con el logo en `Services/Imagen`); al
  cambiarla, quitarla o borrar al socio se borra el archivo viejo
  (`Socio::booted`). Decisión: de momento la foto solo se ve en el listado
  de socios del admin, ni en rankings ni en la web pública; los que no
  tienen, una silueta (`public/img/socio.svg`).
- **Horario y quedada de la manga** (14 de septiembre de 2026): la manga
  tiene hora de inicio y de fin (`hora_inicio`, `hora_fin`) y una quedada
  previa opcional, dónde y a qué hora se junta el club antes de ir al agua
  (`quedada_lugar`, `quedada_hora`, `quedada_url`). Todo opcional. Sale en
  la convocatoria («…en Embalse de Entrepeñas, de 08:00 a 14:00.» y «🤝
  Quedada previa a las 07:15 en Bar La Presa» con su «cómo llegar»), en la
  página pública de la manga, en el Inicio del socio, en el listado de
  mangas y en los widgets del admin. Las horas van en columnas `time` y se
  enseñan siempre en corto («08:00») con `Manga::horaCorta`.
- **Secciones por socio** (`seccion_socio`, 14 de septiembre de 2026): hasta
  entonces un socio «era» de una sección solo si había pescado alguna manga
  de ella, y quien no había ido a ninguna no salía en el ranking; en la hoja
  de Bass Extremadura sí salen (cuatro con 96 = 48 + 48). Ahora la pertenencia
  vive en `seccion_socio`: la aprende `Participacion::created`, la rellenó la
  migración con los pesajes que había, y se corrige en la ficha del socio
  («Secciones en las que compite») o en la sección («Socios de la sección»,
  con marcar todos). `Scoring::sociosDelRanking` une a los que pescaron con
  los socios de la sección: los sin mangas suman sus ausencias (48 por manga
  en federación; en suma lo pescado, lo que dé o quite la ausencia) y los
  descartes se les aplican igual (con «también las no pescadas» se descarta
  una ausencia; con «solo las pescadas», nada). Un socio de baja sigue en la
  sección y en el ranking con la etiqueta «Baja» (conserva historial y sigue
  sumando ausencias, salvo que el admin lo desmarque). No hay altas a mitad
  de temporada: los socios de la sección son los mismos todo el año y las
  ausencias cuentan desde la primera manga para todos. Pestañas arriba de
  Socios y de Mangas (`Filament/Concerns/PestanasDeSeccion`: «Todos ·
  Orilla · Pato · Embarcación», solo si el club tiene dos o más secciones),
  recordando en la sesión la última de cada admin; desde la pestaña de una
  sección, «Nuevo socio», «Añadir varios» y «Nueva manga» ya son de ella
  (`?seccion=ID`) y «Dar acceso» solo lista a los suyos; con una sola sección
  en el club, todo va a ella. En el pesaje, primero los de la sección y luego
  «Otros socios del club»; en la asistencia, los de otra sección van
  marcados. Sin permisos por sección (las pestañas evitan el error, no lo
  impiden): el paso siguiente sería «admin de sección». Los seeders de Bass
  Madrid y Bass Extremadura meten a todos sus socios en Orilla (sus hojas los
  listan a todos: 23 y 47) y las dos matrices del Club de Pruebas llevan un
  séptimo socio sin mangas en cada sección, verificado contra los oráculos.
- **Descartes: qué mangas se pueden descartar** (`seccions.descartes_ausencias`,
  11 de septiembre de 2026): con descartes, el club elige si «la peor manga»
  puede ser una a la que no se fue (faltar cuenta como la peor y se descarta
  la primera) o solo entre las pescadas (se quita la peor de las que fue; las
  perdidas cuentan igual). Antes «suma lo pescado» hacía lo segundo y «por
  puestos» lo primero sin decirlo, y el cuadro manga a manga tachaba siempre
  una pescada aunque el ranking hubiera descartado la ausencia. Ahora los dos
  sistemas y el cuadro usan la misma función (`Scoring::descartadas`), y una
  ausencia descartada sale tachada en el cuadro. Al migrar, las secciones por
  puestos quedaron en «también las no pescadas» y las de suma en «solo las
  pescadas», como venían funcionando.
- **Una sola regla de empates** (`seccions.desempate`, 11 de septiembre de
  2026): había dos ajustes que se pisaban (el desempate por pieza mayor se
  aplicaba antes que el «promedio» y solo empataban de verdad los que
  coincidían en todo). Ahora la sección responde una sola pregunta, «si
  empatan, ¿quién gana?», que vale para cada manga y para el ranking:
  `pieza_mayor` / `piezas` / `peso` (desempata algo; si siguen igual,
  comparten puesto), `compartido` (nadie: comparten el puesto) o, solo
  sumando puestos, `promedio` (nadie: se reparten el promedio de sus
  puestos). Sin desempate, dos socios a igual peso empatan aunque uno tenga
  la pieza mayor, que es lo que hacen las federaciones. Bass Extremadura va
  en «promedio». La columna `puestos_empate` se fundió en `desempate`.
  **Sumando puestos, el empate del año es otra regla**
  (`seccions.desempate_general`, 14 de septiembre de 2026): «promedio» y
  «comparten» son reglas de manga que no dicen nada de la general, y los
  reglamentos la resuelven aparte (Castilla-La Mancha: más gramos en el año
  y luego mejor manga; FEPyC: pieza mayor y luego menos capturas). La
  sección responde «si empatan en el ranking de la temporada, ¿quién
  gana?»: `compartido` (por defecto, lo de siempre), `peso` (o `medida` en
  secciones por centímetros), `mejor_manga` (la de menos puntos de las que
  pescó), `pieza_mayor`, `menos_piezas` o `piezas`; una sola regla y, si
  siguen igual, comparten. Solo en el sistema de la federación: en «suma lo
  pescado» la misma regla vale para manga y año. La matriz de la federación
  del Club de Pruebas la cubre (séptima variable) y el oráculo
  independiente la reproduce. Bass Extremadura sigue en «comparten» (así lo
  enseña su hoja) hasta que el club diga otra cosa.
- **Puntos por no ir** (`seccions.puntos_no_asistencia`, septiembre de 2026):
  en el sistema por puestos es lo que se lleva un ausente por manga (socios +
  1; a 0, el último de esa manga + 1) y se configura en el formulario. El
  motor admite también un valor (incluso negativo) en el sistema «suma lo
  pescado», sumado por cada manga celebrada que un socio del ranking no
  pescó, pero **el formulario ya no lo ofrece ahí** (decisión del 11 de
  septiembre de 2026: en «suma lo pescado» solo hay puntos por asistencia,
  que es lo que entienden los clubes). Sale en el resumen de reglas y en las
  celdas «—» del cuadro manga a manga.
- **Enlaces de acceso de un solo uso** (WhatsApp), no emails: el admin genera
  el enlace desde la ficha del socio y se lo manda. Sin cuenta → la crea; con
  cuenta → restablece su contraseña. Cero dependencia de SMTP, cuentas y
  recuperación resueltas con el mismo mecanismo. Sesiones de 30 días para que
  el socio no tenga que reloguearse.
- **El sistema avisa de las mangas por gestionar**: manga con fecha pasada sin
  marcar como celebrada → badge rojo en el menú y aviso en el dashboard del
  admin hasta que la cierre. Asistencia en un checklist («Marcar asistencia»),
  que solo elimina desmarcados sin capturas.
- **Pesaje rápido, sin formularios** (septiembre 2026): tocar una manga abre
  su página de pesaje. Todos los asistentes en una lista, una casilla por
  dato (piezas y gramos, o los centímetros de cada pez separados por
  espacios) y cada casilla se guarda sola al salir de ella; Enter salta a la
  siguiente. Lo tecleado lo traduce `app/Services/PesajeRapido.php` (acepta
  «3.450» y «3,450» como 3450 g) y sustituye las capturas de esa
  participación: peso/piezas → una captura con el total; medida → un pez por
  captura. El detalle pez a pez con notas sigue en «Datos de la manga». Quien
  viene sin estar en la lista se añade desde un desplegable en la misma
  página; el checklist «Marcar asistencia» es el mismo en pesaje y en
  participaciones (`Mangas/Actions/AsistenciaAction`). El cierre natural es
  «Cerrar la manga y ver la clasificación».
- **Alta de socios pegando la lista**: los clubes no tienen CSV, tienen una
  lista en el WhatsApp o en un papel. «Añadir varios» acepta un socio por
  línea con email opcional detrás, aguanta numeraciones y viñetas, y salta
  los que ya existen sin distinguir mayúsculas ni tildes (`Club::altaDeSocios`).
- **Clasificaciones públicas SIEMPRE, para compartir por WhatsApp** (decisión
  de septiembre de 2026): `/c/{club}/{seccion}` (ranking de la sección, cuadro
  manga a manga y última manga) y `/c/{club}/manga/{id}` (clasificación de una
  manga) las ve cualquiera con el enlace, sea o no del club y tenga o no el
  club activado «perfil público» (ese interruptor solo afecta a la portada
  `/c/{club}`). Las secciones llevan `slug` único por club, generado una vez y
  que no cambia al renombrar, para que un enlace compartido no muera. El botón
  «Compartir por WhatsApp» (`partials/compartir`, sin dependencias) está en el
  ranking del admin, en el cuadro, en la clasificación de cada manga, en el
  panel del socio y en las páginas públicas: abre WhatsApp directamente
  (`wa.me/?text=`: la app en el móvil, WhatsApp Web en escritorio) con el
  podio y el enlace ya escritos (`App\Services\Compartir`). Nada de hoja de
  compartir del sistema: se probó y la gente esperaba WhatsApp (septiembre de
  2026). Las páginas públicas llevan etiquetas Open Graph
  para que WhatsApp enseñe título y podio en la vista previa, y el pie «Hecho
  con Plica» enlaza a la landing: es el bucle de captación.
- **Pieza mayor y desempates** (septiembre de 2026): siempre hay premio a la
  pieza mayor, así que el pesaje rápido tiene una casilla «mayor» (con un solo
  pez se deduce sola; por medida es el pez más largo, que ya va pez a pez) y
  `Scoring` la calcula por manga y por temporada (`piezaMayorDe`): sale bajo
  cada clasificación y ranking, en el cuadro y en los textos de WhatsApp. El
  desempate se configura por sección («Si empatan, gana…»: pieza mayor, más
  piezas o, en secciones por piezas, más peso; nunca por lo mismo en lo que se
  empata) y vale para la manga y para el ranking. Si tras el desempate siguen
  iguales, **comparten puesto** (1º, 1º, 3º): nunca decide el azar ni el
  orden de la base de datos. Nada de sorteos.
- **Logotipo del club** («Mi club» en el admin): se sube PNG/JPG/WebP y
  `App\Services\LogoClub` lo convierte a WebP de 512 px como máximo (GD,
  sin dependencias) en el disco `public` (`php artisan storage:link` en el
  deploy). Sale en la cabecera de los dos paneles (`Club::marca()`, logo +
  nombre), en el encabezado del Inicio del socio, en la portada pública y como
  `og:image` de la vista previa de WhatsApp. Al cambiarlo o quitarlo se borra
  el archivo anterior.
- **Marca de Plica** (`App\Support\Marca`): el original es `logo plica.png` en
  la raíz; de ahí salen `public/brand` (WebP de cabecera, imagen por defecto
  de vista previa 1200×630) y `public/icons` (favicons, apple-touch, iconos
  del manifiesto y uno «maskable» con fondo blanco). No hay SVG: es un
  degradado raster y vectorizarlo a mano lo estropearía; los PNG cubren todos
  los tamaños. Donde no hay club en sesión (login, web), la marca es Plica.
- **Landing y textos legales**: la portada cuenta el ciclo de una manga
  (convocar, pesar, compartir), lo que hace y las preguntas de los presidentes;
  el formulario de solicitud es el de siempre. El precio del plan
  (150 €/temporada, gratis hasta enero, fundadores a 99 €) sigue en
  `config/plica.php` pero **está oculto de momento** (decisión de septiembre
  de 2026): la sección de la landing y la cláusula «Precio y pago» de
  `/condiciones` van dentro de un comentario Blade, y condiciones dice
  «gratuito durante el lanzamiento». Para volver a enseñarlo, quitar los dos
  comentarios y actualizar `LandingYLegalTest`. `/aviso-legal`, `/privacidad`,
  `/cookies` y `/condiciones` (con el anexo de encargo de tratamiento del
  art. 28 RGPD) son plantillas adaptadas al producto, **pendientes de que un
  gestor las revise**; el titular sale de `config/plica.php` (`PLICA_TITULAR`,
  `PLICA_NIF`, `PLICA_DIRECCION`, `PLICA_EMAIL_LEGAL` en `.env`). Titular y
  email son obligatorios (RGPD) y salen entre corchetes hasta rellenarlos; NIF
  y dirección solo los exige la LSSI cuando hay actividad económica, así que
  mientras Plica sea gratuita van vacíos y su línea no se pinta. Rellenarlos
  antes de cobrar al primer club. Solo hay cookies técnicas, así que no
  hay banner: están exentas de consentimiento (art. 22.2 LSSI).
- **Dar acceso a los socios**: en Socios, «Dar acceso» lista a los socios
  activos (primero los que no tienen cuenta), cada uno con su enlace personal
  de un solo uso y dos botones: «WhatsApp» (abre WhatsApp directamente) y
  «Copiar». Se mandan uno a uno: el enlace de un socio crea la cuenta
  *de ese socio*, así que nunca va a un grupo, y por eso no hay «copiar
  todos» (lo hubo y se quitó en septiembre de 2026). El mismo mensaje sale en
  la ficha de cada socio (`Socio::mensajeAcceso()`). «Copiar» funciona también
  sin HTTPS (`partials/copiar.blade.php`). **Con teléfono en la ficha**, el
  botón de WhatsApp abre directamente el chat del socio con el mensaje
  (`wa.me/<número>?text=`); sin él, WhatsApp pide elegir el contacto. El
  número se normaliza para wa.me con `Socio::telefonoWhatsApp()` (un móvil
  español de 9 cifras lleva el 34 por defecto). La ficha del socio pide solo
  nombre, teléfono y email; el alta en bloque acepta teléfono y email detrás
  del nombre, en cualquier orden.
- **Instalable en el móvil**: `public/manifest.webmanifest` + iconos en
  `public/icons` (generados desde el SVG de marca), enlazados en la web pública
  y en los dos paneles. «Añadir a pantalla de inicio» abre `/app` a pantalla
  completa; el login único manda a cada uno a su panel.
- **Aviso de cambio de temporada** (`NuevaTemporadaWidget`): en diciembre, o
  si la temporada activa es de un año ya pasado, el Inicio del admin propone
  «Crear la Temporada N+1» con el nombre ya puesto; desaparece en cuanto
  existe. **El cambio de año son dos toques**: crear la temporada y repasar
  los barcos. Socios y secciones son del club y siguen; al crear la temporada
  (`CreateTemporada`) se copian los equipos de la anterior
  (`Temporada::copiarEquiposDe`: mismo nombre, mismos socios, sin los de baja,
  solo en secciones por equipos que aún no tengan equipos ese año) y la
  notificación dice cuántos y cuántos quedaron incompletos. Probado viajando en
  el tiempo a diciembre y enero (`CambioDeTemporadaTest`). Las páginas que calculan rankings memorizan el resultado dentro de
  la petición con `once()` para no calcular dos veces lo mismo.
- **La portada pública resume** cada sección como el panel del socio: podio,
  pieza mayor y «Ver ranking completo y manga a manga»; y la última manga con
  su podio por sección. Las páginas públicas de sección y manga solo enlazan a
  la portada si el club tiene el perfil público activado.
- **Convocatoria y «asistiré»** (septiembre de 2026): la manga tiene un enlace
  de ubicación (Google Maps) y el admin la convoca por WhatsApp desde su ficha
  o su pesaje: un modal con el texto ya escrito (fecha en palabras, lugar,
  «cómo llegar» y el enlace público de la manga), que se retoca antes de
  compartir (`Mangas/Actions/ConvocarAction`). El socio marca «Asistiré» en su
  panel o desde el enlace público, y ve cuántos van. Es una **intención**
  (tabla `confirmacions`), nunca una asistencia: no crea participación ni
  puntúa. Al pasar lista, el checklist arranca con los confirmados si aún no
  había nadie apuntado, y el admin decide quién ha venido de verdad. El
  listado de mangas enseña «N confirmados» hasta que se pasa lista.
- **El Ranking del admin ES la página pública, con pestañas** (septiembre de
  2026): `/admin/ranking?seccion={slug}`, una pestaña por sección como en Mangas
  y sin «Todas» (no hay ranking general). Debajo se pinta el MISMO parcial que
  `/c/{club}/{seccion}` (`public/partials/seccion-ranking`, datos de
  `App\Support\RankingDeSeccion`): escudo y nombre del club grandes (sin enlace
  a la portada), líder y pieza mayor de la temporada, la clasificación general
  como cuadro manga a manga directamente, los botones de compartir y la última
  manga. Para que el panel pueda pintar Tailwind de la web, carga
  `resources/css/publico.css` (solo tema y utilidades, sin preflight) dentro de
  un envoltorio `.plica-web` de fondo claro. En el panel, las mangas enlazan a su
  clasificación del admin (`$urlManga`) y hay un enlace «Ver la página pública».
  La pestaña se recuerda en sesión; sin recuerdo, se abre la sección con más
  participaciones de la temporada. `/admin/ranking/{id}` (`RankingSeccion`)
  sigue existiendo para enlaces directos con el mismo parcial y vuelve a su
  pestaña con «Atrás». Un solo diseño de ranking que mantener.
- **Ranking manga a manga** (`/admin/ranking/{seccion}`, página `RankingSeccion`):
  cada sección del ranking enlaza a un cuadro tipo hoja de cálculo con los
  pescadores en filas (en el orden del ranking, con sus mismos puntos) y las
  mangas celebradas en columnas: lo pescado, el puesto en esa manga, ganador
  de cada manga en dorado, líder resaltado, mangas descartadas tachadas y «—»
  si no participó. Lo calcula `Scoring::cuadroSeccion`, que reutiliza el
  mismo ranking y la misma clasificación de manga (no hay dos verdades). En
  móvil: pescador fijo a la izquierda y total fijo a la derecha (lo que se
  desliza son las mangas), nombres abreviados («Mario L.»), solo el número en
  cada celda con la unidad en la cabecera, y la tabla a sangre hasta los
  bordes de la tarjeta. Ojo: las cabeceras fijas van con `color`, no con
  `opacity`, porque la opacidad vuelve translúcido el fondo del sticky.
- **La tarjeta del podio** (septiembre de 2026): cada manga y cada ranking de
  sección tienen una imagen 1080×1920 (historia de WhatsApp) con el podio de
  tres con foto, del 4º al 33º en dos columnas, la pieza mayor y «y N
  pescadores más en plicapesca.es». La pinta `App\Services\Podio` con GD y
  las fuentes Poppins de `resources/podio/fuentes` (OFL), a partir de la
  MISMA clasificación de `Scoring`: no hay una segunda verdad. Es una caché en
  `storage/app/podios`, con un hash del contenido en el nombre: se genera la
  primera vez que alguien la pide, cambia sola si cambia un pesaje, y se puede
  vaciar (`Podio::vaciar()`) sin perder nada; no entra en el backup. Rutas
  públicas `/c/{club}/manga/{id}/podio.jpg` (`?seccion=` en mangas de club con
  varias secciones) y `/c/{club}/{seccion}/podio.jpg`, siempre con `?v=hash`
  para que WhatsApp no cachee una vieja. Esa imagen es la vista previa
  (Open Graph) de los enlaces de manga y sección: quien pega el enlace en un
  grupo ve el podio. El botón «Compartir imagen» (`partials/compartir-imagen`,
  en páginas públicas y paneles) adjunta el JPEG a la hoja de compartir del
  móvil, la única excepción a «WhatsApp directo», porque un enlace wa.me no
  puede llevar un fichero; en escritorio descarga. Fondo: una foto de
  `resources/podio/fondos` elegida por el id (estable por manga); sin fotos,
  degradado oscuro. Requiere GD con FreeType y JPEG.
- **Secciones por equipos** (septiembre de 2026, en construcción): en
  embarcación la plica es del barco y en carpfishing del equipo, así que una
  sección tiene `modalidad` (`individual` o `equipos`) y `tamano_equipo`. Los
  equipos (`Equipo`: sección, temporada, nombre opcional y socios) son fijos
  toda la temporada; un socio solo está en un equipo por sección y temporada;
  sin nombre, el equipo se presenta con los nombres de sus socios. El menú
  «Equipos» solo aparece si el club tiene alguna sección por equipos, con
  pestañas por sección y «Añadir varios» pegando la lista («Los Lucios: Mario
  López / Javier Ruiz» por línea; `Club::altaDeEquipos` da de alta a los socios
  que no existan y los apunta a la sección). Un club de orilla no nota nada.
  Decisiones: los reservas quedan fuera de momento; si cambia un miembro a
  mitad de temporada el historial sale con la formación actual; un barco con
  un solo tripulante cuenta igual. **La participación es del participante**
  (`participacions.socio_id` o `equipo_id`, nunca los dos): en una sección por
  equipos quien participa en la manga es el equipo, las capturas cuelgan de su
  participación y el motor puntúa por participante (`App\Support\Participante`:
  socio o equipo, con `nombre`, `id`, `foto`, `socios` e `incluye($socioId)`
  para el «· tú»). Las filas de `Scoring` llevan `participante` y, como alias,
  `socio`, para que vistas y tests que leen `->socio->nombre` sigan valiendo.
  `Scoring::segunModalidad` hace que en una sección por equipos solo cuenten
  las participaciones de equipos (y al revés): cambiar la modalidad con
  historial no mezcla barcos con personas. Pasar lista y el pesaje rápido
  listan equipos en las mangas de una sección por equipos (`Manga::porEquipos`,
  `equiposPosibles`); el checklist arranca marcando los equipos con algún socio
  que dijo «asistiré». Las mangas de todo el club siguen siendo individuales.
  Cómo se pinta un equipo (`Participante::lineas`, `nombreCorto`,
  `detalleEquipo`): con nombre propio, su nombre (y sus socios en pequeño en el
  pesaje); sin nombre, un socio por línea en listas y cuadros, aunque la fila
  salga más alta; en columnas estrechas «Mario L. / Sergio R.». La tarjeta del
  podio dibuja los avatares de los socios solapados y el nombre en una o dos
  líneas. El Inicio del socio dice «Tu equipo va 3º». Todas las vistas de
  clasificación son parciales compartidos, así que el panel del socio lo
  hereda sin código propio.
- **Descartes: qué es «la peor manga»** (arreglado el 15 de septiembre de 2026):
  en «suma lo pescado», cada manga aporta lo pescado más los puntos por
  asistencia, o los puntos por ausencia si no se fue; el descarte quita la que
  menos aporta, y si la sección descarta no pescadas, «faltar cuenta como la
  peor manga y es la primera que se descarta» (lo que dice el formulario). Por
  puestos, cada ausencia ya lleva su coste en puntos (el que eligió la
  sección), así que compite con las demás por puntos. En los dos sistemas las
  mangas van por fecha y, a igualdad, se descarta la más antigua, igual en el
  ranking y en el cuadro (antes el ranking usaba el orden de guardado y podía
  tachar una manga distinta de la del cuadro). Sumando lo pescado, **no ir no
  puede sumar**: el formulario no admite puntos por ausencia positivos (0 o
  castigo). **Quién pesca no se cambia con historial**: el radio
  individual/equipos se bloquea si la sección tiene pesajes en la temporada
  activa (cambiarlo escondería ese historial). **Los socios de un equipo con
  capturas no se tocan** (cambiarían quién ganó; el nombre sí). Y una
  participación es de un socio o de un equipo, nunca de ninguno ni de los dos
  (`Participacion::saving`).
- **El formulario de la sección no engaña** (revisión del 15 de septiembre de
  2026): al elegir un sistema se ponen **los valores de ese sistema**
  (`Seccion::valoresDelSistema`), no solo se quitan los inválidos: «Federación»
  trae empate de manga por promedio, general por gramos (centímetros en
  medida), bolo por la media y ausencias descartables; «suma lo pescado», sus
  valores de siempre. Por puestos, quien no va se lleva «el último puesto de
  esa manga más uno» o «un número fijo» (campo virtual `ausencia_fija`; en la
  base sigue siendo `puntos_no_asistencia` = 0 para el automático,
  `SeccionForm::normalizar`), sin hablar de ceros. Fuera el «número de socios»
  del formulario (solo confundía; la columna queda). Los empates se llaman
  «Comparten el puesto» y «Se reparten el promedio (18,5 cada uno), como la
  federación»; el bolo se explica con el ejemplo, sin fórmula. La frase «Así
  puntúa esta sección» va dentro del bloque del ranking, justo bajo el sistema,
  pegada arriba al hacer scroll para verla en el móvil mientras se tocan las
  opciones; los socios de la sección van al final.
- **Las reglas se cuentan en una frase** (`Seccion::resumenReglas`): la misma
  frase en el formulario de sección (en vivo, mientras se configura), en el
  listado y bajo cada ranking (admin, socio y página pública). Si el club no
  entiende cómo puntúa, la frase está mal, no el motor.
- **Listados sin consultas por fila**: la visibilidad de «Borrar» sale de
  `withCount`, no de una consulta por registro.
- **Crear una sección encadena con su calendario**: al guardar una sección
  se va directo a «crear manga» con esa sección puesta (`?seccion=ID`, solo
  si es del club). El formulario de manga es el único con «Crear y añadir
  otra» a propósito: deja puestas temporada y sección y solo hay que teclear
  nombre y fecha de la siguiente. Al crear una manga se vuelve al listado,
  no a su ficha. Dos secciones con el mismo nombre en un club no se pueden
  crear.
- **Móvil primero en TODAS las vistas — también el admin**: los admins de club
  gestionan desde el móvil. Listas con el dato que manda según el criterio de
  la sección, tipografía grande, objetivos táctiles de 44px+ y formularios a
  una columna en pantallas pequeñas.
- **Sensación de app, nunca scroll lateral**: las tablas de Filament usan un
  `Split` plano — UNA sola línea por registro en móvil y escritorio; el dato
  secundario entra por breakpoints (`visibleFrom`) según cabe. Acciones
  siempre a la vista en la fila: lápiz de editar y papelera de borrar (icon
  buttons); lo demás en un menú «⋮». Tocar la fila también abre el registro.
  Un override CSS en `AdminPanelProvider` mantiene las acciones en la misma
  línea en pantallas pequeñas. Toda página interior (crear/editar, pesaje,
  clasificación, perfil) lleva flecha «Atrás» arriba (render hook
  `PAGE_START`), y es **jerárquica, no el historial del navegador**: va
  siempre al «padre» (Mangas → pesaje de la manga → datos / clasificación;
  crear/editar → su listado). Con el historial, un formulario recién enviado
  volvía relleno y se creaba dos veces lo mismo. El destino lo decide
  `AdminPanelProvider::urlAtras` a partir de `Livewire::current()`. Ambos paneles en modo SPA; el panel
  del socio sin menú lateral (una sola pantalla, ancho `2xl`). Iconos SIEMPRE Heroicons
  (los emojis solo dentro de frases); las clasificaciones comparten el
  parcial `filament/partials/lista-clasificacion` en admin y socio.
- **El Inicio del socio es un resumen** (septiembre de 2026): por cada sección,
  el podio, «Vas Nº de M» con su valor, su fila aunque esté fuera del podio,
  la pieza mayor y «Ver ranking completo» (`/app/ranking/{seccion}`: lista
  entera, cuadro manga a manga y última manga). La última manga enseña el
  podio de cada sección y «Ver clasificación completa» (`/app/manga/{id}`).
  Las clasificaciones comparten diseño en los dos paneles
  (`filament/partials/lista-clasificacion`): medallas en el podio, barra de
  distancia con el líder, «· tú» en la fila del socio; el cuadro manga a
  manga es un parcial compartido (`filament/partials/cuadro`) con el enlace de
  cada manga inyectado por el panel.
- **Un solo login** (septiembre de 2026): socios y admins entran por
  `/app/login` («Entrar en Plica») y cada uno acaba en su panel según su rol
  (`App\Http\Responses\LoginPorRol`, enlazado como `LoginResponse` de
  Filament), respetando el enlace del que venían. `/admin/login` existe solo
  para reenviar al login único (`LoginRedirigido`), porque Filament necesita
  una página de login por panel para redirigir a los invitados.
- **Cero callejones sin salida**: un socio que entra en /admin es redirigido
  a su panel; un enlace de acceso usado explica qué hacer; el dashboard del
  admin ofrece siempre «¿Qué quieres hacer?» con las tareas habituales.
- **Solicitudes de la landing** visibles solo para el dueño de la plataforma
  (`PLICA_SUPERADMIN_EMAIL` en `.env`), no para los admins de club.
- **El sistema no interviene el día de la manga**: el ritual del agua no se
  toca (plicas en papel); se digitaliza el después. El pesaje en vivo /
  videopesaje es fase futura, y por eso las capturas ya guardan medida.
- **Multi-tenant desde el día 1**: todo scoped por `club_id`; meter el club
  n.º 2 es dar de alta una fila.
- **Ranking «por puestos» retirado del formulario** (agosto 2026): nadie lo
  usaba y confundía. Toda sección puntúa por suma total. El motor sigue en
  `Scoring` (con sus tests); para reactivarlo, re-añadir el radio de
  `sistema_puntuacion` en el formulario de secciones.
- **El ranking enseña el valor que ordena**: con descartes, la suma bruta
  puede desordenarse a la vista; se muestra `puntos` en su unidad natural
  (kg/cm/piezas), y como «pts» solo si hay puntos por asistencia.

## PENDIENTES — leer antes de desplegar

Aparcado a propósito, no olvidado:

1. **Reglas reales del club piloto**: validar que los botones de sección
   cubren su reglamento; si no, añadir el botón que falte (nunca un motor
   de fórmulas genérico sin reglamentos reales delante).
2. **Datos reales**: nombre del club, socios y calendario de mangas de la
   temporada (hoy hay placeholders de demo).
3. **Deploy a VPS**: hecho el 11 de septiembre de 2026, con dominio, HTTPS,
   cron de backup y réplica en Drive (ver «Producción»). Quedan el correo por
   Gmail (las solicitudes van al log mientras tanto) y el número de WhatsApp
   de la landing (`PLICA_WHATSAPP`).
4. **SMTP opcional**: la recuperación de contraseña ya funciona por enlace de
   acceso (sin correo). Si algún día se quiere el "olvidé mi contraseña"
   autoservicio clásico, configurar mailer y `->passwordReset()`.
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
9. ~~Blindar secciones en BD~~: hecho. `mangas.seccion_id` es obligatorio y
   con `restrictOnDelete`; como toda participación cuelga de una manga de su
   sección, borrar una sección con historial lo impide la base de datos.
11. ~~Secciones por socio~~: hecho el 14 de septiembre de 2026 (ver
    decisiones). Queda para después «admin de sección» (permisos por sección:
    hoy las pestañas evitan el error, no lo impiden).
10. **Panel de Plica (superadmin), con cobros** (apuntado el 11 de septiembre
    de 2026, se hará después del lanzamiento): hoy el superadmin es un admin
    de club más que además ve «Solicitudes». Falta un tercer panel `/plica`,
    solo para `PLICA_SUPERADMIN_EMAIL`, con: Inicio (clubes activos, socios y
    mangas totales, solicitudes sin atender, último acceso por club); Clubes
    (lista con socios, mangas, temporada activa, logo y perfil público;
    «Nuevo club» que hace lo de `plica:club` y enseña el enlace de acceso;
    «Entrar como su admin» para dar soporte); Solicitudes con «Montar club»
    que rellena el alta y la marca atendida; y **cobros por temporada**: para
    cada club y temporada, si es fundador (99 €) o normal (150 €), gratis
    hasta el 1 de enero de 2027, pagado / pendiente, fecha y método
    (transferencia), para saber de un vistazo quién paga y quién no. Al
    hacerlo, borrar el club «Plica» de producción y dejar al superadmin
    fuera de `/admin` salvo cuando entre como admin de un club.
