<?php

namespace App\Filament\Resources\Mangas\Pages;

use App\Filament\Resources\Mangas\MangaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManga extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    protected static string $resource = MangaResource::class;
}
