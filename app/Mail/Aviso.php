<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso en texto plano al email de notificaciones de Plica (copias, servidor…). */
class Aviso extends Mailable
{
    public function __construct(public string $asunto, public string $texto) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Plica] {$this->asunto}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.aviso');
    }
}
