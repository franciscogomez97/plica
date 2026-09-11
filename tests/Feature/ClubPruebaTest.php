<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Manga;
use App\Models\Seccion;
use App\Models\Socio;
use App\Models\Temporada;
use App\Models\User;
use App\Services\Scoring;
use Database\Seeders\ClubPruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** El club de pruebas de producción: completo, con accesos sencillos y reiniciable. */
class ClubPruebaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_club_de_pruebas_esta_completo(): void
    {
        $this->seed(ClubPruebaSeeder::class);

        $club = Club::where('slug', 'club-de-pruebas')->firstOrFail();
        $this->assertTrue($club->perfil_publico);
        $this->assertSame(16, $club->socios()->count());
        $this->assertSame(1, $club->socios()->where('activo', false)->count());
        $this->assertSame(['Embarcación', 'Orilla', 'Pato — Lucio'], $club->seccions()->orderBy('nombre')->pluck('nombre')->all());

        // Los dos accesos entran, cada uno con su rol.
        $this->assertTrue(Auth::validate(['email' => 'club@club.com', 'password' => 'club1234']));
        $this->assertTrue(Auth::validate(['email' => 'socio@club.com', 'password' => 'club1234']));
        $this->assertTrue(User::where('email', 'club@club.com')->firstOrFail()->isAdmin());
        $socioUser = User::where('email', 'socio@club.com')->firstOrFail();
        $this->assertFalse($socioUser->isAdmin());
        $this->assertSame($socioUser->id, Socio::where('nombre', 'Mario López')->firstOrFail()->user_id);

        // Mangas: 12 pesadas con ranking, 2 pasadas sin pesar (pendientes) y 3 próximas con asistencias.
        $mangas = Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->get();
        $this->assertCount(17, $mangas);
        $this->assertSame(12, $mangas->where('estado', Manga::ESTADO_CELEBRADA)->count());
        $pendientes = $mangas->filter(fn (Manga $m) => $m->pendienteDeGestion());
        $this->assertCount(2, $pendientes);
        $this->assertSame(0, $pendientes->sum(fn (Manga $m) => $m->participacions()->count()));
        $proximas = $mangas->filter(fn (Manga $m) => $m->estado === Manga::ESTADO_PROGRAMADA && $m->fecha->isFuture());
        $this->assertCount(3, $proximas);
        foreach ($proximas as $proxima) {
            $this->assertNotNull($proxima->ubicacion_url);
            $this->assertSame(4, $proxima->confirmacions()->count());
        }

        // Rankings hechos en las tres secciones, con pieza mayor.
        $temporada = Temporada::where('club_id', $club->id)->where('activa', true)->firstOrFail();
        $ranking = Scoring::rankingTemporada($temporada);
        $this->assertCount(3, $ranking);
        foreach ($ranking as $grupo) {
            $this->assertGreaterThan(3, $grupo->filas->count(), $grupo->nombre);
            $this->assertNotNull($grupo->piezaMayor, $grupo->nombre);
        }

        // Embarcación va por puestos con promedio: la 2ª manga trae empates a propósito (1,5 y 5,5).
        $embarcacion = $club->seccions()->where('nombre', 'Embarcación')->firstOrFail();
        $segunda = Manga::where('seccion_id', $embarcacion->id)->where('estado', Manga::ESTADO_CELEBRADA)->orderBy('fecha')->skip(1)->firstOrFail();
        $puntos = Scoring::puntosPorPuesto(Scoring::clasificacionManga($segunda)->first()->filas, Seccion::EMPATE_PROMEDIO);
        $this->assertSame([1.5, 1.5, 3, 5.5, 5.5, 5.5, 5.5], array_values($puntos));
        $this->get('/c/club-de-pruebas/embarcacion')->assertOk()->assertSee('1,5')->assertSee('5,5');

        // La web pública del club y de una sección responden.
        $this->get('/c/club-de-pruebas')->assertOk()->assertSee('Club de Pruebas');
        $this->get('/c/club-de-pruebas/orilla')->assertOk()->assertSee('Ranking Orilla');
    }

    public function test_volver_a_ejecutarlo_reinicia_el_club_sin_cambiar_ids_ni_duplicar_nada(): void
    {
        $this->seed(ClubPruebaSeeder::class);
        $club = Club::where('slug', 'club-de-pruebas')->firstOrFail();
        $admin = User::where('email', 'club@club.com')->firstOrFail();
        $embarcacion = $club->seccions()->where('slug', 'embarcacion')->firstOrFail();
        $mario = Socio::where('club_id', $club->id)->where('nombre', 'Mario López')->firstOrFail();
        $hashAntes = $admin->password;

        // Los testers hacen de las suyas.
        $club->socios()->first()->update(['nombre' => 'Cambiado por un tester']);
        $club->socios()->create(['nombre' => 'Socio de un tester']);
        $club->seccions()->create(['nombre' => 'Sección de un tester']);
        User::create(['name' => 'Cuenta de un tester', 'email' => 'tester@club.com', 'password' => 'secreto123', 'club_id' => $club->id, 'role' => User::ROLE_SOCIO]);
        $embarcacion->update(['puntos_no_asistencia' => 99]);
        Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->first()->delete();

        $this->seed(ClubPruebaSeeder::class);

        // Mismo club, misma cuenta (misma contraseña, sin rehash), misma sección, mismo socio: los ids no cambian.
        $this->assertSame(1, Club::where('slug', 'club-de-pruebas')->count());
        $this->assertSame($club->id, Club::where('slug', 'club-de-pruebas')->firstOrFail()->id);
        $this->assertSame($admin->id, User::where('email', 'club@club.com')->firstOrFail()->id);
        $this->assertSame($hashAntes, $admin->fresh()->password);
        $this->assertSame($embarcacion->id, $club->seccions()->where('slug', 'embarcacion')->firstOrFail()->id);
        $this->assertSame(17, $embarcacion->fresh()->puntos_no_asistencia);
        $this->assertSame($mario->id, Socio::where('club_id', $club->id)->where('nombre', 'Mario López')->firstOrFail()->id);

        // Y lo de los testers, fuera; lo de siempre, de vuelta.
        $this->assertSame(16, $club->socios()->count());
        $this->assertSame(0, Socio::whereIn('nombre', ['Cambiado por un tester', 'Socio de un tester'])->count());
        $this->assertSame(3, $club->seccions()->count());
        $this->assertSame(0, User::where('email', 'tester@club.com')->count());
        $this->assertSame(17, Manga::whereHas('temporada', fn ($q) => $q->where('club_id', $club->id))->count());
        $this->assertSame(0, User::whereNull('club_id')->where('email', 'like', '%@club.com')->count());
    }

    public function test_los_resultados_son_los_mismos_en_cada_reinicio(): void
    {
        $this->seed(ClubPruebaSeeder::class);
        $temporada = Temporada::where('activa', true)->firstOrFail();
        $antes = Scoring::rankingTemporada($temporada)->map(fn ($g) => $g->filas->map(fn ($f) => [$f->socio->nombre, $f->puntos])->all())->all();

        $this->seed(ClubPruebaSeeder::class);
        $temporada = Temporada::where('activa', true)->firstOrFail();
        $despues = Scoring::rankingTemporada($temporada)->map(fn ($g) => $g->filas->map(fn ($f) => [$f->socio->nombre, $f->puntos])->all())->all();

        $this->assertSame($antes, $despues);
    }
}
