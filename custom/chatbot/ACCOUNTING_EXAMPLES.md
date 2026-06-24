# 💡 Exemples Pratiques - Questions Comptables au Chatbot

## 📊 1. QUESTIONS SUR LE BILAN

### Q1: Bilan Complet
**Utilisateur**: "Fais-moi un bilan pour l'année 2024"
**Réponse du Chatbot**:
```
BILAN 2024 (01/01/2024 - 31/12/2024)

ACTIF (Classe 1)
├─ Immobilisations corporelles (101): 500,000.00 MRU
├─ Immobilisations incorporelles (102): 250,000.00 MRU
├─ Équipements (103): 150,000.00 MRU
└─ TOTAL ACTIF: 900,000.00 MRU

PASSIF (Classe 2)
├─ Dettes fournisseurs (401): 200,000.00 MRU
├─ Dettes bancaires (402): 150,000.00 MRU
└─ TOTAL PASSIF: 350,000.00 MRU

CAPITAUX PROPRES (Classe 3)
├─ Capital social (301): 400,000.00 MRU
├─ Réserves (302): 100,000.00 MRU
├─ Résultat (306): 50,000.00 MRU
└─ TOTAL CAPITAUX: 550,000.00 MRU

═══════════════════════════════════════
Vérification: Actif = Passif + Capitaux
900,000 = 350,000 + 550,000 ✓ Équilibre
```

### Q2: Bilan Partiel par Classe
**Utilisateur**: "Quel est mon bilan actif seulement?"
**Réponse du Chatbot**:
```
ACTIF TOTAL 2024: 900,000.00 MRU

Détail par compte:
- Immobilisations: 750,000.00 MRU
- Actif circulant: 150,000.00 MRU
```

### Q3: Comparaison Périodes
**Utilisateur**: "Bilan janvier vs février"
**Réponse du Chatbot**:
```
Compte      │ Janvier    │ Février    │ Variation
────────────┼────────────┼────────────┼─────────
511 (CA)    │100,000.00  │120,000.00  │+20,000.00
512 (Banque)│ 50,000.00  │ 45,000.00  │ -5,000.00
411 (Client)│150,000.00  │170,000.00  │+20,000.00
```

---

## 💳 2. QUESTIONS SUR LES SOLDES DE COMPTES

### Q4: Solde Simple
**Utilisateur**: "Solde du compte 512?"
**Réponse du Chatbot**:
```
COMPTE 512 (Banque) - Période: 01/01/2024-31/12/2024

Débits:      500,000.00 MRU
Crédits:     450,000.00 MRU
─────────────────────────
SOLDE NET:    50,000.00 MRU (Débiteur)
```

### Q5: Solde Client
**Utilisateur**: "Quel est le solde de mon compte clients 411?"
**Réponse du Chatbot**:
```
COMPTE 411 (Clients) - Exercice 2024

Débits:      1,500,000.00 MRU
Crédits:     1,350,000.00 MRU
─────────────────────────
SOLDE:         150,000.00 MRU (Clients doivent)
```

### Q6: Solde Fournisseur
**Utilisateur**: "Solde compte 401 (fournisseurs)?"
**Réponse du Chatbot**:
```
COMPTE 401 (Fournisseurs) - Janvier 2024

Débits:       500,000.00 MRU
Crédits:      600,000.00 MRU
─────────────────────────
SOLDE:       -100,000.00 MRU (Créditeur - nous devons)
```

### Q7: Solde Précis sur Période
**Utilisateur**: "Solde du compte 512 entre le 15/01 et 31/01?"
**Réponse du Chatbot**:
```
COMPTE 512 - 15/01/2024 à 31/01/2024

Débits:       25,000.00 MRU
Crédits:      18,000.00 MRU
─────────────────────────
SOLDE:         7,000.00 MRU
```

---

## 🏦 3. QUESTIONS SUR LES TRANSACTIONS BANCAIRES

### Q8: Transactions Récentes
**Utilisateur**: "Mouvements banque ce mois-ci"
**Réponse du Chatbot**:
```
TRANSACTIONS BANCAIRES - AVRIL 2024

Date       │ Libellé              │ Montant     │ Type
───────────┼──────────────────────┼─────────────┼──────
2024-04-25 │ Dépôt chèque CLIENT  │ 50,000.00   │ ➕ IN
2024-04-23 │ Virement FOURNISSEUR │ 30,000.00   │ ➖ OUT
2024-04-20 │ Paiement Facture EDF │  5,000.00   │ ➖ OUT
2024-04-18 │ Virement SARL XYZ    │ 15,000.00   │ ➕ IN
2024-04-15 │ Retrait guichet      │ 10,000.00   │ ➖ OUT

═══════════════════════════════════════
RÉSUMÉ AVRIL:
Total Entrées:  65,000.00 MRU
Total Sorties:  45,000.00 MRU
────────────────────────────
BILAN NET:      20,000.00 MRU ✓
```

### Q9: Transactions Filtrées par Période
**Utilisateur**: "Transactions du trimestre Q1 2024"
**Réponse du Chatbot**:
```
TRANSACTIONS BANQUE - Q1 2024 (01/01-31/03)

Janvier:     50 transactions | In: 500,000 MRU | Out: 450,000 MRU
Février:     48 transactions | In: 480,000 MRU | Out: 470,000 MRU
Mars:        52 transactions | In: 520,000 MRU | Out: 500,000 MRU
─────────────────────────────────────────────────────────────────
TOTAL Q1:   150 transactions | In: 1,500,000 | Out: 1,420,000
NET:                           80,000.00 MRU
```

### Q10: Transactions par Type
**Utilisateur**: "Combien de dépôts et retraits en janvier?"
**Réponse du Chatbot**:
```
TRANSACTIONS JANVIER 2024

DÉPÔTS (Entrées):    25 transactions | Total: 500,000.00 MRU
RETRAITS (Sorties):  25 transactions | Total: 450,000.00 MRU

Types de sorties:
- Chèques: 10 | 200,000.00 MRU
- Virements: 12 | 220,000.00 MRU
- Espèces: 3 | 30,000.00 MRU
```

---

## 📋 4. QUESTIONS SUR LE RÉSUMÉ COMPTABLE

### Q11: Résumé par Journal
**Utilisateur**: "Résumé comptable année 2024"
**Réponse du Chatbot**:
```
RÉSUMÉ COMPTABLE 2024 - PAR JOURNAL

JOURNAL VT (Ventes - Clients)
├─ Écritures: 150
├─ Débits: 1,500,000.00 MRU
├─ Crédits: 1,350,000.00 MRU
└─ Balance: 150,000.00 MRU

JOURNAL AC (Achats - Fournisseurs)
├─ Écritures: 120
├─ Débits: 1,200,000.00 MRU
├─ Crédits: 1,250,000.00 MRU
└─ Balance: -50,000.00 MRU

JOURNAL BQ (Banque)
├─ Écritures: 200
├─ Débits: 800,000.00 MRU
├─ Crédits: 750,000.00 MRU
└─ Balance: 50,000.00 MRU

JOURNAL OD (Opérations Diverses)
├─ Écritures: 50
├─ Débits: 100,000.00 MRU
├─ Crédits: 100,000.00 MRU
└─ Balance: 0.00 MRU

═════════════════════════════════════
TOTAL GÉNÉRAL:
├─ Total Écritures: 520
├─ Total Débits: 3,600,000.00 MRU
├─ Total Crédits: 3,450,000.00 MRU
└─ BALANCE GÉNÉRALE: 150,000.00 MRU
```

### Q12: Mouvements d'un Journal Spécifique
**Utilisateur**: "Détail du journal BQ (banque) en février"
**Réponse du Chatbot**:
```
JOURNAL BQ - FÉVRIER 2024

50 écritures comptables:
- Débits totaux: 50,000.00 MRU
- Crédits totaux: 45,000.00 MRU
- Solde: 5,000.00 MRU

Principaux mouvements:
1. Dépôt client SARL XYZ: 25,000.00 MRU
2. Paiement fournisseur ABC: 20,000.00 MRU
3. Frais bancaires: 1,000.00 MRU
...
```

---

## 🎯 5. QUESTIONS COMBINÉES / ANALYTIQUES

### Q13: Analyse Complète
**Utilisateur**: "Analyse complète de ma situation financière"
**Réponse du Chatbot**:
```
ANALYSE FINANCIÈRE - AVRIL 2024

🔴 BILAN
─────────────────────────
Actif:           900,000.00 MRU
Passif:          350,000.00 MRU
Capitaux:        550,000.00 MRU
✓ Équilibré

🟢 LIQUIDITÉ (Banque)
─────────────────────────
Solde Banque:     50,000.00 MRU
Flux Avril:      +20,000.00 MRU
Tendance:         ↗ Positive

🟡 CRÉANCES
─────────────────────────
Clients (411):   150,000.00 MRU
Trend:           Stable

⚪ DETTES
─────────────────────────
Fournisseurs:    100,000.00 MRU
Banques:          50,000.00 MRU
Total:           150,000.00 MRU

📊 RATIOS
─────────────────────────
Liquidité: 50,000 / 150,000 = 0.33 (Faible)
Solvabilité: 550,000 / 350,000 = 1.57 (Bon)
```

### Q14: Anomalies Détectées
**Utilisateur**: "Y a-t-il des anomalies dans mes comptes?"
**Réponse du Chatbot**:
```
ANALYSE D'ANOMALIES

⚠️ ALERTES:
1. Compte 512 (Banque): Solde bas (50,000 MRU)
   → Action: Monitorer les sorties
   
2. Compte 411 (Clients): 150,000 MRU à recouvrer
   → Action: Relancer les clients
   
3. Journal VT (Ventes): Plus de débits que crédits
   → Normal pour compte client

✓ VÉRIFICATIONS:
- Balance générale équilibrée
- Tous les journaux vérifiés
- Aucun compte anormal
```

---

## 🔍 6. QUESTIONS SPÉCIFIQUES PAR SECTEUR

### Q15: Gestion des Stocks
**Utilisateur**: "Lien entre stock et bilan?"
**Réponse du Chatbot**:
```
Stock en comptabilité (Compte 301 - Stocks):

Valeur au bilan: 150,000.00 MRU
Mouvements avril: -5,000.00 MRU
Valeur actuelle: 145,000.00 MRU

Stock physique: À vérifier en inventaire
```

### Q16: TVA et Impôts
**Utilisateur**: "TVA collectée vs TVA déductible?"
**Réponse du Chatbot**:
```
TVA - AVRIL 2024 (Mauritanie: 16%)

TVA COLLECTÉE (Ventes):
Chiffre d'affaires HT: 500,000.00 MRU
TVA 16%: 80,000.00 MRU

TVA DÉDUCTIBLE (Achats):
Achats HT: 300,000.00 MRU
TVA 16%: 48,000.00 MRU

TVA À PAYER: 80,000 - 48,000 = 32,000.00 MRU
```

---

## 📈 7. QUESTIONS POUR PRENDRE DES DÉCISIONS

### Q17: "Puis-je augmenter mes dépenses?"
**Réponse du Chatbot**:
```
Analyse de votre capacité de dépense:

Liquidité actuelle: 50,000.00 MRU
Cash flow mensuel: +20,000.00 MRU
Dettes mensuelles: 15,000.00 MRU

Capacité: 50,000 - (15,000 × 3 mois) = 5,000 MRU
Recommandation: ⚠️ Limiter les dépenses supplémentaires
```

### Q18: "Quelle est ma marge commerciale?"
**Réponse du Chatbot**:
```
MARGE COMMERCIALE - ANNÉE 2024

Chiffre d'affaires: 1,500,000.00 MRU
Coût des ventes: 900,000.00 MRU
─────────────────────────────────
Marge brute: 600,000.00 MRU
Taux: 40%

Charges d'exploitation: 300,000.00 MRU
─────────────────────────────────
Résultat net: 300,000.00 MRU
Taux net: 20%
```

---

## ✅ RÉSUMÉ DES CAPACITÉS

| Catégorie | Questions Possibles | Exemples |
|-----------|-------------------|----------|
| **Bilan** | Bilan complet, actif/passif, comparaison | "Bilan 2024", "Actif total?" |
| **Soldes** | Compte spécifique, période, comparaison | "Solde 512?", "Compte 411 en janvier?" |
| **Banque** | Mouvements, périodes, résumé | "Transactions ce mois?", "Entrées/sorties?" |
| **Résumé** | Par journal, global, analytique | "Résumé par journal", "Total débits/crédits?" |
| **Analyse** | Anomalies, ratios, tendances | "Anomalies?", "Liquidité?", "Solvabilité?" |

---

**Version**: 1.0  
**Créé**: 2026-04-28  
**Langue**: Français (aussi disponible en Arabe et English)
