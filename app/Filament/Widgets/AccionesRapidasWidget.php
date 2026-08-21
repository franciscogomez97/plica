<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/** Botones grandes con las tareas habituales: pensado para admins en móvil. */
class AccionesRapidasWidget extends Widget
{
    protected string $view = 'filament.widgets.acciones-rapidas';

    protected static ?int $sort = -5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
}
