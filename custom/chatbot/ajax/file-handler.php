<?php
/**
 * Chatbot IA - File Upload & Analysis Handler
 * Handles image and PDF uploads, extracts data, sends to Claude for analysis
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
// GET FILE FROM REQUEST
// ============================================================
if (empty($_FILES['file'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Aucun fichier fourni']));
}

$file = $_FILES['file'];
$message = trim($_POST['message'] ?? '');

// ============================================================
// VALIDATION
// ============================================================
$allowed_mimes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'application/pdf' => 'pdf'];
$allowed_exts = ['png', 'jpg', 'jpeg', 'pdf'];

if (!in_array($file['type'], array_keys($allowed_mimes)) && !in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowed_exts)) {
    http_response_code(400);
    die(json_encode(['error' => 'Type de fichier non accepté. Acceptés: PNG, JPG, JPEG, PDF']));
}

if ($file['size'] > 25 * 1024 * 1024) {
    http_response_code(413);
    die(json_encode(['error' => 'Fichier trop volumineux (max 25 MB)']));
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Erreur upload: ' . $file['error']]));
}

// ============================================================
// DETERMINE FILE TYPE & READ CONTENT
// ============================================================
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$is_image = in_array($ext, ['png', 'jpg', 'jpeg']);
$is_pdf = $ext === 'pdf';

// Read file content
$file_content = file_get_contents($file['tmp_name']);
if ($file_content === false) {
    http_response_code(500);
    die(json_encode(['error' => 'Impossible de lire le fichier']));
}

// ============================================================
// SEND TO CLAUDE FOR ANALYSIS
// ============================================================

// Detect API provider
if (strpos($api_key, 'sk-or-') === 0) {
    $provider = 'openrouter';
    $api_url = 'https://openrouter.ai/api/v1/messages';
    $model = $conf->global->CHATBOT_MODEL ?? 'anthropic/claude-sonnet-4-6';
    $api_headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'HTTP-Referer: ' . $_SERVER['HTTP_HOST'] ?? 'localhost'
    ];
} elseif (strpos($api_key, 'sk-ant-') === 0) {
    $provider = 'anthropic';
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
// PREPARE REQUEST BASED ON FILE TYPE
// ============================================================

if ($is_image) {
    // ── IMAGE: Use Claude Vision ──────────────────────────
    $base64_image = base64_encode($file_content);
    $image_media_type = $ext === 'png' ? 'image/png' : 'image/jpeg';

    $claude_request = [
        'model' => $model,
        'max_tokens' => 1024,
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $image_media_type,
                            'data' => $base64_image
                        ]
                    ],
                    [
                        'type' => 'text',
                        'text' => 'Analyse cette image. Est-ce une facture, une liste de produits, ou un autre document ? ' .
                                 'Extrais les informations suivantes en format JSON: ' .
                                 '{"type": "facture_client|facture_fournisseur|liste_produits|autre", ' .
                                 '"description": "brève description", ' .
                                 '"extracted_data": "les données principales extraites"}. ' .
                                 'Réponds UNIQUEMENT en JSON valide.'
                    ]
                ]
            ]
        ]
    ];
} else {
    // ── PDF: Extract text, then analyze ──────────────────
    $pdf_text = extractTextFromPDF($file['tmp_name']);

    if (empty($pdf_text)) {
        http_response_code(400);
        die(json_encode(['error' => 'Impossible d\'extraire le texte du PDF']));
    }

    // Limit text to prevent token overflow
    if (strlen($pdf_text) > 4000) {
        $pdf_text = substr($pdf_text, 0, 4000) . '...';
    }

    $claude_request = [
        'model' => $model,
        'max_tokens' => 1024,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Analyse ce document PDF:\n\n' . $pdf_text . '\n\n' .
                           'Est-ce une facture, une liste de produits, ou un autre document ? ' .
                           'Extrais les informations suivantes en format JSON: ' .
                           '{"type": "facture_client|facture_fournisseur|liste_produits|autre", ' .
                           '"description": "brève description", ' .
                           '"extracted_data": "les données principales extraites"}. ' .
                           'Réponds UNIQUEMENT en JSON valide.'
            ]
        ]
    ];
}

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
curl_close($ch);

if ($http_code !== 200) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur lors de l\'analyse: ' . substr($response, 0, 100)]));
}

$response_data = json_decode($response, true);

if (!$response_data || empty($response_data['content'])) {
    http_response_code(500);
    die(json_encode(['error' => 'Réponse API invalide']));
}

// Extract text response
$analysis = '';
if (isset($response_data['content'][0]['type']) && $response_data['content'][0]['type'] === 'text') {
    $analysis = $response_data['content'][0]['text'] ?? '';
}

if (empty($analysis)) {
    http_response_code(500);
    die(json_encode(['error' => 'Pas d\'analyse reçue']));
}

// Try to parse as JSON
$extracted = null;
try {
    $extracted = json_decode($analysis, true);
    if (!is_array($extracted)) {
        throw new Exception('Not array');
    }
} catch (Exception $e) {
    // If not valid JSON, return raw analysis
    $extracted = ['analysis' => $analysis];
}

// ============================================================
// RESPONSE
// ============================================================

$response_json = [
    'success' => true,
    'file_type' => $ext,
    'file_name' => basename($file['name']),
    'document_type' => $extracted['type'] ?? 'autre',
    'analysis' => $extracted,
    'message' => 'Document analysé. ' . ($extracted['description'] ?? '')
];

die(json_encode($response_json));

// ============================================================
// HELPER: Extract text from PDF
// ============================================================
function extractTextFromPDF($pdf_path) {
    // Try pdftotext if available (shell)
    if (@shell_exec('which pdftotext')) {
        $output_file = tempnam(sys_get_temp_dir(), 'pdf_');
        @shell_exec('pdftotext "' . escapeshellarg($pdf_path) . '" "' . escapeshellarg($output_file) . '" 2>/dev/null');
        if (file_exists($output_file)) {
            $text = file_get_contents($output_file);
            @unlink($output_file);
            return $text;
        }
    }

    // Fallback: Try with Imagick if available
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick('pdf:' . $pdf_path);
            $imagick->setImageFormat('txt');
            return $imagick;
        } catch (Exception $e) {
            // Continue
        }
    }

    // Last resort: Try basic PDF text extraction (very limited)
    $pdf_content = file_get_contents($pdf_path);
    if (preg_match_all('/BT[\s\n]+(.*?)ET/s', $pdf_content, $matches)) {
        $text = '';
        foreach ($matches[1] as $match) {
            if (preg_match_all('/\((.*?)\)/', $match, $str_matches)) {
                $text .= implode(' ', $str_matches[1]) . '\n';
            }
        }
        return $text;
    }

    return '';
}
?>
