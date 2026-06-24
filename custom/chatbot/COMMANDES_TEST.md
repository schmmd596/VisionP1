# 🧪 Guide Complet des Commandes de Test - Tafkir IA

Ce guide fournit les commandes exactes à exécuter pour tester chaque fonctionnalité du chatbot. **Testez dans cet ordre pour éviter les dépendances.**

---

## 📊 Phase 1: Factures et Auto-Création d'Entités

### Test 1.1: Créer Facture Fournisseur (AUTO-CRÉE tout)
```
👤 Vous: "créer facture fournisseur Khaled 1000 oranges prix 100 MRU paye Bankily"

✓ Réponse attendue:
✓ Facture fourn. F2401-001 créée (1 lignes, 100000 MRU) + paiement Bankily

🔍 Vérifications:
□ Supplier "Khaled" a été créé (Tiers → Fournisseurs)
□ Product "oranges" a été créé (Produits → Produits)
□ Facture F2401-001 existe (Achats → Factures)
□ Compte bancaire Bankily a été créé (Comptabilité → Banques)
□ 2 écritures comptables créées (Comptabilité → Écritures):
   - Débit 601 (Achats) = 100000 MRU
   - Crédit 401 (Fournisseurs) = 100000 MRU
```

### Test 1.2: Créer Facture Client (AUTO-CRÉE client + produit)
```
👤 Vous: "créer facture client Ahmed 500 tomates prix 50 MRU"

✓ Réponse attendue:
✓ Facture client F2401-001 créée (1 lignes, 25000 MRU)

🔍 Vérifications:
□ Client "Ahmed" a été créé (Tiers → Clients)
□ Product "tomates" a été créé (Produits → Produits)
□ Facture F2401-001 existe (Ventes → Factures)
□ 2 écritures comptables créées:
   - Débit 411 (Clients) = 25000 MRU
   - Crédit 701 (Ventes) = 25000 MRU
```

### Test 1.3: Payer une Facture
```
👤 Vous: "payer facture F2401-001 par Bankily"

✓ Réponse attendue:
✓ Paiement F2401-001 enregistré

🔍 Vérifications:
□ Paiement est enregistré (Ventes → Paiements reçus)
□ Facture reste VALIDÉE (non payée automatiquement)
```

---

## 🏦 Phase 2: Gestion Bancaire

### Test 2.1: Lister mes Banques
```
👤 Vous: "quelles sont mes banques ?"

✓ Réponse attendue (JSON):
{
  "Bankily": "Actif",
  "Sedad": "Actif",
  "UBA": "Actif" (si créé précédemment)
}

🔍 Vérifications:
□ Au moins 3 comptes bancaires affichés
□ Format: | Nom | Solde |
```

### Test 2.2: Créer Compte Bancaire
```
👤 Vous: "créer compte bancaire UBA"

✓ Réponse attendue:
✓ Compte bancaire UBA créé

🔍 Vérifications:
□ UBA apparaît dans "mes banques"
□ Solde initial = 0
□ Compte est ACTIF (clos=0)
```

### Test 2.3: Transférer entre Banques
```
👤 Vous: "transférer 5000 MRU de Bankily à Sedad"

✓ Réponse attendue:
✓ Transfert 5000 MRU Bankily → Sedad

🔍 Vérifications:
□ 2 transactions bancaires créées:
   - Débit Bankily = 5000
   - Crédit Sedad = 5000
```

---

## 📈 Phase 3: Comptabilité Complète

### Test 3.1: Afficher Bilan (CRITICAL TEST)
```
👤 Vous: "affiche mon bilan"

✓ Réponse attendue:
Actif: 125000 MRU
Passif: 125000 MRU
Équilibre: ✓

Explication des chiffres (après tests 1.1 et 1.2):
- Actif (Classes 1-4):
  □ 411 (Clients) = 25000 MRU (de la facture client)
  □ 601 (Achats) = 100000 MRU (de la facture fournisseur)
  TOTAL: 125000 MRU

- Passif (Classes 5-7):
  □ 401 (Fournisseurs) = 100000 MRU
  □ 701 (Ventes) = 25000 MRU
  TOTAL: 125000 MRU

⚠️ Si "Aucune réponse reçue" → Database issue:
  1. Vérifiez compteur: SELECT COUNT(*) FROM llx_accounting_bookkeeping;
  2. Doit montrer 4 entrées (2 par facture)
  3. Si 0 → Les factures n'ont pas créé d'écritures comptables
```

### Test 3.2: Afficher Compte de Résultat
```
👤 Vous: "résultat comptable"

✓ Réponse attendue:
Produits: 25000 MRU (compte 701)
Charges: 100000 MRU (compte 601)
Résultat: -75000 MRU (déficit)
IS 25%: 0 MRU (pas d'impôt sur déficit)
Net: -75000 MRU

Note: Le résultat est négatif car charges > produits (test déséquilibré volontairement)
```

### Test 3.3: Solde d'un Compte
```
👤 Vous: "solde compte 411"

✓ Réponse attendue:
Compte 411 (Clients): 25000 MRU

🔍 Vérifications:
□ Correspond à la facture client créée
□ Signe correct (positif = débiteur)
```

### Test 3.4: Créer Écriture Comptable Manuelle
```
👤 Vous: "créer écriture comptable journal AC: Débit 512 (Banque) 10000 MRU, Crédit 411 (Clients) 10000 MRU"

✓ Réponse attendue:
✓ Écriture comptable créée

🔍 Vérifications:
□ Écriture existe dans Comptabilité → Écritures
□ Totaux debit = credit = 10000
```

---

## 📦 Phase 4: Stock et Analyse

### Test 4.1: Analyser Stock
```
👤 Vous: "analyse stock"

✓ Réponse attendue:
Top 10 produits bas stock:
- oranges: 1000 pcs (seuil 500) → ⚠️ OK
- tomates: 500 pcs (seuil 100) → ⚠️ OK

Top 10 produits lents:
- [Vide si aucun produit n'a d'historique]

Valeur stock total: X MRU

🔍 Vérifications:
□ oranges et tomates apparaissent
□ Comparaison avec seuil d'alerte
```

### Test 4.2: Recevoir Stock (Entrée)
```
👤 Vous: "recevoir 100 oranges au magasin"

✓ Réponse attendue:
✓ Mouvement stock enregistré: +100 oranges

🔍 Vérifications:
□ Product "oranges" stock = 1100 (1000 + 100)
□ Mouvement visible: Produits → Stock → Mouvements
```

### Test 4.3: Livrer Stock (Sortie)
```
👤 Vous: "livrer 50 tomates"

✓ Réponse attendue:
✓ Mouvement stock enregistré: -50 tomates

🔍 Vérifications:
□ Product "tomates" stock = 450 (500 - 50)
□ Mouvement visible: Produits → Stock → Mouvements
```

---

## 🎯 Phase 5: Prédictions et Recommandations

### Test 5.1: Prédire Besoins Stock
```
👤 Vous: "prédire stock 3 mois"

✓ Réponse attendue:
Prévision stock à 3 mois:
- oranges: 1100 pcs disponible, ventes estimées 30/mois → 6 mois stock ✓
- tomates: 450 pcs disponible, ventes estimées 20/mois → 3 mois stock ⚠️
  Recommandation: Commander 100 pcs supplémentaires

🔍 Vérifications:
□ Estimation basée sur moyennes 12 derniers mois (ou vides si pas d'historique)
□ Recommandations logiques
```

### Test 5.2: Conseils Comptables
```
👤 Vous: "quel est mon taux TVA collectée ?"

✓ Réponse attendue:
TVA collectée (compte 4457): X MRU (18% si pas d'autres factures)
TVA déductible (compte 4456): Y MRU
TVA nette à payer/récupérer: (Y - X) MRU

🔍 Vérifications:
□ Utilise le Plan Comptable Mauritanien
□ Taux TVA Mauritanie = 16% (par défaut)
```

---

## 🖼️ Phase 6: Vision et OCR (Optionnel - si image disponible)

### Test 6.1: Analyser Image Facture
```
👤 Vous: [Uploader une image de facture]

✓ Réponse attendue:
{
  "fournisseur": "Nom du fournisseur",
  "montant": 50000,
  "date": "2026-04-20",
  "ref": "INV-001",
  "lignes": [
    {"produit": "...", "qty": 100, "prix": 500}
  ]
}

🔍 Vérifications:
□ Montant extrait correctement
□ Fournisseur identifié
□ Lignes parsées
```

### Test 6.2: Auto-créer depuis Image
```
👤 Vous: "créer facture depuis image" [avec image de facture]

✓ Réponse attendue:
✓ Facture fournisseur créée depuis image: F2401-XXX

🔍 Vérifications:
□ Facture créée avec les données de l'image
□ Fournisseur auto-créé si absent
□ Produits auto-créés si absents
□ Écritures comptables créées
```

---

## 🏆 Résumé des Résultats Attendus

| Feature | Status | Commande Clé | Vérification |
|---------|--------|---|---|
| ✅ Auto-créer entités | OK | "créer facture fournisseur..." | Client/Product/Supplier créés |
| ✅ Factures fournisseurs | OK | "créer facture fournisseur..." | Invoice + Accounting entries |
| ✅ Factures clients | OK | "créer facture client..." | Invoice + Accounting entries |
| ✅ Paiements | OK | "payer facture..." | Payment recorded |
| ✅ Banques | OK | "mes banques" | List + Create + Transfer |
| ✅ **Bilan** | 🔑 CRÍTICO | "affiche mon bilan" | Actif = Passif ✓ |
| ✅ Compte de résultat | OK | "résultat comptable" | Produits - Charges |
| ✅ Stock | OK | "analyse stock" | Top products + Recommendations |
| ✅ Mouvements | OK | "recevoir/livrer..." | Stock updated |
| ✅ Prédictions | OK | "prédire stock..." | Forecasts + Recommendations |

---

## ⚡ Quick Debug Checklist

Si une fonctionnalité ne marche pas:

### Bilan Timeout
```bash
# Check database
mysql> SELECT COUNT(*) FROM llx_accounting_bookkeeping;
# Doit montrer: 4, 6, 8, etc. (nombre pair d'écritures)

# Check entries for your invoices
mysql> SELECT * FROM llx_accounting_bookkeeping ORDER BY doc_date DESC LIMIT 10;

# Check bookkeeping class
mysql> SHOW INDEXES FROM llx_accounting_bookkeeping;
# Doit avoir index sur numero_compte et entity
```

### Entités Non Créées
```bash
# Check logs
tail -f /var/log/apache2/error.log

# Or check DB directly
mysql> SELECT * FROM llx_societe WHERE nom LIKE '%Khaled%';
mysql> SELECT * FROM llx_product WHERE ref = 'oranges';
```

### Factures Sans Écritures
```bash
# Check if facture was validated
mysql> SELECT ref, status FROM llx_facture WHERE ref = 'F2401-001';
# status doit être 1 (validée)

# Check if auto_create_accounting_entries was called
grep "Bookkeeping debit entry" /var/log/apache2/error.log
```

---

**💾 Dernière mise à jour:** 2026-04-20  
**👤 Créateur:** Claude Code Assistant  
**🧪 Statut:** Ready for testing
