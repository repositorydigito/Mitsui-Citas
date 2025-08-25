<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaCreada extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $datosCliente;
    public $datosVehiculo;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, array $datosCliente, array $datosVehiculo)
    {
        // Cargar la relación de servicios adicionales
        $this->appointment = $appointment->load('additionalServices.additionalService');
        $this->datosCliente = $datosCliente;
        $this->datosVehiculo = $datosVehiculo;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Tu cita ha sido confirmada',
            cc: ['citasmantenimiento@mitsuiautomotriz.com'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.cita-creada',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}