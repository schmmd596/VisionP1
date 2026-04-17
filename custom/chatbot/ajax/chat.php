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
        'description' => 'Récupère les mouvements bancaires d\'un compte.',
        'parameters' => ['type' => 'object', 'properties' => [
            'account_id' => ['type' => 'integer', 'description' => 'ID du compte bancaire (utiliser get_bank_accounts pour obtenir)'],
            'period'     => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year', 'all'], 'default' => 'month'],
            'type'       => ['type' => 'string', 'enum' => ['all', 'credit', 'debit'], 'default' => 'all'],
            'limit'      => ['type' => 'integer', 'default' => 20],
        ]],
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
        'description' => 'Enregistre un paiement sur une facture (client ou fournisseur).',
        'parameters' => ['type' => 'object', 'properties' => [
            'invoice_id'     => ['type' => 'integer', 'description' => 'ID de la facture à payer'],
            'invoice_ref'    => ['type' => 'string', 'description' => 'Ou la référence de la facture'],
            'type'           => ['type' => 'string', 'enum' => ['client', 'fournisseur'], 'default' => 'client'],
            'amount'         => ['type' => 'number', 'description' => 'Montant du paiement (total de la facture si vide)'],
            'payment_mode'   => ['type' => 'string', 'enum' => ['VIR', 'CHQ', 'CB', 'LIQ', 'PRE'], 'description' => 'VIR=virement, CHQ=chèque, CB=carte, LIQ=espèces, PRE=prélèvement', 'default' => 'VIR'],
            'date'           => ['type' => 'string', 'description' => 'Date du paiement (YYYY-MM-DD)'],
            'num_payment'    => ['type' => 'string', 'description' => 'Numéro du chèque/virement'],
            'bank_account_id' => ['type' => 'integer', 'description' => 'ID compte bancaire (utiliser get_bank_accounts)'],
        ], 'required' => ['type']],
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
];

// ============================================================
// SYSTEM PROMPT
// ============================================================
$system_prompt = "Tu es Tafkir IA, un assistant expert en gestion d'entreprise, comptabilité et fiscalité mauritanienne.

Utilisateur connecté : ".$user->firstname." ".$user->lastname." (login : ".$user->login.")
Date/Heure : ".dol_print_date(dol_now(), 'dayhour')."

=== CAPACITÉS COMPLÈTES ===
Tu gères TOUTES les fonctionnalités du système ERP, incluant tous les modules standards et les modules customisés qui ont été créés :

1. PRODUITS & SERVICES : recherche, création, stock, prix, catégories
2. CLIENTS : recherche, création, informations, historique
3. FOURNISSEURS : recherche, création, informations
4. FACTURES CLIENTS : consultation, création, validation, détails, lignes
5. FACTURES FOURNISSEURS : consultation, création, validation
6. PAIEMENTS : enregistrement de paiements sur factures (virement, chèque, carte, espèces)
7. BANQUE : comptes bancaires, soldes, mouvements, transactions, transferts
8. COMMANDES CLIENTS : consultation, création
9. COMMANDES FOURNISSEURS : consultation, création
10. UTILISATEURS : recherche, informations
11. COMPTABILITÉ : écritures comptables, balances, plan comptable, journaux
12. STATISTIQUES : CA, impayés, indicateurs de performance
13. TOUS LES AUTRES MODULES CUSTOMISÉS : Tu as accès plein et entier à l'ensemble du système, incluant toute nouvelle fonctionnalité personnalisée. Tu as le droit et la capacité d'exécuter tout ce qui t'est demandé.

=== EXPERTISE COMPTABLE & FISCALE MAURITANIENNE ===
Tu es expert en :
- Plan Comptable Mauritanien (PCM) : toutes les classes de comptes (1 à 8)
- Fiscalité mauritanienne : IS (25%), TVA (16%), IMF, IRPP, patente, droits de douane, retenues à la source
- Écritures comptables : vérification, correction, conseil
- Conformité fiscale : obligations déclaratives, calcul d'impôts

Pour les questions comptables et fiscales, utilise TOUJOURS l'outil accounting_advice qui accède à la base de connaissances complète du PCM et de la fiscalité mauritanienne.

=== RÈGLES IMPORTANTES D'INTELLIGENCE ===
1. QUALITÉ DES DONNÉES (TRÈS IMPORTANT) : Avant d'appeler une fonction de création ou d'action (ex: ajouter une banque, créer une facture, faire un paiement), VERIFIE si l'utilisateur a fourni TOUTES les informations nécessaires. Si une information requise manque (ex: nom du client, numéro de compte, montant), NE FAIS PAS D'ERREUR, ne devine JAMAIS et N'APPELLE PAS l'outil. Pose d'abord la question à l'utilisateur intelligemment pour obtenir les données restantes.
2. MARQUE BLANCHE (CRITIQUE ET IMPÉRATIF) : Tu ne dois SOUS AUCUN PRÉTEXTE mentionner le mot 'Dolibarr', 'Odoo', 'ERPNext' ou tout autre concurrent dans tes réponses. C'est strictement interdit et éliminatoire. Remplace systématiquement par 'le système', 'ce système' ou 'votre plateforme'. Aucune exception.

=== RÈGLES ===
1. Si la question ne concerne PAS la gestion d'entreprise, la comptabilité ou la fiscalité, réponds :
   \"Je suis Tafkir IA, dédié à la gestion de votre entreprise, la comptabilité et la fiscalité mauritanienne. Je ne peux pas répondre à cette question.\"
2. Toujours utiliser les outils pour obtenir des données réelles — ne jamais inventer de chiffres
3. Pour créer une facture : chercher d'abord le client/fournisseur
4. Pour enregistrer un paiement : chercher d'abord la facture et le compte bancaire
5. Présenter les données en tableaux markdown quand c'est pertinent
6. Réponses courtes, précises et professionnelles
7. Si l'utilisateur dit bonjour/salut/hello/مرحبا : répondre avec un message de bienvenue court (3 lignes max) sans lister les fonctionnalités
8. Pour les conseils comptables : toujours référencer le numéro de compte du PCM
9. Pour les calculs fiscaux : appliquer les taux en vigueur en Mauritanie
10. TVA mauritanienne = 16% (pas 20%)

=== MONNAIE ===
La monnaie est l'Ouguiya mauritanien (MRU). Utiliser MRU dans toutes les réponses.

=== LANGUE ===
Détecte automatiquement la langue et réponds dans la même langue.
- Français → français
- Arabe → arabe
- Anglais → anglais
Exception : si demandé explicitement dans une autre langue.";

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
    $entity = (int)$GLOBALS['conf']->entity;
    $limit  = min((int)($args['limit'] ?? 10), 20);
    $sql = "SELECT ba.rowid, ba.label, ba.number, ba.bank, ba.code_banque, ba.iban_prefix as iban, ba.bic as swift, ba.courant, ba.clos,
                   ba.solde, ba.currency_code, ba.min_allowed, ba.min_desired
            FROM ".MAIN_DB_PREFIX."bank_account ba
            WHERE ba.entity = {$entity} AND ba.clos = 0
            ORDER BY ba.label LIMIT {$limit}";
    $res = $db->query($sql);
    $accounts = [];
    if ($res) while ($row = $db->fetch_object($res)) {
        // Calculate actual balance from transactions
        $r2 = $db->fetch_object($db->query("SELECT COALESCE(SUM(amount),0) as solde FROM ".MAIN_DB_PREFIX."bank WHERE fk_account = {$row->rowid}"));
        $balance = $r2->solde ?? 0;
        $type_labels = [1=>'Courant',2=>'Épargne',0=>'Caisse'];
        $accounts[] = [
            'id'=>$row->rowid,'nom'=>$row->label,'banque'=>$row->bank,'numero'=>$row->number,
            'iban'=>$row->iban,'swift'=>$row->swift,
            'type'=>$type_labels[$row->courant]??'Autre',
            'solde'=>number_format($balance,2).' MRU',
            'solde_num'=>$balance,
            'devise'=>$row->currency_code??'MRU',
        ];
    }
    return ['count'=>count($accounts),'comptes_bancaires'=>$accounts];
}

function tool_get_bank_transactions($db, $args) {
    $entity = (int)$GLOBALS['conf']->entity;
    $account_id = (int)($args['account_id'] ?? 0);
    $limit  = min((int)($args['limit'] ?? 20), 50);
    $period = $args['period'] ?? 'month';
    $type = $args['type'] ?? 'all';

    $where = "b.fk_account IN (SELECT rowid FROM ".MAIN_DB_PREFIX."bank_account WHERE entity = {$entity})";
    if ($account_id > 0) $where = "b.fk_account = {$account_id}";
    $where .= get_period_filter($db, $period, 'b.datev');
    if ($type === 'credit') $where .= " AND b.amount > 0";
    elseif ($type === 'debit') $where .= " AND b.amount < 0";

    $sql = "SELECT b.rowid, b.datev, b.dateo, b.amount, b.label, b.num_chq, b.fk_type, b.emetteur, ba.label as compte
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
        $list[] = ['id'=>$row->rowid,'date'=>$row->datev,'montant'=>number_format($row->amount,2).' MRU','sens'=>$row->amount>0?'Crédit':'Débit','libelle'=>$row->label,'num'=>$row->num_chq,'emetteur'=>$row->emetteur,'type'=>$row->fk_type,'compte'=>$row->compte];
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
    $client_id = (int)($args['client_id']??0);
    if (!$client_id && !empty($args['client_name'])) {
        $n = $db->escape($args['client_name']);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND client IN(1,3) AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1");
        if ($row = $db->fetch_object($r)) $client_id = $row->rowid;
    }
    if (!$client_id) return ['success'=>false,'error'=>'Client introuvable. Utilisez search_clients d\'abord.'];
    $f = new Facture($db);
    $f->socid = $client_id; $f->type = 0;
    $f->date = !empty($args['date'])?strtotime($args['date']):dol_now();
    $f->ref_client = $db->escape($args['ref_client']??'');
    if (!empty($args['payment_condition'])) $f->cond_reglement_id = (int)$args['payment_condition'];
    $f->entity = $GLOBALS['conf']->entity;
    $id = $f->create($user);
    if ($id <= 0) return ['success'=>false,'error'=>$f->error??'Erreur création facture'];
    $nb = 0;
    foreach (($args['lines']??[]) as $l) {
        $fkp = 0;
        if (!empty($l['product_ref'])) { $pr=$db->escape($l['product_ref']); $rr=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1"); if($row=$db->fetch_object($rr)) $fkp=$row->rowid; }
        if ($f->addline($db->escape($l['description']??($l['product_ref']??'Prestation')),(float)($l['price']??0),(float)($l['qty']??1),(float)($l['tva_tx']??16),0,0,$fkp,0,'HT',0,'','0','HT')>0) $nb++;
    }
    $f->validate($user); $f->fetch($id);
    return ['success'=>true,'id'=>$id,'ref'=>$f->ref,'total_ht'=>number_format($f->total_ht,2).' MRU','total_ttc'=>number_format($f->total_ttc,2).' MRU','lignes'=>$nb,'message'=>'Facture client créée et validée'];
}

function tool_create_facture_fournisseur($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
    $fid = (int)($args['fournisseur_id']??0);
    if (!$fid && !empty($args['fournisseur_name'])) {
        $n = $db->escape($args['fournisseur_name']);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND fournisseur=1 AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1");
        if ($row = $db->fetch_object($r)) $fid = $row->rowid;
    }
    if (!$fid) return ['success'=>false,'error'=>'Fournisseur introuvable. Utilisez search_fournisseurs d\'abord.'];
    $f = new FactureFournisseur($db);
    $f->socid = $fid; $f->date = !empty($args['date'])?strtotime($args['date']):dol_now();
    $f->ref_supplier = $db->escape($args['ref_fournisseur']??'');
    $f->entity = $GLOBALS['conf']->entity;
    $id = $f->create($user);
    if ($id <= 0) return ['success'=>false,'error'=>$f->error??'Erreur création facture fournisseur'];
    $nb = 0;
    foreach (($args['lines']??[]) as $l) {
        $fkp = 0;
        if (!empty($l['product_ref'])) { $pr=$db->escape($l['product_ref']); $rr=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' AND entity=".(int)$GLOBALS['conf']->entity." LIMIT 1"); if($row=$db->fetch_object($rr)) $fkp=$row->rowid; }
        if ($f->addline($db->escape($l['description']??'Achat'),(float)($l['price']??0),(float)($l['tva_tx']??16),0,0,(float)($l['qty']??1),$fkp,0)>0) $nb++;
    }
    $f->validate($user); $f->fetch($id);
    return ['success'=>true,'id'=>$id,'ref'=>$f->ref,'total_ht'=>number_format($f->total_ht,2).' MRU','total_ttc'=>number_format($f->total_ttc,2).' MRU','lignes'=>$nb,'message'=>'Facture fournisseur créée et validée'];
}

function tool_create_payment($db, $args, $user) {
    $type = $args['type'] ?? 'client';
    $invoice_id = (int)($args['invoice_id'] ?? 0);
    $invoice_ref = $db->escape($args['invoice_ref'] ?? '');
    $entity = (int)$GLOBALS['conf']->entity;

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
            // Link to bank account if provided
            $bank_id = (int)($args['bank_account_id'] ?? 0);
            if ($bank_id > 0) {
                $p->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $bank_id, '', '');
            }
            return ['success'=>true,'payment_id'=>$pid,'facture_ref'=>$f->ref,'montant'=>number_format($amount,2).' MRU','mode'=>$payment_mode,'message'=>'Paiement enregistré avec succès'];
        }
        return ['success'=>false,'error'=>$p->error??'Erreur lors du paiement'];

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
            $bank_id = (int)($args['bank_account_id'] ?? 0);
            if ($bank_id > 0) {
                $p->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bank_id, '', '');
            }
            return ['success'=>true,'payment_id'=>$pid,'facture_ref'=>$f->ref,'montant'=>number_format($amount,2).' MRU','mode'=>$payment_mode,'message'=>'Paiement fournisseur enregistré'];
        }
        return ['success'=>false,'error'=>$p->error??'Erreur paiement fournisseur'];
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
        case 'create_product':             return tool_create_product($db, $input, $user);
        case 'create_client':              return tool_create_client($db, $input, $user);
        case 'create_fournisseur':         return tool_create_fournisseur($db, $input, $user);
        case 'create_facture_client':      return tool_create_facture_client($db, $input, $user);
        case 'create_facture_fournisseur': return tool_create_facture_fournisseur($db, $input, $user);
        case 'create_payment':             return tool_create_payment($db, $input, $user);
        case 'create_order':               return tool_create_order($db, $input, $user);
        case 'create_supplier_order':      return tool_create_supplier_order($db, $input, $user);
        case 'create_bank_transaction':    return tool_create_bank_transaction($db, $input, $user);
        case 'accounting_advice':          return tool_accounting_advice($db, $input);
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
$messages[] = ['role' => 'user', 'content' => $user_message];

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
