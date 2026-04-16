<?php
/**
 * Chatbot IA - Page de configuration administrateur
 */

$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = @include "../../../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (!$user->admin) accessforbidden();

$langs->load("admin");

$action = GETPOST('action', 'aZ09');

// ── Save settings ──────────────────────────────────────────
if ($action === 'update') {
    $api_key    = GETPOST('CHATBOT_API_KEY', 'alphanohtml');
    $model      = GETPOST('CHATBOT_MODEL', 'alphanohtml');
    $enabled    = GETPOST('CHATBOT_ENABLED', 'aZ09') ? '1' : '0';
    $max_tokens = (int)GETPOST('CHATBOT_MAX_TOKENS', 'int');

    if ($max_tokens < 256)  $max_tokens = 256;
    if ($max_tokens > 8192) $max_tokens = 8192;

    dolibarr_set_const($db, 'CHATBOT_API_KEY',    $api_key,                  'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'CHATBOT_MODEL',       $model ?: 'anthropic/claude-sonnet-4-6', 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'CHATBOT_ENABLED',     $enabled,                  'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'CHATBOT_MAX_TOKENS',  $max_tokens,               'chaine', 0, '', $conf->entity);

    setEventMessages("Configuration sauvegardée avec succès.", null, 'mesgs');
    header("Location: setup.php");
    exit;
}

// ── Current values ─────────────────────────────────────────
$current_key    = $conf->global->CHATBOT_API_KEY ?? '';
$current_model  = $conf->global->CHATBOT_MODEL ?? 'anthropic/claude-sonnet-4-6';
$current_enabled = $conf->global->CHATBOT_ENABLED ?? '1';
$current_tokens = $conf->global->CHATBOT_MAX_TOKENS ?? '2048';

// Detect provider from key
$key_prefix = substr($conf->global->CHATBOT_API_KEY ?? '', 0, 6);
$detected_provider = ($key_prefix === 'sk-or-') ? 'openrouter' : (($key_prefix === 'sk-ant') ? 'anthropic' : 'openai');

// ── Available models ───────────────────────────────────────
$models = [
    // OpenRouter models
    'anthropic/claude-sonnet-4-6'  => '[OpenRouter] Claude Sonnet 4.6 (Recommandé)',
    'anthropic/claude-opus-4-6'    => '[OpenRouter] Claude Opus 4.6 (Le plus puissant)',
    'anthropic/claude-haiku-4-5'   => '[OpenRouter] Claude Haiku 4.5 (Le plus rapide)',
    'openai/gpt-4o'                => '[OpenRouter] GPT-4o',
    'openai/gpt-4o-mini'           => '[OpenRouter] GPT-4o Mini (Économique)',
    'google/gemini-2.0-flash-001'  => '[OpenRouter] Gemini 2.0 Flash',
    // Native Anthropic
    'claude-sonnet-4-6'            => '[Anthropic] Claude Sonnet 4.6',
    'claude-opus-4-6'              => '[Anthropic] Claude Opus 4.6',
    // Native OpenAI
    'gpt-4o'                       => '[OpenAI] GPT-4o',
    'gpt-4o-mini'                  => '[OpenAI] GPT-4o Mini',
];

// ── Page render ────────────────────────────────────────────
llxHeader('', 'Configuration Chatbot IA', '');

print load_fiche_titre('🤖 Configuration du Chatbot IA', '', 'fa-robot');

// Status banner
if (empty($current_key)) {
    print '<div class="warning"><strong>Clé API manquante.</strong> Le chatbot ne fonctionnera pas sans clé API.</div>';
} elseif ($current_enabled === '1') {
    $provider_labels = ['openrouter'=>'OpenRouter (sk-or-...)', 'anthropic'=>'Anthropic (sk-ant-...)', 'openai'=>'OpenAI (sk-...)'];
    print '<div class="ok"><strong>Chatbot actif.</strong> Provider détecté : <strong>'.$provider_labels[$detected_provider].'</strong></div>';
} else {
    print '<div class="warning"><strong>Chatbot désactivé</strong> dans les paramètres.</div>';
}

?>
<br>
<form method="POST" action="setup.php">
<input type="hidden" name="action" value="update">
<?php print '<input type="hidden" name="token" value="'.newToken().'">'; ?>

<table class="noborder centpercent">
<thead>
<tr class="liste_titre">
    <th colspan="3">Paramètres de l'API Claude (Anthropic)</th>
</tr>
</thead>
<tbody>

<!-- API Key -->
<tr class="oddeven">
    <td class="fieldrequired" style="width:30%"><strong>Clé API Anthropic</strong></td>
    <td>
        <input type="password" name="CHATBOT_API_KEY" id="CHATBOT_API_KEY"
               value="<?php echo htmlspecialchars($current_key); ?>"
               size="60" class="flat"
               placeholder="sk-ant-api03-...">
        <button type="button" onclick="toggleKey()" class="button smallpaddingimp">Afficher</button>
    </td>
    <td>
        Obtenez votre clé sur
        <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>
    </td>
</tr>

<!-- Model -->
<tr class="oddeven">
    <td><strong>Modèle Claude</strong></td>
    <td>
        <select name="CHATBOT_MODEL" class="flat" style="min-width:300px">
            <?php foreach ($models as $id => $label): ?>
            <option value="<?php echo $id; ?>" <?php echo ($current_model === $id) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </td>
    <td>Le modèle utilisé pour générer les réponses</td>
</tr>

<!-- Max tokens -->
<tr class="oddeven">
    <td><strong>Tokens maximum</strong></td>
    <td>
        <input type="number" name="CHATBOT_MAX_TOKENS"
               value="<?php echo (int)$current_tokens; ?>"
               min="256" max="8192" step="256" class="flat" style="width:100px">
    </td>
    <td>Longueur max des réponses (256-8192, recommandé: 2048)</td>
</tr>

<!-- Enabled -->
<tr class="oddeven">
    <td><strong>Activer le chatbot</strong></td>
    <td>
        <input type="checkbox" name="CHATBOT_ENABLED" value="1"
               <?php echo ($current_enabled === '1') ? 'checked' : ''; ?>>
        Afficher le widget sur toutes les pages
    </td>
    <td>Désactivez pour masquer le chatbot sans désinstaller le module</td>
</tr>

</tbody>
</table>

<br>
<div class="center">
    <input type="submit" class="button button-save" value="Sauvegarder la configuration">
    <a href="../../../admin/modules.php" class="button button-cancel">Retour aux modules</a>
</div>

</form>

<!-- Test section -->
<br>
<table class="noborder centpercent">
<thead>
<tr class="liste_titre">
    <th>Test de connexion à l'API</th>
</tr>
</thead>
<tbody>
<tr class="oddeven">
<td>
    <button type="button" class="button" onclick="testApi()">🔌 Tester la connexion API</button>
    <span id="test-result" style="margin-left:15px;font-weight:600"></span>
</td>
</tr>
</tbody>
</table>

<!-- Capabilities documentation -->
<br>
<table class="noborder centpercent">
<thead>
<tr class="liste_titre">
    <th colspan="2">Fonctionnalités disponibles dans le chatbot</th>
</tr>
</thead>
<tbody>
<?php
$features = [
    ['🔍 Recherche en temps réel', 'Produits, clients, fournisseurs - données directement depuis la base Dolibarr'],
    ['📊 Statistiques', 'CA, factures impayées, stock faible - par jour/semaine/mois/année'],
    ['📄 Factures clients', 'Créer, avec sélection automatique du client et des produits'],
    ['📄 Factures fournisseurs', 'Créer avec référence fournisseur et lignes détaillées'],
    ['➕ Produits', 'Ajouter de nouveaux produits avec catégorie, prix, TVA'],
    ['📋 Historique factures', 'Consulter les dernières factures clients et fournisseurs'],
    ['🌐 Multilingue', 'Répond en français, arabe, anglais selon la question posée'],
];
foreach ($features as $f): ?>
<tr class="oddeven">
    <td style="width:35%;font-weight:600"><?php echo $f[0]; ?></td>
    <td><?php echo $f[1]; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<script>
function toggleKey() {
    var el = document.getElementById('CHATBOT_API_KEY');
    el.type = el.type === 'password' ? 'text' : 'password';
}

function testApi() {
    var result = document.getElementById('test-result');
    result.style.color = '#888';
    result.textContent = 'Test en cours...';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo dol_buildpath('/chatbot/ajax/chat.php', 1); ?>', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.timeout = 15000;
    xhr.onload = function() {
        try {
            var data = JSON.parse(xhr.responseText);
            if (data.success) {
                result.style.color = '#22c55e';
                result.textContent = '✅ Connexion réussie ! L\'API répond correctement.';
            } else {
                result.style.color = '#ef4444';
                result.textContent = '❌ Erreur: ' + (data.error || 'Inconnue');
            }
        } catch(e) {
            result.style.color = '#ef4444';
            result.textContent = '❌ Réponse invalide du serveur';
        }
    };
    xhr.onerror = xhr.ontimeout = function() {
        result.style.color = '#ef4444';
        result.textContent = '❌ Impossible de contacter le serveur';
    };
    xhr.send(JSON.stringify({message: 'Réponds juste "OK" pour confirmer que tu fonctionnes.', history: []}));
}
</script>

<?php llxFooter(); ?>
