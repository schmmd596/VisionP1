# 🚀 COMMENT UTILISER LES NOUVELLES FONCTIONNALITÉS COMPTABLES

## ⏱️ TEMPS TOTAL: 5 MINUTES

---

## ✅ ÉTAPE 1: OUVRIR LE CHATBOT (30 secondes)

1. **Allez sur une page Dolibarr** (n'importe laquelle)
2. **Repérez le widget Tafkir IA** en bas à droite (💬)
3. **Cliquez dessus** pour ouvrir le chat

✓ Le chatbot s'ouvre normalement

---

## ✅ ÉTAPE 2: TESTER LES BILANS (1 minute)

### Test 1: Bilan Complet
```
Tapez: "Quel est notre bilan?"
ou
Tapez: "Bilan 2024"

Vous verrez:
├─ ACTIF (classe 1): montant total
├─ PASSIF (classe 2): montant total
├─ CAPITAUX (classe 3): montant total
└─ VÉRIFICATION: Actif = Passif + Capitaux
```

### Test 2: Bilan Partiel
```
Tapez: "Bilan actif seulement"

Vous verrez:
- Tous les comptes actif avec leurs soldes
- Total actif
```

---

## ✅ ÉTAPE 3: TESTER LES SOLDES (1 minute)

### Test 1: Compte Bancaire
```
Tapez: "Quel est le solde du compte 512?"
ou simplement
Tapez: "Solde 512"

Vous verrez:
- Débits: X.XX MRU
- Crédits: Y.YY MRU
- SOLDE NET: Z.ZZ MRU
```

### Test 2: Compte Client
```
Tapez: "Solde compte 411"

Vous verrez:
- Montant que vos clients vous doivent
```

### Test 3: Compte Fournisseur
```
Tapez: "Solde 401"

Vous verrez:
- Montant que vous devez à vos fournisseurs
```

---

## ✅ ÉTAPE 4: TESTER LES TRANSACTIONS (1 minute)

### Test 1: Transactions Récentes
```
Tapez: "Mouvements banque ce mois"
ou
Tapez: "Transactions"

Vous verrez:
| Date | Libellé | Montant | Type |
|------|---------|---------|------|
| ... | ... | ... | IN/OUT |

+ Résumé:
  - Total Entrées: X MRU
  - Total Sorties: Y MRU
  - BILAN NET: Z MRU
```

### Test 2: Période Spécifique
```
Tapez: "Mouvements Q1 2024"
ou
Tapez: "Transactions janvier"

Vous verrez:
- Transactions de la période demandée
```

---

## ✅ ÉTAPE 5: TESTER LE RÉSUMÉ (1 minute)

### Résumé Complet
```
Tapez: "Résumé comptable"

Vous verrez:
JOURNAL VT (Ventes):
- Écritures: 150
- Débits: 1,500,000 MRU
- Crédits: 1,350,000 MRU

JOURNAL AC (Achats):
- Écritures: 120
- Débits: 1,200,000 MRU
- Crédits: 1,250,000 MRU

JOURNAL BQ (Banque):
...

TOTAL GÉNÉRAL:
- Total Débits: X MRU
- Total Crédits: Y MRU
- BALANCE: Z MRU
```

---

## 🎯 AUTRES QUESTIONS POSSIBLES

### 💡 Questions Simples
```
"Bilan?"
"Solde 512"
"Mouvements"
"Résumé"
```

### 💡 Questions Spécifiques par Période
```
"Bilan janvier"
"Solde 411 en février"
"Transactions Q2"
"Résumé du trimestre"
```

### 💡 Questions Analytiques
```
"Comparaison janvier/février"
"Anomalies?"
"Liquidité?"
"Total débits/crédits"
```

### 💡 Questions Détaillées
```
"Détail du compte 512"
"Mouvements banque filtrés par date"
"Toutes les transactions de plus de 100k"
```

---

## 🔍 CE QUE VOUS VERREZ

### Exemple de Réponse - Bilan
```
BILAN AVRIL 2024

ACTIF (Classe 1)
├─ Immobilisations: 500,000.00 MRU
├─ Stocks: 150,000.00 MRU
├─ Clients: 200,000.00 MRU
└─ Banque: 50,000.00 MRU
═════════════════════════════════════
TOTAL ACTIF: 900,000.00 MRU

PASSIF (Classe 2)
├─ Fournisseurs: 200,000.00 MRU
├─ Dettes Banque: 150,000.00 MRU
═════════════════════════════════════
TOTAL PASSIF: 350,000.00 MRU

CAPITAUX (Classe 3)
├─ Capital: 400,000.00 MRU
├─ Résultat: 150,000.00 MRU
═════════════════════════════════════
TOTAL CAPITAUX: 550,000.00 MRU

✓ ÉQUILIBRE: 900,000 = 350,000 + 550,000
```

### Exemple de Réponse - Transactions
```
TRANSACTIONS BANQUE - AVRIL 2024

Date       │ Libellé           │ Montant    │ Type
───────────┼───────────────────┼────────────┼─────
2024-04-25 │ Dépôt chèque      │ 50,000.00  │ ➕
2024-04-20 │ Paiement EDF      │  5,000.00  │ ➖
2024-04-15 │ Virement fourniss │ 30,000.00  │ ➖

RÉSUMÉ:
Total Entrées: 50,000.00 MRU
Total Sorties: 35,000.00 MRU
─────────────────────────────
BILAN NET: 15,000.00 MRU ✓
```

---

## ⚡ SI ERREUR 429

Si vous voyez: ⚠ **Erreur API (429)**

### Solution Rapide
```
1. Rafraîchissez le navigateur (F5 ou Ctrl+F5)
2. Attendez 2 secondes
3. Posez la question à nouveau
4. Ça devrait fonctionner ✓
```

---

## 🎓 CONSEILS D'UTILISATION

### ✅ Bonnes Pratiques
```
✓ Soyez précis: "Solde 512" plutôt que "Solde"
✓ Spécifiez les périodes: "Février" ou "Q1 2024"
✓ Utilisez les numéros de compte: "411" plutôt que "clients"
✓ Demandez ce que vous voylez: "Bilan?" ou "Mouvements?"
```

### ❌ À Éviter
```
❌ Trop vague: "Donnez-moi tout"
❌ Sans période: "Transactions" (utilisez "ce mois")
❌ Termes inconnus: "Ledger" au lieu de "Écritures"
❌ Demandes impossibles: "Prédire le bilan futur"
```

---

## 📞 CAS PARTICULIERS

### "Je n'obtiens pas de réponse"
**Possible Cause**: Pas d'écritures en DB pour la période

**Solution**:
1. Vérifiez que vous avez des écritures comptables
2. Demandez une période où il y a des données
3. Vérifiez votre entité Dolibarr

### "Les chiffres ne correspondent pas"
**Possible Cause**: Mauvaise période ou mauvais filtre

**Solution**:
1. Spécifiez précisément la période
2. Demandez le détail pour vérifier
3. Vérifiez en comptabilité directement

### "Temps de réponse lent"
**Possible Cause**: Serveur surchargé ou base DB volumineuse

**Solution**:
1. Réduisez la plage de dates
2. Demandez un compte spécifique au lieu du bilan complet
3. Attendez un moment avant de re-essayer

---

## 🏁 RÉSUMÉ RAPIDE

| Action | Commande | Résultat |
|--------|----------|----------|
| **Bilan** | "Bilan?" | ✅ Actif/Passif/Capitaux |
| **Solde** | "Solde 512" | ✅ Débits/Crédits/Net |
| **Banque** | "Mouvements" | ✅ Liste transactions |
| **Résumé** | "Résumé" | ✅ Par journal |

---

## 🎉 C'EST PRÊT!

Vous pouvez maintenant:
✅ Consulter vos bilans en temps réel
✅ Vérifier les soldes de comptes
✅ Analyser les transactions bancaires
✅ Voir les résumés par journal
✅ Poser des questions complexes

**Lancez-vous! Posez une question comptable maintenant.** 🚀

---

**Pour Plus de Détails**:
- Voir: `ACCOUNTING_EXAMPLES.md` (18+ exemples)
- Voir: `ACCOUNTING_FEATURES.md` (documentation technique)
- Voir: `TEST_COMPTABILITE.md` (guide de test complet)
- Voir: `COMPTABILITE_RESUME.md` (résumé complet)

---

**Version**: 1.0
**Créé**: 2026-04-28
**Statut**: ✅ PRÊT À UTILISER
