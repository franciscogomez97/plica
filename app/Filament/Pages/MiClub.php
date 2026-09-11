<?php

namespace App\Filament\Pages;

use App\Models\Club;
use App\Services\LogoClub;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * «Mi club»: los datos del club y su logotipo. El logo se convierte a WebP
 * al subirlo (LogoClub) y sale en la cabecera de los dos paneles, en la web
 * pública y en la vista previa de WhatsApp.
 */
class MiClub extends Page
{
    protected string $view = 'filament.mi-club';

    protected static ?string $slug = 'mi-club';

    protected static ?string $title = 'Mi club';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 50;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getClub()->only([
            'nombre', 'localidad', 'descripcion', 'email_contacto', 'telefono_contacto', 'perfil_publico', 'logo',
        ]));
    }

    public function getClub(): Club
    {
        return auth()->user()->club;
    }

    public function getSubheading(): ?string
    {
        return 'Lo que ven los socios y cualquiera que abra un enlace del club.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('logo')
                    ->label('Logotipo')
                    ->image()
                    ->disk(LogoClub::DISCO)
                    ->directory('logos')
                    ->visibility('public')
                    ->maxSize(4096)
                    ->imagePreviewHeight('140')
                    ->helperText('PNG, JPG o WebP, hasta 4 MB. Se reduce a un tamaño razonable y se guarda en WebP. Sale en el panel de los socios, en la web pública y en la vista previa de los enlaces en WhatsApp.')
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => LogoClub::guardar($file, $this->getClub())),
                TextInput::make('nombre')
                    ->label('Nombre del club')
                    ->required()
                    ->maxLength(120),
                TextInput::make('localidad')
                    ->label('Localidad')
                    ->maxLength(120),
                Textarea::make('descripcion')
                    ->label('Presentación')
                    ->helperText('Dos o tres líneas para la portada pública.')
                    ->rows(3),
                TextInput::make('email_contacto')
                    ->label('Email de contacto')
                    ->email()
                    ->maxLength(120),
                TextInput::make('telefono_contacto')
                    ->label('Teléfono de contacto')
                    ->tel()
                    ->maxLength(40),
                Toggle::make('perfil_publico')
                    ->label('Portada pública del club')
                    ->helperText('La portada en /c/'.$this->getClub()->slug.'. Las clasificaciones que se comparten por WhatsApp son públicas siempre, con o sin portada.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $datos = $this->form->getState();
        $club = $this->getClub();

        // Logo cambiado o quitado: el archivo viejo no se queda huérfano.
        if (filled($club->logo) && $club->logo !== ($datos['logo'] ?? null)) {
            LogoClub::borrar($club->logo);
        }

        $club->update($datos);

        Notification::make()
            ->title('Guardado')
            ->body('Los cambios ya se ven en el panel de los socios y en la web pública.')
            ->success()
            ->send();
    }
}
