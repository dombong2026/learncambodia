<?php

namespace App\Support;

final class OutboundMail
{
    public static function deliveryEnabled(?string $mailer = null, array $visited = []): bool
    {
        if ($mailer === null) {
            ManagedSmtpSettings::refresh();
            $mailer = config('mail.default');
        }
        if (! is_string($mailer) || in_array($mailer, $visited, true)) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}", []);
        $transport = $configuration['transport'] ?? null;
        if ($transport === null || in_array($transport, ['log', 'array'], true)) {
            return false;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? [];
            foreach ($children as $child) {
                if (! self::deliveryEnabled($child, [...$visited, $mailer])) {
                    return false;
                }
            }

            return $children !== [];
        }

        return true;
    }
}
