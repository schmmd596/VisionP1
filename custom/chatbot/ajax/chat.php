<?php
/**
 * Chatbot IA - Backend AJAX Handler
 * Supporte : OpenRouter (sk-or-v1-...), OpenAI (sk-...), Anthropic (sk-ant-...)
 */

define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREPLUGINS', 1);

$res = 0;
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php"))   $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

if (!$user->id) { http_response_code(401); die(json_encode(['error' => 'Non authentifié'])); }
if (empty($conf->chatbot->enabled)) { http_response_code(403); die(json_encode(['error' => 'Module désactivé'])); }

$api_key = $conf->global->CHATBOT_API_KEY ?? '';
if (empty($api_key)) {
    http_response_code(503);
    die(json_encode(['error' => 'Clé API non configurée. Allez dans Configuration → Chatbot IA → Setup.']));
}

header('Content-Type: application/json; charset=utf-8');

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
// TOOLS (format OpenAI/OpenRouter)
// ============================================================
$tools = [
    ['type' => 'function', 'function' => [
        'name' => 'search_products',
        'description' => 'Recherche des produits dans Dolibarr. Retourne ref, label, prix vente, stock.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Terme de recherche (laisser vide pour tout lister)'],
            'limit'  => ['type' => 'integer', 'description' => 'Nombre max de résultats', 'default' => 10],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'search_clients',
        'description' => 'Recherche des clients dans Dolibarr.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Nom ou code client'],
            'limit'  => ['type' => 'integer', 'default' => 10],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'search_fournisseurs',
        'description' => 'Recherche des fournisseurs dans Dolibarr.',
        'parameters' => ['type' => 'object', 'properties' => [
            'search' => ['type' => 'string', 'description' => 'Nom ou code fournisseur'],
            'limit'  => ['type' => 'integer', 'default' => 10],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_invoices',
        'description' => 'Récupère les factures clients ou fournisseurs récentes.',
        'parameters' => ['type' => 'object', 'properties' => [
            'type'   => ['type' => 'string', 'enum' => ['client', 'fournisseur']],
            'status' => ['type' => 'string', 'enum' => ['all', 'draft', 'validated', 'paid'], 'default' => 'all'],
            'limit'  => ['type' => 'integer', 'default' => 10],
        ], 'required' => ['type']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'get_stats',
        'description' => 'Retourne les statistiques : CA, factures impayées, produits stock faible, etc.',
        'parameters' => ['type' => 'object', 'properties' => [
            'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month', 'year'], 'default' => 'month'],
        ]],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_product',
        'description' => 'Crée un nouveau produit dans Dolibarr.',
        'parameters' => ['type' => 'object', 'properties' => [
            'ref'         => ['type' => 'string'],
            'label'       => ['type' => 'string'],
            'price'       => ['type' => 'number'],
            'description' => ['type' => 'string'],
            'tva_tx'      => ['type' => 'number', 'default' => 20],
            'type'        => ['type' => 'integer', 'description' => '0=produit, 1=service', 'default' => 0],
            'categorie'   => ['type' => 'string'],
        ], 'required' => ['ref', 'label', 'price']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_facture_client',
        'description' => 'Crée une facture client dans Dolibarr.',
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
                'tva_tx'      => ['type' => 'number', 'default' => 20],
            ], 'required' => ['qty', 'price']]],
        ], 'required' => ['lines']],
    ]],
    ['type' => 'function', 'function' => [
        'name' => 'create_facture_fournisseur',
        'description' => 'Crée une facture fournisseur dans Dolibarr.',
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
                'tva_tx'      => ['type' => 'number', 'default' => 20],
            ], 'required' => ['qty', 'price']]],
        ], 'required' => ['lines']],
    ]],
];

// ============================================================
// SYSTEM PROMPT
// ============================================================
$system_prompt = "Tu es un assistant IA intégré dans Dolibarr ERP/CRM. Tu réponds en français (ou dans la langue de l'utilisateur).

Utilisateur connecté: ".$user->firstname." ".$user->lastname." (login: ".$user->login.")
Date/Heure: ".dol_print_date(dol_now(), 'dayhour')."

Tes capacités:
1. Consulter en temps réel produits, clients, fournisseurs, factures et statistiques
2. Créer des factures clients et fournisseurs
3. Ajouter de nouveaux produits

Règles:
- Toujours utiliser les outils pour obtenir des données réelles avant de répondre
- Pour créer une facture, chercher d'abord le client/fournisseur avec search_clients/search_fournisseurs
- Présenter les données sous forme de tableaux markdown quand pertinent
- Être précis, concis et professionnel";

// ============================================================
// TOOL EXECUTION
// ============================================================
function tool_search_products($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = $db->escape($GLOBALS['conf']->entity);
    $sql = "SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx, p.fk_product_type, p.stock, p.seuil_stock_alerte
            FROM ".MAIN_DB_PREFIX."product p
            WHERE p.entity = {$entity} AND p.tosell = 1";
    if (!empty($search)) $sql .= " AND (p.ref LIKE '%{$search}%' OR p.label LIKE '%{$search}%')";
    $sql .= " ORDER BY p.label LIMIT {$limit}";
    $res = $db->query($sql);
    $products = [];
    if ($res) {
        while ($row = $db->fetch_object($res)) {
            $products[] = [
                'id'     => $row->rowid,
                'ref'    => $row->ref,
                'nom'    => $row->label,
                'prix'   => number_format($row->price, 2).' €',
                'tva'    => $row->tva_tx.'%',
                'type'   => $row->fk_product_type == 0 ? 'Produit' : 'Service',
                'stock'  => $row->stock ?? 0,
                'alerte' => $row->seuil_stock_alerte ?? 0,
            ];
        }
    } else {
        return ['count'=>0,'products'=>[],'sql_error'=>$db->lasterror()];
    }
    return ['count'=>count($products),'products'=>$products];
}

function tool_search_clients($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = $db->escape($GLOBALS['conf']->entity);
    $sql = "SELECT s.rowid, s.nom, s.code_client, s.email, s.phone, s.town
            FROM ".MAIN_DB_PREFIX."societe s
            WHERE s.entity = {$entity} AND s.client IN (1,3)";
    if (!empty($search)) $sql .= " AND (s.nom LIKE '%{$search}%' OR s.code_client LIKE '%{$search}%')";
    $sql .= " ORDER BY s.nom LIMIT {$limit}";
    $res = $db->query($sql);
    $clients = [];
    if ($res) while ($row = $db->fetch_object($res))
        $clients[] = ['id'=>$row->rowid,'nom'=>$row->nom,'code'=>$row->code_client,'email'=>$row->email,'tel'=>$row->phone,'ville'=>$row->town];
    return ['count'=>count($clients),'clients'=>$clients];
}

function tool_search_fournisseurs($db, $args) {
    $search = $db->escape($args['search'] ?? '');
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = $db->escape($GLOBALS['conf']->entity);
    $sql = "SELECT s.rowid, s.nom, s.code_fournisseur, s.email, s.phone, s.town
            FROM ".MAIN_DB_PREFIX."societe s
            WHERE s.entity = {$entity} AND s.fournisseur = 1";
    if (!empty($search)) $sql .= " AND (s.nom LIKE '%{$search}%' OR s.code_fournisseur LIKE '%{$search}%')";
    $sql .= " ORDER BY s.nom LIMIT {$limit}";
    $res = $db->query($sql);
    $list = [];
    if ($res) while ($row = $db->fetch_object($res))
        $list[] = ['id'=>$row->rowid,'nom'=>$row->nom,'code'=>$row->code_fournisseur,'email'=>$row->email,'tel'=>$row->phone,'ville'=>$row->town];
    return ['count'=>count($list),'fournisseurs'=>$list];
}

function tool_get_invoices($db, $args) {
    $type   = $args['type'] ?? 'client';
    $limit  = min((int)($args['limit'] ?? 10), 50);
    $entity = $db->escape($GLOBALS['conf']->entity);
    if ($type === 'client') {
        $sql = "SELECT f.rowid, f.ref, f.ref_client, f.datef, f.total_ht, f.total_ttc, f.paye, f.fk_statut, s.nom
                FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=f.fk_soc
                WHERE f.entity={$entity} ORDER BY f.datef DESC LIMIT {$limit}";
    } else {
        $sql = "SELECT f.rowid, f.ref, f.ref_supplier as ref_client, f.datef, f.total_ht, f.total_ttc, f.paye, f.fk_statut, s.nom
                FROM ".MAIN_DB_PREFIX."facture_fourn f LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=f.fk_soc
                WHERE f.entity={$entity} ORDER BY f.datef DESC LIMIT {$limit}";
    }
    $res = $db->query($sql);
    $list = [];
    $sl = [0=>'Brouillon',1=>'Validée',3=>'Annulée'];
    if ($res) while ($row = $db->fetch_object($res))
        $list[] = ['id'=>$row->rowid,'ref'=>$row->ref,'client'=>$row->nom,'date'=>$row->datef,'ht'=>number_format($row->total_ht,2).' €','ttc'=>number_format($row->total_ttc,2).' €','statut'=>$row->paye==1?'Payée':($sl[$row->fk_statut]??'?')];
    return ['type'=>$type,'count'=>count($list),'factures'=>$list];
}

function tool_get_stats($db, $args) {
    $period = $args['period'] ?? 'month';
    $entity = $db->escape($GLOBALS['conf']->entity);
    switch ($period) {
        case 'today': $ds = dol_mktime(0,0,0,(int)date('m'),(int)date('d'),(int)date('Y')); break;
        case 'week':  $ds = dol_now()-7*86400; break;
        case 'year':  $ds = dol_mktime(0,0,0,1,1,(int)date('Y')); break;
        default:      $ds = dol_mktime(0,0,0,(int)date('m'),1,(int)date('Y'));
    }
    $dss = $db->idate($ds);
    $r1 = $db->fetch_object($db->query("SELECT COUNT(*) nb, SUM(total_ht) ca_ht, SUM(total_ttc) ca_ttc FROM ".MAIN_DB_PREFIX."facture WHERE entity={$entity} AND fk_statut IN(1,2) AND datef>='{$dss}'"));
    $r2 = $db->fetch_object($db->query("SELECT COUNT(*) nb, SUM(total_ttc) total FROM ".MAIN_DB_PREFIX."facture_fourn WHERE entity={$entity} AND fk_statut IN(1,2) AND datef>='{$dss}'"));
    $r3 = $db->fetch_object($db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."product WHERE entity={$entity} AND tosell=1"));
    $r4 = $db->fetch_object($db->query("SELECT COUNT(*) nb FROM ".MAIN_DB_PREFIX."societe WHERE entity={$entity} AND client IN(1,3) AND status=1"));
    $r5 = $db->fetch_object($db->query("SELECT COUNT(*) nb, SUM(total_ttc) total FROM ".MAIN_DB_PREFIX."facture WHERE entity={$entity} AND fk_statut=1 AND paye=0"));
    return [
        'periode'           => $period,
        'ca_ht'             => number_format($r1->ca_ht??0,2).' €',
        'ca_ttc'            => number_format($r1->ca_ttc??0,2).' €',
        'nb_factures_client'=> $r1->nb??0,
        'nb_factures_fourn' => $r2->nb??0,
        'achats_ttc'        => number_format($r2->total??0,2).' €',
        'impayees'          => ['nb'=>$r5->nb??0,'montant'=>number_format($r5->total??0,2).' €'],
        'nb_produits'       => $r3->nb??0,
        'nb_clients_actifs' => $r4->nb??0,
    ];
}

function tool_create_product($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
    $p = new Product($db);
    $p->ref = $db->escape($args['ref']); $p->label = $db->escape($args['label']);
    $p->description = $db->escape($args['description']??'');
    $p->price = (float)($args['price']??0); $p->tva_tx = (float)($args['tva_tx']??20);
    $p->price_ttc = $p->price*(1+$p->tva_tx/100);
    $p->type = (int)($args['type']??0); $p->status = 1; $p->status_buy = 1;
    $p->entity = $GLOBALS['conf']->entity;
    $id = $p->create($user);
    if ($id > 0) return ['success'=>true,'id'=>$id,'ref'=>$p->ref,'message'=>'Produit créé avec succès'];
    return ['success'=>false,'error'=>$p->error??'Erreur création produit'];
}

function tool_create_facture_client($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    $client_id = (int)($args['client_id']??0);
    if (!$client_id && !empty($args['client_name'])) {
        $n = $db->escape($args['client_name']);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND client IN(1,3) AND entity=".$db->escape($GLOBALS['conf']->entity)." LIMIT 1");
        if ($row = $db->fetch_object($r)) $client_id = $row->rowid;
    }
    if (!$client_id) return ['success'=>false,'error'=>'Client introuvable. Utilisez search_clients.'];
    $f = new Facture($db);
    $f->socid = $client_id; $f->type = 0;
    $f->date = !empty($args['date'])?strtotime($args['date']):dol_now();
    $f->ref_client = $db->escape($args['ref_client']??'');
    $f->entity = $GLOBALS['conf']->entity;
    $id = $f->create($user);
    if ($id <= 0) return ['success'=>false,'error'=>$f->error??'Erreur création facture'];
    $nb = 0;
    foreach (($args['lines']??[]) as $l) {
        $fkp = 0;
        if (!empty($l['product_ref'])) { $pr=$db->escape($l['product_ref']); $rr=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' AND entity=".$db->escape($GLOBALS['conf']->entity)." LIMIT 1"); if($row=$db->fetch_object($rr)) $fkp=$row->rowid; }
        if ($f->addline($db->escape($l['description']??($l['product_ref']??'Prestation')),(float)($l['price']??0),(float)($l['qty']??1),(float)($l['tva_tx']??20),0,0,$fkp,0,'HT',0,'','0','HT')>0) $nb++;
    }
    $f->validate($user); $f->fetch($id);
    return ['success'=>true,'id'=>$id,'ref'=>$f->ref,'total_ht'=>number_format($f->total_ht,2).' €','total_ttc'=>number_format($f->total_ttc,2).' €','lignes'=>$nb,'message'=>'Facture client créée et validée'];
}

function tool_create_facture_fournisseur($db, $args, $user) {
    require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
    $fid = (int)($args['fournisseur_id']??0);
    if (!$fid && !empty($args['fournisseur_name'])) {
        $n = $db->escape($args['fournisseur_name']);
        $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%{$n}%' AND fournisseur=1 AND entity=".$db->escape($GLOBALS['conf']->entity)." LIMIT 1");
        if ($row = $db->fetch_object($r)) $fid = $row->rowid;
    }
    if (!$fid) return ['success'=>false,'error'=>'Fournisseur introuvable. Utilisez search_fournisseurs.'];
    $f = new FactureFournisseur($db);
    $f->socid = $fid; $f->date = !empty($args['date'])?strtotime($args['date']):dol_now();
    $f->ref_supplier = $db->escape($args['ref_fournisseur']??'');
    $f->entity = $GLOBALS['conf']->entity;
    $id = $f->create($user);
    if ($id <= 0) return ['success'=>false,'error'=>$f->error??'Erreur création facture fournisseur'];
    $nb = 0;
    foreach (($args['lines']??[]) as $l) {
        $fkp = 0;
        if (!empty($l['product_ref'])) { $pr=$db->escape($l['product_ref']); $rr=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='{$pr}' AND entity=".$db->escape($GLOBALS['conf']->entity)." LIMIT 1"); if($row=$db->fetch_object($rr)) $fkp=$row->rowid; }
        if ($f->addline($db->escape($l['description']??'Achat'),(float)($l['price']??0),(float)($l['tva_tx']??20),0,0,(float)($l['qty']??1),$fkp,0)>0) $nb++;
    }
    $f->validate($user); $f->fetch($id);
    return ['success'=>true,'id'=>$id,'ref'=>$f->ref,'total_ht'=>number_format($f->total_ht,2).' €','total_ttc'=>number_format($f->total_ttc,2).' €','lignes'=>$nb,'message'=>'Facture fournisseur créée et validée'];
}

function execute_tool($name, $input, $db, $user) {
    switch ($name) {
        case 'search_products':            return tool_search_products($db, $input);
        case 'search_clients':             return tool_search_clients($db, $input);
        case 'search_fournisseurs':        return tool_search_fournisseurs($db, $input);
        case 'get_invoices':               return tool_get_invoices($db, $input);
        case 'get_stats':                  return tool_get_stats($db, $input);
        case 'create_product':             return tool_create_product($db, $input, $user);
        case 'create_facture_client':      return tool_create_facture_client($db, $input, $user);
        case 'create_facture_fournisseur': return tool_create_facture_fournisseur($db, $input, $user);
        default:                           return ['error' => 'Outil inconnu: '.$name];
    }
}

// ============================================================
// API CALL (OpenAI-compatible format for OpenRouter & OpenAI)
// ============================================================
function call_llm($api_url, $api_key, $model, $max_tokens, $system_prompt, $messages, $tools, $provider) {
    if ($provider === 'anthropic') {
        // Native Anthropic format
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
        // OpenAI / OpenRouter format
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
            $headers[] = 'X-Title: Dolibarr Chatbot';
        }
    }
    $ch = curl_init($api_url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_TIMEOUT=>60, CURLOPT_HTTPHEADER=>$headers]);
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
$messages[] = ['role' => 'user', 'content' => $user_message];

$final_text = '';
$max_iter = 10;

for ($iter = 0; $iter < $max_iter; $iter++) {
    $response = call_llm($api_url, $api_key, $model, $max_tokens, $system_prompt, $messages, $tools, $provider);

    if (isset($response['error'])) {
        echo json_encode(['success'=>false,'error'=>$response['error']]);
        exit;
    }

    if ($provider === 'anthropic') {
        // --- Anthropic native format ---
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

    } else {
        // --- OpenAI / OpenRouter format ---
        $choice       = $response['choices'][0] ?? [];
        $finish_reason= $choice['finish_reason'] ?? 'stop';
        $msg          = $choice['message'] ?? [];
        $messages[]   = $msg;

        if ($finish_reason === 'tool_calls') {
            $tool_calls = $msg['tool_calls'] ?? [];
            foreach ($tool_calls as $tc) {
                $fn_name  = $tc['function']['name'] ?? '';
                $fn_args  = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
                $result   = execute_tool($fn_name, $fn_args, $db, $user);
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
            continue;
        }

        $final_text = $msg['content'] ?? '';
        break;
    }
}

if (empty($final_text)) $final_text = 'Je n\'ai pas pu générer une réponse. Veuillez réessayer.';

echo json_encode(['success'=>true,'message'=>$final_text,'provider'=>$provider], JSON_UNESCAPED_UNICODE);
