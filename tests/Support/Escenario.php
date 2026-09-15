<?php

namespace Tests\Support;

use App\Models\Club;
use App\Models\Equipo;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use Illuminate\Support\Collection;

/**
 * Un escenario de temporada escrito como se piensa: una línea por socio, una
 * columna por manga. Cada celda es lo que hizo ese socio en esa manga:
 *   - un entero: lo pescado en la unidad del criterio (gramos, mm o piezas) en una sola captura;
 *   - un array ['g' => 3200, 'p' => 2, 'mayor' => 1800] o, en medida, una lista de mm [420, 385];
 *   - 'bolo': fue y no pescó nada;
 *   - null o '—': no fue.
 * Los socios que aparecen son de la sección aunque no hayan ido a nada.
 * El mismo escenario sirve para probar cualquier configuración de la sección:
 * `configurar()` cambia la sección sin reconstruir nada.
 */
final class Escenario
{
    public Club $club;

    public Temporada $temporada;

    public Seccion $seccion;

    /** @var Collection<string, Socio> por nombre */
    public Collection $socios;

    /** @var Collection<int, Manga> por índice de columna */
    public Collection $mangas;

    /** @var Collection<string, Equipo> por nombre (solo en modalidad equipos) */
    public Collection $equipos;

    /** @var array<string, array<int, mixed>> la especificación tal cual */
    public array $spec;

    /** @param  array<string, array<int, mixed>>  $spec  socio => [celda por manga] */
    public static function crear(array $spec, array $seccion = [], ?string $slug = null): self
    {
        $e = new self;
        $e->spec = $spec;
        $slug ??= 'escenario-'.uniqid();
        $e->club = Club::create(['nombre' => 'Club '.$slug, 'slug' => $slug]);
        $e->temporada = Temporada::create(['club_id' => $e->club->id, 'nombre' => 'Temporada', 'activa' => true]);
        $e->seccion = Seccion::create(['club_id' => $e->club->id, 'nombre' => 'Sección'] + $seccion);
        $e->equipos = collect();

        $numMangas = max(array_map('count', $spec) ?: [0]);
        $e->mangas = collect(range(1, max(1, $numMangas)))->map(fn (int $i) => Manga::create([
            'temporada_id' => $e->temporada->id,
            'seccion_id' => $e->seccion->id,
            'nombre' => "Manga {$i}",
            'fecha' => '2026-03-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'estado' => Manga::ESTADO_CELEBRADA,
        ]));

        $e->socios = collect($spec)->mapWithKeys(fn ($celdas, string $nombre) => [$nombre => Socio::create(['club_id' => $e->club->id, 'nombre' => $nombre])]);
        foreach ($e->socios as $socio) {
            $socio->seccions()->attach($e->seccion->id);
        }

        $e->escribirParticipaciones();

        return $e;
    }

    /** Cambia la configuración de la sección (criterio, sistema, descartes…) sin tocar los datos. */
    public function configurar(array $seccion): self
    {
        $this->seccion->update($seccion);
        $this->seccion = $this->seccion->fresh();

        return $this;
    }

    /**
     * Pasa el escenario a equipos: cada socio pasa a ser un equipo de una persona
     * (o los grupos que se indiquen, nombre de equipo => socios) y las plicas se
     * reescriben a nombre del equipo. Sirve para probar que un equipo puntúa igual.
     *
     * @param  array<string, string[]>|null  $grupos
     */
    public function comoEquipos(?array $grupos = null): self
    {
        $this->configurar(['modalidad' => Seccion::MODALIDAD_EQUIPOS, 'tamano_equipo' => 2]);
        $grupos ??= collect($this->spec)->mapWithKeys(fn ($c, string $nombre) => [$nombre => [$nombre]])->all();

        foreach ($grupos as $nombreEquipo => $miembros) {
            $equipo = $this->seccion->equipos()->create(['temporada_id' => $this->temporada->id, 'nombre' => $nombreEquipo]);
            $equipo->socios()->attach(collect($miembros)->map(fn (string $n) => $this->socios[$n]->id)->all());
            $this->equipos[$nombreEquipo] = $equipo->load('socios');
        }

        foreach ($this->mangas as $manga) {
            $manga->participacions()->delete();
        }
        foreach ($grupos as $nombreEquipo => $miembros) {
            // La plica del equipo: la celda del primer miembro que tenga algo (para equipos de uno, la suya).
            foreach ($this->mangas as $i => $manga) {
                $celda = null;
                foreach ($miembros as $n) {
                    $c = $this->spec[$n][$i] ?? null;
                    if ($c !== null && $c !== '—') {
                        $celda = $c;
                        break;
                    }
                }
                if ($celda === null) {
                    continue;
                }
                $this->escribirCelda($manga->participacions()->create(['equipo_id' => $this->equipos[$nombreEquipo]->id, 'seccion_id' => $this->seccion->id]), $celda);
            }
        }

        return $this;
    }

    private function escribirParticipaciones(): void
    {
        foreach ($this->spec as $nombre => $celdas) {
            foreach ($this->mangas as $i => $manga) {
                $celda = $celdas[$i] ?? null;
                if ($celda === null || $celda === '—') {
                    continue;
                }
                $this->escribirCelda($manga->participacions()->create(['socio_id' => $this->socios[$nombre]->id, 'seccion_id' => $this->seccion->id]), $celda);
            }
        }
    }

    private function escribirCelda($participacion, mixed $celda): void
    {
        if ($celda === 'bolo') {
            return;
        }
        $criterio = $this->seccion->criterio ?? Seccion::CRITERIO_PESO;

        if (is_int($celda)) {
            match ($criterio) {
                Seccion::CRITERIO_MEDIDA => $participacion->capturas()->create(['piezas' => 1, 'medida_mm' => $celda]),
                Seccion::CRITERIO_PIEZAS => $participacion->capturas()->create(['piezas' => $celda, 'peso_gramos' => 0]),
                default => $participacion->capturas()->create(['piezas' => 1, 'peso_gramos' => $celda]),
            };

            return;
        }

        // Lista de peces en mm (medida) o detalle ['g' => gramos, 'p' => piezas, 'mayor' => gramos].
        if (array_is_list($celda)) {
            foreach ($celda as $mm) {
                $participacion->capturas()->create(['piezas' => 1, 'medida_mm' => $mm]);
            }

            return;
        }

        $participacion->capturas()->create(['piezas' => $celda['p'] ?? 1, 'peso_gramos' => $celda['g'] ?? 0]);
        if (isset($celda['mayor'])) {
            $participacion->update(['pieza_mayor_gramos' => $celda['mayor']]);
        }
    }
}
