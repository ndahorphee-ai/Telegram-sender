<?php

namespace App\Security;  // Changé de App\Middleware à App\Security

use App\Security\BotDetector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BotDetectionMiddleware implements MiddlewareInterface
{
    private $botDetector;

    public function __construct(BotDetector $botDetector)
    {
        $this->botDetector = $botDetector;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip bot detection for OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }
        
        // Récupération de l'adresse IP du client
        $serverParams = $request->getServerParams();
        $ipAddress = $serverParams['REMOTE_ADDR'] ?? '';
        
        // Si l'adresse IP est derrière un proxy
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($ips[0]);
        }

        // Détection des bots
        if ($this->botDetector->detectBot($ipAddress)) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write($this->botDetector->getBlockedPage());
            return $response->withStatus(404);
        }

        // Si ce n'est pas un bot, continuer le traitement
        return $handler->handle($request);
    }
}