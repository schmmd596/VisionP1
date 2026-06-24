# 🧪 GUIDE DE TEST - FONCTIONNALITÉS COMPTABLES

## ✅ VÉRIFICATION RAPIDE (5 minutes)

### Étape 1: Vérifier les Fichiers (30 secondes)
```bash
# Vérifier que accounting_tools.php existe
ls -la custom/chatbot/accounting_tools.php

# Vérifier que chat.php a été modifié
grep "get_accounting_summary" custom/chatbot/ajax/chat.php
```

**Résultat attendu**: Les deux fichiers existent ✓

---

### Étape 2: Tester via le Navigateur (2 minutes)

#### Test 1: Ouvrir le Chatbot
```
1. Allez sur une page Dolibarr
2. Cliquez sur le widget 💬 (bas droite)
3. Vérifiez que le chat s'ouvre
```

**Résultat**: Widget visible et fonctionnel ✓

#### Test 2: Poser une Question Simple
```
Posez: "Bilan?"
ou
Posez: "Solde 512"
```

**Résultat Attendu**:
- Pas d'erreur 429 ✓
- Pas de message "Outil inconnu" ✓
- Une réponse avec données réelles ✓

---

### Étape 3: Valider les Données (1 minute)

#### Demander le Bilan
```
Question: "Fais-moi un bilan"
Vérifier:
✓ Affiche l'actif (classe 1)
✓ Affiche le passif (classe 2)
✓ Affiche les capitaux (classe 3)
✓ Les totaux sont correctes
✓ Actif = Passif + Capitaux
```

#### Demander un Solde
```
Question: "Solde du compte 512"
Vérifier:
✓ Affiche débits
✓ Affiche crédits
✓ Affiche le solde net
✓ Solde net = débits - crédits
```

#### Demander les Transactions
```
Question: "Mouvements banque ce mois"
Vérifier:
✓ Liste les transactions
✓ Montre dates et montants
✓ Affiche les totaux (entrées/sorties)
✓ Calcule le bilan net
```

---

## 🔧 TESTS DÉTAILLÉS

### Test Suite 1: Bilans

#### Test 1.1: Bilan Complet
```
Question: "Bilan complet pour l'année"
Pas d'erreur: ✓ ou ✗
Données affichées: ✓ ou ✗
Format lisible: ✓ ou ✗
Totaux corrects: ✓ ou ✗
```

#### Test 1.2: Bilan Partiel
```
Question: "Bilan actif seulement"
Réponse contient actif: ✓ ou ✗
Réponse ne contient pas passif: ✓ ou ✗
```

#### Test 1.3: Bilan Période Spécifique
```
Question: "Bilan janvier 2024"
Correctement limité à janvier: ✓ ou ✗
Dates affichées correctes: ✓ ou ✗
```

---

### Test Suite 2: Soldes de Comptes

#### Test 2.1: Compte Bancaire
```
Question: "Solde 512"
Affiche débits: ✓ ou ✗
Affiche crédits: ✓ ou ✗
Calcule net: ✓ ou ✗
Montants > 0: ✓ ou ✗
```

#### Test 2.2: Compte Client
```
Question: "Solde 411"
Affiche client débiteur: ✓ ou ✗
Montant > 0: ✓ ou ✗
```

#### Test 2.3: Compte Fournisseur
```
Question: "Solde 401"
Affiche fournisseur créditeur: ✓ ou ✗
Montant < 0 ou crédit: ✓ ou ✗
```

---

### Test Suite 3: Transactions Bancaires

#### Test 3.1: Transactions Récentes
```
Question: "Mouvements ce mois"
Liste transactions: ✓ ou ✗
Affiche dates: ✓ ou ✗
Affiche montants: ✓ ou ✗
Affiche types (IN/OUT): ✓ ou ✗
```

#### Test 3.2: Résumé Transactions
```
Question: "Transactions"
Total entrées > 0: ✓ ou ✗
Total sorties > 0: ✓ ou ✗
Bilan net calculé: ✓ ou ✗
Période affichée: ✓ ou ✗
```

#### Test 3.3: Période Personnalisée
```
Question: "Transactions Q1 2024"
Limité à Q1: ✓ ou ✗
Dates correctes (01/01-31/03): ✓ ou ✗
```

---

### Test Suite 4: Résumés Comptables

#### Test 4.1: Résumé par Journal
```
Question: "Résumé comptable"
Affiche journaux (VT, AC, BQ, OD): ✓ ou ✗
Affiche débits par journal: ✓ ou ✗
Affiche crédits par journal: ✓ ou ✗
Affiche balance générale: ✓ ou ✗
```

#### Test 4.2: Journal Spécifique
```
Question: "Journal BQ"
Affiche transactions BQ: ✓ ou ✗
Débits/Crédits BQ: ✓ ou ✗
```

---

## 🐛 DÉPANNAGE

### Problème 1: "Erreur 429"
```
Symptôme: ⚠ Erreur API (429)
Cause: Provider mal détecté
Solution:
1. Vérifier chat.php (détection Mistral prioritaire au modèle)
2. Rafraîchir navigateur
3. Vérifier modèle = "mistral-*"
```

### Problème 2: "Outil inconnu"
```
Symptôme: ❌ Outil inconnu: get_balance_sheet
Cause: Tool non enregistré dans execute_tool()
Solution:
1. Vérifier accounting_tools.php existe
2. Vérifier tool dans le switch de execute_tool()
3. Vérifier syntaxe des tool definitions
```

### Problème 3: "Pas de données"
```
Symptôme: Réponse vide ou 0 résultat
Cause: Pas d'écritures pour la période
Solution:
1. Vérifier écritures en comptabilité
2. Vérifier période configurée correctement
3. Vérifier entité correcte
```

### Problème 4: "Erreur de syntaxe"
```
Symptôme: Erreur PHP / Parse error
Cause: Fichier mal sauvegardé
Solution:
1. Vérifier accounting_tools.php (pas d'erreur PHP)
2. Vérifier chat.php (parenthèses, accolades)
3. Vérifier encodage UTF-8
```

---

## 📋 CHECKLIST FINALE

### Avant d'Utiliser
- [ ] Fichier accounting_tools.php existe
- [ ] chat.php modifié avec les 4 nouveaux outils
- [ ] execute_tool() contient get_accounting_summary
- [ ] Tool definitions complètes
- [ ] System prompt mis à jour
- [ ] Pas d'erreur PHP
- [ ] Erreur 429 Mistral corrigée

### Tests Basiques
- [ ] Chatbot s'ouvre sans erreur
- [ ] "Bilan?" retourne résultats
- [ ] "Solde 512" retourne montants
- [ ] "Mouvements" retourne transactions
- [ ] "Résumé" retourne par journal

### Tests Avancés
- [ ] Bilans par période
- [ ] Soldes comparés
- [ ] Transactions filtrées
- [ ] Anomalies détectées
- [ ] Analyses complètes

### Validation Finale
- [ ] Toutes données correctes
- [ ] Format lisible
- [ ] Temps de réponse acceptable
- [ ] Pas d'erreur dans les logs
- [ ] Prêt pour production

---

## 📊 RAPPORT DE TEST TEMPLATE

```
DATE: ___/___/____
TESTER: ________________

BILAN:
- Complet: [ ] OK  [ ] KO
- Partiel: [ ] OK  [ ] KO
- Période: [ ] OK  [ ] KO

SOLDES:
- 512: [ ] OK  [ ] KO
- 411: [ ] OK  [ ] KO
- 401: [ ] OK  [ ] KO

TRANSACTIONS:
- Récentes: [ ] OK  [ ] KO
- Période: [ ] OK  [ ] KO
- Résumé: [ ] OK  [ ] KO

RÉSUMÉ:
- Par journal: [ ] OK  [ ] KO
- Global: [ ] OK  [ ] KO
- Balance: [ ] OK  [ ] KO

ERREURS TROUVÉES:
____________________________
____________________________

NOTES:
____________________________
____________________________

STATUT FINAL: [ ] ✅ PRÊT  [ ] ❌ À CORRIGER
```

---

## 🚀 APRÈS LES TESTS

Si tout fonctionne ✅:
1. Communiquer à l'équipe que c'est prêt
2. Documenter les cas d'usage
3. Former les utilisateurs
4. Monitorer les performances

Si erreurs ❌:
1. Identifier le problème spécifique
2. Consulter la section dépannage
3. Corriger
4. Re-tester
5. Documenter la correction

---

**Date Création**: 2026-04-28
**Dernière Mise à Jour**: 2026-04-28
**Temps de Test Estimé**: 10-15 minutes
**Ressource**: Dolibarr + Mistral/Anthropic API
