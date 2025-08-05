<?php
/**
 * Bot Telegram - Apple Security Updates + Firmware Notifier
 * 05-08-2025
 * ----------------------------------------------------------
 * - Monitorea https://support.apple.com/en-us/102774
 * - Detecta cambios en acknowledgements (mes/nombres)
 * - Detecta nuevas versiones de iOS, iPadOS, macOS, bridgeOS
 * - Notifica automáticamente a todos los usuarios
 * - Ejecutable vía Webhook o GET status=check
 * - Developer By Gerson Aldaz
 */

 // Mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

class AppleBot {
    private $token;
    private $appleUrl = "https://support.apple.com/en-us/102774";
    private $baseApi  = "https://api.ipsw.me/v4/device/";
    private $usersFile = __DIR__ . '/users.json';
    private $ackFile   = __DIR__ . '/latest_ack.json';
    private $firmwareFile = __DIR__ . '/latest_firmware.json';

    // Dispositivos representativos por OS
    private $devices = [
        'iOS'     => 'iPhone15,2',
        'iPadOS'  => 'iPad13,1',
        'macOS'   => 'Mac14,6',
        'bridgeOS'=> 'iBridge2,15'
    ];

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Maneja /start desde Telegram Webhook
     */
    public function handleWebhook() {
        $input = json_decode(file_get_contents('php://input'), true);
        $chatId = $input['message']['chat']['id'] ?? null;
        $text   = $input['message']['text'] ?? '';

        if ($text === '/start' && $chatId) {
            $this->registerUser($chatId);
            $this->sendMessage($chatId, "✅ Suscripción activa.\nRecibirás alertas de Apple Security y nuevas versiones de iOS, iPadOS, macOS, bridgeOS.");
        }
    }

    /**
     * Ejecuta chequeo (cronjob)
     */
    public function runChecker() {
        $this->checkAcknowledgements();
        $this->checkFirmwares();
    }

    private function registerUser($chatId) {
        $users = $this->loadJson($this->usersFile, []);
        if (!in_array($chatId, $users)) {
            $users[] = $chatId;
            file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
        }
    }

    private function sendMessage($chatId, $message) {
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $logFile = __DIR__ . '/telegram_log.txt'; 

        $message = mb_convert_encoding($message, 'UTF-8', 'auto');
        $maxLength = 4000;
        $parts = str_split($message, $maxLength);

        foreach ($parts as $index => $part) {
            $post = [
                'chat_id' => $chatId,
                'text' => $part,
                'parse_mode' => 'HTML'
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $response = curl_exec($ch);

            $logMessage = "[" . date("Y-m-d H:i:s") . "] ChatID: $chatId\n";
            if (curl_errno($ch)) {
                $logMessage .= "cURL Error: " . curl_error($ch) . "\n";
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $logMessage .= "HTTP $httpCode\nResponse: " . $response . "\n";
            }

            $logMessage .= "-----------------------------\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);

            curl_close($ch);

            if ($index > 0) sleep(1);
        }
    }




    private function loadJson($file, $default) {
        return file_exists($file) ? json_decode(file_get_contents($file), true) : $default;
    }

    /**
     * Monitorea reconocimientos Apple
     */
    
    private function checkAcknowledgements() {
        $html = file_get_contents($this->appleUrl);

        // Extraer todos los meses (h3) y sus listas
        preg_match_all('/<h3[^>]*>([A-Za-z]+\s+\d{4})<\/h3>\s*<ul[^>]*>(.*?)<\/ul>/is', $html, $matches, PREG_SET_ORDER);

        if (empty($matches)) return;

        // Primer match = mes más reciente
        $month = trim($matches[0][1]);
        preg_match_all('/<li[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/li>/is', $matches[0][2], $names);

        $latest = [
            'month' => $month,
            'names' => array_map('strip_tags', $names[1] ?? [])
        ];

        $saved = $this->loadJson($this->ackFile, []);

        if ($saved['month'] !== $latest['month'] || $saved['names'] !== $latest['names']) {
            $message = "🔔 <b>Apple web server security acknowledgements UPDATE!</b>\n\n"
                    . "{$latest['month']}\n\n"
                    . implode("\n", $latest['names']);       
            $this->notifyAll($message);
            file_put_contents($this->ackFile, json_encode($latest, JSON_PRETTY_PRINT));
        }
    }



    /**
     * Monitorea versiones de firmware para OS seleccionados
     */
    private function checkFirmwares() {
        $this->devices = [
            'iOS'     => 'iPhone16,2',   // iPhone 15 Pro Max
            'iPadOS'  => 'iPad14,1',     // iPad Pro 2024
            'macOS'   => 'Mac14,6',      // MacBook Pro 2023
            'bridgeOS'=> 'iBridge2,15'   // BridgeOS
        ];

        $latest = [];
        $details = [];

        foreach ($this->devices as $os => $identifier) {
            $url = $this->baseApi . $identifier;
            $data = json_decode(file_get_contents($url), true);
            $fw = $data['firmwares'] ?? [];

            if (!empty($fw)) {
                $version = $fw[0]['version'];
                $build   = $fw[0]['buildid'];
                $latest[$os] = "$version ($build)";

                // Buscar link y detalles
                list($url, $fixes) = $this->getSecurityDetails($os, $version);
                $details[$os] = [
                    'fixes' => implode("\n", $fixes),
                    'link'  => $url ?: "https://support.apple.com/en-us/HT201222"
                ];


            }
        }

        $saved = $this->loadJson($this->firmwareFile, []);
        $changes = [];
        foreach ($latest as $os => $ver) {
            if (($saved[$os] ?? '') !== $ver) {
                $changes[] = "• $os → $ver";
            }
        }

        if (!empty($changes)) {
            $msg = "🚀 <b>Nueva actualización de sistema detectada:</b>\n\n" . implode("\n", $changes);

            // Añadir detalles con link
            foreach ($details as $os => $info) {
                $msg .= "\n\n🔐 <b>$os Detalles de seguridad:</b>\n{$info['fixes']}\n🔗 {$info['link']}";
            }

            $this->notifyAll($msg);
            file_put_contents($this->firmwareFile, json_encode($latest, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Obtiene el link y detalles de seguridad desde la página oficial de Apple
     */
    private function getSecurityDetails($os, $version) {
        try {
            $html = file_get_contents("https://support.apple.com/en-us/HT201222");
            if ($html === false) {
                throw new Exception("No se pudo cargar la página HT201222");
            }

            $patternLink = '/<a href="(\/en-us\/\d+)"[^>]*>\s*' . preg_quote($os, '/') . '[^<]*' . preg_quote($version, '/') . '[^<]*<\/a>/i';
            if (!preg_match($patternLink, $html, $m)) {
                throw new Exception("No se encontró enlace de seguridad para $os $version");
            }

            $url = 'https://support.apple.com' . $m[1];
            $page = file_get_contents($url);
            if ($page === false) {
                throw new Exception("No se pudo cargar la página de detalles $url");
            }

            preg_match_all(
                '/<h3[^>]*>(.*?)<\/h3>\s*' .
                '(?:<p[^>]*>(.*?)<\/p>\s*){3}' .
                '<p[^>]*>(CVE-\d{4}-\d+.*?)<\/p>/is',
                $page,
                $matches,
                PREG_SET_ORDER
            );

            $fixes = [];
            foreach ($matches as $m) {
                $component = strip_tags($m[1] ?? '');
                $impact    = strip_tags($m[2] ?? '');
                $desc      = strip_tags($m[3] ?? '');
                $cve       = isset($m[4]) ? strip_tags($m[4]) : 'Sin CVE';

                $line = "• $component → $impact | $desc | $cve";
                $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5);
                if (strlen($line) > 120) {
                    $line = substr($line, 0, 117) . '...';
                }

                $fixes[] = $line;
            }

            if (empty($fixes)) {
                $fixes[] = "Detalles aún no publicados.";
            }

            return [$url, $fixes];

        } catch (Exception $e) {
            error_log("[getSecurityDetails] " . $e->getMessage());
            return [null, ["Error: " . $e->getMessage()]];
        }
    }

    private function notifyAll($message) {
        echo $message;
        $users = $this->loadJson($this->usersFile, []);
        foreach ($users as $chatId) {
            $this->sendMessage($chatId, $message);
        }
    }
}

/* ---------------- SU PINCHE USO ----------------
   1️ Webhook Telegram:
       POST /bot_ios.php -> handleWebhook()
   2️ Cronjob vía GET:
       /bot_ios.php check
---------------------------------------*/

$bot = new AppleBot("TOKEN TELEGRAM AQUI");

if ((isset($_GET['status']) && $_GET['status'] === 'check') || 
    (isset($argv[1]) && $argv[1] === 'check')) {
    $bot->runChecker();
} else {
    $bot->handleWebhook();
}
