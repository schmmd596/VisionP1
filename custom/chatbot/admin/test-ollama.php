<?php
/**
 * Testeur Ollama - Valide la connexion et les performances
 */

// Include Dolibarr
$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) {
    // Mode standalone
    session_start();
}

// Check auth if Dolibarr is loaded
if (isset($user) && !$user->admin) {
    http_response_code(403);
    die(json_encode(['error' => 'Accès refusé']));
}

// Get params
$action = $_GET['action'] ?? 'dashboard';
$ollama_url = $_GET['url'] ?? 'http://localhost:11434';
$model = $_GET['model'] ?? 'mistral';

header('Content-Type: application/json; charset=utf-8');

function test_ollama_connection($url) {
    $ch = curl_init($url . '/api/tags');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $time = microtime(true) - $start;
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => $http_code === 200 && $response !== false,
        'http_code' => $http_code,
        'response_time' => number_format($time * 1000, 2) . ' ms',
        'error' => $error,
        'data' => $response ? json_decode($response, true) : null
    ];
}

function test_ollama_model($url, $model) {
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'Dis OK']
        ],
        'stream' => false
    ];

    $ch = curl_init($url . '/api/chat');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $time = microtime(true) - $start;
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = $response ? json_decode($response, true) : null;

    return [
        'success' => $http_code === 200 && $data && !empty($data['message']['content']),
        'http_code' => $http_code,
        'response_time' => number_format($time * 1000, 2) . ' ms',
        'response' => $data['message']['content'] ?? null,
        'error' => $error,
        'tokens' => [
            'prompt' => $data['prompt_eval_count'] ?? 0,
            'completion' => $data['eval_count'] ?? 0,
            'total' => ($data['prompt_eval_count'] ?? 0) + ($data['eval_count'] ?? 0)
        ],
        'timing' => [
            'load_duration' => ($data['load_duration'] ?? 0) / 1e9,
            'prompt_eval_duration' => ($data['prompt_eval_duration'] ?? 0) / 1e9,
            'eval_duration' => ($data['eval_duration'] ?? 0) / 1e9,
        ]
    ];
}

// Routes
switch ($action) {
    case 'status':
        $result = test_ollama_connection($ollama_url);
        die(json_encode($result));

    case 'models':
        $conn = test_ollama_connection($ollama_url);
        if ($conn['success'] && isset($conn['data']['models'])) {
            die(json_encode([
                'success' => true,
                'count' => count($conn['data']['models']),
                'models' => array_column($conn['data']['models'], 'name')
            ]));
        } else {
            http_response_code(500);
            die(json_encode(['error' => 'Cannot fetch models: ' . ($conn['error'] ?? 'Unknown')]));
        }

    case 'test-model':
        $result = test_ollama_model($ollama_url, $model);
        http_response_code($result['success'] ? 200 : 500);
        die(json_encode($result));

    case 'dashboard':
    default:
        // Full dashboard
        $connection = test_ollama_connection($ollama_url);

        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $ollama_url,
            'connection' => $connection,
            'models' => null,
            'test' => null
        ];

        if ($connection['success']) {
            $models_data = $connection['data']['models'] ?? [];
            $result['models'] = [
                'count' => count($models_data),
                'list' => array_map(function($m) {
                    return [
                        'name' => $m['name'],
                        'size' => round($m['size'] / 1e9, 2) . ' GB',
                        'modified' => $m['modified_at'] ?? 'N/A'
                    ];
                }, $models_data)
            ];

            // Test with first model if available
            if (!empty($models_data)) {
                $test_model = $models_data[0]['name'];
                $result['test'] = test_ollama_model($ollama_url, $test_model);
            }
        }

        die(json_encode($result));
}

?>
