<?php
namespace App;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class TelegramClient
{
    private string $botToken;
    private string $chatId;
    private Client $http;
    private ?LoggerInterface $logger;

    public function __construct(?string $botToken, ?string $chatId, ?LoggerInterface $logger = null)
    {
        $this->botToken = $botToken ?? '';
        $this->chatId = $chatId ?? '';
        $this->logger = $logger;
        $this->http = new Client([
            'timeout' => 10.0
        ]);
    }

    /**
     * Send a message to telegram
     * @throws \Exception on failure
     */
    public function sendMessage(string $message): array
    {
        if (empty($this->botToken) || empty($this->chatId)) {
            throw new \Exception('Telegram bot token or chat id not configured');
        }

        // Construction complète de l'URL
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

        try {
            $res = $this->http->post($url, [
                'form_params' => [
                    'chat_id' => $this->chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ]
            ]);

            $body = json_decode((string)$res->getBody(), true);
            
            // Ajout de logs pour le débogage
            $this->logger?->debug('Telegram API response', [
                'status' => $res->getStatusCode(),
                'body' => $body
            ]);
            
            if ($res->getStatusCode() === 200 && isset($body['ok']) && $body['ok'] === true) {
                return $body;
            }

            $this->logger?->error('Telegram API error', $body ?? []);
            throw new \Exception('Telegram API error: ' . ($body['description'] ?? 'unknown'));
        } catch (\Throwable $e) {
            $this->logger?->error('Error sending to Telegram: ' . $e->getMessage());
            throw new \Exception('Failed to send message to Telegram: ' . $e->getMessage());
        }
    }
}