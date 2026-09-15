<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Temporada;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La tarjeta del podio: una imagen 1080×1920 (historia de WhatsApp) con la
 * clasificación de una manga o el ranking de una sección, para compartir y
 * como vista previa del enlace. Se pinta con GD, sin navegador ni dependencias,
 * a partir de la MISMA clasificación que enseñan la web y los paneles
 * (`Scoring`): no hay una segunda verdad.
 *
 * Es una caché: se genera la primera vez que alguien la pide y se guarda en
 * storage/app/podios con un hash del contenido en el nombre. Si cambia un
 * pesaje, cambia el hash, se pinta otra y se borra la anterior. Se puede vaciar
 * la carpeta sin perder nada. No entra en el backup.
 *
 * Fondo: una foto de resources/podio/fondos elegida por el id (estable por
 * manga, distinta entre mangas); sin fotos, un degradado oscuro.
 */
class Podio
{
    public const ANCHO = 1080;

    public const ALTO = 1920;

    /** Cuántos salen en la tarjeta: podio de 3 + dos columnas de hasta 15 cada una. */
    public const MAXIMO = 33;

    private const NAVY = [15, 23, 42];

    private const BLANCO = [255, 255, 255];

    private const GRIS = [203, 213, 225];

    private const ESMERALDA = [5, 150, 105];

    private const MEDALLAS = [[245, 158, 11], [148, 163, 184], [180, 83, 9]];

    // ----------------------------------------------------------------------
    // Qué tarjeta
    // ----------------------------------------------------------------------

    /**
     * Tarjeta de una manga. En una manga de club con varias secciones se pinta
     * una por sección (`$grupo` es el grupo de `Scoring::clasificacionManga`).
     * Devuelve la ruta absoluta del JPEG.
     */
    public static function deManga(Manga $manga, ?object $grupo = null): string
    {
        $grupo ??= Scoring::clasificacionManga($manga)->first();
        $club = $manga->temporada->club;

        $filas = $grupo?->filas ?? collect();
        $criterio = $grupo?->criterio ?? Seccion::CRITERIO_PESO;

        $datos = [
            'club' => $club->nombre,
            'logo' => static::rutaLocal($club->logo),
            'titulo' => $manga->nombre,
            'subtitulo' => implode(' · ', array_filter([
                $grupo?->nombre !== 'Sin sección' ? $grupo?->nombre : null,
                $manga->fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            ])),
            'filas' => $filas->map(fn (object $f) => static::filaDe($f, Scoring::valorPrincipal($criterio, $f)))->values()->all(),
            'piezaMayor' => $grupo?->piezaMayor ? "Pieza mayor: {$grupo->piezaMayor->socio->nombre} · {$grupo->piezaMayor->texto}" : null,
            'semilla' => $manga->id,
        ];

        return static::archivo('manga-'.$manga->id.'-s'.($grupo?->seccion?->id ?? 0), $datos);
    }

    /** Tarjeta del ranking de una sección en su temporada (`$grupo` de `Scoring::rankingTemporada`). */
    public static function deRanking(Temporada $temporada, Seccion $seccion, ?object $grupo = null): string
    {
        $grupo ??= Scoring::rankingTemporada($temporada)->firstWhere('seccionId', $seccion->id);
        $club = $seccion->club;

        $filas = $grupo?->filas ?? collect();
        $mangas = $grupo?->numMangas ?? 0;

        $datos = [
            'club' => $club->nombre,
            'logo' => static::rutaLocal($club->logo),
            'titulo' => 'Ranking '.$seccion->nombre,
            'subtitulo' => $temporada->nombre.' · '.$mangas.($mangas === 1 ? ' manga' : ' mangas'),
            // Misma regla que la web y el texto de WhatsApp: «pts» si la sección va por
            // puestos o tiene puntos de participación/no asistencia; si no, la unidad.
            'filas' => $filas
                ->map(fn (object $f) => static::filaDe($f, Compartir::valorRanking($grupo, $f)))
                ->values()->all(),
            'piezaMayor' => $grupo?->piezaMayor ? "Pieza mayor: {$grupo->piezaMayor->socio->nombre} · {$grupo->piezaMayor->texto}" : null,
            'semilla' => 1000 + $seccion->id,
        ];

        return static::archivo('ranking-'.$temporada->id.'-'.$seccion->slug, $datos);
    }

    /** Versión (hash) de la tarjeta de una manga: para que WhatsApp no cachee una vieja. */
    public static function versionManga(Manga $manga, ?object $grupo = null): string
    {
        return static::versionDe(static::deManga($manga, $grupo));
    }

    public static function versionRanking(Temporada $temporada, Seccion $seccion, ?object $grupo = null): string
    {
        return static::versionDe(static::deRanking($temporada, $seccion, $grupo));
    }

    /** URL pública de la tarjeta de una manga (con la versión, para compartir y para Open Graph). */
    public static function urlManga(Manga $manga, ?object $grupo = null): string
    {
        $club = $manga->temporada->club;
        $parametros = ['club' => $club->slug, 'manga' => $manga->id, 'v' => static::versionManga($manga, $grupo)];
        if ($grupo && $grupo->seccion && ! $manga->seccion_id) {
            $parametros['seccion'] = $grupo->seccion->slug;
        }

        return route('club.manga.podio', $parametros);
    }

    public static function urlRanking(Temporada $temporada, Seccion $seccion, ?object $grupo = null): string
    {
        return route('club.seccion.podio', ['club' => $seccion->club->slug, 'seccion' => $seccion->slug, 'v' => static::versionRanking($temporada, $seccion, $grupo)]);
    }

    private static function filaDe(object $f, string $valor): array
    {
        $pt = $f->participante;

        return [
            'puesto' => $f->puesto,
            'nombre' => $pt->nombre,
            'lineas' => $pt->lineas(),          // en el podio: un equipo sin nombre, un socio por línea
            'corto' => $pt->nombreCorto(),      // en las columnas: «Mario L.», el equipo, o «Mario L. / Sergio R.»
            'valor' => $valor,
            'fotos' => array_map(fn ($ruta) => static::rutaLocal($ruta), $pt->fotos()), // un equipo: sus avatares solapados
        ];
    }

    private static function rutaLocal(?string $ruta): ?string
    {
        if (blank($ruta)) {
            return null;
        }
        $absoluta = Storage::disk('public')->path($ruta);

        return is_file($absoluta) ? $absoluta : null;
    }

    // ----------------------------------------------------------------------
    // Caché en disco
    // ----------------------------------------------------------------------

    public static function carpeta(): string
    {
        return storage_path('app/podios');
    }

    private static function archivo(string $prefijo, array $datos): string
    {
        $fondo = static::fondoPara((int) $datos['semilla']);
        $hash = substr(md5(json_encode($datos).'|'.$fondo.'|'.static::versionDiseno()), 0, 12);
        $carpeta = static::carpeta();
        $ruta = "{$carpeta}/{$prefijo}-{$hash}.jpg";

        if (is_file($ruta)) {
            return $ruta;
        }

        if (! is_dir($carpeta)) {
            mkdir($carpeta, 0755, true);
        }
        // Una sola versión viva por tarjeta: fuera las anteriores.
        foreach (glob("{$carpeta}/{$prefijo}-*.jpg") ?: [] as $vieja) {
            @unlink($vieja);
        }

        file_put_contents($ruta, static::pintar($datos, $fondo));

        return $ruta;
    }

    private static function versionDe(string $ruta): string
    {
        return (string) preg_replace('/^.*-([0-9a-f]{12})\.jpg$/', '$1', $ruta);
    }

    /** Cambiar cuando cambie el diseño, para que se regeneren todas. */
    private static function versionDiseno(): string
    {
        return '10';
    }

    // ----------------------------------------------------------------------
    // Pintar
    // ----------------------------------------------------------------------

    public static function fondoPara(int $semilla): ?string
    {
        $fondos = glob(resource_path('podio/fondos/*.{jpg,jpeg,png,webp}'), GLOB_BRACE) ?: [];
        sort($fondos);

        return $fondos === [] ? null : $fondos[$semilla % count($fondos)];
    }

    private static function fuente(string $peso = 'Bold'): string
    {
        return resource_path("podio/fuentes/Poppins-{$peso}.ttf");
    }

    /** @return string bytes JPEG */
    private static function pintar(array $d, ?string $fondo): string
    {
        $W = self::ANCHO;
        $H = self::ALTO;
        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true);

        static::pintarFondo($img, $fondo);

        // ---- Cabecera: club, título, subtítulo
        $x = 64;
        $anchoTexto = $W - 128;
        if ($d['logo']) {
            // Escudo del club arriba a la derecha, grande; el texto de la cabecera le deja sitio.
            $lado = 170;
            static::imagenAjustada($img, $d['logo'], $W - 64 - $lado, 64, $lado, $lado);
            $anchoTexto = $W - 128 - $lado - 24;
        }
        static::texto($img, static::recortar($d['club'], 30, 'SemiBold', $anchoTexto), $x, 110, 30, 'SemiBold', self::GRIS);
        $y = 190;
        $titulo = static::recortar($d['titulo'], 56, 'ExtraBold', $anchoTexto);
        static::texto($img, $titulo, $x, $y, 56, 'ExtraBold', self::BLANCO);
        static::texto($img, static::recortar($d['subtitulo'], 32, 'Regular', $anchoTexto), $x, $y + 56, 32, 'Regular', self::GRIS);

        // ---- Podio de tres
        $filas = $d['filas'];
        $podio = array_slice($filas, 0, 3);
        $resto = array_slice($filas, 3, self::MAXIMO - 3);
        $fuera = max(0, count($filas) - self::MAXIMO);

        $baseY = 860;
        // Tres tarjetas con 24 px de aire entre ellas: laterales de 282, central de 340.
        $geom = [ // [x, ancho, alto, foto]
            0 => [(int) (($W - 340) / 2), 340, 540, 200],
            1 => [64, 282, 470, 160],
            2 => [$W - 64 - 282, 282, 470, 160],
        ];
        foreach ($podio as $i => $f) {
            [$cx, $cw, $ch, $foto] = $geom[$i];
            $cy = $baseY - $ch;
            static::caja($img, $cx, $cy, $cw, $ch, 28, self::NAVY, 0.78);
            static::bordeSuperior($img, $cx, $cy, $cw, $ch, 28, self::MEDALLAS[$i]);
            static::textoCentrado($img, (string) $f['puesto'], $cx + $cw / 2, $cy + 92, $i === 0 ? 76 : 64, 'ExtraBold', self::MEDALLAS[$i]);
            static::avatares($img, $f['fotos'], $cx + $cw / 2, $cy + 126, $foto);
            // Nombre completo si cabe; si no, un poco más pequeño; si tampoco, «Nombre A.».
            // Un equipo sin nombre: un socio por línea.
            $lineas = $f['lineas'];
            $tamNombre = count($lineas) > 1 ? ($i === 0 ? 26 : 24) : ($i === 0 ? 32 : 28);
            $dosLineas = count($lineas) > 1;
            $y = $cy + 126 + $foto + ($dosLineas ? 48 : 60);
            foreach ($lineas as $k => $linea) {
                $texto = static::recortar($linea, $tamNombre, 'Bold', $cw - 32, abreviar: true);
                static::textoCentrado($img, $texto, $cx + $cw / 2, $y + $k * ($tamNombre + 8), $tamNombre, 'Bold', self::BLANCO);
            }
            // Con dos líneas de nombre, el valor baja para no rozarlas.
            $yValor = $cy + 126 + $foto + ($dosLineas ? 150 : 118);
            static::textoCentrado($img, $f['valor'], $cx + $cw / 2, $yValor, $i === 0 ? 44 : 38, 'ExtraBold', self::BLANCO);
        }

        // ---- Pieza mayor
        $y = $baseY + 62;
        if ($d['piezaMayor']) {
            $tam = static::anchoTexto($d['piezaMayor'], 28, 'SemiBold') <= $W - 128 ? 28 : 24;
            static::textoCentrado($img, static::recortar($d['piezaMayor'], $tam, 'SemiBold', $W - 128), $W / 2, $y, $tam, 'SemiBold', self::GRIS);
        }

        // ---- Del 4 en adelante, dos columnas de hasta 15
        $y0 = 950;
        $alto = 48;
        $hueco = 6;   // 15 filas → termina en 1760, por encima del «y N más» (1798) y del pie
        $colAncho = (int) (($W - 128 - 24) / 2);
        // Hasta ocho, una sola columna; más, mitad y mitad (nunca 15 y 2).
        $porColumna = count($resto) > 8 ? (int) ceil(count($resto) / 2) : max(1, count($resto));
        foreach ($resto as $i => $f) {
            $col = intdiv($i, $porColumna);
            $fila = $i % $porColumna;
            $rx = 64 + $col * ($colAncho + 24);
            $ry = $y0 + $fila * ($alto + $hueco);
            static::caja($img, $rx, $ry, $colAncho, $alto, 14, self::NAVY, 0.55);
            $ty = $ry + 33;
            static::texto($img, (string) $f['puesto'], $rx + 18, $ty, 24, 'Bold', self::BLANCO);
            $valorAncho = static::anchoTexto($f['valor'], 24, 'Bold');
            static::texto($img, $f['valor'], $rx + $colAncho - 18 - $valorAncho, $ty, 24, 'Bold', self::BLANCO);
            // El nombre entero si cabe; si no, la cadena de abreviaturas («Juan Antonio P.», «J. Antonio P.», «Juan A.»).
            $nombre = static::recortar($f['nombre'], 24, 'SemiBold', $colAncho - 66 - $valorAncho - 20, abreviar: true);
            static::texto($img, $nombre, $rx + 66, $ty, 24, 'SemiBold', self::BLANCO);
        }

        // ---- Pie
        if ($fuera > 0) {
            static::textoCentrado($img, "y {$fuera} ".($fuera === 1 ? 'pescador más' : 'pescadores más').' en plicapesca.es', $W / 2, $H - 122, 26, 'SemiBold', self::GRIS);
        }
        // Pie: logo de Plica + «plicapesca.es», centrados como un solo bloque.
        $logoPlica = public_path(ltrim(\App\Support\Marca::LOGO, '/'));
        $ladoLogo = 52;
        $textoPie = 'plicapesca.es';
        $anchoPie = static::anchoTexto($textoPie, 26, 'SemiBold');
        $inicio = ($W - ($ladoLogo + 16 + $anchoPie)) / 2;
        if (is_file($logoPlica)) {
            static::imagenAjustada($img, $logoPlica, (int) $inicio, $H - 66 - $ladoLogo + 8, $ladoLogo, $ladoLogo);
            static::texto($img, $textoPie, $inicio + $ladoLogo + 16, $H - 66 + 4, 26, 'SemiBold', self::GRIS);
        } else {
            static::textoCentrado($img, 'Hecho con Plica · '.$textoPie, $W / 2, $H - 60, 26, 'SemiBold', self::GRIS);
        }

        ob_start();
        imagejpeg($img, null, 84);

        return (string) ob_get_clean();
    }

    private static function pintarFondo(\GdImage $img, ?string $fondo): void
    {
        $W = self::ANCHO;
        $H = self::ALTO;

        if ($fondo && ($foto = @imagecreatefromstring((string) file_get_contents($fondo))) !== false) {
            // Recorte «cover» centrado.
            $fw = imagesx($foto);
            $fh = imagesy($foto);
            $escala = max($W / $fw, $H / $fh);
            $rw = (int) ceil($W / $escala);
            $rh = (int) ceil($H / $escala);
            $rx = intdiv($fw - $rw, 2);
            $ry = intdiv($fh - $rh, 2);
            imagecopyresampled($img, $foto, 0, 0, $rx, $ry, $W, $H, $rw, $rh);
        } else {
            // Degradado azul noche → navy.
            for ($y = 0; $y < $H; $y++) {
                $t = $y / $H;
                $c = imagecolorallocate($img, (int) (30 - 15 * $t), (int) (58 - 35 * $t), (int) (95 - 53 * $t));
                imageline($img, 0, $y, $W, $y, $c);
            }
        }

        // Velo oscuro: ligero arriba (la foto se ve detrás del título), más denso hacia abajo
        // donde van las filas. Las cajas llevan su propio fondo, así que no hace falta más.
        [$r, $g, $b] = self::NAVY;
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            $opacidad = $fondo
                ? ($t < 0.5 ? 0.18 + 0.32 * ($t / 0.5) : 0.50 + 0.22 * (($t - 0.5) / 0.5))
                : ($t < 0.45 ? 0.35 + 0.5 * ($t / 0.45) : 0.85);
            $alpha = (int) round(127 * (1 - $opacidad));
            imageline($img, 0, $y, $W, $y, imagecolorallocatealpha($img, $r, $g, $b, $alpha));
        }
    }

    /** Rectángulo redondeado semitransparente, pintado en una capa para que la transparencia sea uniforme. */
    private static function caja(\GdImage $img, int $x, int $y, int $w, int $h, int $radio, array $rgb, float $opacidad): void
    {
        $capa = imagecreatetruecolor($w, $h);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);
        imagefill($capa, 0, 0, imagecolorallocatealpha($capa, 0, 0, 0, 127));
        $c = imagecolorallocatealpha($capa, $rgb[0], $rgb[1], $rgb[2], (int) round(127 * (1 - $opacidad)));
        static::rellenoRedondeado($capa, 0, 0, $w, $h, $radio, $c);
        imagecopy($img, $capa, $x, $y, 0, 0, $w, $h);
    }

    /** Borde de color de 7 px en el borde superior, siguiendo las esquinas redondeadas (como un border-top en CSS). */
    private static function bordeSuperior(\GdImage $img, int $x, int $y, int $w, int $h, int $radio, array $rgb): void
    {
        $capa = imagecreatetruecolor($w, $h);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);
        $transparente = imagecolorallocatealpha($capa, 0, 0, 0, 127);
        imagefill($capa, 0, 0, $transparente);
        static::rellenoRedondeado($capa, 0, 0, $w, $h, $radio, imagecolorallocate($capa, $rgb[0], $rgb[1], $rgb[2]));
        static::rellenoRedondeado($capa, 0, 7, $w, $h + $radio, $radio, $transparente); // vacía el interior
        imagecopy($img, $capa, $x, $y, 0, 0, $w, $h);
    }

    private static function rellenoRedondeado(\GdImage $im, int $x, int $y, int $w, int $h, int $radio, int $color): void
    {
        if ($radio <= 0) {
            imagefilledrectangle($im, $x, $y, $x + $w - 1, $y + $h - 1, $color);

            return;
        }
        imagefilledrectangle($im, $x + $radio, $y, $x + $w - $radio - 1, $y + $h - 1, $color);
        imagefilledrectangle($im, $x, $y + $radio, $x + $w - 1, $y + $h - $radio - 1, $color);
        foreach ([[$x + $radio, $y + $radio], [$x + $w - $radio - 1, $y + $radio], [$x + $radio, $y + $h - $radio - 1], [$x + $w - $radio - 1, $y + $h - $radio - 1]] as [$ex, $ey]) {
            imagefilledellipse($im, $ex, $ey, $radio * 2, $radio * 2, $color);
        }
    }

    /**
     * Uno o varios avatares centrados en $cx: un socio, su foto; un equipo, las
     * fotos de sus socios solapadas (cada una algo más pequeña).
     *
     * @param  array<int, ?string>  $fotos
     */
    private static function avatares(\GdImage $img, array $fotos, float $cx, float $y, int $lado): void
    {
        $n = max(1, count($fotos));
        if ($n === 1) {
            static::circulo($img, $fotos[0] ?? null, $cx - $lado / 2, $y, $lado, null);

            return;
        }
        $n = min($n, 3);
        $ladoCada = (int) round($lado * ($n === 2 ? 0.78 : 0.66));
        $paso = (int) round($ladoCada * 0.62);
        $anchoTotal = $ladoCada + $paso * ($n - 1);
        $x0 = $cx - $anchoTotal / 2;
        $yFoto = $y + ($lado - $ladoCada) / 2;
        for ($k = 0; $k < $n; $k++) {
            static::circulo($img, $fotos[$k] ?? null, $x0 + $k * $paso, $yFoto, $ladoCada, null);
        }
    }

    /** Foto redonda (o iniciales sobre esmeralda si no hay foto). */
    private static function circulo(\GdImage $img, ?string $foto, float $x, float $y, int $lado, ?string $nombre): void
    {
        $capa = imagecreatetruecolor($lado, $lado);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);
        $transparente = imagecolorallocatealpha($capa, 0, 0, 0, 127);
        imagefill($capa, 0, 0, $transparente);

        $origen = $foto ? @imagecreatefromstring((string) file_get_contents($foto)) : false;
        if ($origen !== false) {
            $s = min(imagesx($origen), imagesy($origen));
            imagecopyresampled($capa, $origen, 0, 0, intdiv(imagesx($origen) - $s, 2), intdiv(imagesy($origen) - $s, 2), $lado, $lado, $s, $s);
        } else {
            static::avatar($capa, $lado);
        }

        // Máscara circular con borde suavizado.
        $r = $lado / 2;
        for ($py = 0; $py < $lado; $py++) {
            for ($px = 0; $px < $lado; $px++) {
                $dist = sqrt(($px + 0.5 - $r) ** 2 + ($py + 0.5 - $r) ** 2);
                if ($dist > $r) {
                    imagesetpixel($capa, $px, $py, $transparente);
                } elseif ($dist > $r - 1.5) {
                    $rgb = imagecolorsforindex($capa, imagecolorat($capa, $px, $py));
                    $a = (int) min(127, $rgb['alpha'] + 127 * ($dist - ($r - 1.5)) / 1.5);
                    imagesetpixel($capa, $px, $py, imagecolorallocatealpha($capa, $rgb['red'], $rgb['green'], $rgb['blue'], $a));
                }
            }
        }
        // Aro blanco fino.
        imagealphablending($capa, true);
        imagesetthickness($capa, 3);
        imageellipse($capa, (int) $r, (int) $r, $lado - 3, $lado - 3, imagecolorallocatealpha($capa, 255, 255, 255, 40));
        imagesetthickness($capa, 1);

        imagecopy($img, $capa, (int) $x, (int) $y, 0, 0, $lado, $lado);
    }

    /** Pega una imagen dentro de una caja (contain), centrada, conservando su transparencia. */
    private static function imagenAjustada(\GdImage $img, string $ruta, int $x, int $y, int $w, int $h): void
    {
        $origen = @imagecreatefromstring((string) file_get_contents($ruta));
        if ($origen === false) {
            return;
        }
        imagealphablending($origen, true);
        imagesavealpha($origen, true);
        $ow = imagesx($origen);
        $oh = imagesy($origen);
        $escala = min($w / $ow, $h / $oh);
        $nw = max(1, (int) round($ow * $escala));
        $nh = max(1, (int) round($oh * $escala));
        $capa = imagecreatetruecolor($nw, $nh);
        imagealphablending($capa, false);
        imagesavealpha($capa, true);
        imagefill($capa, 0, 0, imagecolorallocatealpha($capa, 0, 0, 0, 127));
        imagecopyresampled($capa, $origen, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
        imagecopy($img, $capa, $x + intdiv($w - $nw, 2), $y + intdiv($h - $nh, 2), 0, 0, $nw, $nh);
    }

    /** Avatar genérico para quien no tiene foto: silueta (cabeza y hombros) clara sobre gris azulado. */
    private static function avatar(\GdImage $capa, int $lado): void
    {
        $fondo = imagecolorallocate($capa, 71, 85, 105);   // slate-600
        $figura = imagecolorallocate($capa, 203, 213, 225); // slate-300
        imagefilledrectangle($capa, 0, 0, $lado, $lado, $fondo);
        $c = $lado / 2;
        // Cabeza
        imagefilledellipse($capa, (int) $c, (int) ($lado * 0.40), (int) ($lado * 0.36), (int) ($lado * 0.36), $figura);
        // Hombros: media elipse que sale por abajo del círculo (la máscara circular la recorta).
        imagefilledellipse($capa, (int) $c, (int) ($lado * 1.02), (int) ($lado * 0.80), (int) ($lado * 0.70), $figura);
    }

    private static function texto(\GdImage $img, string $t, float $x, float $y, int $tam, string $peso, array $rgb): void
    {
        imagettftext($img, $tam, 0, (int) $x, (int) $y, imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]), static::fuente($peso), $t);
    }

    private static function textoCentrado(\GdImage $img, string $t, float $cx, float $y, int $tam, string $peso, array $rgb): void
    {
        static::texto($img, $t, $cx - static::anchoTexto($t, $tam, $peso) / 2, $y, $tam, $peso, $rgb);
    }

    private static function anchoTexto(string $t, int $tam, string $peso): int
    {
        $caja = imagettfbbox($tam, 0, static::fuente($peso), $t);

        return (int) abs($caja[2] - $caja[0]);
    }

    /** Si no cabe: la cadena de abreviaturas del nombre (si se pide) y, al final, puntos suspensivos. */
    private static function recortar(string $t, int $tam, string $peso, int $maximo, bool $abreviar = false): string
    {
        if (static::anchoTexto($t, $tam, $peso) <= $maximo) {
            return $t;
        }
        if ($abreviar) {
            foreach (\App\Support\Participante::abreviaturas($t) as $corto) {
                if (static::anchoTexto($corto, $tam, $peso) <= $maximo) {
                    return $corto;
                }
            }
            $t = \App\Support\Participante::abreviar($t);
        }
        while (mb_strlen($t) > 1 && static::anchoTexto($t.'…', $tam, $peso) > $maximo) {
            $t = mb_substr($t, 0, -1);
        }

        return rtrim($t).'…';
    }

    /** La misma abreviatura que la web («Miguel Ángel T.», «Sergio del R.»). */
    public static function abreviar(string $nombre): string
    {
        return \App\Support\Participante::abreviar($nombre);
    }

    public static function iniciales(string $nombre): string
    {
        $partes = array_values(array_filter(preg_split('/\s+/', trim($nombre)) ?: []));

        return mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($partes, 0, 2)))) ?: '?';
    }

    /** Descripción de dónde está la caché, para el README y para vaciarla. */
    public static function vaciar(): int
    {
        $n = 0;
        foreach (glob(static::carpeta().'/*.jpg') ?: [] as $f) {
            $n += @unlink($f) ? 1 : 0;
        }

        return $n;
    }

    /** @return Collection<int, string> */
    public static function fondos(): Collection
    {
        return collect(glob(resource_path('podio/fondos/*.{jpg,jpeg,png,webp}'), GLOB_BRACE) ?: [])->map(fn ($f) => basename($f))->values();
    }

    public static function clubDe(Manga $manga): Club
    {
        return $manga->temporada->club;
    }
}
