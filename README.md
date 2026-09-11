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

Cubren: páginas públicas, panel admin completo, clasificación por secciones,
panel de socio, control de acceso, flujo de invitación completo, el motor
de puntuación (puestos, descartes, puntos de participación) con casos
calculados a mano, el pesaje rápido (guardado por casilla, medidas pez a
pez, aislamiento entre clubes), el alta de socios en bloque, el resumen de
reglas en formulario, rankings y página pública, el cuadro manga a manga por
sección, la navegación «Atrás» jerárquica y el encadenado sección → mangas.

## Producción

Plica vive en un VPS (Ubuntu 24.04, Nginx, PHP-FPM 8.4, PostgreSQL 16) en
`/var/www/plica`, desplegado el 11 de septiembre de 2026 por IP y HTTP
(dominio y HTTPS, después). Se despliega desde el Mac, sin git en el servidor:

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

**En el servidor, fuera del repo:** el `.env` de producción (clave de
Postgres en `/root/.plica_db_pass`), el sitio de Nginx en
`/etc/nginx/sites-available/plica` (raíz `public/`, subidas hasta 12 MB) y
la copia nocturna: `deploy/plica-backup` copiado a `/usr/local/bin` y una
línea de cron de root a las 3:30 (volcado de Postgres + logos, 14 días, en
`/var/backups/plica`). Pendiente sacar esas copias de la máquina.

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

## Decisiones tomadas (y por qué)

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
  panel del socio y en las páginas públicas: en el móvil abre la hoja de
  compartir del sistema, en escritorio WhatsApp Web, con el podio ya escrito
  (`App\Services\Compartir`). Las páginas públicas llevan etiquetas Open Graph
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
  de un solo uso y dos botones: «WhatsApp» (hoja de compartir del móvil o
  wa.me) y «Copiar». Se mandan uno a uno: el enlace de un socio crea la cuenta
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
  existe. Las páginas que calculan rankings memorizan el resultado dentro de
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
  (kg/cm/piezas), y como «pts» solo si hay puntos de participación.

## PENDIENTES — leer antes de desplegar

Aparcado a propósito, no olvidado:

1. **Reglas reales del club piloto**: validar que los botones de sección
   cubren su reglamento; si no, añadir el botón que falte (nunca un motor
   de fórmulas genérico sin reglamentos reales delante).
2. **Datos reales**: nombre del club, socios y calendario de mangas de la
   temporada (hoy hay placeholders de demo).
3. **Deploy a VPS**: hecho el 11 de septiembre de 2026 por IP y HTTP (ver
   «Producción»). Quedan dominio + HTTPS, cron de backup, copias fuera de la
   máquina y el correo por Gmail.
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
