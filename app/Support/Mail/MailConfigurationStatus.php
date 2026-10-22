<?php

namespace App\Support\Mail;

/**
 * Safe, display-ready view of Laravel mail configuration (no secrets).
 */
final class MailConfigurationStatus
{
    /**
     * @param  list<array{label: string, value: string, sensitive?: bool}>  $rows
     * @param  list<string>  $guidance
     */
    public function __construct(
        public readonly string $mailer,
        public readonly string $transport,
        public readonly string $mode,
        public readonly string $modeLabelAr,
        public readonly string $readinessLabelAr,
        public readonly bool $configLooksComplete,
        public readonly bool $isLocalOnly,
        public readonly bool $canAttemptProviderSend,
        public readonly array $rows,
        public readonly array $guidance,
        public readonly string $fromAddressMasked,
        public readonly string $fromName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mailer' => $this->mailer,
            'transport' => $this->transport,
            'mode' => $this->mode,
            'mode_label_ar' => $this->modeLabelAr,
            'readiness_label_ar' => $this->readinessLabelAr,
            'config_looks_complete' => $this->configLooksComplete,
            'is_local_only' => $this->isLocalOnly,
            'can_attempt_provider_send' => $this->canAttemptProviderSend,
            'rows' => $this->rows,
            'guidance' => $this->guidance,
            'from_address_masked' => $this->fromAddressMasked,
            'from_name' => $this->fromName,
        ];
    }
}
