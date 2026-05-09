<?php
/**
 * Chatbot IA - Ollama Simple Version
 */

// Avoid output before headers
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// Define Dolibarr constants
define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREPLUGINS', 1);

// Include Dolibarr
$res = 0;
if (file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";

// Clean buffer
ob_clean();

// Headers for streaming
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Get input
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo "data: " . json_encode(['error' => 'Message vide']) . "\n\n";
    flush();
    exit;
}

// Get Ollama config
$model = 'tinyllama';
$url = 'http://161.97.70.41:11434/api/chat';

if (!empty($GLOBALS['conf']->global->CHATBOT_MODEL)) {
    $m = $GLOBALS['conf']->global->CHATBOT_MODEL;
    if (strpos($m, 'ollama:') === 0) {
        $model = substr($m, 7);
    }
}

if (!empty($GLOBALS['conf']->global->CHATBOT_OLLAMA_URL)) {
    $url = rtrim($GLOBALS['conf']->global->CHATBOT_OLLAMA_URL, '/') . '/api/chat';
}

// Build request
$history = $input['history'] ?? [];
$messages = [];

// Add history
foreach ($history as $msg) {
    if (!empty($msg['role']) && !empty($msg['content'])) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}

// Add user message
$messages[] = ['role' => 'user', 'content' => $message];

$payload = json_encode([
    'model' => $model,
    'messages' => $messages,
    'stream' => true
]);

// Call Ollama
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => 0,
    CURLOPT_BINARYTRANSFER => 1,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_BUFFERSIZE => 4096,
    CURLOPT_WRITEFUNCTION => function($curl, $data) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (empty($line = trim($line))) continue;

            $json = json_decode($line, true);
            if (empty($json)) continue;

            // Send token
            if (!empty($json['message']['content'])) {
                $token = $json['message']['content'];
                echo "data: " . json_encode(['token' => $token]) . "\n\n";
                @flush();
                @ob_flush();
            }

            // Check if done
            if (!empty($json['done'])) {
                echo "data: [DONE]\n\n";
                @flush();
                @ob_flush();
            }
        }
        return strlen($data);
    }
]);

$result = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Send error if any
if ($code !== 200 && $code !== 0) {
    echo "data: " . json_encode(['error' => "HTTP $code"]) . "\n\n";
}
if (!empty($error)) {
    echo "data: " . json_encode(['error' => "cURL: $error"]) . "\n\n";
}

exit;
?>
