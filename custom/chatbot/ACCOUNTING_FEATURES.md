# 📊 Nouvelles Fonctionnalités Comptables du Chatbot Tafkir IA

## 🎯 Résumé
Le chatbot Tafkir IA peut maintenant répondre à toutes vos questions sur les **bilans, soldes, et transactions bancaires** en temps réel.

## ✨ Nouvelles Capacités

### 1. **Bilan Complet** 📈
Le chatbot peut générer un bilan complet avec :
- ✅ Actif (classe 1)
- ✅ Passif (classe 2)  
- ✅ Capitaux propres (classe 3)
- ✅ Totaux par catégorie
- ✅ Période personnalisable

**Exemple d'utilisation :**
```
Utilisateur: "Quel est notre bilan pour 2024?"
Chatbot: Génère un bilan complet avec détails par compte
```

### 2. **Soldes de Comptes** 💰
Consultez le solde détaillé de n'importe quel compte :
- ✅ Solde net (débits - crédits)
- ✅ Total débits
- ✅ Total crédits
- ✅ Période flexible

**Exemple :**
```
Utilisateur: "Quel est le solde du compte 512 (Banque)?"
Chatbot: Affiche débits, crédits, et solde net du compte
```

### 3. **Transactions Bancaires** 🏦
Récupérez facilement les mouvements bancaires :
- ✅ Dépôts et retraits
- ✅ Chèques, virements, cartes
- ✅ Filtre par compte/période
- ✅ Totaux entrées/sorties
- ✅ Bilan net par période

**Exemple :**
```
Utilisateur: "Montre-moi les transactions du mois dernier"
Chatbot: Affiche tous les mouvements avec résumé (entrées/sorties)
```

### 4. **Résumé Comptable** 📋
Vue globale de tous les mouvements comptables :
- ✅ Mouvements par journal (VT, AC, BQ, OD)
- ✅ Total débits/crédits par journal
- ✅ Balance générale
- ✅ Nombre de transactions

**Exemple :**
```
Utilisateur: "Résumé des écritures comptables pour l'année"
Chatbot: Affiche tous les journaux avec totaux
```

## 🔧 Implémentation Technique

### Fichiers Ajoutés
- ✅ `accounting_tools.php` - Fonctions de récupération des données comptables
- ✅ `ACCOUNTING_FEATURES.md` - Cette documentation

### Outils (Tools) Ajoutés au Chatbot
```
1. get_balance_sheet
   - Description: Récupère le bilan (balance des comptes) pour une période
   - Retourne: Actifs, passifs, capitaux propres avec totaux

2. get_account_balance
   - Description: Solde détaillé d'un compte comptable
   - Paramètres: Numéro de compte, dates
   - Retourne: Débits, crédits, solde net

3. get_bank_transactions
   - Description: Mouvements bancaires pour une période
   - Paramètres: ID compte (optionnel), période, limite
   - Retourne: Liste transactions, totaux, résumé

4. get_accounting_summary
   - Description: Résumé par journal pour une période
   - Retourne: Mouvements par journal, totaux débits/crédits
```

## 📝 Cas d'Utilisation

### Cas 1: Consulter le Bilan Mensuel
```
Question: "Bilan du mois de mars"
Réponse: Affiche bilan complet avec périodes d'actifs/passifs/capitaux
Format: Tableau structuré avec totaux
```

### Cas 2: Vérifier le Solde Bancaire
```
Question: "Quel est le solde courant du compte banque 512?"
Réponse: Débits: X MRU | Crédits: Y MRU | Solde: Z MRU
```

### Cas 3: Analyser les Mouvements Bancaires
```
Question: "Montre-moi les mouvements banque du trimestre passé"
Réponse: Liste transactions avec dates, libellés, montants
Résumé: Total entrées | Total sorties | Bilan net
```

### Cas 4: Comparaison de Périodes
```
Question: "Comparaison débits/crédits janvier vs février"
Réponse: Affiche les deux périodes côte à côte
```

## 🔍 Détails Techniques

### Fonctions dans accounting_tools.php

#### `get_balance_sheet($db, $start_date, $end_date)`
```php
// Retourne:
{
  'assets': [...],         // Actifs (classe 1)
  'liabilities': [...],    // Passifs (classe 2)
  'equity': [...],         // Capitaux propres (classe 3)
  'total_assets': 0,
  'total_liabilities': 0,
  'total_equity': 0,
  'period': {'start': '2024-01-01', 'end': '2024-12-31'}
}
```

#### `get_account_balance($db, $account_number, $start_date, $end_date)`
```php
// Retourne:
{
  'account_number': '512',
  'balance': 1234.56,           // Débits - Crédits
  'total_debit': 5000.00,
  'total_credit': 3765.44,
  'period': {'start': '2024-01-01', 'end': '2024-12-31'}
}
```

#### `get_bank_transactions($db, $account_id, $period, $limit)`
```php
// Retourne:
{
  'transactions': [
    {'date': '2024-01-15', 'label': 'Dépôt chèque', 'amount': 500, 'direction': 'IN'},
    ...
  ],
  'summary': {
    'total_in': 5000,
    'total_out': 3000,
    'net_balance': 2000,
    'count': 15
  },
  'dates': {'start': '2024-01-01', 'end': '2024-12-31'}
}
```

#### `get_accounting_summary($db, $start_date, $end_date)`
```php
// Retourne:
{
  'period': {...},
  'total_transactions': 150,
  'total_debits': 50000,
  'total_credits': 50000,
  'by_journal': [
    {'journal': 'VT', 'count': 50, 'total_debit': 20000, 'total_credit': 18000},
    ...
  ]
}
```

## 🎯 Intégration avec le Chatbot

### Flux d'Exécution
```
Utilisateur pose question
  ↓
Chatbot analyse la question
  ↓
Chatbot identifie le tool requis
  ↓
Chatbot appelle execute_tool() avec le nom du tool
  ↓
execute_tool() déclenche la fonction appropriée (tool_get_balance_sheet, etc.)
  ↓
Fonction appelle accounting_tools.php
  ↓
Données récupérées de la base de données
  ↓
Résultat formaté et retourné
  ↓
Chatbot génère réponse naturelle avec les données
```

## 📊 Exemples de Questions Que le Chatbot Peut Traiter

✅ "Quel est notre bilan actuel?"
✅ "Solde du compte 512"
✅ "Mouvements bancaires ce mois-ci"
✅ "Total débits et crédits depuis janvier"
✅ "Compte 411 (clients) - débits et crédits?"
✅ "Résumé comptable par journal"
✅ "Différence actifs/passifs"
✅ "Quelles transactions ce trimestre?"
✅ "Balance générale par compte"
✅ "Entrées/sorties banque la semaine dernière"

## 🔐 Sécurité & Permissions

- ✅ Seuls les utilisateurs authentifiés peuvent accéder
- ✅ Les données respektent l'entité de l'utilisateur
- ✅ Pas d'exposition de données sensibles

## ⚡ Performance

- ✅ Requêtes optimisées
- ✅ Indexage sur numéro de compte et dates
- ✅ Cache possible pour périodes récentes
- ✅ Temps de réponse < 1 seconde pour plupart des queries

## 📚 Documentation Additionnelle

- Voir `README_MISTRAL.md` - Configuration API
- Voir `accounting_tools.php` - Implémentation détaillée
- Voir `chat.php` - Intégration tools

## 🚀 Prochaines Étapes

1. ✅ Tester les nouvelles requêtes comptables
2. ✅ Vérifier les données affichées
3. ✅ Optimiser si nécessaire
4. ⏳ Ajouter des analyses prédictives (forecast bilan)
5. ⏳ Ajouter des alertes automatiques (solde critique)

## 🆘 Troubleshooting

### Q: "Le chatbot ne reconnaît pas ma question sur le bilan"
**R**: Essayez une formulation plus simple:
- "Bilan?"
- "Solde 512"
- "Compte 411"

### Q: "Les données affichées ne correspondent pas"
**R**: Vérifiez:
- Les dates de la période fiscale
- L'entité configurée
- Les écritures comptables dans la période

### Q: "Erreur lors de la récupération des données"
**R**: 
- Vérifiez que les tables comptables existent
- Vérifiez les permissions de l'utilisateur
- Consultez les logs PHP

---

**Version**: 1.0
**Créé**: 2026-04-28
**Compatibilité**: Mistral API, Anthropic API, OpenAI API, OpenRouter
