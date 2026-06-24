# 📚 GUIDE COMPLET - Maîtriser Tafkir IA Chatbot

## 🎯 Objectif
Chatbot ERP **ultra-performant** avec réponses 1 ligne max. Auto-crée clients, fournisseurs, produits, banques.

---

## 📊 1. FACTURES FOURNISSEURS (Achat)

### ✅ Commande minimale
```
"créer facture fournisseur khaled 1000 oranges prix 100 MRU paye par Bankily"
```

**Résultat attendu** (1 ligne):
```
✓ Facture fourn. F2401-001 créée (1 lignes, 100000 MRU) + paiement Bankily
```

### Fichiers impliqués
- **Classe** : `fourn/class/fournisseur.facture.class.php`
  - Fonction `create()` ligne 397
  - Fonction `addline()` ligne 2121
  
- **List** : `fourn/facture/list.php`
  - Affiche toutes factures fournisseurs

### Paramètres reconnus
```php
[
    'fournisseur_name' => 'Khaled',  // Auto-crée si absent
    'lines' => [
        [
            'product_ref' => 'oranges',  // Auto-crée produit
            'description' => 'Oranges frais',
            'qty' => 1000,
            'price' => 100,
            'tva_tx' => 16  // Défaut Mauritanie
        ]
    ],
    'date' => '2024-04-20',  // Défaut : aujourd'hui
    'bank_name' => 'Bankily'  // Auto-crée si absent, auto-paie
]
```

### 🔧 Comment ça marche
1. Chatbot parse : `khaled` (fournisseur) + `1000 oranges` (qty product) + `100 MRU` (prix)
2. **Recherche fournisseur** dans BD : `SELECT FROM societe WHERE nom LIKE '%khaled%'`
3. **Si absent** : crée avec `country_id = 141` (Mauritanie)
4. **Recherche produit** `oranges` : `SELECT FROM product WHERE ref LIKE '%oranges%'`
5. **Si absent** : crée produit auto avec prix
6. **Crée facture** `FactureFournisseur::create()`
7. **Ajoute lignes** `addline(desc, price, tva, 0, 0, qty, product_id, 0)`
8. **Valide** facture
9. **AUTO-CRÉE ÉCRITURES COMPTABLES** (Débit 601 Achats, Crédit 401 Fournisseurs) ✨
10. **Paie automatiquement** via Bankily (ou crée compte si absent)

---

## 💼 2. FACTURES CLIENTS (Vente)

### ✅ Commande minimale
```
"créer facture client Ahmed 500 tomates 50 MRU"
```

### Paramètres
```php
[
    'client_name' => 'Ahmed',
    'lines' => [[
        'product_ref' => 'tomates',
        'qty' => 500,
        'price' => 50
    ]],
    'payment_condition' => 30  // Optionnel : 30j, 60j, etc.
]
```

### Fichiers
- **Classe** : `compta/facture/class/facture.class.php`
  - Fonction `create()` ligne ~400
  - Fonction `addline()` ligne ~2000

- **List** : `compta/facture/list.php`

---

## 🏦 3. BANQUES & PAIEMENTS

### ✅ Lister mes banques
```
"quelles sont mes banques ?"
```

**Réponse** :
```
| ID | Nom | Solde |
| 1  | Bankily | 150000 MRU |
| 2  | Sedad | 80000 MRU |
```

### ✅ Créer compte bancaire
```
"créer compte bancaire UBA"
```

### ✅ Payer une facture
```
"payer facture F2401-001 par Bankily"
```

### Fichiers impliqués
- **Classe** : `compta/bank/class/account.class.php`
  - Fonction `create()` ligne ~100
  - Fonction `addline()` pour transactions

- **List** : `compta/bank/list.php`
  - Affiche tous comptes actifs (clos=0)

- **Transactions** : `compta/bank/bankentries_list.php`

### Code SQL pour lister banques
```sql
SELECT rowid, label, solde 
FROM llx_bank_account 
WHERE entity IN (0, conf->entity)
ORDER BY label
```

---

## 📈 4. BILANS & GRANDS LIVRES

### ✅ Afficher bilan
```
"affiche mon bilan"
```

**Réponse** :
```
Actif : 500000 MRU
Passif : 300000 MRU
Résultat : +200000 MRU ✓
```

### ✅ Afficher compte de résultat
```
"résultat année 2024"
```

### ✅ Voir solde compte
```
"solde compte 512 (banque)"
```

### Fichiers impliqués
- **Classe** : `accountancy/class/bookkeeping.class.php`
  - Fonction `createFromValues()` ligne ~150
  - Fonction `fetchAllBalance()` pour soldes

- **List** : `accountancy/bookkeeping/listbyaccount.php`
  - Grand livre par compte

### Code SQL pour bilan
```sql
SELECT numero_compte, 
       SUM(debit) as total_db,
       SUM(credit) as total_cr,
       (SUM(debit) - SUM(credit)) as solde
FROM llx_accounting_bookkeeping
WHERE entity = conf->entity
GROUP BY numero_compte
```

---

## 📦 5. STOCK & PRODUITS

### ✅ Analyser stock
```
"analyse stock"
```

### ✅ Ajouter stock
```
"recevoir 100 oranges au magasin"
```

### Fichiers
- **Classe** : `product/stock/class/mouvementstock.class.php`
  - Fonction `_create()` pour mouvements
  - Fonction `livraison()` pour sorties
  - Fonction `reception()` pour entrées

- **List** : `product/reassort.php`

---

## 🔧 6. AJUSTEMENTS POUR MAÎTRISE COMPLÈTE

### ❌ Problème #1 : get_bank_accounts retourne 0
**Cause** : Filtre entity trop strict
**Solution** : Utiliser `entity IN (0, conf->entity)`
**Fichier** : `custom/chatbot/ajax/chat.php` ligne 928

### ❌ Problème #2 : Réponses trop longues
**Cause** : System prompt pas appliqué
**Solution** : Forcer réponses JSON avec clé `✓` pour succès
**Fichier** : `custom/chatbot/ajax/chat.php` ligne 426-550

### ❌ Problème #3 : Questions inutiles lors création
**Cause** : Fonction pose questions au lieu de créer
**Solution** : Auto-créer ce qui manque, jamais poser questions
**Fichier** : `custom/chatbot/ajax/chat.php` fonctions create_*

### ❌ Problème #4 : Bilan vide (accounting_bookkeeping table empty)
**Cause** : Factures créées/validées mais pas d'écritures comptables enregistrées
**Solution** : Fonction `auto_create_accounting_entries_for_invoice()` appelée après validation
  - Crée 2 entrées par facture : Débit + Crédit
  - Client invoice: Débit 411 (Clients), Crédit 701 (Ventes)
  - Supplier invoice: Débit 601 (Achats), Crédit 401 (Fournisseurs)
**Fichier** : `custom/chatbot/ajax/chat.php` ligne 1286-1340
**Résultat** : Bilan maintenant rapide et exact ✓

---

## 📋 TEST CHECKLIST

Testez ces commandes pour valider la maîtrise :

- [ ] `"mes banques"` → liste 4 comptes
- [ ] `"créer facture fourn Ali 50 stylos 2 MRU paye Sedad"` → 1 ligne succès
- [ ] `"créer facture client Bob 100 cahiers 5 MRU"` → 1 ligne succès  
- [ ] `"payer facture F2401-001 par Bankily"` → succès, crée banque si absent
- [ ] `"affiche mon bilan"` → bilan structuré
- [ ] `"résultat"` → compte résultat année

Si tous ✓ → **Chatbot maîtrisé** 🚀

---

## 🎓 Pour aller plus loin

### Apprendre les classes Dolibarr
1. **Societe** : `societe/class/societe.class.php` - Tiers (client/fournisseur)
2. **Facture** : `compta/facture/class/facture.class.php` - Factures clients
3. **FactureFournisseur** : `fourn/class/fournisseur.facture.class.php` - Factures achat
4. **Product** : `product/class/product.class.php` - Produits
5. **Account** : `compta/bank/class/account.class.php` - Comptes bancaires
6. **BookKeeping** : `accountancy/class/bookkeeping.class.php` - Écritures comptables

### Exemples SQL utiles

**Trouver un tiers par nom** :
```sql
SELECT rowid, nom, code_fournisseur FROM llx_societe 
WHERE nom LIKE '%nom%' LIMIT 1
```

**Trouver un produit** :
```sql
SELECT rowid, ref, label, price FROM llx_product 
WHERE ref = 'REF' OR label LIKE '%nom%'
```

**Solde banque** :
```sql
SELECT SUM(amount) as solde FROM llx_bank 
WHERE fk_account = ACCOUNT_ID
```

**Grand livre d'un compte** :
```sql
SELECT * FROM llx_accounting_bookkeeping 
WHERE numero_compte = '512'
ORDER BY doc_date
```

---

## 💡 TIPS

✅ **Toujours** utiliser noms simples (pas d'accents spéciaux)
✅ **Toujours** préciser qty + prix pour produits
✅ **Jamais** mettre entity dans les queries - le chatbot le gère
✅ **Toujours** tester d'abord avec les banques existantes
✅ **Données minimales** : client/fourn + 1 ligne = facture créée

---

**Dernière mise à jour** : 2026-04-20
**Chatbot version** : Tafkir IA v2.0+ avec maîtrise complète
