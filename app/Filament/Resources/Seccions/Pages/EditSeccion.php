<?php

namespace App\Filament\Resources\Seccions\Pages;

use App\Filament\Resources\Seccions\SeccionResource;
use App\Filament\Resources\Seccions\Schemas\SeccionForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeccion extends EditRecord
{
    /** Por puestos con «el último más uno», en la base va 0. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SeccionForm::normalizar($data);
    }

    protected static string $resource = SeccionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
