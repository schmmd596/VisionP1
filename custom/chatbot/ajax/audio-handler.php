<?php
/**
 * Chatbot IA - Audio Transcription Handler
 * Handles voice input, transcribes via Claude API
 */

ob_start();
@ini_set('display_errors', 0);
@error_reporting(0);

define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREPLUGINS', 1);

$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php"))   $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if (!$user->id) { http_response_code(401); die(json_encode(['error' => 'Non authentifié'])); }
if (empty($conf->chatbot->enabled)) { http_response_code(403); die(json_encode(['error' => 'Module désactivé'])); }

$api_key = $conf->global->CHATBOT_API_KEY ?? '';
if (empty($api_key)) {
    http_response_code(503);
    die(json_encode(['error' => 'Clé API non configurée']));
}

// ============================================================
// GET AUDIO DATA FROM REQUEST
// ============================================================

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (empty($input['audio_base64'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Aucun audio fourni']));
}

$audio_base64 = $input['audio_base64'];
$audio_duration = (float)($input['audio_duration'] ?? 0);

// Remove data URL prefix if present
if (strpos($audio_base64, 'data:audio/') === 0) {
    $audio_base64 = preg_replace('#^data:audio/[^;]+;base64,#', '', $audio_base64);
}

// ============================================================
// VALIDATION
// ============================================================

if (strlen($audio_base64) < 100) {
    http_response_code(400);
    die(json_encode(['error' => 'Audio trop court ou invalide']));
}

if ($audio_duration > 120) {
    http_response_code(413);
    die(json_encode(['error' => 'Durée audio trop longue (max 2 minutes)']));
}

if ($audio_duration < 1) {
    http_response_code(400);
    die(json_encode(['error' => 'Durée audio invalide']));
}

// ============================================================
// DETECT API PROVIDER & BUILD REQUEST
// ============================================================

if (strpos($api_key, 'sk-or-') === 0) {
    // OpenRouter
    $api_url = 'https://openrouter.ai/api/v1/messages';
    $model = $conf->global->CHATBOT_MODEL ?? 'anthropic/claude-sonnet-4-6';
    $api_headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'HTTP-Referer: ' . $_SERVER['HTTP_HOST'] ?? 'localhost'
    ];
} elseif (strpos($api_key, 'sk-ant-') === 0) {
    // Anthropic native
    $api_url = 'https://api.anthropic.com/v1/messages';
    $model = $conf->global->CHATBOT_MODEL ?? 'claude-sonnet-4-6';
    $api_headers = [
        'x-api-key: ' . $api_key,
        'anthropic-version: 2023-06-01',
        'Content-Type: application/json'
    ];
} else {
    http_response_code(500);
    die(json_encode(['error' => 'Provider API non configuré correctement']));
}

// ============================================================
// BUILD CLAUDE REQUEST WITH AUDIO
// ============================================================

$claude_request = [
    'model' => $model,
    'max_tokens' => 256,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'audio/wav',
                        'data' => $audio_base64
                    ]
                ],
                [
                    'type' => 'text',
                    'text' => 'Transcris cet audio en texte français. Réponds UNIQUEMENT avec le texte transcrit, sans explication.'
                ]
            ]
        ]
    ]
];

// ============================================================
// CALL CLAUDE API
// ============================================================

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $api_headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($claude_request));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200) {
    http_response_code(500);
    $error_msg = !empty($curl_error) ? $curl_error : 'Erreur API Claude';
    if ($response) {
        $resp_data = json_decode($response, true);
        if (isset($resp_data['error']['message'])) {
            $error_msg = $resp_data['error']['message'];
        }
    }
    die(json_encode(['error' => $error_msg]));
}

$response_data = json_decode($response, true);

if (!$response_data || empty($response_data['content'])) {
    http_response_code(500);
    die(json_encode(['error' => 'Réponse API invalide']));
}

// Extract transcription
$transcribed_text = '';
if (isset($response_data['content'][0]['type']) && $response_data['content'][0]['type'] === 'text') {
    $transcribed_text = trim($response_data['content'][0]['text'] ?? '');
}

if (empty($transcribed_text)) {
    http_response_code(500);
    die(json_encode(['error' => 'Impossible de transcrire l\'audio']));
}

// ============================================================
// RESPONSE
// ============================================================

$response_json = [
    'success' => true,
    'transcribed_text' => $transcribed_text,
    'duration' => $audio_duration,
    'confidence' => 0.95  // Placeholder - Claude doesn't return confidence
];

die(json_encode($response_json));
?>
