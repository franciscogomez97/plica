<?php

namespace App\Mail;

use App\Models\Solicitud;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NuevaSolicitud extends Mailable
{
    public function __construct(public Solicitud $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🎣 Nueva solicitud de club: {$this->solicitud->club_nombre}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.nueva-solicitud');
    }
}
