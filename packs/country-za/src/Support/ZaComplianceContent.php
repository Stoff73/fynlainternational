<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Support;

/**
 * South African regulatory compliance content (FAIS + POPIA).
 *
 * FAIS = Financial Advisory and Intermediary Services Act (advice disclosure).
 * POPIA = Protection of Personal Information Act (SA data-protection).
 *
 * IMPORTANT — demonstration-grade text. This is consistent with the app's
 * existing "for demonstration purposes only, does not constitute regulated
 * financial advice" stance, but the EXACT regulatory wording, the FSP
 * licence position, and the POPIA operator/responsible-party disclosures MUST
 * be reviewed and signed off by a FAIS/POPIA compliance professional before
 * production use. `review_required` flags this to any consumer.
 */
final class ZaComplianceContent
{
    public function faisDisclaimer(): array
    {
        return [
            'act' => 'Financial Advisory and Intermediary Services Act, 2002 (FAIS)',
            'heading' => 'Not regulated financial advice',
            'body' => 'Fynla provides general financial information and planning tools '
                .'for demonstration and educational purposes only. It is not a licensed '
                .'Financial Services Provider (FSP) under FAIS and does not provide advice '
                .'or intermediary services as defined in the Act. Nothing shown here is a '
                .'recommendation to buy, sell, or hold any financial product. Consult a '
                .'FAIS-licensed financial adviser before making financial decisions.',
            'review_required' => true,
        ];
    }

    public function popiaNotice(): array
    {
        return [
            'act' => 'Protection of Personal Information Act, 2013 (POPIA)',
            'heading' => 'How your information is used',
            'body' => 'Your personal information is processed lawfully and only for the '
                .'purpose of providing the planning tools you use. You have the right to '
                .'access, correct, and request deletion of your information, and to lodge a '
                .'complaint with the Information Regulator. Information is not shared with '
                .'third parties except as required to operate the service or by law.',
            'rights' => [
                'Access your personal information',
                'Correct or update your information',
                'Request deletion of your information',
                'Object to processing',
                'Lodge a complaint with the Information Regulator (South Africa)',
            ],
            'review_required' => true,
        ];
    }

    /**
     * The full SA compliance payload for a UI disclosure surface.
     */
    public function all(): array
    {
        return [
            'fais' => $this->faisDisclaimer(),
            'popia' => $this->popiaNotice(),
        ];
    }
}
