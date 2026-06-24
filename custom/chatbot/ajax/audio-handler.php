<?php
/**
 * Chatbot IA - Audio Transcription Handler (DISABLED)
 * Note: Web Speech API (navigateur) est utilisé à la place (gratuit + fiable)
 *
 * Cette API reste pour compatibilité future si besoin d'une transcription serveur
 */

ob_start();
@ini_set('display_errors', 0);
@error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
die(json_encode([
    'error' => 'Transcription serveur désactivée. Utilisation Web Speech API du navigateur.',
    'info' => 'Les navigateurs modernes supportent nativement la reconnaissance vocale sans serveur externe'
]));
?>
