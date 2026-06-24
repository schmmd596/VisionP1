<?php
/**
 * Test de Diagnostic - Erreur 429
 */

// Inclure Dolibarr
$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}

header('Content-Type: text/html; charset=utf-8');

print "<!DOCTYPE html>";
print "<html><head><title>Test 429</title>";
print "<style>body{font-family:Arial; padding:20px;} .ok{color:green} .error{color:red} .info{color:blue} pre{background:#f0f0f0; padding:10px;}</style>";
print "</head><body>";

print "<h1>🧪 Diagnostic Erreur 429</h1>";

// 1. Vérifier les constantes
print "<h2>1️⃣ Configuration Sauvegardée</h2>";
$api_key = $conf->global->CHATBOT_API_KEY ?? '';
$provider = $conf->global->CHATBOT_PROVIDER ?? 'non-défini';
$model = $conf->global->CHATBOT_MODEL ?? 'non-défini';

print "<p><strong>Provider:</strong> <span class='" . ($provider === 'mistral' ? 'ok' : 'error') . "'>$provider</span></p>";
print "<p><strong>Model:</strong> <span class='" . (strpos($model, 'mistral-') === 0 ? 'ok' : 'error') . "'>$model</span></p>";
print "<p><strong>API Key:</strong> <span class='info'>***" . substr($api_key, -10) . "</span></p>";

if (empty($api_key)) {
    print "<p class='error'>❌ ERREUR: Pas de clé API configurée!</p>";
}

// 2. Tester la détection
print "<h2>2️⃣ Détection Automatique</h2>";
if (empty($provider) || $provider === 'auto-detect') {
    if (strpos($model, 'mistral-') === 0 || strpos($model, 'pixtral-') === 0) {
        print "<p class='ok'>✅ Détecté comme MISTRAL (par modèle)</p>";
    } else {
        print "<p class='error'>❌ Non détecté comme Mistral</p>";
    }
}

// 3. Tester la connexion à Mistral
print "<h2>3️⃣ Test de Connexion Mistral</h2>";

if (!empty($api_key) && (strpos($model, 'mistral-') === 0 || strpos($model, 'pixtral-') === 0)) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mistral.ai/v1/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        print "<p class='error'>❌ Erreur CURL: $error</p>";
    } else {
        print "<p><strong>Code HTTP:</strong> <span class='" . ($http_code === 200 ? 'ok' : 'error') . "'>$http_code</span></p>";

        if ($http_code === 200) {
            print "<p class='ok'>✅ Connexion Mistral OK!</p>";
            $data = json_decode($response, true);
            if (isset($data['data'])) {
                print "<p>Modèles disponibles: " . count($data['data']) . "</p>";
            }
        } elseif ($http_code === 429) {
            print "<p class='error'>❌ Erreur 429 - QUOTA DÉPASSÉ</p>";
            print "<p>Vérifiez votre quota sur: https://console.mistral.ai/</p>";
        } elseif ($http_code === 401 || $http_code === 403) {
            print "<p class='error'>❌ Erreur 401/403 - CLÉ INVALIDE</p>";
            print "<p>Votre clé API est invalide ou expirée</p>";
        } else {
            print "<p class='error'>❌ Erreur HTTP $http_code</p>";
            print "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    }
} else {
    print "<p class='error'>❌ Configuration non valide pour Mistral</p>";
}

// 4. Recommandations
print "<h2>4️⃣ Recommandations</h2>";
print "<ul>";
print "<li><strong>Vérifier:</strong> https://console.mistral.ai/ - Quota et clés API</li>";
print "<li><strong>Si 429:</strong> Quota dépassé - Attendez ou payez</li>";
print "<li><strong>Si 401:</strong> Clé invalide - Créez une nouvelle clé</li>";
print "<li><strong>Si OK:</strong> Le problème vient du chatbot lui-même</li>";
print "</ul>";

print "</body></html>";
?>
