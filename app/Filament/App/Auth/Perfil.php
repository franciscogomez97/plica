<?php

namespace App\Filament\App\Auth;

use App\Models\Socio;
use App\Services\FotoSocio;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Perfil del socio: además de nombre, email y contraseña, su foto y su
 * número de licencia federativa, que viven en su ficha de socio. La foto no
 * se enseña de momento en ningún sitio público: solo en el listado de socios
 * del admin. Vive fuera de App/Pages para que el panel no la descubra como
 * una página más (ya se registra con ->profile()).
 */
class Perfil extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        $socio = $this->socio();

        return $schema
            ->components(array_values(array_filter([
                $socio ? FileUpload::make('foto')
                    ->label('Foto')
                    ->image()
                    ->avatar()
                    // El recorte al cuadrado lo hace el servidor (FotoSocio): sin exigir proporción al archivo subido.
                    ->imageAspectRatio(null)
                    ->disk(FotoSocio::DISCO)
                    ->directory('socios')
                    ->visibility('public')
                    ->maxSize(6144)
                    ->helperText('Opcional. Se recorta al centro y se guarda pequeña. De momento solo la ve el admin del club.')
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => FotoSocio::guardar($file, $socio->club_id))
                    ->nullable() : null,
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $socio ? TextInput::make('licencia')
                    ->label('Nº de licencia federativa')
                    ->placeholder('Si la tienes')
                    ->maxLength(40)
                    ->nullable() : null,
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ])));
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($socio = $this->socio()) {
            $data['foto'] = $socio->foto;
            $data['licencia'] = $socio->licencia;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($socio = $this->socio()) {
            $socio->update([
                'foto' => $data['foto'] ?? null,
                'licencia' => filled($data['licencia'] ?? null) ? trim($data['licencia']) : null,
            ]);
        }

        unset($data['foto'], $data['licencia']);

        return parent::handleRecordUpdate($record, $data);
    }

    private function socio(): ?Socio
    {
        return $this->getUser()->socio;
    }
}
