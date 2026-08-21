<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class Inicio extends Dashboard
{
    protected static ?string $title = 'Inicio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -20;

    public function getHeading(): string
    {
        return 'Hola, '.str(auth()->user()->name)->before(' ').' 👋';
    }

    public function getSubheading(): ?string
    {
        return auth()->user()->club?->nombre;
    }
}
