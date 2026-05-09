<?php
/**
 * Ollama API Client for local LLM
 */

class OllamaClient {
    private $url;
    private $model;
    private $max_tokens;

    public function __construct($url = 'http://localhost:11434', $model = 'mistral', $max_tokens = 2048) {
        $this->url = rtrim($url, '/');
        $this->model = $model;
        $this->max_tokens = $max_tokens;
    }

    /**
     * Check if Ollama server is available
     */
    public function isAvailable() {
        try {
            $ch = curl_init($this->url . '/api/tags');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $http_code === 200 && $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get list of available models
     */
    public function getModels() {
        try {
            $ch = curl_init($this->url . '/api/tags');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            return $data['models'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Stream chat completion from Ollama
     */
    public function streamChat($messages, $system_prompt = null) {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
            'options' => [
                'num_ctx' => 4096,
                'top_p' => 0.9,
                'top_k' => 40,
                'temperature' => 0.7,
            ]
        ];

        if ($system_prompt) {
            array_unshift($payload['messages'], [
                'role' => 'system',
                'content' => $system_prompt
            ]);
        }

        $ch = curl_init($this->url . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 300,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Get non-streaming chat completion
     */
    public function chat($messages, $system_prompt = null) {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'num_ctx' => 4096,
                'top_p' => 0.9,
                'top_k' => 40,
                'temperature' => 0.7,
            ]
        ];

        if ($system_prompt) {
            array_unshift($payload['messages'], [
                'role' => 'system',
                'content' => $system_prompt
            ]);
        }

        $ch = curl_init($this->url . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new Exception('Ollama API Error: HTTP ' . $http_code);
        }

        $data = json_decode($response, true);
        return $data['message']['content'] ?? '';
    }
}
?>
