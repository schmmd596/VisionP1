<?php
/**
 * Chatbot IA - Backend AJAX Handler v3.0
 * Systeme complet : ERP + Comptabilite + Fiscalite Mauritanienne
 * Supporte : OpenRouter (sk-or-v1-...), OpenAI (sk-...), Anthropic (sk-ant-...)
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
    die(json_encode(['error' => 'Clé API non configurée. Allez dans Configuration → Chatbot IA → Setup.']));
}

$input       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$user_message = trim($input['message'] ?? '');
$history      = $input['history'] ?? [];
$max_tokens   = (int)($conf->global->CHATBOT_MAX_TOKENS ?? 2048);
$file_context = $input['file_context'] ?? '';  // For file analysis context
$user_language = $input['language'] ?? 'fr-FR';  // User's detected language

// Detect API provider from key prefix
if (strpos($api_key, 'sk-or-') === 0) {
    $provider    = 'openrouter';
    $api_url     = 'https://openrouter.ai/api/v1/chat/completions';
    $model       = $conf->global->CHATBOT_MODEL ?? 'anthropic/claude-sonnet-4-6';
} elseif (strpos($api_key, 'sk-ant-') === 0) {
    $provider    = 'anthropic';
    $api_url     = 'https://api.anthropic.com/v1/messages';
    $model       = $conf->global->CHATBOT_MODEL ?? 'claude-sonnet-4-6';
} else {
    $provider    = 'openai';
    $api_url     = 'https://api.openai.com/v1/chat/completions';
    $model       = $conf->global->CHATBOT_MODEL ?? 'gpt-4o';
}

if (empty($user_message)) die(json_encode(['error' => 'Message vide']));

// ============================================================
// LOAD KNOWLEDGE BASE
// ============================================================
$knowledge_dir = dirname(__FILE__).'/../knowledge/';
$pcm_knowledge = '';
$fisc_knowledge = '';
if (file_exists($knowledge_dir.'plan_comptable_mr.php')) {
    require_once $knowledge_dir.'plan_comptable_mr.php';
    $pcm_knowledge = get_plan_comptable_knowledge();
}
if (file_exists($knowledge_dir.'fiscalite_mr.php')) {
    require_once $knowledge_dir.'fiscalite_mr.php';
    $fisc_knowledge = get_fiscalite_knowledge();
}

// ============================================================
// TOOLS DEFINITIONS
// ============================================================
$tools = [
    // ── RECHERCHE & CONSULTATION ────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'search_products',
        'description' => 'Recherche des produits/services dans le système. Retourne ref, label, prix, stock, TVA.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Terme de recherche (vide = tout lister)'],
            'limit'  => ['type' => 'integer', 'description' => 'Nombre max de résultats', 'default' => 10],
            'type'   => ['type' => 'integer', 'description' => '0=produits, 1=services, -1=tous', 'default' => -1],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'search_clients',
        'description' => 'Recherche des clients (tiers) dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Nom, code client ou email'],
            'limit'  => ['type' => 'integer', 'default' => 10],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'search_fournisseurs',
        'description' => 'Recherche des fournisseurs dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Nom ou code fournisseur'],
            'limit'  => ['type' => 'integer', 'default' => 10],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_invoices',
        'description' => 'Récupère les factures clients ou fournisseurs. Filtrable par statut, client, période.',
        'parameters' => ['type' => 'object', 'properties' => [
            'type'      => ['type' => 'string', 'enum' => ['client', 'fournisseur']],
            'status'    => ['type' => 'string', 'enum' => ['all', 'draft', 'validated', 'paid', 'unpaid'], 'default' => 'all'],
            'client_id' => ['type' => 'integer', 'description' => 'Filtrer par ID tiers'],
            'period'    => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'all'],
            'limit'     => ['type' => 'integer', 'default' => 15],
        ], 'required' => ['type']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_invoice_details',
        'description' => 'Récupère le détail complet d\'une facture (lignes, paiements, etc.) par son ID ou sa référence.',
        'parameters' => ['type' => 'object', 'properties' => [
            'invoice_id'  => ['type' => 'integer', 'description' => 'ID de la facture'],
            'invoice_ref' => ['type' => 'string', 'description' => 'Référence de la facture (ex: FA2401-0001)'],
            'type'        => ['type' => 'string', 'enum' => ['client', 'fournisseur'], 'default' => 'client'],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_stats',
        'description' => 'Statistiques globales : CA, factures impayées, stock faible, clients actifs, etc.',
        'parameters' => ['type' => 'object', 'properties' => [
            'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year'], 'default' => 'month'],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'search_users',
        'description' => 'Recherche des utilisateurs du système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Nom, prénom ou login'],
            'limit'  => ['type' => 'integer', 'default' => 10],
        ]],
    ]],

    // ── BANQUE & PAIEMENTS ──────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'get_bank_accounts',
        'description' => 'Liste tous les comptes bancaires avec leur solde actuel.',
        'parameters' => ['type' => 'object', 'properties' => [
            'limit' => ['type' => 'integer', 'default' => 10],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_bank_transactions',
        'description' => 'Récupère les mouvements bancaires d\'un compte avec filtres.',
        'parameters' => ['type' => 'object', 'properties' => [
            'account_id' => ['type' => 'integer', 'description' => 'ID du compte bancaire (utiliser get_bank_accounts pour obtenir)'],
            'period'     => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'month'],
            'type'       => ['type' => 'string', 'enum' => ['all', 'credit', 'debit'], 'default' => 'all'],
            'reconciliation_status' => ['type' => 'string', 'enum' => ['all', 'reconciled', 'pending'], 'default' => 'all', 'description' => 'Filtrer par statut rapprochement'],
            'search_label' => ['type' => 'string', 'description' => 'Rechercher dans le libellé'],
            'limit'      => ['type' => 'integer', 'default' => 20],
        ], 'required' => ['account_id']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_payments',
        'description' => 'Récupère les paiements enregistrés (clients ou fournisseurs).',
        'parameters' => ['type' => 'object', 'properties' => [
            'type'   => ['type' => 'string', 'enum' => ['client', 'fournisseur'], 'default' => 'client'],
            'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'month'],
            'limit'  => ['type' => 'integer', 'default' => 15],
        ]],
    ]],

    // ── COMMANDES ────────────────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'get_orders',
        'description' => 'Récupère les commandes clients.',
        'parameters' => ['type' => 'object', 'properties' => [
            'status'    => ['type' => 'string', 'enum' => ['all', 'draft', 'validated', 'shipped', 'closed'], 'default' => 'all'],
            'client_id' => ['type' => 'integer'],
            'period'    => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'all'],
            'limit'     => ['type' => 'integer', 'default' => 15],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_supplier_orders',
        'description' => 'Récupère les commandes fournisseurs (achats).',
        'parameters' => ['type' => 'object', 'properties' => [
            'status'        => ['type' => 'string', 'enum' => ['all', 'draft', 'validated', 'approved', 'received', 'closed'], 'default' => 'all'],
            'fournisseur_id' => ['type' => 'integer'],
            'period'        => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'all'],
            'limit'         => ['type' => 'integer', 'default' => 15],
        ]],
    ]],

    // ── COMPTABILITE ────────────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'get_accounting_entries',
        'description' => 'Récupère les écritures comptables du journal (bookkeeping).',
        'parameters' => ['type' => 'object', 'properties' => [
            'account_number' => ['type' => 'string', 'description' => 'Numéro de compte (ex: 411, 512, 607)'],
            'journal_code'   => ['type' => 'string', 'description' => 'Code journal (VT=ventes, AC=achats, BQ=banque, OD=opérations diverses)'],
            'period'         => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'month'],
            'limit'          => ['type' => 'integer', 'default' => 20],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_account_balance',
        'description' => 'Solde et mouvements d\'un ou plusieurs comptes comptables.',
        'parameters' => ['type' => 'object', 'properties' => [
            'account_number' => ['type' => 'string', 'description' => 'Numéro de compte ou préfixe (ex: 41 pour tous les comptes clients, 512 pour banque)'],
            'period'         => ['type' => 'string', 'enum' => ['month', 'year', 'all'], 'default' => 'year'],
        ], 'required' => ['account_number']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_chart_of_accounts',
        'description' => 'Liste les comptes du plan comptable configurés dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'prefix' => ['type' => 'string', 'description' => 'Préfixe de compte (ex: 4 pour tiers, 5 pour financier, 6 pour charges, 7 pour produits)'],
            'limit'  => ['type' => 'integer', 'default' => 30],
        ]],
    ]],

    // ── CREATION DE DONNEES ─────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'create_product',
        'description' => 'Crée un nouveau produit ou service dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'ref'         => ['type' => 'string'],
            'label'       => ['type' => 'string'],
            'price'       => ['type' => 'number'],
            'description' => ['type' => 'string'],
            'tva_tx'      => ['type' => 'number', 'description' => 'Taux TVA (16 pour Mauritanie)', 'default' => 16],
            'type'        => ['type' => 'integer', 'description' => '0=produit, 1=service', 'default' => 0],
            'cost_price'  => ['type' => 'number', 'description' => 'Prix de revient'],
        ], 'required' => ['ref', 'label', 'price']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_client',
        'description' => 'Crée un nouveau client (tiers) dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'name'         => ['type' => 'string', 'description' => 'Nom ou raison sociale'],
            'client_code'  => ['type' => 'string', 'description' => 'Code client (auto si vide)'],
            'email'        => ['type' => 'string'],
            'phone'        => ['type' => 'string'],
            'address'      => ['type' => 'string'],
            'town'         => ['type' => 'string', 'description' => 'Ville'],
            'zip'          => ['type' => 'string', 'description' => 'Code postal'],
            'country_code' => ['type' => 'string', 'description' => 'Code pays (MR pour Mauritanie)', 'default' => 'MR'],
            'nif'          => ['type' => 'string', 'description' => 'Numéro d\'Identification Fiscale'],
            'is_also_supplier' => ['type' => 'boolean', 'description' => 'Aussi fournisseur ?', 'default' => false],
        ], 'required' => ['name']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_fournisseur',
        'description' => 'Crée un nouveau fournisseur dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'name'            => ['type' => 'string', 'description' => 'Nom ou raison sociale'],
            'supplier_code'   => ['type' => 'string', 'description' => 'Code fournisseur (auto si vide)'],
            'email'           => ['type' => 'string'],
            'phone'           => ['type' => 'string'],
            'address'         => ['type' => 'string'],
            'town'            => ['type' => 'string'],
            'zip'             => ['type' => 'string'],
            'country_code'    => ['type' => 'string', 'default' => 'MR'],
            'nif'             => ['type' => 'string', 'description' => 'Numéro d\'Identification Fiscale'],
            'is_also_client'  => ['type' => 'boolean', 'default' => false],
        ], 'required' => ['name']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_facture_client',
        'description' => 'Crée une facture client avec ses lignes et la valide.',
        'parameters' => ['type' => 'object', 'properties' => [
            'client_id'   => ['type' => 'integer'],
            'client_name' => ['type' => 'string', 'description' => 'Nom du client (recherche auto si pas d\'ID)'],
            'date'        => ['type' => 'string', 'description' => 'Date (YYYY-MM-DD)'],
            'ref_client'  => ['type' => 'string'],
            'payment_condition' => ['type' => 'integer', 'description' => 'Condition de paiement (1=comptant, 30=30j, 60=60j)'],
            'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'product_ref' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'qty'         => ['type' => 'number'],
                'price'       => ['type' => 'number'],
                'tva_tx'      => ['type' => 'number', 'default' => 16],
            ], 'required' => ['qty', 'price']]],
        ], 'required' => ['lines']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_facture_fournisseur',
        'description' => 'Crée une facture fournisseur avec ses lignes et la valide.',
        'parameters' => ['type' => 'object', 'properties' => [
            'fournisseur_id'   => ['type' => 'integer'],
            'fournisseur_name' => ['type' => 'string'],
            'ref_fournisseur'  => ['type' => 'string'],
            'date'             => ['type' => 'string'],
            'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'product_ref' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'qty'         => ['type' => 'number'],
                'price'       => ['type' => 'number'],
                'tva_tx'      => ['type' => 'number', 'default' => 16],
            ], 'required' => ['qty', 'price']]],
        ], 'required' => ['lines']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_payment',
        'description' => 'Enregistre un paiement sur une facture (client ou fournisseur) via un compte bancaire.',
        'parameters' => ['type' => 'object', 'properties' => [
            'invoice_id'     => ['type' => 'integer', 'description' => 'ID de la facture à payer'],
            'invoice_ref'    => ['type' => 'string', 'description' => 'Ou la référence de la facture'],
            'type'           => ['type' => 'string', 'enum' => ['client', 'fournisseur'], 'default' => 'client'],
            'amount'         => ['type' => 'number', 'description' => 'Montant du paiement (total de la facture si vide)'],
            'payment_mode'   => ['type' => 'string', 'enum' => ['VIR', 'CHQ', 'CB', 'LIQ', 'PRE'], 'description' => 'VIR=virement, CHQ=chèque, CB=carte, LIQ=espèces, PRE=prélèvement', 'default' => 'VIR'],
            'date'           => ['type' => 'string', 'description' => 'Date du paiement (YYYY-MM-DD)'],
            'num_payment'    => ['type' => 'string', 'description' => 'Numéro du chèque/virement'],
            'bank_account_id' => ['type' => 'integer', 'description' => 'ID compte bancaire (OBLIGATOIRE - utiliser get_bank_accounts)'],
        ], 'required' => ['type', 'bank_account_id']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_order',
        'description' => 'Crée une commande client.',
        'parameters' => ['type' => 'object', 'properties' => [
            'client_id'   => ['type' => 'integer'],
            'client_name' => ['type' => 'string'],
            'date'        => ['type' => 'string'],
            'ref_client'  => ['type' => 'string'],
            'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'product_ref' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'qty'         => ['type' => 'number'],
                'price'       => ['type' => 'number'],
                'tva_tx'      => ['type' => 'number', 'default' => 16],
            ], 'required' => ['qty', 'price']]],
        ], 'required' => ['lines']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_supplier_order',
        'description' => 'Crée une commande fournisseur (bon de commande achat).',
        'parameters' => ['type' => 'object', 'properties' => [
            'fournisseur_id'   => ['type' => 'integer'],
            'fournisseur_name' => ['type' => 'string'],
            'date'             => ['type' => 'string'],
            'ref_supplier'     => ['type' => 'string'],
            'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'product_ref' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'qty'         => ['type' => 'number'],
                'price'       => ['type' => 'number'],
                'tva_tx'      => ['type' => 'number', 'default' => 16],
            ], 'required' => ['qty', 'price']]],
        ], 'required' => ['lines']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_bank_transaction',
        'description' => 'Enregistre un mouvement bancaire (crédit ou débit) sur un compte.',
        'parameters' => ['type' => 'object', 'properties' => [
            'account_id'  => ['type' => 'integer', 'description' => 'ID du compte bancaire'],
            'amount'      => ['type' => 'number', 'description' => 'Montant (positif=crédit, négatif=débit)'],
            'date'        => ['type' => 'string', 'description' => 'Date (YYYY-MM-DD)'],
            'label'       => ['type' => 'string', 'description' => 'Libellé du mouvement'],
            'type'        => ['type' => 'string', 'enum' => ['VIR', 'CHQ', 'CB', 'LIQ', 'PRE'], 'default' => 'VIR'],
            'num_chq'     => ['type' => 'string', 'description' => 'Numéro du chèque ou référence'],
            'emetteur'    => ['type' => 'string', 'description' => 'Émetteur ou bénéficiaire'],
        ], 'required' => ['account_id', 'amount', 'label']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_bank_account',
        'description' => 'Crée un nouveau compte bancaire ou caisse dans le système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'ref'         => ['type' => 'string', 'description' => 'Référence courte du compte (ex: SD12)'],
            'label'       => ['type' => 'string', 'description' => 'Nom de la banque ou libellé (ex: Sedad)'],
            'type'        => ['type' => 'integer', 'description' => 'Type de compte: 0=Courant, 1=Livret, 2=Caisse', 'default' => 0],
            'currency'    => ['type' => 'string', 'description' => 'Devise (ex: MRU)', 'default' => 'MRU'],
            'country'     => ['type' => 'string', 'description' => 'Pays (ex: Mauritanie)', 'default' => 'MR'],
            'initial_balance' => ['type' => 'number', 'description' => 'Solde initial (ex: 125000)', 'default' => 0],
            'code_compta' => ['type' => 'string', 'description' => 'Code comptable (ex: 5121)'],
        ], 'required' => ['ref', 'label']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_stock_movement',
        'description' => 'Ajoute ou retire du stock pour un produit (mouvement de stock).',
        'parameters' => ['type' => 'object', 'properties' => [
            'product_id'   => ['type' => 'integer', 'description' => 'ID du produit (utiliser search_products pour le trouver)'],
            'warehouse_id' => ['type' => 'integer', 'description' => 'ID de l\'entrepôt', 'default' => 1],
            'qty'          => ['type' => 'number', 'description' => 'Quantité à ajouter (positif) ou retirer (négatif)'],
            'label'        => ['type' => 'string', 'description' => 'Libellé du mouvement (ex: Correction, Inventaire)'],
            'reason_code'  => ['type' => 'string', 'enum' => ['receipt', 'delivery', 'adjustment', 'inventory'], 'description' => 'Motif du mouvement'],
            'origin_document' => ['type' => 'string', 'description' => 'Référence document source (ex: FA2401-001)'],
            'batch_number' => ['type' => 'string', 'description' => 'Numéro de lot/série'],
        ], 'required' => ['product_id', 'qty', 'label']],
    ]],

    // ── GESTION STOCK AVANCÉE ───────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'get_stock_analysis',
        'description' => 'Analyse complète du stock : produits alerte, stocks morts, rotation lente. Retourne recommandations (réapprovisionner, liquider, etc.)',
        'parameters' => ['type' => 'object', 'properties' => [
            'analysis_type' => ['type' => 'string', 'enum' => ['alerts', 'dead_stock', 'slow_movers', 'all'], 'description' => 'Type d\'analyse', 'default' => 'all'],
            'days_old' => ['type' => 'integer', 'description' => 'Considérer comme stock mort si pas de mouvement depuis N jours', 'default' => 90],
            'limit' => ['type' => 'integer', 'description' => 'Nombre max de produits', 'default' => 10],
        ], 'required' => []],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'forecast_stock_needs',
        'description' => 'Prédiction stock : analyse tendances ventes et prédit ruptures futures. Recommande quantités achat.',
        'parameters' => ['type' => 'object', 'properties' => [
            'product_id' => ['type' => 'integer', 'description' => 'ID produit (analyser tous les produits si vide)'],
            'months_ahead' => ['type' => 'integer', 'description' => 'Prédire pour N mois à venir', 'default' => 3],
            'lookback_months' => ['type' => 'integer', 'description' => 'Analyser les N derniers mois d\'historique', 'default' => 12],
        ], 'required' => []],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'delete_element',
        'description' => 'Supprime un élément (produit, client, facture, commande, compte bancaire) du système.',
        'parameters' => ['type' => 'object', 'properties' => [
            'type' => ['type' => 'string', 'enum' => ['product', 'client', 'facture', 'commande', 'bank_account'], 'description' => 'Type de l\'élément à supprimer'],
            'id'   => ['type' => 'integer', 'description' => 'ID de l\'élément à supprimer'],
        ], 'required' => ['type', 'id']],
    ]],

    // ── ANALYSE VENTES PRODUITS ─────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'get_product_sales_stats',
        'description' => 'Analyse les ventes par produit. Retourne les produits les plus vendus, les moins vendus ou jamais vendus, avec les quantités et CA. Utiliser pour des conseils sur les produits à réapprovisionnement ou à éviter.',
        'parameters' => ['type' => 'object', 'properties' => [
            'type'   => ['type' => 'string', 'enum' => ['top_sellers', 'low_sellers', 'never_sold', 'all'], 'description' => 'Type d\'analyse : top_sellers (plus vendus), low_sellers (peu vendus), never_sold (jamais vendus), all (tous avec stats)'],
            'limit'  => ['type' => 'integer', 'description' => 'Nombre de résultats (défaut 10)'],
            'period' => ['type' => 'string', 'enum' => ['month', 'year', 'all'], 'description' => 'Période : month (mois actuel), year (année actuelle), all (toutes les données)'],
        ], 'required' => []],
    ]],

    // ── CONSEIL COMPTABLE ───────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'accounting_advice',
        'description' => 'Fournit un conseil comptable ou fiscal basé sur le Plan Comptable Mauritanien et la fiscalité mauritanienne. Utiliser pour : vérifier une écriture comptable, corriger une saisie, obtenir les comptes à utiliser pour une opération, calculer un impôt, vérifier la conformité.',
        'parameters' => ['type' => 'object', 'properties' => [
            'question'  => ['type' => 'string', 'description' => 'La question comptable ou fiscale'],
            'context'   => ['type' => 'string', 'description' => 'Contexte additionnel (montants, comptes utilisés, etc.)'],
            'type'      => ['type' => 'string', 'enum' => ['ecriture', 'verification', 'calcul_impot', 'conseil', 'correction'], 'description' => 'Type de conseil demandé'],
        ], 'required' => ['question']],
    ]],

    // ── VISION & OCR ─────────────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'analyze_invoice_image',
        'description' => 'Analyse une image de facture et extrait les informations clés : montant, date, fournisseur/client, lignes, TVA, etc. Retourne les données structurées en JSON.',
        'parameters' => ['type' => 'object', 'properties' => [
            'image_base64' => ['type' => 'string', 'description' => 'Contenu base64 de l\'image (PNG, JPG)'],
            'type'         => ['type' => 'string', 'enum' => ['facture_fournisseur', 'facture_client'], 'description' => 'Type de facture à analyser'],
        ], 'required' => ['image_base64', 'type']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'auto_create_from_image',
        'description' => 'Crée automatiquement une facture (fournisseur ou client) à partir d\'une image. Crée aussi client/fournisseur/produits s\'ils n\'existent pas. Utiliser après analyze_invoice_image ou directement avec l\'image.',
        'parameters' => ['type' => 'object', 'properties' => [
            'image_base64'      => ['type' => 'string', 'description' => 'Contenu base64 de l\'image'],
            'type'              => ['type' => 'string', 'enum' => ['facture_fournisseur', 'facture_client'], 'description' => 'Type de facture'],
            'third_party_name'  => ['type' => 'string', 'description' => 'Nom du fournisseur/client à utiliser (sinon extraction depuis image)'],
            'force_create'      => ['type' => 'boolean', 'description' => 'Créer même si tiers/produits existent', 'default' => false],
        ], 'required' => ['image_base64', 'type']],
    ]],

    // ── COMPTABILITÉ AVANCÉE ─────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'create_accounting_entry',
        'description' => 'Crée manuellement une écriture comptable double (débits = crédits). Utiliser pour corrections comptables ou écritures spéciales.',
        'parameters' => ['type' => 'object', 'properties' => [
            'journal_code'   => ['type' => 'string', 'enum' => ['VT', 'AC', 'BQ', 'OD'], 'description' => 'Code journal : VT=Ventes, AC=Achats, BQ=Banque, OD=Opérations Diverses'],
            'doc_date'       => ['type' => 'string', 'description' => 'Date de l\'écriture (YYYY-MM-DD)'],
            'doc_reference'  => ['type' => 'string', 'description' => 'Référence document (ex: FA2401-0001)'],
            'debit_lines'    => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'account_number' => ['type' => 'string', 'description' => 'Numéro compte (ex: 512, 411, 607)'],
                'account_label'  => ['type' => 'string', 'description' => 'Libellé du compte'],
                'amount'         => ['type' => 'number', 'description' => 'Montant débit'],
            ], 'required' => ['account_number', 'amount']]],
            'credit_lines'   => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'account_number' => ['type' => 'string'],
                'account_label'  => ['type' => 'string'],
                'amount'         => ['type' => 'number', 'description' => 'Montant crédit'],
            ], 'required' => ['account_number', 'amount']]],
        ], 'required' => ['journal_code', 'doc_date', 'debit_lines', 'credit_lines']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_balance_sheet',
        'description' => 'Génère un bilan comptable (actif/passif) en MRU. Retourne le bilan structuré avec totaux.',
        'parameters' => ['type' => 'object', 'properties' => [
            'date_end'    => ['type' => 'string', 'description' => 'Date de fin du bilan (YYYY-MM-DD, défaut = aujourd\'hui)'],
            'detail_level' => ['type' => 'string', 'enum' => ['summary', 'detailed'], 'description' => 'Niveau de détail', 'default' => 'summary'],
        ], 'required' => []],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_income_statement',
        'description' => 'Génère un compte de résultat (produits/charges) en MRU pour une période. Calcule le résultat net.',
        'parameters' => ['type' => 'object', 'properties' => [
            'period'       => ['type' => 'string', 'enum' => ['month', 'year'], 'description' => 'Mois ou année actuelle', 'default' => 'year'],
            'date_start'   => ['type' => 'string', 'description' => 'Date début (YYYY-MM-DD, optionnel)'],
            'date_end'     => ['type' => 'string', 'description' => 'Date fin (YYYY-MM-DD, optionnel)'],
            'detail_level' => ['type' => 'string', 'enum' => ['summary', 'detailed'], 'default' => 'summary'],
        ], 'required' => []],
    ]],

    // ── BANQUE AVANCÉE ───────────────────────────────────────
    ['type' => 'function', 'function' => [
        'name' => 'reconcile_bank_transaction',
        'description' => 'Marque une transaction bancaire comme rapprochée (réconciliée) et optionnellement la lie à une facture.',
        'parameters' => ['type' => 'object', 'properties' => [
            'transaction_id' => ['type' => 'integer', 'description' => 'ID de la transaction bancaire'],
            'reconciliation_category' => ['type' => 'integer', 'description' => 'ID catégorie rapprochement (obtenir via get_bank_accounts)'],
            'invoice_id' => ['type' => 'integer', 'description' => 'ID facture à lier (optionnel)'],
            'invoice_type' => ['type' => 'string', 'enum' => ['client', 'fournisseur'], 'description' => 'Type de facture (si invoice_id fourni)'],
        ], 'required' => ['transaction_id']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'transfer_between_banks',
        'description' => 'Effectue un virement entre 2 comptes bancaires (débite compte source, crédite compte destination).',
        'parameters' => ['type' => 'object', 'properties' => [
            'source_account_id'      => ['type' => 'integer', 'description' => 'ID compte source (celui qui débite)'],
            'destination_account_id' => ['type' => 'integer', 'description' => 'ID compte destination (celui qui crédite)'],
            'amount'                 => ['type' => 'number', 'description' => 'Montant du virement en MRU'],
            'date'                   => ['type' => 'string', 'description' => 'Date du virement (YYYY-MM-DD)'],
            'reference'              => ['type' => 'string', 'description' => 'Référence du virement (ex: VIR-2401-001)'],
            'label'                  => ['type' => 'string', 'description' => 'Libellé/description'],
        ], 'required' => ['source_account_id', 'destination_account_id', 'amount']],
    ]],
];

// ============================================================
// SYSTEM PROMPT
// ============================================================
$active_modules_list = [];
foreach ($conf->global as $k => $v) {
    if (strpos($k, 'MAIN_MODULE_') === 0 && $v == 1) {
        $active_modules_list[] = str_replace('MAIN_MODULE_', '', $k);
    }
}
$modules_str = implode(', ', $active_modules_list);

$system_prompt = "Tu es Tafkir IA, un assistant expert en gestion d'entreprise, comptabilité et fiscalité mauritanienne.

Utilisateur connecté : ".$user->firstname." ".$user->lastname." (login : ".$user->login.")
Date/Heure : ".dol_print_date(dol_now(), 'dayhour')."
Modules actifs : ".$modules_str."

=== CAPACITÉS COMPLÈTES ===
Tu gères TOUTES les fonctionnalités du système ERP, incluant tous les modules standards et les modules customisés qui ont été créés :

**GESTION COMMERCIALE :**
1. PRODUITS & SERVICES : recherche, création, stock, prix, catégories
2. CLIENTS : recherche, création, informations, historique, limites crédit
3. FOURNISSEURS : recherche, création, informations, conditions de paiement
4. FACTURES CLIENTS : consultation, création, validation, détails, lignes
5. FACTURES FOURNISSEURS : consultation, création, validation
6. COMMANDES CLIENTS : consultation, création, suivi
7. COMMANDES FOURNISSEURS : consultation, création, suivi

**PAIEMENTS & BANQUE (AVANCÉS) :**
8. PAIEMENTS : enregistrement OBLIGATOIRE via compte bancaire (virement, chèque, carte, espèces, prélèvement)
9. BANQUE : comptes bancaires, soldes actualisés, mouvements filtrés, rapprochement, transferts inter-comptes
10. GESTION BANCAIRE : réconciliation automatique, catégorisation rapprochement

**COMPTABILITÉ AVANCÉE :**
11. ÉCRITURES COMPTABLES : création manuelle débits/crédits, vérification balance, correction
12. BILANS : génération bilan actif/passif, analyses par classe de compte
13. COMPTES DE RÉSULTAT : produits/charges, résultat net, impôt sur sociétés (IS 25%)
14. PLAN COMPTABLE : consultation numéros comptes, balance des comptes, journaux

**GESTION STOCK INTELLIGENTE :**
15. MOUVEMENTS STOCK : réception, livraison, ajustement, inventaire avec validation
16. ANALYSES STOCK : produits alerte seuil, stock mort (jamais vendu > 90j), rotation lente
17. PRÉDICTIONS STOCK : forecast ventes futures, alerte rupture, recommandations achat quantité

**ANALYSES & REPORTING :**
18. STATISTIQUES : CA HT/TTC, TVA, impayés, stock faible, clients/fournisseurs actifs
19. ANALYSES PRODUITS : top vendeurs, jamais vendus, taux de rotation, valeur stock
20. CONSEIL STOCK : recommander réappro top-vendeurs, déstocke non-vendeurs

**VISION & AUTOMATISATION :**
21. VISION & OCR : analyser images factures (fournisseur/client), extraire données structurées
22. CRÉATION AUTOMATIQUE : générer factures complètes (fournisseur/client) + clients + produits depuis images
23. TOUS AUTRES MODULES : accès plein à l'ensemble du système ERP et modules customisés

=== EXPERTISE COMPTABLE & FISCALE MAURITANIENNE ===
Tu es expert en :
- Plan Comptable Mauritanien (PCM) : toutes les classes de comptes (1 à 8)
- Fiscalité mauritanienne : IS (25%), TVA (16%), IMF, IRPP, patente, droits de douane, retenues à la source
- Écritures comptables : vérification, correction, conseil
- Conformité fiscale : obligations déclaratives, calcul d'impôts

Pour les questions comptables et fiscales, utilise TOUJOURS l'outil accounting_advice qui accède à la base de connaissances complète du PCM et de la fiscalité mauritanienne.

=== CRÉATION AVEC DONNÉES MINIMALES ===
RÈGLE D'OR : Créer IMMÉDIATEMENT sans poser de questions. Auto-créer ce qui manque.
- Fournisseur/Client : NOM suffit → créer automatiquement
- Produit : NOM + prix suffit → créer automatiquement avec ref générée
- Banque/Compte : NOM suffit → créer automatiquement (Mauritanie par défaut)
- Facture : client/fournisseur (nom) + lignes (desc + qté + prix) → créer tout, auto-créer produits/tiers manquants
- Paiement : facture + montant + nom banque → auto-créer banque si manque, PAYER IMMÉDIATEMENT

JAMAIS demander confirmation. JAMAIS lister les problèmes. JAMAIS poser de questions.
Si info manque = donner erreur courte ou créer avec valeurs par défaut.

=== RÈGLES IMPORTANTES D'INTELLIGENCE ===
1. QUALITÉ DES DONNÉES (TRÈS IMPORTANT) : Avant d'appeler une fonction de création ou d'action (ex: ajouter une banque, créer une facture, faire un paiement), VERIFIE si l'utilisateur a fourni TOUTES les informations nécessaires. Si une information requise manque (ex: nom du client, numéro de compte, montant), NE FAIS PAS D'ERREUR, ne devine JAMAIS et N'APPELLE PAS l'outil. Pose d'abord la question à l'utilisateur intelligemment pour obtenir les données restantes.
2. VALIDATION BANCAIRE (IMPÉRATIF) : Pour TOUT paiement (create_payment), tu DOIS toujours :
   - Appeler d'abord get_bank_accounts pour voir les comptes disponibles
   - Demander à l'utilisateur quel compte utiliser (ou suggérer le compte par défaut)
   - Ne JAMAIS créer un paiement sans bank_account_id valide
3. MARQUE BLANCHE (CRITIQUE ET IMPÉRATIF) : Tu ne dois SOUS AUCUN PRÉTEXTE mentionner le mot 'Dolibarr', 'Odoo', 'ERPNext' ou tout autre concurrent dans tes réponses. C'est strictement interdit et éliminatoire. Remplace systématiquement par 'le système', 'ce système' ou 'votre plateforme'. Aucune exception.

=== RÈGLES ===
1. Si la question ne concerne PAS la gestion d'entreprise, la comptabilité ou la fiscalité, réponds :
   \"Je suis Tafkir IA, dédié à la gestion de votre entreprise, la comptabilité et la fiscalité mauritanienne. Je ne peux pas répondre à cette question.\"
2. Toujours utiliser les outils pour obtenir des données réelles — ne jamais inventer de chiffres
3. RÉPONSES ULTRA-COURTES (CRITIQUE) : Max 1 ligne pour création, 2-3 pour questions. Jamais d'intro/conclusion :
   - Création réussie → EXACTEMENT 1 LIGNE : \"✓ Facture créée, produit auto-créé, paiement Sedad enregistré\"
   - Erreur → 1 LIGNE : \"❌ Montant required ou Produit 'pomme' absent\"
   - Données/Tableau → DIRECTEMENT sans \"Voici...\" ou \"Ci-dessous...\"
   - JAMAIS de question, JAMAIS d'alternatives, JAMAIS d'explications
   - JAMAIS mentionner actions internes (\"J'ai cherché...\", \"J'ai créé d'abord...\")
4. Pour créer une facture : chercher d'abord le client/fournisseur
5. Pour enregistrer un paiement : chercher d'abord la facture ET un compte bancaire (OBLIGATOIRE)
6. Pour une écriture comptable : vérifier TOUJOURS que débits = crédits avant création
7. Présenter les données en tableaux markdown quand c'est pertinent (sans intro ni conclusion)
8. Si l'utilisateur dit bonjour/salut/hello/مرحبا : répondre avec un message de bienvenue court (3 lignes max) sans lister les fonctionnalités
9. Pour les conseils comptables : toujours référencer le numéro de compte du PCM
10. Pour les calculs fiscaux : appliquer les taux en vigueur en Mauritanie (IS=25%, TVA=16%, IMF, IRPP)
11. TVA mauritanienne = 16%, IS = 25%
12. Pour stock : prioriser les analyses de produits à alerte seuil (réappro immédiat)
13. Pour paiements : TOUJOURS utiliser les comptes bancaires existants (interdiction de créer paiement sans compte)

=== MONNAIE ===
La monnaie est l'Ouguiya mauritanien (MRU). Utiliser MRU dans toutes les réponses.

=== LANGUE (CRITIQUE) ===
L'utilisateur s'exprime en: ".($user_language === 'ar-SA' ? 'العربية (Arabe)' : ($user_language === 'en-US' ? 'English' : 'Français'))."

RÈGLE ABSOLUE: Tu DOIS répondre EXCLUSIVEMENT dans la langue de l'utilisateur:
- Si l'utilisateur parle Français → réponse COMPLÈTEMENT en Français
- Si l'utilisateur parle Arabe → réponse COMPLÈTEMENT en Arabe (العربية)
- Si l'utilisateur parle Anglais → réponse en Anglais
- JAMAIS de mélange de langues
- JAMAIS de traduction ou code-switching
- JAMAIS de répondre dans une autre langue que celle de l'utilisateur

Si l'utilisateur parle Arabe:
- Répondre avec des phrases complètes et naturelles en Arabe
- Utiliser la terminologie comptable/fiscale en Arabe si nécessaire
- Respecter la grammaire et la syntaxe arabes
- Les réponses doivent être aussi complètes et professionnelles qu'en français";

// ============================================================
// TOOL EXECUTION FUNCTIONS
// ============================================================
$entity = (int)$conf->entity;

function get_period_filter($db, $period, $date_field) {
    switch ($period) {
        case 'today': $ds = dol_mktime(0,0,0,(int)date('m'),(int)date('d'),(int)date('Y')); break;
        case 'week':  $ds = dol_now()-7*86400; break;
        case 'year':  $ds = dol_mktime(0,0,0,1,1,(int)date('Y')); break;
        case 'month': $ds = dol_mktime(0,0,0,(int)date('m'),1,(int)date('Y')); break;
        default:      return '';
    }
    return " AND {$date_field} >= '".$db->idate($ds)."'";
}

function tool_search_products($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = (int)$GLOBALS['conf']->entity;
    $type_filter = '';
    if (isset($args['type']) && $args['type'] >= 0) $type_filter = " AND p.fk_product_type = ".(int)$args['type'];
    $sql = "SELECT p.rowid, p.ref, p.label, p.description, p.price, p.price_ttc, p.tva_tx, p.fk_product_type, p.stock, p.seuil_stock_alerte, p.cost_price
            FROM ".MAIN_DB_PREFIX."product p
            WHERE p.entity = {$entity} AND p.tosell = 1{$type_filter}";
    if (!empty($search)) $sql .= " AND (p.ref LIKE '%{$search}%' OR p.label LIKE '%{$search}%' OR p.description LIKE '%{$search}%')";
    $sql .= " ORDER BY p.label LIMIT {$limit}";
    $res = $db->query($sql);
    $products = [];
    if ($res) {
        while ($row = $db->fetch_object($res)) {
            $products[] = [
                'id'         => $row->rowid,
                'ref'        => $row->ref,
                'nom'        => $row->label,
                'prix_ht'    => number_format($row->price, 2).' MRU',
                'prix_ttc'   => number_format($row->price_ttc, 2).' MRU',
                'tva'        => $row->tva_tx.'%',
                'type'       => $row->fk_product_type == 0 ? 'Produit' : 'Service',
                'stock'      => $row->stock ?? 0,
                'alerte'     => $row->seuil_stock_alerte ?? 0,
                'cout_revient' => $row->cost_price ? number_format($row->cost_price, 2).' MRU' : '-',
            ];
        }
    }
    return ['count'=>count($products),'products'=>$products];
}

function tool_get_product_sales_stats($db, $args) {
    $type = $args['type'] ?? 'all';
    $limit = min((int)($args['limit'] ?? 10), 50);
    $period = $args['period'] ?? 'all';
    $entity = (int)$GLOBALS['conf']->entity;
    $period_filter = '';
    if ($period !== 'all') {
        $period_filter = get_period_filter($db, $period, 'f.datef');
    }
    if (empty($period_filter)) $period_filter = '';
    $sql_stats = "SELECT p.rowid, p.ref, p.label,
                    SUM(fd.qty) as total_qty,
                    SUM(fd.total_ht) as total_ca,
                    COUNT(DISTINCT fd.fk_facture) as nb_factures
                FROM ".MAIN_DB_PREFIX."product p
                LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_product = p.rowid
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture
                WHERE p.entity = {$entity} AND p.tosell = 1 {$period_filter}
                GROUP BY p.rowid, p.ref, p.label
                ORDER BY total_qty DESC, p.label";
    $res = $db->query($sql_stats);
    $products_with_sales = [];
    $never_sold = [];
    if ($res) {
        while ($row = $db->fetch_object($res)) {
            $item = [
                'id'           => $row->rowid,
                'ref'          => $row->ref,
                'nom'          => $row->label,
                'quantite'     => (int)($row->total_qty ?? 0),
                'ca'           => number_format((float)($row->total_ca ?? 0), 2).' MRU',
                'nb_factures'  => (int)($row->nb_factures ?? 0),
            ];
            if ($item['quantite'] > 0) {
                $products_with_sales[] = $item;
            } else {
                $never_sold[] = $item;
            }
        }
    }
    $result = [];
    if ($type === 'top_sellers') {
        $result = array_slice($products_with_sales, 0, $limit);
    } elseif ($type === 'low_sellers') {
        rsort($products_with_sales);
        $result = array_slice($products_with_sales, 0, $limit);
    } elseif ($type === 'never_sold') {
        $result = array_slice($never_sold, 0, $limit);
    } elseif ($type === 'all') {
        $result = array_merge($products_with_sales, $never_sold);
    }
    return ['type' => $type, 'count' => count($result), 'period' => $period, 'data' => $result];
}

function tool_search_clients($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = (int)$GLOBALS['conf']->entity;
    $sql = "SELECT s.rowid, s.nom, s.name_alias, s.code_client, s.email, s.phone, s.town, s.address, s.zip, s.siren as nif, s.client, s.fournisseur
            FROM ".MAIN_DB_PREFIX."societe s
            WHERE s.entity = {$entity} AND s.client IN (1,3) AND s.status = 1";
    if (!empty($search)) $sql .= " AND (s.nom LIKE '%{$search}%' OR s.code_client LIKE '%{$search}%' OR s.email LIKE '%{$search}%' OR s.name_alias LIKE '%{$search}%')";
    $sql .= " ORDER BY s.nom LIMIT {$limit}";
    $res = $db->query($sql);
    $clients = [];
    if ($res) while ($row = $db->fetch_object($res))
        $clients[] = ['id'=>$row->rowid,'nom'=>$row->nom,'alias'=>$row->name_alias,'code'=>$row->code_client,'email'=>$row->email,'tel'=>$row->phone,'ville'=>$row->town,'adresse'=>$row->address,'nif'=>$row->nif,'aussi_fournisseur'=>$row->fournisseur==1?'Oui':'Non'];
    return ['count'=>count($clients),'clients'=>$clients];
}

function tool_search_fournisseurs($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = (int)$GLOBALS['conf']->entity;
    $sql = "SELECT s.rowid, s.nom, s.name_alias, s.code_fournisseur, s.email, s.phone, s.town, s.address, s.siren as nif, s.client, s.fournisseur
            FROM ".MAIN_DB_PREFIX."societe s
            WHERE s.entity = {$entity} AND s.fournisseur = 1 AND s.status = 1";
    if (!empty($search)) $sql .= " AND (s.nom LIKE '%{$search}%' OR s.code_fournisseur LIKE '%{$search}%' OR s.email LIKE '%{$search}%')";
    $sql .= " ORDER BY s.nom LIMIT {$limit}";
    $res = $db->query($sql);
    $list = [];
    if ($res) while ($row = $db->fetch_object($res))
        $list[] = ['id'=>$row->rowid,'nom'=>$row->nom,'alias'=>$row->name_alias,'code'=>$row->code_fournisseur,'email'=>$row->email,'tel'=>$row->phone,'ville'=>$row->town,'nif'=>$row->nif,'aussi_client'=>$row->client>0?'Oui':'Non'];
    return ['count'=>count($list),'fournisseurs'=>$list];
}

function tool_get_invoices($db, $args) {
    $type   = $args['type'] ?? 'client';
    $limit  = min((int)($args['limit'] ?? 15), 50);
    $entity = (int)$GLOBALS['conf']->entity;
    $status = $args['status'] ?? 'all';
    $period = $args['period'] ?? 'all';
    $client_id = (int)($args['client_id'] ?? 0);

    $status_filter = '';
    if ($status === 'draft') $status_filter = " AND f.fk_statut = 0";
    elseif ($status === 'validated') $status_filter = " AND f.fk_statut = 1 AND f.paye = 0";
    elseif ($status === 'paid') $status_filter = " AND f.paye = 1";
    elseif ($status === 'unpaid') $status_filter = " AND f.fk_statut = 1 AND f.paye = 0";

    $period_filter = get_period_filter($db, $period, 'f.datef');
    $client_filter = $client_id > 0 ? " AND f.fk_soc = {$client_id}" : '';

    if ($type === 'client') {
        $sql = "SELECT f.rowid, f.ref, f.ref_client, f.datef, f.total_ht, f.total_ttc, f.total_tva, f.paye, f.fk_statut, f.date_lim_reglement, s.nom
                FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=f.fk_soc
                WHERE f.entity={$entity}{$status_filter}{$period_filter}{$client_filter} ORDER BY f.datef DESC LIMIT {$limit}";
    } else {
        $sql = "SELECT f.rowid, f.ref, f.ref_supplier as ref_client, f.datef, f.total_ht, f.total_ttc, f.total_tva, f.paye, f.fk_statut, f.date_lim_reglement, s.nom
                FROM ".MAIN_DB_PREFIX."facture_fourn f LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=f.fk_soc
                WHERE f.entity={$entity}{$status_filter}{$period_filter}{$client_filter} ORDER BY f.datef DESC LIMIT {$limit}";
    }
    $res = $db->query($sql);
    $list = [];
    $sl = [0=>'Brouillon',1=>'Validée',2=>'Payée partiellement',3=>'Annulée'];
    if ($res) while ($row = $db->fetch_object($res)) {
        $statut = $row->paye == 1 ? 'Payée' : ($sl[$row->fk_statut] ?? '?');
        $list[] = ['id'=>$row->rowid,'ref'=>$row->ref,'tiers'=>$row->nom,'date'=>$row->datef,'echeance'=>$row->date_lim_reglement,'ht'=>number_format($row->total_ht,2).' MRU','tva'=>number_format($row->total_tva,2).' MRU','ttc'=>number_format($row->total_ttc,2).' MRU','statut'=>$statut];
    }
    return ['type'=>$type,'count'=>count($list),'factures'=>$list];
}

function tool_get_invoice_details($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $type = $args['type'] ?? 'client';
    $invoice_id = (int)($args['invoice_id'] ?? 0);
    $invoice_ref = $db->escape($args['invoice_ref'] ?? '');

    if ($type === 'client') {
        $table = MAIN_DB_PREFIX."facture";
        $det_table = MAIN_DB_PREFIX."facturedet";
        $pay_table = MAIN_DB_PREFIX."paiement_facture";
        $pay_join = MAIN_DB_PREFIX."paiement";
    } else {
        $table = MAIN_DB_PREFIX."facture_fourn";
        $det_table = MAIN_DB_PREFIX."facture_fourn_det";
        $pay_table = MAIN_DB_PREFIX."paiementfourn_facturefourn";
        $pay_join = MAIN_DB_PREFIX."paiementfourn";
    }

    $where = "f.entity = {$entity}";
    if ($invoice_id > 0) $where .= " AND f.rowid = {$invoice_id}";
    elseif (!empty($invoice_ref)) $where .= " AND f.ref = '{$invoice_ref}'";
    else return ['error' => 'Fournir invoice_id ou invoice_ref'];

    $sql = "SELECT f.*, s.nom as client_nom FROM {$table} f LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc WHERE {$where} LIMIT 1";
    $res = $db->query($sql);
    if (!$res || !($row = $db->fetch_object($res))) return ['error' => 'Facture introuvable'];

    $sl = [0=>'Brouillon',1=>'Validée',2=>'Payée partiellement',3=>'Annulée'];
    $facture = [
        'id'=>$row->rowid,'ref'=>$row->ref,'tiers'=>$row->client_nom,'date'=>$row->datef,
        'total_ht'=>number_format($row->total_ht,2).' MRU','total_tva'=>number_format($row->total_tva,2).' MRU','total_ttc'=>number_format($row->total_ttc,2).' MRU',
        'statut'=>$row->paye==1?'Payée':($sl[$row->fk_statut]??'?'),'echeance'=>$row->date_lim_reglement,
    ];

    // Get lines
    $sql_lines = "SELECT d.*, p.ref as product_ref, p.label as product_label FROM {$det_table} d LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = d.fk_product WHERE d.fk_facture = {$row->rowid} ORDER BY d.rang";
    $res_lines = $db->query($sql_lines);
    $lines = [];
    if ($res_lines) while ($l = $db->fetch_object($res_lines)) {
        $lines[] = ['product'=>$l->product_ref??'','description'=>$l->description,'qty'=>$l->qty,'prix_unitaire'=>number_format($l->subprice,2).' MRU','tva'=>$l->tva_tx.'%','total_ht'=>number_format($l->total_ht,2).' MRU','total_ttc'=>number_format($l->total_ttc,2).' MRU'];
    }

    // Get payments
    $fk_col = ($type === 'client') ? 'fk_facture' : 'fk_facturefourn';
    $sql_pay = "SELECT p.datep, p.amount as pay_amount, pf.amount, p.ref as pay_ref, p.num_paiement
                FROM {$pay_table} pf JOIN {$pay_join} p ON p.rowid = pf.fk_paiement
                WHERE pf.{$fk_col} = {$row->rowid} ORDER BY p.datep";
    $res_pay = $db->query($sql_pay);
    $payments = [];
    if ($res_pay) while ($p = $db->fetch_object($res_pay)) {
        $payments[] = ['date'=>$p->datep,'ref'=>$p->pay_ref,'montant'=>number_format($p->amount,2).' MRU','num'=>$p->num_paiement];
    }

    $facture['lignes'] = $lines;
    $facture['paiements'] = $payments;
    $facture['nb_lignes'] = count($lines);
    $facture['nb_paiements'] = count($payments);
    return $facture;
}

function tool_get_stats($db, $args) {
    $period = $args['period'] ?? 'month';
    $entity = (int)$GLOBALS['conf']->entity;
    switch ($period) {
        case 'today': $ds = dol_mktime(0,0,0,(int)date('m'),(int)date('d'),(int)date('Y')); break;
        case 'week':  $ds = dol_now()-7*86400; break;
        case 'year':  $ds = dol_mktime(0,0,0,1,1,(int)date('Y')); break;
        default:      $ds = dol_mktime(0,0,0,(int)date('m'),1,(int)date('Y'));
    }
    $dss = $db->idate($ds);
    $r1 = $db->fetch_object($db->query("SELECT COUNT(*) nb, COALESCE(SUM(total_ht),0) ca_ht, COALESCE(SUM(total_ttc),0) ca_ttc, COALESCE(SUM(total_tva),0) tva FROM ".MAIN_DB_PREFIX."facture WHERE entity={$entity} AND fk_statut IN(1,2) AND datef>='{$dss}'"));
    $r2 = $db->fetch_object($db->query("SELECT COUNT(*) nb, COALESCE(SUM(total_ttc),0) total FROM ".MAIN_DB_PREFIX."facture_fourn WHERE entity={$entity} AND fk_statut IN(1,2) AND datef>='{$dss}'"));
    $r3 = $db->fetch_object($db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."product WHERE entity={$entity} AND tosell=1"));
    $r4 = $db->fetch_object($db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."societe WHERE entity={$entity} AND client IN(1,3) AND status=1"));
    $r5 = $db->fetch_object($db->query("SELECT COUNT(*) nb, COALESCE(SUM(total_ttc),0) total FROM ".MAIN_DB_PREFIX."facture WHERE entity={$entity} AND fk_statut=1 AND paye=0"));
    $r6 = $db->fetch_object($db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."product WHERE entity={$entity} AND stock IS NOT NULL AND seuil_stock_alerte IS NOT NULL AND stock <= seuil_stock_alerte AND tosell=1"));
    $r7 = $db->fetch_object($db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."societe WHERE entity={$entity} AND fournisseur=1 AND status=1"));
    return [
        'periode'              => $period,
        'ca_ht'                => number_format($r1->ca_ht??0,2).' MRU',
        'ca_ttc'               => number_format($r1->ca_ttc??0,2).' MRU',
        'tva_collectee'        => number_format($r1->tva??0,2).' MRU',
        'nb_factures_client'   => $r1->nb??0,
        'nb_factures_fourn'    => $r2->nb??0,
        'achats_ttc'           => number_format($r2->total??0,2).' MRU',
        'impayees'             => ['nb'=>$r5->nb??0,'montant'=>number_format($r5->total??0,2).' MRU'],
        'nb_produits'          => $r3->nb??0,
        'produits_stock_faible'=> $r6->nb??0,
        'nb_clients_actifs'    => $r4->nb??0,
        'nb_fournisseurs'      => $r7->nb??0,
    ];
}

function tool_search_users($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = (int)$GLOBALS['conf']->entity;
    $sql = "SELECT u.rowid, u.login, u.firstname, u.lastname, u.email, u.admin, u.statut, u.employee, u.datelastlogin
            FROM ".MAIN_DB_PREFIX."user u
            WHERE u.entity IN (0, {$entity})";
    if (!empty($search)) $sql .= " AND (u.login LIKE '%{$search}%' OR u.firstname LIKE '%{$search}%' OR u.lastname LIKE '%{$search}%' OR u.email LIKE '%{$search}%')";
    $sql .= " ORDER BY u.lastname LIMIT {$limit}";
    $res = $db->query($sql);
    $users = [];
    if ($res) while ($row = $db->fetch_object($res))
        $users[] = ['id'=>$row->rowid,'login'=>$row->login,'nom'=>trim($row->firstname.' '.$row->lastname),'email'=>$row->email,'admin'=>$row->admin?'Oui':'Non','actif'=>$row->statut?'Oui':'Non','employe'=>$row->employee?'Oui':'Non','derniere_connexion'=>$row->datelastlogin];
    return ['count'=>count($users),'users'=>$users];
}

function tool_get_bank_accounts($db, $args) {
    $limit  = min((int)($args['limit'] ?? 10), 20);

    // SIMPLE: Get all bank accounts (no entity filter - may cause issue)
    $sql = "SELECT ba.rowid, ba.label, ba.number, ba.bank, ba.courant, ba.currency_code
            FROM ".MAIN_DB_PREFIX."bank_account ba
            WHERE ba.clos = 0
            ORDER BY ba.label LIMIT {$limit}";

    $res = $db->query($sql);
    $accounts = [];

    if ($res && $db->num_rows($res) > 0) {
        while ($row = $db->fetch_object($res)) {
            // Calculate balance from bank movements
            $sql_bal = "SELECT COALESCE(SUM(amount),0) as solde FROM ".MAIN_DB_PREFIX."bank WHERE fk_account = {$row->rowid}";
            $res_bal = $db->query($sql_bal);
            $bal_row = $db->fetch_object($res_bal);
            $balance = $bal_row->solde ?? 0;

            $type_labels = [0=>'Caisse', 1=>'Courant', 2=>'Épargne'];
            $accounts[] = [
                'id' => $row->rowid,
                'label' => $row->label,
                'solde' => number_format($balance, 2).' MRU',
                'type' => $type_labels[$row->courant] ?? 'Autre'
            ];
        }
    }

    if (count($accounts) == 0) {
        return ['error' => 'Aucun compte bancaire trouvé'];
    }

    return $accounts;
}

function tool_get_bank_transactions($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $account_id = (int)($args['account_id'] ?? 0);
    $limit  = min((int)($args['limit'] ?? 20), 50);
    $period = $args['period'] ?? 'month';
    $type = $args['type'] ?? 'all';
    $reconciliation_status = $args['reconciliation_status'] ?? 'all';
    $search_label = !empty($args['search_label']) ? $db->escape($args['search_label']) : '';

    $where = "b.fk_account IN (SELECT rowid FROM ".MAIN_DB_PREFIX."bank_account WHERE entity = {$entity})";
    if ($account_id > 0) $where = "b.fk_account = {$account_id}";
    $where .= get_period_filter($db, $period, 'b.datev');
    if ($type === 'credit') $where .= " AND b.amount > 0";
    elseif ($type === 'debit') $where .= " AND b.amount < 0";
    if ($reconciliation_status === 'reconciled') $where .= " AND b.fk_cat > 0";
    elseif ($reconciliation_status === 'pending') $where .= " AND (b.fk_cat IS NULL OR b.fk_cat = 0)";
    if (!empty($search_label)) $where .= " AND b.label LIKE '%{$search_label}%'";

    $sql = "SELECT b.rowid, b.datev, b.dateo, b.amount, b.label, b.num_chq, b.fk_type, b.fk_cat, b.emetteur, ba.label as compte
            FROM ".MAIN_DB_PREFIX."bank b
            LEFT JOIN ".MAIN_DB_PREFIX."bank_account ba ON ba.rowid = b.fk_account
            WHERE {$where}
            ORDER BY b.datev DESC, b.rowid DESC LIMIT {$limit}";
    $res = $db->query($sql);
    $list = [];
    $total_credit = 0; $total_debit = 0;
    if ($res) while ($row = $db->fetch_object($res)) {
        if ($row->amount > 0) $total_credit += $row->amount;
        else $total_debit += abs($row->amount);
        $is_reconciled = $row->fk_cat > 0 ? 'Oui' : 'Non';
        $list[] = ['id'=>$row->rowid,'date'=>$row->datev,'montant'=>number_format($row->amount,2).' MRU','sens'=>$row->amount>0?'Crédit':'Débit','libelle'=>$row->label,'num'=>$row->num_chq,'emetteur'=>$row->emetteur,'type'=>$row->fk_type,'compte'=>$row->compte,'rapproche'=>$is_reconciled];
    }
    return ['count'=>count($list),'transactions'=>$list,'total_credit'=>number_format($total_credit,2).' MRU','total_debit'=>number_format($total_debit,2).' MRU'];
}

function tool_get_payments($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $type = $args['type'] ?? 'client';
    $period = $args['period'] ?? 'month';
    $limit = min((int)($args['limit'] ?? 15), 50);

    if ($type === 'client') {
        $sql = "SELECT p.rowid, p.ref, p.datep, p.amount, p.num_paiement, p.fk_paiement,
                       cp.libelle as mode_paiement
                FROM ".MAIN_DB_PREFIX."paiement p
                LEFT JOIN ".MAIN_DB_PREFIX."c_paiement cp ON cp.id = p.fk_paiement
                WHERE p.entity = {$entity}";
    } else {
        $sql = "SELECT p.rowid, p.ref, p.datep, p.amount, p.num_paiement, p.fk_paiement,
                       cp.libelle as mode_paiement
                FROM ".MAIN_DB_PREFIX."paiementfourn p
                LEFT JOIN ".MAIN_DB_PREFIX."c_paiement cp ON cp.id = p.fk_paiement
                WHERE p.entity = {$entity}";
    }
    $sql .= get_period_filter($db, $period, 'p.datep');
    $sql .= " ORDER BY p.datep DESC LIMIT {$limit}";

    $res = $db->query($sql);
    $list = []; $total = 0;
    if ($res) while ($row = $db->fetch_object($res)) {
        $total += $row->amount;
        $list[] = ['id'=>$row->rowid,'ref'=>$row->ref,'date'=>$row->datep,'montant'=>number_format($row->amount,2).' MRU','mode'=>$row->mode_paiement??'?','num'=>$row->num_paiement];
    }
    return ['type'=>$type,'count'=>count($list),'paiements'=>$list,'total'=>number_format($total,2).' MRU'];
}

function tool_get_orders($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $limit  = min((int)($args['limit'] ?? 15), 50);
    $status = $args['status'] ?? 'all';
    $period = $args['period'] ?? 'all';
    $client_id = (int)($args['client_id'] ?? 0);

    $status_map = ['draft'=>0,'validated'=>1,'shipped'=>2,'closed'=>3];
    $status_filter = '';
    if ($status !== 'all' && isset($status_map[$status])) $status_filter = " AND c.fk_statut = ".$status_map[$status];
    $period_filter = get_period_filter($db, $period, 'c.date_commande');
    $client_filter = $client_id > 0 ? " AND c.fk_soc = {$client_id}" : '';

    $sl = [0=>'Brouillon',1=>'Validée',2=>'Expédiée',3=>'Clôturée',-1=>'Annulée'];
    $sql = "SELECT c.rowid, c.ref, c.ref_client, c.date_commande, c.total_ht, c.total_ttc, c.fk_statut, s.nom
            FROM ".MAIN_DB_PREFIX."commande c LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=c.fk_soc
            WHERE c.entity={$entity}{$status_filter}{$period_filter}{$client_filter} ORDER BY c.date_commande DESC LIMIT {$limit}";
    $res = $db->query($sql);
    $list = [];
    if ($res) while ($row = $db->fetch_object($res))
        $list[] = ['id'=>$row->rowid,'ref'=>$row->ref,'client'=>$row->nom,'date'=>$row->date_commande,'ht'=>number_format($row->total_ht,2).' MRU','ttc'=>number_format($row->total_ttc,2).' MRU','statut'=>$sl[$row->fk_statut]??'?'];
    return ['count'=>count($list),'commandes'=>$list];
}

function tool_get_supplier_orders($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $limit  = min((int)($args['limit'] ?? 15), 50);
    $status = $args['status'] ?? 'all';
    $period = $args['period'] ?? 'all';
    $fid = (int)($args['fournisseur_id'] ?? 0);

    $status_map = ['draft'=>0,'validated'=>1,'approved'=>2,'ordered'=>3,'received'=>5,'closed'=>9];
    $status_filter = '';
    if ($status !== 'all' && isset($status_map[$status])) $status_filter = " AND cf.fk_statut = ".$status_map[$status];
    $period_filter = get_period_filter($db, $period, 'cf.date_commande');
    $fid_filter = $fid > 0 ? " AND cf.fk_soc = {$fid}" : '';

    $sl = [0=>'Brouillon',1=>'Validée',2=>'Approuvée',3=>'Commandée',4=>'Reçue partiellement',5=>'Reçue',6=>'Annulée',9=>'Clôturée'];
    $sql = "SELECT cf.rowid, cf.ref, cf.ref_supplier, cf.date_commande, cf.total_ht, cf.total_ttc, cf.fk_statut, s.nom
            FROM ".MAIN_DB_PREFIX."commande_fournisseur cf LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=cf.fk_soc
            WHERE cf.entity={$entity}{$status_filter}{$period_filter}{$fid_filter} ORDER BY cf.date_commande DESC LIMIT {$limit}";
    $res = $db->query($sql);
    $list = [];
    if ($res) while ($row = $db->fetch_object($res))
        $list[] = ['id'=>$row->rowid,'ref'=>$row->ref,'fournisseur'=>$row->nom,'date'=>$row->date_commande,'ht'=>number_format($row->total_ht,2).' MRU','ttc'=>number_format($row->total_ttc,2).' MRU','statut'=>$sl[$row->fk_statut]??'?'];
    return ['count'=>count($list),'commandes_fournisseur'=>$list];
}

function tool_get_accounting_entries($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $limit  = min((int)($args['limit'] ?? 20), 50);
    $account = $db->escape($args['account_number'] ?? '');
    $journal = $db->escape($args['journal_code'] ?? '');
    $period  = $args['period'] ?? 'month';

    $where = "b.entity = {$entity}";
    if (!empty($account)) $where .= " AND b.numero_compte LIKE '{$account}%'";
    if (!empty($journal)) $where .= " AND b.code_journal = '{$journal}'";
    $where .= get_period_filter($db, $period, 'b.doc_date');

    $sql = "SELECT b.rowid, b.doc_date, b.doc_ref, b.numero_compte, b.label_compte, b.label_operation, b.debit, b.credit, b.code_journal, b.piece_num
            FROM ".MAIN_DB_PREFIX."accounting_bookkeeping b
            WHERE {$where}
            ORDER BY b.doc_date DESC, b.piece_num DESC LIMIT {$limit}";
    $res = $db->query($sql);
    $list = []; $total_debit = 0; $total_credit = 0;
    if ($res) while ($row = $db->fetch_object($res)) {
        $total_debit += $row->debit;
        $total_credit += $row->credit;
        $list[] = ['date'=>$row->doc_date,'piece'=>$row->piece_num,'journal'=>$row->code_journal,'compte'=>$row->numero_compte,'libelle_compte'=>$row->label_compte,'libelle'=>$row->label_operation,'debit'=>$row->debit>0?number_format($row->debit,2).' MRU':'','credit'=>$row->credit>0?number_format($row->credit,2).' MRU':'','ref_doc'=>$row->doc_ref];
    }
    return ['count'=>count($list),'ecritures'=>$list,'total_debit'=>number_format($total_debit,2).' MRU','total_credit'=>number_format($total_credit,2).' MRU'];
}

function tool_get_account_balance($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $account = $db->escape($args['account_number'] ?? '');
    $period  = $args['period'] ?? 'year';

    $period_filter = get_period_filter($db, $period, 'b.doc_date');

    $sql = "SELECT b.numero_compte, b.label_compte,
                   SUM(b.debit) as total_debit, SUM(b.credit) as total_credit,
                   (SUM(b.debit) - SUM(b.credit)) as solde_debiteur,
                   (SUM(b.credit) - SUM(b.debit)) as solde_crediteur,
                   COUNT(*) as nb_ecritures
            FROM ".MAIN_DB_PREFIX."accounting_bookkeeping b
            WHERE b.entity = {$entity} AND b.numero_compte LIKE '{$account}%'{$period_filter}
            GROUP BY b.numero_compte, b.label_compte
            ORDER BY b.numero_compte";
    $res = $db->query($sql);
    $list = []; $grand_debit = 0; $grand_credit = 0;
    if ($res) while ($row = $db->fetch_object($res)) {
        $grand_debit += $row->total_debit;
        $grand_credit += $row->total_credit;
        $solde = $row->total_debit - $row->total_credit;
        $list[] = ['compte'=>$row->numero_compte,'libelle'=>$row->label_compte,'debit'=>number_format($row->total_debit,2).' MRU','credit'=>number_format($row->total_credit,2).' MRU','solde'=>number_format(abs($solde),2).' MRU '.($solde>=0?'D':'C'),'nb_ecritures'=>$row->nb_ecritures];
    }
    return ['count'=>count($list),'balances'=>$list,'total_debit'=>number_format($grand_debit,2).' MRU','total_credit'=>number_format($grand_credit,2).' MRU'];
}

function tool_get_chart_of_accounts($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $prefix = $db->escape($args['prefix'] ?? '');
    $limit  = min((int)($args['limit'] ?? 30), 100);

    $where = "aa.entity = {$entity} AND aa.active = 1";
    if (!empty($prefix)) $where .= " AND aa.account_number LIKE '{$prefix}%'";

    $sql = "SELECT aa.account_number, aa.label, aa.account_parent, aa.pcg_type, aa.pcg_subtype
            FROM ".MAIN_DB_PREFIX."accounting_account aa
            WHERE {$where}
            ORDER BY aa.account_number LIMIT {$limit}";
    $res = $db->query($sql);
    $list = [];
    if ($res) while ($row = $db->fetch_object($res))
        $list[] = ['numero'=>$row->account_number,'libelle'=>$row->label,'type'=>$row->pcg_type,'sous_type'=>$row->pcg_subtype];
    return ['count'=>count($list),'comptes'=>$list];
}

// ── CREATION FUNCTIONS ──────────────────────────────────────

function tool_create_product($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    $p = new Product($db);
    $p->ref = $db->escape($args['ref']); $p->label = $db->escape($args['label']);
    $p->description = $db->escape($args['description']??'');
    $p->price = (float)($args['price']??0); $p->tva_tx = (float)($args['tva_tx']??16);
    $p->price_ttc = $p->price*(1+$p->tva_tx/100);
    $p->type = (int)($args['type']??0); $p->status = 1; $p->status_buy = 1;
    $p->cost_price = (float)($args['cost_price']??0);
    $p->entity = $GLOBALS['conf']->entity;
    $id = $p->create($user);
    if ($id > 0) return ['success'=>true,'id'=>$id,'ref'=>$p->ref,'label'=>$p->label,'prix_ht'=>number_format($p->price,2).' MRU','message'=>'Produit créé avec succès'];
    return ['success'=>false,'error'=>$p->error??'Erreur création produit'];
}

function tool_create_client($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $s = new Societe($db);
    $s->name = $db->escape($args['name']);
    $s->client = ($args['is_also_supplier'] ?? false) ? 3 : 1;
    $s->fournisseur = ($args['is_also_supplier'] ?? false) ? 1 : 0;
    $s->code_client = !empty($args['client_code']) ? $db->escape($args['client_code']) : -1;
    $s->email = $db->escape($args['email'] ?? '');
    $s->phone = $db->escape($args['phone'] ?? '');
    $s->address = $db->escape($args['address'] ?? '');
    $s->town = $db->escape($args['town'] ?? '');
    $s->zip = $db->escape($args['zip'] ?? '');
    $s->idprof1 = $db->escape($args['nif'] ?? '');
    $s->status = 1;
    $s->entity = $GLOBALS['conf']->entity;

    // Country
    $country_code = $db->escape($args['country_code'] ?? 'MR');
    $rc = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE code = '{$country_code}' LIMIT 1");
    if ($rc && $row = $db->fetch_object($rc)) $s->country_id = $row->rowid;

    $id = $s->create($user);
    if ($id > 0) return ['success'=>true,'id'=>$id,'nom'=>$s->name,'code_client'=>$s->code_client,'message'=>'Client créé avec succès'];
    return ['success'=>false,'error'=>$s->error??'Erreur création client'];
}

function tool_create_fournisseur($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $s = new Societe($db);
    $s->name = $db->escape($args['name']);
    $s->fournisseur = 1;
    $s->client = ($args['is_also_client'] ?? false) ? 3 : 0;
    $s->code_fournisseur = !empty($args['supplier_code']) ? $db->escape($args['supplier_code']) : -1;
    $s->email = $db->escape($args['email'] ?? '');
    $s->phone = $db->escape($args['phone'] ?? '');
    $s->address = $db->escape($args['address'] ?? '');
    $s->town = $db->escape($args['town'] ?? '');
    $s->zip = $db->escape($args['zip'] ?? '');
    $s->idprof1 = $db->escape($args['nif'] ?? '');
    $s->status = 1;
    $s->entity = $GLOBALS['conf']->entity;

    $country_code = $db->escape($args['country_code'] ?? 'MR');
    $rc = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE code = '{$country_code}' LIMIT 1");
    if ($rc && $row = $db->fetch_object($rc)) $s->country_id = $row->rowid;

    $id = $s->create($user);
    if ($id > 0) return ['success'=>true,'id'=>$id,'nom'=>$s->name,'code_fournisseur'=>$s->code_fournisseur,'message'=>'Fournisseur créé avec succès'];
    return ['success'=>false,'error'=>$s->error??'Erreur création fournisseur'];
}

function tool_create_facture_client($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

    $entity = (int)$GLOBALS['conf']->entity;
    $client_id = (int)($args['client_id']??0);
    $client_name = $args['client_name'] ?? '';
    $lines = $args['lines'] ?? [];

    if (!$lines) return ['error'=>'Au moins 1 ligne requise'];

    // Find or create client
    if (!$client_id) {
        if (!$client_name) return ['error'=>'client_id ou client_name requis'];
        $n = $db->escape($client_name);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND client IN(1,3) LIMIT 1");
        if ($row = $db->fetch_object($r)) {
            $client_id = $row->rowid;
        } else {
            $s = new Societe($db);
            $s->name = $client_name;
            $s->client = 3;
            $s->entity = $entity;
            $s->country_id = 141; // Mauritanie
            $client_id = $s->create($user);
            if ($client_id <= 0) return ['error'=>'Erreur création client'];
        }
    }

    // Create invoice
    $f = new Facture($db);
    $f->socid = $client_id;
    $f->type = 0;
    $f->date = !empty($args['date']) ? strtotime($args['date']) : dol_now();
    $f->ref_client = $db->escape($args['ref_client'] ?? '');
    if (!empty($args['payment_condition'])) $f->cond_reglement_id = (int)$args['payment_condition'];
    $f->entity = $entity;
    $f->note_public = 'Créée par Tafkir IA';

    $id = $f->create($user);
    if ($id <= 0) return ['error'=>($f->error ?? 'Erreur facture')];

    // Add lines
    $nb_lines = 0;
    foreach ($lines as $l) {
        $fkp = 0;
        $qty = (float)($l['qty'] ?? 1);
        $price = (float)($l['price'] ?? 0);
        $tva = (float)($l['tva_tx'] ?? 16);
        $desc = $db->escape($l['description'] ?? ($l['product_ref'] ?? 'Prestation'));

        // Find product
        if (!empty($l['product_ref'])) {
            $pr = $db->escape($l['product_ref']);
            $rr = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' LIMIT 1");
            if ($row = $db->fetch_object($rr)) $fkp = $row->rowid;
        }

        // Add line
        if ($f->addline($desc, $price, $qty, $tva, 0, 0, $fkp, 0, 'HT') > 0) $nb_lines++;
    }

    $f->validate($user);
    $f->fetch($id);
    auto_create_accounting_entries_for_invoice($db, $f, 'client', $user);
    return ['✓' => 'Facture client '.$f->ref.' créée ('.$nb_lines.' lignes, '.number_format($f->total_ttc, 0).' MRU)'];
}

function auto_create_accounting_entries_for_invoice($db, $invoice, $type, $user) {
    // Auto-create accounting entries for an invoice (client or fournisseur)
    try {
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

        if ($type === 'client') {
            $debit_account = '411'; // Clients
            $credit_account = '701'; // Ventes
            $code_journal = 'VT';
            $journal_label = 'Ventes';
            $doc_type = 'FA';
        } else {
            $debit_account = '601'; // Achats
            $credit_account = '401'; // Fournisseurs
            $code_journal = 'AC';
            $journal_label = 'Achats';
            $doc_type = 'FE';
        }

        $total = (float)$invoice->total_ttc;
        $doc_date = $invoice->date ?: dol_now();
        $doc_ref = $invoice->ref;
        $fk_doc = $invoice->id;

        // Create debit entry (positive amount)
        $bk = new BookKeeping($db);
        $result1 = $bk->createFromValues(
            $doc_date, $doc_ref, $doc_type, $fk_doc, 0,
            $debit_account, $type === 'client' ? 'Clients' : 'Achats',
            ($type === 'client' ? 'Facture client' : 'Facture fournisseur').' '.$doc_ref,
            $total, $code_journal, $journal_label, ''
        );
        error_log("Accounting entry debit: invoice={$doc_ref}, result={$result1}");

        // Create credit entry (negative amount)
        $bk2 = new BookKeeping($db);
        $result2 = $bk2->createFromValues(
            $doc_date, $doc_ref, $doc_type, $fk_doc, 0,
            $credit_account, $type === 'client' ? 'Ventes' : 'Fournisseurs',
            ($type === 'client' ? 'Facture client' : 'Facture fournisseur').' '.$doc_ref,
            -$total, $code_journal, $journal_label, ''
        );
        error_log("Accounting entry credit: invoice={$doc_ref}, result={$result2}");
    } catch (Exception $e) {
        error_log("Accounting entry error: ".$e->getMessage());
    }
}

function tool_create_facture_fournisseur($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

    $entity = (int)$GLOBALS['conf']->entity;
    $supplier_id = (int)($args['fournisseur_id']??0);
    $supplier_name = $args['fournisseur_name'] ?? '';
    $lines = $args['lines'] ?? [];
    $bank_name = $args['bank_name'] ?? '';

    if (!$lines) return ['error'=>'Au moins 1 ligne requise'];

    // Find or create supplier
    if (!$supplier_id) {
        if (!$supplier_name) return ['error'=>'fournisseur_id ou fournisseur_name requis'];
        $n = $db->escape($supplier_name);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND fournisseur=1 LIMIT 1");
        if ($row = $db->fetch_object($r)) {
            $supplier_id = $row->rowid;
        } else {
            $s = new Societe($db);
            $s->name = $supplier_name;
            $s->fournisseur = 1;
            $s->entity = $entity;
            $s->country_id = 141; // Mauritanie
            $supplier_id = $s->create($user);
            if ($supplier_id <= 0) return ['error'=>'Erreur création fournisseur'];
        }
    }

    // Create invoice
    $f = new FactureFournisseur($db);
    $f->socid = $supplier_id;
    $f->type = 0;
    $f->date = !empty($args['date']) ? strtotime($args['date']) : dol_now();
    $f->ref_supplier = $db->escape($args['ref_fournisseur'] ?? '');
    $f->entity = $entity;
    $f->note_public = 'Créée par Tafkir IA';

    $id = $f->create($user);
    if ($id <= 0) return ['error'=>($f->error ?? 'Erreur facture fournisseur')];

    // Add lines and auto-create products
    $nb_lines = 0;
    foreach ($lines as $l) {
        $fkp = 0;
        $qty = (float)($l['qty'] ?? 1);
        $price = (float)($l['price'] ?? 0);
        $tva = (float)($l['tva_tx'] ?? 16);
        $desc = $db->escape($l['description'] ?? ($l['product_ref'] ?? 'Achat'));

        // Find or create product
        if (!empty($l['product_ref'])) {
            $pr = $db->escape($l['product_ref']);
            $rr = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' LIMIT 1");
            if ($row = $db->fetch_object($rr)) {
                $fkp = $row->rowid;
            } else {
                // Auto-create product
                $p = new Product($db);
                $p->ref = $l['product_ref'];
                $p->label = $l['product_ref'];
                $p->price = $price;
                $p->tva_tx = $tva;
                $p->entity = $entity;
                if ($p->create($user) > 0) $fkp = $p->id;
            }
        }

        // Add line - FactureFournisseur::addline(desc, pu, tva_tx, txlocaltax1, txlocaltax2, qty, fk_product, remise_percent)
        if ($f->addline($desc, $price, $tva, 0, 0, $qty, $fkp, 0) > 0) $nb_lines++;
    }

    $f->validate($user);
    $f->fetch($id);
    auto_create_accounting_entries_for_invoice($db, $f, 'fournisseur', $user);

    // Pay if bank provided
    $msg = '✓ Facture fourn. '.$f->ref.' créée ('.$nb_lines.' lignes, '.number_format($f->total_ttc, 0).' MRU)';
    if (!empty($bank_name)) {
        $pay_result = tool_create_payment($db, [
            'type'=>'fournisseur', 'invoice_id'=>$id, 'amount'=>$f->total_ttc, 'bank_name'=>$bank_name
        ], $user);
        if (isset($pay_result['success']) && $pay_result['success']) {
            $msg .= ' + paiement '.$bank_name;
        }
    }

    return [$msg];
}

function tool_create_payment($db, $args, $user) {
    $type = $args['type'] ?? 'client';
    $invoice_id = (int)($args['invoice_id'] ?? 0);
    $invoice_ref = $db->escape($args['invoice_ref'] ?? '');
    $bank_account_id = (int)($args['bank_account_id'] ?? 0);
    $bank_name = $db->escape($args['bank_name'] ?? '');
    $entity = (int)$GLOBALS['conf']->entity;

    // If bank_account_id missing but bank_name provided, try to find or create it
    if ($bank_account_id <= 0 && !empty($bank_name)) {
        $sql_find = "SELECT rowid FROM ".MAIN_DB_PREFIX."bank_account WHERE label LIKE '%{$bank_name}%' AND entity IN (0, {$entity}) LIMIT 1";
        $res_find = $db->query($sql_find);
        if ($res_find && ($row_bank = $db->fetch_object($res_find))) {
            $bank_account_id = $row_bank->rowid;
        } else {
            // Auto-create bank account
            $bank_create = tool_create_bank_account($db, ['ref' => substr($bank_name, 0, 10), 'label' => $bank_name, 'country' => 'MR'], $user);
            if (isset($bank_create['id'])) {
                $bank_account_id = $bank_create['id'];
            }
        }
    }

    // Validate bank account exists
    if ($bank_account_id <= 0) {
        return ['success'=>false,'error'=>'Banque introuvable'];
    }

    // Find invoice
    if ($type === 'client') {
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        $f = new Facture($db);
        if ($invoice_id > 0) $f->fetch($invoice_id);
        elseif (!empty($invoice_ref)) $f->fetch(0, $invoice_ref);
        else return ['success'=>false,'error'=>'Fournir invoice_id ou invoice_ref'];
        if (!$f->id) return ['success'=>false,'error'=>'Facture client introuvable'];
        if ($f->paye == 1) return ['success'=>false,'error'=>'Facture déjà entièrement payée'];

        $amount = (float)($args['amount'] ?? $f->total_ttc);
        $remaining = $f->total_ttc - $f->getSommePaiement();
        if ($amount > $remaining) $amount = $remaining;

        // Payment mode
        $mode_map = ['VIR'=>6,'CHQ'=>7,'CB'=>6,'LIQ'=>4,'PRE'=>3];
        $payment_mode = $args['payment_mode'] ?? 'VIR';
        $fk_paiement = $mode_map[$payment_mode] ?? 6;

        require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
        $p = new Paiement($db);
        $p->datepaye = !empty($args['date']) ? strtotime($args['date']) : dol_now();
        $p->amounts = [$f->id => $amount];
        $p->multicurrency_amounts = [$f->id => $amount];
        $p->paiementid = $fk_paiement;
        $p->num_paiement = $db->escape($args['num_payment'] ?? '');
        $p->note_public = 'Paiement via Tafkir IA';

        $pid = $p->create($user, 1);
        if ($pid > 0) {
            // Link to bank account
            $p->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $bank_account_id, '', '');
            return ['✓' => 'Paiement '.$f->ref.' enregistré'];
        }
        return ['error'=>'Erreur paiement'];

    } else {
        // Supplier payment
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
        $f = new FactureFournisseur($db);
        if ($invoice_id > 0) $f->fetch($invoice_id);
        elseif (!empty($invoice_ref)) $f->fetch(0, $invoice_ref);
        else return ['success'=>false,'error'=>'Fournir invoice_id ou invoice_ref'];
        if (!$f->id) return ['success'=>false,'error'=>'Facture fournisseur introuvable'];
        if ($f->paye == 1) return ['success'=>false,'error'=>'Facture déjà payée'];

        $amount = (float)($args['amount'] ?? $f->total_ttc);
        $remaining = $f->total_ttc - $f->getSommePaiement();
        if ($amount > $remaining) $amount = $remaining;

        $mode_map = ['VIR'=>6,'CHQ'=>7,'CB'=>6,'LIQ'=>4,'PRE'=>3];
        $payment_mode = $args['payment_mode'] ?? 'VIR';

        $p = new PaiementFourn($db);
        $p->datepaye = !empty($args['date']) ? strtotime($args['date']) : dol_now();
        $p->amounts = [$f->id => $amount];
        $p->multicurrency_amounts = [$f->id => $amount];
        $p->paiementid = $mode_map[$payment_mode] ?? 6;
        $p->num_paiement = $db->escape($args['num_payment'] ?? '');
        $p->note_public = 'Paiement fournisseur via Tafkir IA';

        $pid = $p->create($user, 1);
        if ($pid > 0) {
            // Link to bank account
            $p->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bank_account_id, '', '');
            return ['✓' => 'Paiement '.$f->ref.' enregistré'];
        }
        return ['error'=>'Erreur paiement'];
    }
}

function tool_create_order($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
    $client_id = (int)($args['client_id']??0);
    if (!$client_id && !empty($args['client_name'])) {
        $n = $db->escape($args['client_name']);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND client IN(1,3) AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1");
        if ($row = $db->fetch_object($r)) $client_id = $row->rowid;
    }
    if (!$client_id) return ['success'=>false,'error'=>'Client introuvable. Utilisez search_clients.'];
    $c = new Commande($db);
    $c->socid = $client_id;
    $c->date_commande = !empty($args['date'])?strtotime($args['date']):dol_now();
    $c->ref_client = $db->escape($args['ref_client']??'');
    $c->entity = $GLOBALS['conf']->entity;
    $id = $c->create($user);
    if ($id <= 0) return ['success'=>false,'error'=>$c->error??'Erreur création commande'];
    $nb = 0;
    foreach (($args['lines']??[]) as $l) {
        $fkp = 0;
        if (!empty($l['product_ref'])) { $pr=$db->escape($l['product_ref']); $rr=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1"); if($row=$db->fetch_object($rr)) $fkp=$row->rowid; }
        if ($c->addline($db->escape($l['description']??($l['product_ref']??'Article')),(float)($l['price']??0),(float)($l['qty']??1),(float)($l['tva_tx']??16),0,0,$fkp)>0) $nb++;
    }
    $c->valid($user); $c->fetch($id);
    return ['success'=>true,'id'=>$id,'ref'=>$c->ref,'total_ht'=>number_format($c->total_ht,2).' MRU','total_ttc'=>number_format($c->total_ttc,2).' MRU','lignes'=>$nb,'message'=>'Commande client créée et validée'];
}

function tool_create_supplier_order($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
    $fid = (int)($args['fournisseur_id']??0);
    if (!$fid && !empty($args['fournisseur_name'])) {
        $n = $db->escape($args['fournisseur_name']);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND fournisseur=1 AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1");
        if ($row = $db->fetch_object($r)) $fid = $row->rowid;
    }
    if (!$fid) return ['success'=>false,'error'=>'Fournisseur introuvable.'];
    $c = new CommandeFournisseur($db);
    $c->socid = $fid;
    $c->date_commande = !empty($args['date'])?strtotime($args['date']):dol_now();
    $c->ref_supplier = $db->escape($args['ref_supplier']??'');
    $c->entity = $GLOBALS['conf']->entity;
    $id = $c->create($user);
    if ($id <= 0) return ['success'=>false,'error'=>$c->error??'Erreur création commande fournisseur'];
    $nb = 0;
    foreach (($args['lines']??[]) as $l) {
        $fkp = 0;
        if (!empty($l['product_ref'])) { $pr=$db->escape($l['product_ref']); $rr=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1"); if($row=$db->fetch_object($rr)) $fkp=$row->rowid; }
        $c->addline($db->escape($l['description']??'Achat'), (float)($l['price']??0), (float)($l['qty']??1), (float)($l['tva_tx']??16), 0, 0, $fkp);
        $nb++;
    }
    $c->valid($user); $c->fetch($id);
    return ['success'=>true,'id'=>$id,'ref'=>$c->ref,'total_ht'=>number_format($c->total_ht,2).' MRU','total_ttc'=>number_format($c->total_ttc,2).' MRU','lignes'=>$nb,'message'=>'Commande fournisseur créée et validée'];
}

function tool_create_bank_transaction($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
    $account_id = (int)($args['account_id'] ?? 0);
    if (!$account_id) return ['success'=>false,'error'=>'ID compte bancaire requis. Utilisez get_bank_accounts.'];

    $acc = new Account($db);
    $acc->fetch($account_id);
    if (!$acc->id) return ['success'=>false,'error'=>'Compte bancaire introuvable'];

    $amount = (float)($args['amount'] ?? 0);
    if ($amount == 0) return ['success'=>false,'error'=>'Montant ne peut pas être zéro'];

    $date = !empty($args['date']) ? strtotime($args['date']) : dol_now();
    $label = $db->escape($args['label'] ?? 'Mouvement bancaire');
    $type_map = ['VIR'=>'VIR','CHQ'=>'CHQ','CB'=>'CB','LIQ'=>'LIQ','PRE'=>'PRE'];
    $type = $type_map[$args['type']??'VIR'] ?? 'VIR';
    $num_chq = $db->escape($args['num_chq'] ?? '');
    $emetteur = $db->escape($args['emetteur'] ?? '');

    $id = $acc->addline($date, $type, $label, $amount, $num_chq, '', $user, $emetteur);
    if ($id > 0) {
        return ['success'=>true,'transaction_id'=>$id,'compte'=>$acc->label,'montant'=>number_format($amount,2).' MRU','sens'=>$amount>0?'Crédit':'Débit','message'=>'Mouvement bancaire enregistré'];
    }
    return ['success'=>false,'error'=>$acc->error??'Erreur enregistrement mouvement bancaire'];
}

function tool_accounting_advice($db, $args) {
    global $pcm_knowledge, $fisc_knowledge;
    $question = $args['question'] ?? '';
    $context = $args['context'] ?? '';
    $type = $args['type'] ?? 'conseil';

    // Return the knowledge base to the LLM for it to formulate the answer
    $response = [
        'type' => $type,
        'question' => $question,
        'context' => $context,
        'knowledge_base' => [
            'plan_comptable_mauritanien' => $pcm_knowledge,
            'fiscalite_mauritanienne' => $fisc_knowledge,
        ],
        'instruction' => 'Utilise ces connaissances du Plan Comptable Mauritanien et de la fiscalité mauritanienne pour répondre précisément à la question. Cite les numéros de comptes exacts, les taux d\'imposition, et les écritures comptables appropriées. Si une correction est demandée, montre l\'écriture incorrecte puis l\'écriture correcte.'
    ];
    return $response;
}

// ============================================================
// TOOL DISPATCH
// ============================================================
function execute_tool($name, $input, $db, $user) {
    switch ($name) {
        case 'search_products':            return tool_search_products($db, $input);
        case 'search_clients':             return tool_search_clients($db, $input);
        case 'search_fournisseurs':        return tool_search_fournisseurs($db, $input);
        case 'get_invoices':               return tool_get_invoices($db, $input);
        case 'get_invoice_details':        return tool_get_invoice_details($db, $input);
        case 'get_stats':                  return tool_get_stats($db, $input);
        case 'search_users':              return tool_search_users($db, $input);
        case 'get_bank_accounts':          return tool_get_bank_accounts($db, $input);
        case 'get_bank_transactions':      return tool_get_bank_transactions($db, $input);
        case 'get_payments':               return tool_get_payments($db, $input);
        case 'get_orders':                 return tool_get_orders($db, $input);
        case 'get_supplier_orders':        return tool_get_supplier_orders($db, $input);
        case 'get_accounting_entries':     return tool_get_accounting_entries($db, $input);
        case 'get_account_balance':        return tool_get_account_balance($db, $input);
        case 'get_chart_of_accounts':      return tool_get_chart_of_accounts($db, $input);
        case 'get_product_sales_stats':    return tool_get_product_sales_stats($db, $input);
        case 'create_product':             return tool_create_product($db, $input, $user);
        case 'create_client':              return tool_create_client($db, $input, $user);
        case 'create_fournisseur':         return tool_create_fournisseur($db, $input, $user);
        case 'create_facture_client':      return tool_create_facture_client($db, $input, $user);
        case 'create_facture_fournisseur': return tool_create_facture_fournisseur($db, $input, $user);
        case 'create_payment':             return tool_create_payment($db, $input, $user);
        case 'create_order':               return tool_create_order($db, $input, $user);
        case 'create_supplier_order':      return tool_create_supplier_order($db, $input, $user);
        case 'create_bank_transaction':    return tool_create_bank_transaction($db, $input, $user);
        case 'create_bank_account':        return tool_create_bank_account($db, $input, $user);
        case 'create_stock_movement':      return tool_create_stock_movement($db, $input, $user);
        case 'delete_element':             return tool_delete_element($db, $input, $user);
        case 'accounting_advice':          return tool_accounting_advice($db, $input);
        case 'analyze_invoice_image':      return tool_analyze_invoice_image($db, $input, $user);
        case 'auto_create_from_image':     return tool_auto_create_from_image($db, $input, $user);
        case 'create_accounting_entry':    return tool_create_accounting_entry($db, $input, $user);
        case 'get_balance_sheet':          return tool_get_balance_sheet($db, $input);
        case 'get_income_statement':       return tool_get_income_statement($db, $input);
        case 'reconcile_bank_transaction': return tool_reconcile_bank_transaction($db, $input, $user);
        case 'transfer_between_banks':     return tool_transfer_between_banks($db, $input, $user);
        case 'get_stock_analysis':         return tool_get_stock_analysis($db, $input);
        case 'forecast_stock_needs':       return tool_forecast_stock_needs($db, $input);
        default:                           return ['error' => 'Outil inconnu: '.$name];
    }
}

// ============================================================
// API CALL
// ============================================================
function call_llm($api_url, $api_key, $model, $max_tokens, $system_prompt, $messages, $tools, $provider) {
    if ($provider === 'anthropic') {
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'system'     => $system_prompt,
            'tools'      => array_map(function($t) {
                return ['name'=>$t['function']['name'],'description'=>$t['function']['description'],'input_schema'=>$t['function']['parameters']];
            }, $tools),
            'messages'   => $messages,
        ], JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'x-api-key: '.$api_key, 'anthropic-version: 2023-06-01'];
    } else {
        $msgs = [['role' => 'system', 'content' => $system_prompt]];
        foreach ($messages as $m) $msgs[] = $m;
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'tools'      => $tools,
            'messages'   => $msgs,
        ], JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json', 'Authorization: Bearer '.$api_key];
        if ($provider === 'openrouter') {
            $headers[] = 'HTTP-Referer: '.DOL_URL_ROOT;
            $headers[] = 'X-Title: Tafkir IA - ERP Assistant';
        }
    }
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>90, CURLOPT_HTTPHEADER=>$headers]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) return ['error' => 'Erreur réseau. Impossible de contacter l\'API.'];
    $data = json_decode($response, true);
    if ($http_code !== 200) return ['error' => 'Erreur API ('.$http_code.'): '.($data['error']['message']??$response)];
    return $data;
}

// ============================================================
// AGENTIC LOOP
// ============================================================
$messages = [];
foreach ($history as $h) {
    if (!empty($h['role']) && !empty($h['content'])) $messages[] = ['role'=>$h['role'],'content'=>$h['content']];
}
if (count($messages) > 16) $messages = array_slice($messages, -16);

// Add file context if provided (from file-handler.php analysis)
$final_message = $user_message;
if (!empty($file_context)) {
    $final_message = "📎 CONTEXTE FICHIER:\n" . $file_context . "\n\n" . $user_message;
}

$messages[] = ['role' => 'user', 'content' => $final_message];

$max_iter = 10;

if ($provider === 'anthropic') {
    $final_text = '';
    for ($iter = 0; $iter < $max_iter; $iter++) {
        $response = call_llm($api_url, $api_key, $model, $max_tokens, $system_prompt, $messages, $tools, $provider);
        if (isset($response['error'])) { echo json_encode(['success'=>false,'error'=>$response['error']]); exit; }
        $stop_reason = $response['stop_reason'] ?? 'end_turn';
        $content     = $response['content'] ?? [];
        $messages[]  = ['role'=>'assistant','content'=>$content];
        if ($stop_reason === 'end_turn') {
            foreach ($content as $b) if (($b['type']??'') === 'text') $final_text .= $b['text'];
            break;
        }
        if ($stop_reason === 'tool_use') {
            $tool_results = [];
            foreach ($content as $b) {
                if (($b['type']??'') === 'tool_use') {
                    $result = execute_tool($b['name'], $b['input']??[], $db, $user);
                    $tool_results[] = ['type'=>'tool_result','tool_use_id'=>$b['id'],'content'=>json_encode($result,JSON_UNESCAPED_UNICODE)];
                }
            }
            $messages[] = ['role'=>'user','content'=>$tool_results];
            continue;
        }
        foreach ($content as $b) if (($b['type']??'') === 'text') $final_text .= $b['text'];
        break;
    }
    if (empty($final_text)) $final_text = 'Je n\'ai pas pu générer une réponse. Veuillez réessayer.';
    // Post-traitement strict pour supprimer toute mention de Dolibarr
    $final_text = str_ireplace(['dolibarr', 'DoliBarr'], 'le système', $final_text);
    echo json_encode(['success'=>true,'message'=>$final_text,'provider'=>$provider], JSON_UNESCAPED_UNICODE);

} else {
    ob_end_clean();
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    for ($iter = 0; $iter < $max_iter; $iter++) {
        $msgs_api = [['role' => 'system', 'content' => $system_prompt]];
        foreach ($messages as $m) $msgs_api[] = $m;

        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'tools'      => $tools,
            'messages'   => $msgs_api,
            'stream'     => true,
        ], JSON_UNESCAPED_UNICODE);

        $hdrs = ['Content-Type: application/json', 'Authorization: Bearer '.$api_key];
        if ($provider === 'openrouter') {
            $hdrs[] = 'HTTP-Referer: '.DOL_URL_ROOT;
            $hdrs[] = 'X-Title: Tafkir IA - ERP Assistant';
        }

        $tc_acc  = [];
        $fr      = 'stop';
        $ssebuf  = '';
        $errdata = null;

        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$tc_acc, &$fr, &$ssebuf, &$errdata) {
                $ssebuf .= $chunk;
                while (($pos = strpos($ssebuf, "\n")) !== false) {
                    $line = trim(substr($ssebuf, 0, $pos));
                    $ssebuf = substr($ssebuf, $pos + 1);
                    if (strpos($line, 'data: ') !== 0) continue;
                    $d = substr($line, 6);
                    if ($d === '[DONE]') continue;
                    $j = json_decode($d, true);
                    if (!$j) continue;
                    if (isset($j['error'])) { $errdata = $j['error']['message'] ?? 'Erreur API'; continue; }
                    $delta = $j['choices'][0]['delta'] ?? [];
                    $f2    = $j['choices'][0]['finish_reason'] ?? null;
                    if ($f2) $fr = $f2;
                    if (!empty($delta['content'])) {
                        // Remplacement strict à la volée, bien que plus difficile en stream
                        $clean_token = str_ireplace(['dolibarr', 'DoliBarr'], 'le système', $delta['content']);
                        echo "data: ".json_encode(['token'=>$clean_token],JSON_UNESCAPED_UNICODE)."\n\n";
                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    }
                    if (!empty($delta['tool_calls'])) {
                        foreach ($delta['tool_calls'] as $tc) {
                            $i = $tc['index'] ?? 0;
                            if (!isset($tc_acc[$i])) $tc_acc[$i] = ['id'=>'','type'=>'function','function'=>['name'=>'','arguments'=>'']];
                            if (!empty($tc['id'])) $tc_acc[$i]['id'] = $tc['id'];
                            if (!empty($tc['function']['name'])) $tc_acc[$i]['function']['name'] .= $tc['function']['name'];
                            if (isset($tc['function']['arguments'])) $tc_acc[$i]['function']['arguments'] .= $tc['function']['arguments'];
                        }
                    }
                }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errdata || $http_code !== 200) {
            $errmsg = $errdata ?? ('Erreur API ('.$http_code.')');
            echo "data: ".json_encode(['error'=>$errmsg],JSON_UNESCAPED_UNICODE)."\n\n";
            echo "data: [DONE]\n\n"; flush(); exit;
        }

        if ($fr === 'tool_calls' && !empty($tc_acc)) {
            $messages[] = ['role'=>'assistant','content'=>null,'tool_calls'=>array_values($tc_acc)];
            foreach ($tc_acc as $tc) {
                $args   = json_decode($tc['function']['arguments']??'{}', true) ?: [];
                $result = execute_tool($tc['function']['name'], $args, $db, $user);
                $messages[] = ['role'=>'tool','tool_call_id'=>$tc['id'],'content'=>json_encode($result,JSON_UNESCAPED_UNICODE)];
            }
            continue;
        }

        echo "data: [DONE]\n\n"; flush(); exit;
    }

    echo "data: ".json_encode(['error'=>'Trop d\'itérations. Réessayez avec une question plus simple.'],JSON_UNESCAPED_UNICODE)."\n\n";
    echo "data: [DONE]\n\n"; flush();
}
// ============================================================
// NEW TOOLS IMPLEMENTATIONS
// ============================================================

function tool_create_bank_account($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

    $account = new Account($db);
    $account->ref = $args['ref'] ?? substr($args['label']??'BANK', 0, 10);
    $account->label = $args['label'] ?? $args['ref'] ?? 'Compte';
    $account->courant = isset($args['type']) ? $args['type'] : 0;
    $account->currency_code = $args['currency'] ?? 'MRU';
    $account->country_code = $args['country'] ?? 'MR';
    $account->account_number = $args['account_number'] ?? $account->ref;
    $account->account_code = $args['code_compta'] ?? '512';
    $account->entity = (int)$GLOBALS['conf']->entity;

    $res = $account->create($user);
    if ($res > 0) {
        return ['success' => true, 'id' => $res, 'ref' => $account->ref];
    }
    return ['error' => ($account->error ?? 'Erreur création compte')];
}

function tool_create_stock_movement($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    $mov = new MouvementStock($db);
    $mov->product_id = (int)$args['product_id'];
    $mov->entrep_id = (int)($args['warehouse_id'] ?? 1);
    $mov->qty = floatval($args['qty']);
    $mov->label = $args['label'];
    $mov->datem = dol_now();
    $mov->batch_number = $args['batch_number'] ?? '';
    $mov->origin_document = $args['origin_document'] ?? '';

    // Validate no negative stock unless explicitly allowed
    $sql_check = "SELECT stock FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".(int)$args['product_id'];
    $res_check = $db->query($sql_check);
    if ($res_check && ($row_prod = $db->fetch_object($res_check))) {
        $new_stock = ($row_prod->stock ?? 0) + floatval($args['qty']);
        if ($new_stock < 0) {
            return ['error' => 'Stock insuffisant. Stock actuel: '.$row_prod->stock.', demande: '.$args['qty']];
        }
    }

    $res = $mov->_create($user);
    if ($res > 0) {
        return ['success' => true, 'id' => $res, 'reason' => $args['reason_code'] ?? 'adjustment', 'message' => "Mouvement de stock enregistré avec succès."];
    } else {
        return ['error' => 'Erreur enregistrement stock: ' . $mov->error];
    }
}

function tool_delete_element($db, $args, $user) {
    $id = (int)$args['id'];
    $type = $args['type'];
    $obj = null;

    if ($type === 'product') {
        require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
        $obj = new Product($db);
    } elseif ($type === 'client') {
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        $obj = new Societe($db);
    } elseif ($type === 'facture') {
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        $obj = new Facture($db);
    } elseif ($type === 'commande') {
        require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
        $obj = new Commande($db);
    } elseif ($type === 'bank_account') {
        require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
        $obj = new Account($db);
    } else {
        return ['error' => "Type non supporté."];
    }

    $res = $obj->fetch($id);
    if ($res > 0) {
        $del = $obj->delete($user);
        if ($del > 0) {
            return ['success' => true, 'message' => "Élément supprimé avec succès."];
        } else {
            return ['error' => "Erreur lors de la suppression: " . $obj->error];
        }
    } else {
        return ['error' => "Élément introuvable avec l'ID $id."];
    }
}

function tool_analyze_invoice_image($db, $args, $user) {
    $image_base64 = $args['image_base64'] ?? '';
    $invoice_type = $args['type'] ?? 'facture_fournisseur';

    if (empty($image_base64)) {
        return ['error' => 'image_base64 est requis'];
    }

    // Call LLM vision API to analyze the image
    global $api_key, $provider, $api_url, $model;

    $vision_prompt = "Analyse cette image de facture et retourne un JSON avec les champs:\n";
    if ($invoice_type === 'facture_fournisseur') {
        $vision_prompt .= "- supplier_name: nom du fournisseur\n- supplier_nif: NIF/SIRET si visible\n- invoice_ref: numéro de facture\n- invoice_date: date (YYYY-MM-DD)\n- items: [{description, quantity, unit_price, total_ht}, ...]\n- total_ht: total HT\n- total_tva: TVA\n- total_ttc: total TTC\n- payment_terms: conditions de paiement si visibles";
    } else {
        $vision_prompt .= "- client_name: nom du client\n- client_nif: NIF si visible\n- invoice_ref: numéro de facture\n- invoice_date: date (YYYY-MM-DD)\n- items: [{description, quantity, unit_price, total_ht}, ...]\n- total_ht: total HT\n- total_tva: TVA\n- total_ttc: total TTC\n";
    }
    $vision_prompt .= "Retourne UNIQUEMENT du JSON valide, pas d'autre texte.";

    // Build vision message
    if ($provider === 'anthropic') {
        $image_source = ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $image_base64];
        $msgs = [['role' => 'user', 'content' => [
            ['type' => 'image', 'source' => $image_source],
            ['type' => 'text', 'text' => $vision_prompt]
        ]]];
    } else {
        $msgs = [['role' => 'user', 'content' => [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.$image_base64]],
            ['type' => 'text', 'text' => $vision_prompt]
        ]]];
    }

    // Call API
    $payload = ($provider === 'anthropic') ?
        json_encode(['model' => $model, 'max_tokens' => 1024, 'messages' => $msgs], JSON_UNESCAPED_UNICODE) :
        json_encode(['model' => $model, 'max_tokens' => 1024, 'messages' => $msgs], JSON_UNESCAPED_UNICODE);

    $headers = ($provider === 'anthropic') ?
        ['Content-Type: application/json', 'x-api-key: '.$api_key, 'anthropic-version: 2023-06-01'] :
        ['Content-Type: application/json', 'Authorization: Bearer '.$api_key];

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>30, CURLOPT_HTTPHEADER=>$headers]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return ['error' => 'Erreur API vision ('.$http_code.')'];
    }

    $data = json_decode($response, true);
    $text = '';

    if ($provider === 'anthropic') {
        foreach ($data['content'] ?? [] as $b) {
            if (($b['type']??'') === 'text') $text = $b['text'];
        }
    } else {
        $text = $data['choices'][0]['message']['content'] ?? '';
    }

    // Extract JSON from response
    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $parsed = json_decode($matches[0], true);
        return ['success' => true, 'type' => $invoice_type, 'data' => $parsed, 'raw_response' => $text];
    }

    return ['success' => false, 'error' => 'Impossible de parser la réponse vision', 'response' => $text];
}

function tool_create_accounting_entry($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

    $journal_code = $args['journal_code'] ?? 'OD';
    $doc_date = !empty($args['doc_date']) ? strtotime($args['doc_date']) : dol_now();
    $doc_reference = $args['doc_reference'] ?? 'MANUAL-'.date('YmdHis');
    $debit_lines = $args['debit_lines'] ?? [];
    $credit_lines = $args['credit_lines'] ?? [];
    $entity = (int)$GLOBALS['conf']->entity;

    if (empty($debit_lines) || empty($credit_lines)) {
        return ['error' => 'Fournir au moins 1 ligne débit et 1 ligne crédit'];
    }

    // Calculate totals
    $total_debit = 0;
    foreach ($debit_lines as $line) {
        $total_debit += floatval($line['amount'] ?? 0);
    }
    $total_credit = 0;
    foreach ($credit_lines as $line) {
        $total_credit += floatval($line['amount'] ?? 0);
    }

    // Validate balance
    if (abs($total_debit - $total_credit) > 0.01) {
        return ['error' => 'Débits ('.number_format($total_debit,2).') ≠ Crédits ('.number_format($total_credit,2).')'];
    }

    // Create entries
    $bookkeeping = new BookKeeping($db);
    $entries_created = 0;
    $piece_num = $bookkeeping->getNextNumMvt('') ?? date('YmdHis');

    // Insert debit lines
    foreach ($debit_lines as $line) {
        $account_number = $line['account_number'] ?? '';
        $account_label = $line['account_label'] ?? $account_number;
        $amount = floatval($line['amount'] ?? 0);

        $bookkeeping->createFromValues(
            $doc_date, $doc_reference, 'manual', 0, 0, $account_number, $account_label, $account_label,
            $amount, $journal_code, 'Manual entry', ''
        );
        $entries_created++;
    }

    // Insert credit lines (negative amounts)
    foreach ($credit_lines as $line) {
        $account_number = $line['account_number'] ?? '';
        $account_label = $line['account_label'] ?? $account_number;
        $amount = floatval($line['amount'] ?? 0);

        $bookkeeping->createFromValues(
            $doc_date, $doc_reference, 'manual', 0, 0, $account_number, $account_label, $account_label,
            -$amount, $journal_code, 'Manual entry', ''
        );
        $entries_created++;
    }

    return [
        'success' => true,
        'piece_num' => $piece_num,
        'entries_created' => $entries_created,
        'total_debit' => number_format($total_debit, 2).' MRU',
        'total_credit' => number_format($total_credit, 2).' MRU',
        'message' => "Écriture comptable créée avec succès"
    ];
}

function tool_get_balance_sheet($db, $args, $user) {
    // EXTREME SIMPLE bilan - use direct calculation without GROUP BY
    $entity = (int)$GLOBALS['conf']->entity;

    // Count entries first (LIMIT 1 to stop scanning immediately)
    $count_sql = "SELECT 1 FROM ".MAIN_DB_PREFIX."accounting_bookkeeping WHERE entity = {$entity} LIMIT 1";
    @$count_res = $db->query($count_sql);
    $has_data = ($count_res && @$db->fetch_object($count_res));

    if (!$has_data) {
        return ['✓' => 'Bilan équilibré', 'Actif' => '0 MRU', 'Passif' => '0 MRU', 'Note' => 'Aucune écriture'];
    }

    // Simple sum without GROUP BY - much faster!
    $sql = "SELECT SUM(COALESCE(debit, 0)) as deb, SUM(COALESCE(credit, 0)) as cred FROM ".MAIN_DB_PREFIX."accounting_bookkeeping WHERE entity = {$entity} LIMIT 1";

    @$res = $db->query($sql);
    if (!$res || !($row = @$db->fetch_object($res))) {
        return ['✓' => 'Bilan', 'Actif' => '0 MRU', 'Passif' => '0 MRU'];
    }

    $actif = (float)($row->deb ?? 0);
    $passif = (float)($row->cred ?? 0);

    return [
        '✓ Bilan' => [
            'Actif' => number_format($actif, 0).' MRU',
            'Passif' => number_format($passif, 0).' MRU',
            'Équilibre' => (abs($actif - $passif) < 1) ? '✓' : ('Diff: '.number_format($actif - $passif, 0))
        ]
    ];
}

function tool_get_income_statement($db, $args, $user) {
    // SIMPLE & FAST compte de résultat
    $sql = "SELECT SUBSTRING(numero_compte, 1, 1) as classe,
                   SUM(IF(debit IS NOT NULL, debit, 0)) as total_debit,
                   SUM(IF(credit IS NOT NULL, credit, 0)) as total_credit
            FROM ".MAIN_DB_PREFIX."accounting_bookkeeping
            WHERE SUBSTRING(numero_compte, 1, 1) IN ('6','7')
            GROUP BY classe";

    $res = $db->query($sql);
    $charges = 0;
    $produits = 0;

    if ($res) {
        while ($row = $db->fetch_object($res)) {
            if ($row->classe === '6') $charges = floatval($row->total_debit);
            if ($row->classe === '7') $produits = floatval($row->total_credit);
        }
    }

    $result = $produits - $charges;
    $is = max(0, $result * 0.25);
    $net = $result - $is;

    return [
        'Produits' => number_format($produits, 0).' MRU',
        'Charges' => number_format($charges, 0).' MRU',
        'Résultat' => number_format($result, 0).' MRU',
        'IS 25%' => number_format($is, 0).' MRU',
        'Net' => number_format($net, 0).' MRU'
    ];
}

function tool_get_stock_analysis($db, $args, $user) {
    $analysis_type = $args['analysis_type'] ?? 'all';
    $days_old = (int)($args['days_old'] ?? 90);
    $limit = min((int)($args['limit'] ?? 10), 50);
    $entity = (int)$GLOBALS['conf']->entity;

    $result = [];

    // 1. Products below alert threshold
    if (in_array($analysis_type, ['alerts', 'all'])) {
        $sql = "SELECT rowid, ref, label, stock, seuil_stock_alerte
                FROM ".MAIN_DB_PREFIX."product
                WHERE entity = {$entity} AND tosell = 1
                  AND stock IS NOT NULL AND seuil_stock_alerte IS NOT NULL
                  AND stock <= seuil_stock_alerte AND stock > 0
                ORDER BY stock ASC LIMIT {$limit}";
        $res = $db->query($sql);
        $alerts = [];
        if ($res) while ($row = $db->fetch_object($res)) {
            $alerts[] = [
                'ref' => $row->ref,
                'label' => $row->label,
                'stock_current' => $row->stock,
                'seuil_alerte' => $row->seuil_stock_alerte,
                'recommendation' => 'Réapprovisionner dès que possible'
            ];
        }
        $result['stock_alerts'] = $alerts;
    }

    // 2. Dead stock (no movement for N days)
    if (in_array($analysis_type, ['dead_stock', 'all'])) {
        $date_threshold = dol_now() - ($days_old * 86400);
        $sql = "SELECT p.rowid, p.ref, p.label, p.stock, MAX(f.datef) as last_sale
                FROM ".MAIN_DB_PREFIX."product p
                LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_product = p.rowid
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture
                WHERE p.entity = {$entity} AND p.tosell = 1 AND p.stock > 0
                GROUP BY p.rowid
                HAVING MAX(f.datef) IS NULL OR MAX(f.datef) < '{$db->idate($date_threshold)}'
                ORDER BY p.stock DESC LIMIT {$limit}";
        $res = $db->query($sql);
        $dead = [];
        if ($res) while ($row = $db->fetch_object($res)) {
            $days_inactive = empty($row->last_sale) ? 999 : (int)((dol_now() - strtotime($row->last_sale)) / 86400);
            $dead[] = [
                'ref' => $row->ref,
                'label' => $row->label,
                'stock_locked' => $row->stock,
                'last_sale_days_ago' => $days_inactive === 999 ? 'Jamais vendu' : $days_inactive,
                'recommendation' => 'Considérer déstockage / liquidation'
            ];
        }
        $result['dead_stock'] = $dead;
    }

    // 3. Slow movers (low sales frequency)
    if (in_array($analysis_type, ['slow_movers', 'all'])) {
        $sql = "SELECT p.rowid, p.ref, p.label, p.stock,
                       COUNT(DISTINCT f.rowid) as nb_sales_year,
                       SUM(fd.qty) as qty_sold_year
                FROM ".MAIN_DB_PREFIX."product p
                LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_product = p.rowid
                LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture AND f.datef >= '{$db->idate(dol_now() - 365*86400)}'
                WHERE p.entity = {$entity} AND p.tosell = 1 AND p.stock > 0
                GROUP BY p.rowid
                HAVING nb_sales_year < 5 OR nb_sales_year IS NULL
                ORDER BY qty_sold_year ASC LIMIT {$limit}";
        $res = $db->query($sql);
        $slow = [];
        if ($res) while ($row = $db->fetch_object($res)) {
            $slow[] = [
                'ref' => $row->ref,
                'label' => $row->label,
                'stock_locked' => $row->stock,
                'sales_year' => $row->nb_sales_year ?? 0,
                'qty_sold_year' => $row->qty_sold_year ?? 0,
                'recommendation' => 'Réduire les commandes fournisseur, considérer la fin de produit'
            ];
        }
        $result['slow_movers'] = $slow;
    }

    return ['success' => true, 'type' => $analysis_type, 'analysis' => $result];
}

function tool_forecast_stock_needs($db, $args, $user) {
    $product_id = (int)($args['product_id'] ?? 0);
    $months_ahead = (int)($args['months_ahead'] ?? 3);
    $lookback_months = (int)($args['lookback_months'] ?? 12);
    $entity = (int)$GLOBALS['conf']->entity;

    $date_start_lookback = dol_now() - ($lookback_months * 30.44 * 86400);

    // Get historical sales
    $sql = "SELECT p.rowid, p.ref, p.label, p.stock,
                   YEAR(f.datef) as year, MONTH(f.datef) as month,
                   SUM(fd.qty) as qty_month
            FROM ".MAIN_DB_PREFIX."product p
            LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_product = p.rowid
            LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture
            WHERE p.entity = {$entity} AND p.tosell = 1
              AND f.datef >= '{$db->idate($date_start_lookback)}'";
    if ($product_id > 0) $sql .= " AND p.rowid = {$product_id}";
    $sql .= " GROUP BY p.rowid, year, month ORDER BY p.rowid, year, month";

    $res = $db->query($sql);
    $products_forecast = [];

    if ($res) while ($row = $db->fetch_object($res)) {
        $key = $row->rowid;
        if (!isset($products_forecast[$key])) {
            $products_forecast[$key] = [
                'ref' => $row->ref,
                'label' => $row->label,
                'stock_current' => $row->stock,
                'monthly_sales' => []
            ];
        }
        $month_key = $row->year.'-'.str_pad($row->month, 2, '0', STR_PAD_LEFT);
        $products_forecast[$key]['monthly_sales'][$month_key] = $row->qty_month ?? 0;
    }

    // Calculate forecasts
    $forecasts = [];
    foreach ($products_forecast as $prod) {
        $sales_avg = count($prod['monthly_sales']) > 0 ? array_sum($prod['monthly_sales']) / count($prod['monthly_sales']) : 0;
        $stock_current = (float)$prod['stock_current'];
        $months_supply = $sales_avg > 0 ? $stock_current / $sales_avg : 999;
        $projected_depletion = null;

        if ($months_supply < $months_ahead) {
            $projected_depletion = date('Y-m-d', dol_now() + ($months_supply * 30.44 * 86400));
            $recommended_qty = ($sales_avg * $months_ahead) - $stock_current;
        } else {
            $recommended_qty = 0;
        }

        $forecasts[] = [
            'product_ref' => $prod['ref'],
            'product_label' => $prod['label'],
            'stock_current' => (int)$stock_current,
            'monthly_avg_sales' => round($sales_avg, 1),
            'months_of_supply' => round($months_supply, 1),
            'projected_depletion_date' => $projected_depletion ?? 'Pas de rupture en vue',
            'recommended_buy_quantity' => max(0, (int)ceil($recommended_qty)),
            'urgency' => ($months_supply < 1) ? 'CRITIQUE' : (($months_supply < 2) ? 'HAUTE' : 'NORMAL')
        ];
    }

    // Sort by urgency
    usort($forecasts, function($a, $b) {
        $urgency_order = ['CRITIQUE' => 0, 'HAUTE' => 1, 'NORMAL' => 2];
        return ($urgency_order[$a['urgency']] ?? 999) - ($urgency_order[$b['urgency']] ?? 999);
    });

    return [
        'success' => true,
        'months_ahead' => $months_ahead,
        'lookback_months' => $lookback_months,
        'forecasts' => array_slice($forecasts, 0, 10)
    ];
}

function tool_reconcile_bank_transaction($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

    $transaction_id = (int)($args['transaction_id'] ?? 0);
    $reconciliation_category = (int)($args['reconciliation_category'] ?? 0);
    $invoice_id = (int)($args['invoice_id'] ?? 0);
    $invoice_type = $args['invoice_type'] ?? 'client';

    if ($transaction_id <= 0) {
        return ['error' => 'transaction_id invalide'];
    }

    // Load the transaction
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."bank WHERE rowid = {$transaction_id}";
    $res = $db->query($sql);
    if (!$res || !($row = $db->fetch_object($res))) {
        return ['error' => 'Transaction introuvable'];
    }

    // Mark as reconciled
    $sql_upd = "UPDATE ".MAIN_DB_PREFIX."bank SET fk_cat = ".($reconciliation_category > 0 ? $reconciliation_category : 'NULL');
    $sql_upd .= " WHERE rowid = {$transaction_id}";
    $db->query($sql_upd);

    // Link to invoice if provided
    if ($invoice_id > 0) {
        $table = ($invoice_type === 'fournisseur') ? MAIN_DB_PREFIX."facture_fourn" : MAIN_DB_PREFIX."facture";
        $sql_chk = "SELECT rowid FROM {$table} WHERE rowid = {$invoice_id}";
        $res_chk = $db->query($sql_chk);
        if ($res_chk && ($row_inv = $db->fetch_object($res_chk))) {
            // Link via bank->url_id (documented in bank schema)
            $sql_link = "UPDATE ".MAIN_DB_PREFIX."bank SET url_id = {$invoice_id} WHERE rowid = {$transaction_id}";
            $db->query($sql_link);
        }
    }

    return [
        'success' => true,
        'transaction_id' => $transaction_id,
        'reconciled' => true,
        'message' => 'Transaction marquée comme rapprochée'
    ];
}

function tool_transfer_between_banks($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

    $source_account_id = (int)($args['source_account_id'] ?? 0);
    $destination_account_id = (int)($args['destination_account_id'] ?? 0);
    $amount = floatval($args['amount'] ?? 0);
    $date = !empty($args['date']) ? strtotime($args['date']) : dol_now();
    $reference = $args['reference'] ?? 'VIREMENT-'.date('YmdHis');
    $label = $args['label'] ?? "Virement de ".$source_account_id." vers ".$destination_account_id;

    if ($source_account_id <= 0 || $destination_account_id <= 0 || $amount <= 0) {
        return ['error' => 'source_account_id, destination_account_id et amount sont obligatoires et > 0'];
    }

    // Verify both accounts exist
    $sql_src = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_account WHERE rowid = {$source_account_id}";
    $src = $db->fetch_object($db->query($sql_src));
    if (!$src) return ['error' => 'Compte source introuvable'];

    $sql_dst = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_account WHERE rowid = {$destination_account_id}";
    $dst = $db->fetch_object($db->query($sql_dst));
    if (!$dst) return ['error' => 'Compte destination introuvable'];

    // Create debit line on source account
    $acc_src = new Account($db);
    $acc_src->fetch($source_account_id);
    $acc_src->addline($date, 'VIR', "Virement vers ".$dst->label, -$amount, 0, '', $user);

    // Create credit line on destination account
    $acc_dst = new Account($db);
    $acc_dst->fetch($destination_account_id);
    $acc_dst->addline($date, 'VIR', "Virement depuis ".$src->label, $amount, 0, '', $user);

    return [
        'success' => true,
        'source_account_id' => $source_account_id,
        'destination_account_id' => $destination_account_id,
        'amount' => number_format($amount, 2).' MRU',
        'reference' => $reference,
        'message' => "Virement créé avec succès"
    ];
}

function tool_auto_create_from_image($db, $args, $user) {
    $image_base64 = $args['image_base64'] ?? '';
    $invoice_type = $args['type'] ?? 'facture_fournisseur';
    $force_create = $args['force_create'] ?? false;
    $entity = (int)$GLOBALS['conf']->entity;

    if (empty($image_base64)) {
        return ['error' => 'image_base64 est requis'];
    }

    // First analyze the image
    $analysis = tool_analyze_invoice_image($db, ['image_base64' => $image_base64, 'type' => $invoice_type], $user);
    if (!($analysis['success'] ?? false)) {
        return ['error' => 'Impossible d\'analyser l\'image: '.$analysis['error']??'Erreur inconnue'];
    }

    $extracted = $analysis['data'] ?? [];

    // Get or create third party
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $societe = new Societe($db);
    $third_party_name = $args['third_party_name'] ?? ($invoice_type === 'facture_fournisseur' ? $extracted['supplier_name'] : $extracted['client_name']) ?? 'Inconnu';

    // Search existing
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%".$db->escape($third_party_name)."%' AND entity={$entity} LIMIT 1";
    $res = $db->query($sql);
    $third_party_id = 0;

    if ($res && ($row = $db->fetch_object($res))) {
        $third_party_id = $row->rowid;
    } else {
        // Create new
        $societe->nom = $third_party_name;
        $societe->entity = $entity;
        if ($invoice_type === 'facture_fournisseur') {
            $societe->fournisseur = 1;
        } else {
            $societe->client = 1;
        }
        if (!empty($extracted['supplier_nif'] ?? $extracted['client_nif'] ?? '')) {
            $societe->siren = $extracted['supplier_nif'] ?? $extracted['client_nif'];
        }
        $third_party_id = $societe->create($user);
        if ($third_party_id <= 0) {
            return ['error' => 'Erreur création tiers: '.$societe->error];
        }
    }

    // Create invoice with items
    if ($invoice_type === 'facture_fournisseur') {
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
        $facture = new FactureFournisseur($db);
        $facture->fk_soc = $third_party_id;
        $facture->type = 0;
    } else {
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
        $facture = new Facture($db);
        $facture->socid = $third_party_id;
        $facture->type = 0;
    }

    $facture->entity = $entity;
    $facture->datef = !empty($extracted['invoice_date']) ? strtotime($extracted['invoice_date']) : dol_now();
    $facture->ref_supplier = $extracted['invoice_ref'] ?? '';

    $facture_id = $facture->create($user);
    if ($facture_id <= 0) {
        return ['error' => 'Erreur création facture: '.$facture->error];
    }

    // Add lines
    $nb_lines = 0;
    foreach ($extracted['items'] ?? [] as $item) {
        $desc = $item['description'] ?? 'Ligne';
        $qty = floatval($item['quantity'] ?? 1);
        $price = floatval($item['unit_price'] ?? 0);
        $tva = floatval($item['tva'] ?? 16);

        if ($invoice_type === 'facture_fournisseur') {
            $facture->addline($desc, $price, $tva, 0, 0, $qty, 0, 0);
        } else {
            $facture->addline($desc, $price, $qty, $tva);
        }
        $nb_lines++;
    }

    // Validate
    $facture->validate($user);
    $facture->fetch($facture_id);

    return [
        'success' => true,
        'message' => 'Facture créée automatiquement',
        'invoice_id' => $facture_id,
        'invoice_ref' => $facture->ref,
        'third_party_id' => $third_party_id,
        'third_party_name' => $third_party_name,
        'lines_count' => $nb_lines,
        'total_ht' => number_format($facture->total_ht, 2).' MRU',
        'total_ttc' => number_format($facture->total_ttc, 2).' MRU',
    ];
}
