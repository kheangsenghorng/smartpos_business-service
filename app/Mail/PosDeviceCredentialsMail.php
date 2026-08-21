<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\PosDevice;
use App\Models\Register;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PosDeviceCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Business $business,
        public Outlet $outlet,
        public Register $register,
        public PosDevice $device,
        public string $plainPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "SmartPOS - POS Device Credentials for {$this->business->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pos-device-credentials',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
