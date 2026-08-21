<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Temporada;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Onboarding de un club nuevo en un solo comando:
 * club + temporada activa + cuenta de admin con contraseña generada.
 */
class CrearClub extends Command
{
    protected $signature = 'plica:club
        {nombre : Nombre del club}
        {admin_email : Email del admin del club}
        {--admin-nombre= : Nombre del admin (por defecto, «Admin de <club>»)}
        {--localidad=}
        {--temporada= : Nombre de la temporada inicial (por defecto, «Temporada <año>»)}';

    protected $description = 'Da de alta un club con su temporada activa y su primer admin';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('admin_email')));

        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el email {$email}.");

            return self::FAILURE;
        }

        $nombre = trim($this->argument('nombre'));

        $slugBase = Str::slug($nombre);
        $slug = $slugBase;
        for ($i = 2; Club::where('slug', $slug)->exists(); $i++) {
            $slug = "{$slugBase}-{$i}";
        }

        $club = Club::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'localidad' => $this->option('localidad'),
        ]);

        Temporada::create([
            'club_id' => $club->id,
            'nombre' => $this->option('temporada') ?: 'Temporada '.now()->year,
            'activa' => true,
        ]);

        $password = Str::password(12, symbols: false);

        User::create([
            'name' => $this->option('admin-nombre') ?: "Admin de {$nombre}",
            'email' => $email,
            'password' => Hash::make($password),
            'club_id' => $club->id,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info("Club «{$nombre}» creado.");
        $this->table(['Qué', 'Valor'], [
            ['Panel de admin', url('/admin')],
            ['Email', $email],
            ['Contraseña (cámbiala en «Perfil»)', $password],
            ['Web pública', route('club.publico', $club)],
        ]);

        return self::SUCCESS;
    }
}
