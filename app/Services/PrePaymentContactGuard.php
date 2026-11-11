<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PrePaymentContactGuard
{
    /**
     * Bloquer le partage de coordonnées et de liens tant que le paiement n’est pas confirmé.
     *
     * @throws ValidationException
     */
    public function ensureNoContactDetails(string $message, string $field = 'body'): void
    {
        $normalized = mb_strtolower($message);
        $phoneCandidate = preg_replace('/\b(?:19|20)\d{2}[-\/.]\d{1,2}[-\/.]\d{1,2}\b/u', '', $message) ?? $message;
        $compact = preg_replace('/[\s().\-+]+/u', '', $phoneCandidate) ?? $phoneCandidate;

        $containsEmail = preg_match('/[\pL0-9._%+-]+\s*@\s*[\pL0-9.-]+\s*\.\s*[a-z]{2,}/iu', $normalized) === 1;
        $containsLink = preg_match('/(?:https?:\/\/|www\.|\b[\w-]+\.(?:com|net|org|ci|io|app|co)\b)/iu', $normalized) === 1;
        $containsPhone = preg_match('/(?:\+?\d[\d\s().-]{7,}\d)/u', $phoneCandidate) === 1
            || preg_match('/(?:\+?\d{8,15})/u', $compact) === 1;

        if ($containsEmail || $containsLink || $containsPhone) {
            throw ValidationException::withMessages([
                $field => 'Les coordonnées et les liens peuvent être partagés après confirmation du paiement.',
            ]);
        }
    }
}
