<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Services\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public Reservation $reservation;
    public ?array $companyBranding;

    /**
     * Create a new message instance.
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;

        // ✅ جلب branding الشركة عشان يظهر في الإيميل
        $this->companyBranding = $this->getCompanyBranding();
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $companyName = $this->companyBranding['app_name'] ?? config('app.name');
        $subject = "✅ Your Reservation at {$companyName} is Confirmed";

        return $this->subject($subject)
            ->view('emails.reservation-confirmed')
            ->with([
                'reservation' => $this->reservation,
                'company' => $this->companyBranding,
                'logo' => $this->getLogoUrl(),
                'primaryColor' => $this->companyBranding['primary_color'] ?? '#1a237e',
            ]);
    }

    /**
     * Get company branding information.
     */
    private function getCompanyBranding(): ?array
    {
        $companyId = $this->reservation->company_id;

        if (!$companyId) {
            return null;
        }

        // ✅ جلب branding من Tenant Context
        return Tenant::forCompany($companyId, function () {
            $company = \App\Models\Company::find(Tenant::id());

            return $company?->branding ?? [
                'app_name' => $company?->name ?? config('app.name'),
                'logo' => null,
                'primary_color' => '#1a237e',
            ];
        });
    }

    /**
     * Get logo URL for the email.
     */
    private function getLogoUrl(): ?string
    {
        $logo = $this->companyBranding['logo'] ?? null;

        if (!$logo) {
            return null;
        }

        return asset('storage/' . $logo);
    }
}
