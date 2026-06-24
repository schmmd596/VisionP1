# ⚡ Quick Reference - Commandes Rapides

Copier-coller ces commandes directement dans le chatbot pour tester rapidement.

---

## 🚀 TEST COMPLET EN 5 MINUTES

### 1️⃣ Créer une facture fournisseur (auto-crée tout)
```
créer facture fournisseur Ali 50 stylos prix 2 MRU paye Bankily
```
**Attend:** `✓ Facture fourn. [REF] créée (1 lignes, 100 MRU) + paiement Bankily`

### 2️⃣ Créer une facture client
```
créer facture client Bob 100 cahiers prix 5 MRU
```
**Attend:** `✓ Facture client [REF] créée (1 lignes, 500 MRU)`

### 3️⃣ Afficher le bilan ✨ CRITICAL TEST
```
affiche mon bilan
```
**Attend:** 
```
Actif: 600 MRU
Passif: 600 MRU
Équilibre: ✓
```

### 4️⃣ Afficher le compte de résultat
```
résultat comptable
```
**Attend:**
```
Produits: 500 MRU
Charges: 100 MRU
Résultat: 400 MRU
```

### 5️⃣ Lister les banques
```
quelles sont mes banques ?
```
**Attend:** Liste avec Bankily, et autres comptes créés

---

## 📦 GESTION DES STOCKS

### Analyser stock
```
analyse stock
```

### Recevoir marchandise
```
recevoir 100 stylos au magasin
```

### Livrer marchandise
```
livrer 50 cahiers
```

---

## 🏦 GESTION BANCAIRE

### Créer compte bancaire
```
créer compte bancaire BMCI
```

### Transférer entre comptes
```
transférer 1000 MRU de Bankily à BMCI
```

### Payer une facture
```
payer facture F2401-001 par Bankily
```

---

## 💼 GESTION DES TIERS

### Créer client manuel
```
créer client Kounta Ahmed
```

### Créer fournisseur manuel
```
créer fournisseur Mauritel
```

### Créer produit manuel
```
créer produit Riz prix 500 MRU
```

---

## 📊 ANALYSES ET RAPPORTS

### Voir solde compte
```
solde compte 411
```

### Voir grand livre
```
grand livre compte 601
```

### Prédictions stock
```
prédire stock 3 mois
```

---

## 🎯 TESTS DE VALIDATION

| Commande | Résultat Attendu | Validé? |
|----------|---|---|
| `créer facture fournisseur Khaled 100 oranges prix 50 MRU paye Bankily` | Facture + Écritures + Paiement | □ |
| `créer facture client Ahmed 50 tomates prix 100 MRU` | Facture + Écritures | □ |
| `affiche mon bilan` | Actif = Passif | □ |
| `résultat comptable` | Produits - Charges calculé | □ |
| `quelles sont mes banques ?` | Bankily + autres | □ |
| `recevoir 10 oranges` | Stock +10 | □ |
| `transférer 5000 MRU de Bankily à Sedad` | Transfert créé | □ |

---

## 🐛 EN CAS DE PROBLÈME

### Si timeout sur bilan
```bash
# Vérifier les écritures comptables
mysql> SELECT COUNT(*) FROM llx_accounting_bookkeeping;
# Doit montrer: 4+ (2 par facture)
```

### Si factures sans écritures
```bash
# Vérifier log d'erreur
tail -f /var/log/apache2/error.log | grep "Bookkeeping"
```

### Si banques ne s'affichent pas
```bash
# Vérifier la base
mysql> SELECT * FROM llx_bank_account WHERE clos = 0;
```

---

**💾 Last Updated:** 2026-04-20  
**🎯 Purpose:** Quick test of all Tafkir IA features
