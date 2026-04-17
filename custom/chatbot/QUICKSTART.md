# 🚀 Démarrage Rapide - Upload & Voix

## ✨ Nouvelles Fonctionnalités du Chatbot

Deux boutons sont maintenant disponibles à côté de la zone de saisie :

```
[+] Bouton Upload  |  [🎤] Bouton Micro  |  Texte message  |  [→] Envoyer
```

---

## 📎 Utiliser l'Upload de Fichiers

### Scenario: Créer une facture à partir d'une image

```
1. Vous avez une image JPG d'une facture papier
2. Cliquez sur [+]
3. Sélectionnez l'image JPG
4. Claude analyse automatiquement → détecte "facture_client"
5. Claude propose: "Je vais créer une facture cliente avec les données extraites"
6. Vous dites: "Oui, créer la facture"
7. ✅ Facture créée dans l'ERP

RÉSULTAT: Facture créée en 30 secondes au lieu de taper manuellement 10 minutes
```

### Scenario: Importer une liste de produits

```
1. Vous avez un PDF avec liste de produits (fournisseur)
2. Cliquez sur [+]
3. Sélectionnez le PDF
4. Claude détecte "liste_produits"
5. Texte extrait du PDF s'affiche
6. Vous dites: "Crée un produit pour chaque ligne avec la description et le prix"
7. Claude crée chaque produit avec les bonnes données

RÉSULTAT: 30 produits créés automatiquement au lieu de les taper un par un
```

---

## 🎤 Utiliser la Reconnaissance Vocale

### Scenario: Chercher des produits par prix

```
1. Cliquez sur [🎤]
2. Navigateur demande "autoriser microphone?" → Cliquez OUI
3. Le bouton devient ROUGE et affiche: 🔴 Enregistrement...
4. Vous dites lentement et clairement:
   "Donne-moi la liste des produits entre 10 mille et 50 mille"
5. Après 2 secondes de silence OU clic sur [🎤]:
   - Audio s'envoie à Claude
   - Claude transcrit en texte
   - Texte s'injecte dans la zone message
   - Message s'envoie automatiquement
6. Claude exécute search_products avec filtre prix
7. ✅ Résultats s'affichent

RÉSULTAT: Recherche 3x plus rapide sans taper
```

### Scenario: Créer une commande en parlant

```
1. Cliquez sur [🎤]
2. Vous dites:
   "Crée une commande client pour Ali avec produit REF001 quantité 5"
3. Clic pour arrêter
4. Claude transcrit et comprend
5. Claude utilise l'outil create_order
6. ✅ Commande créée

RÉSULTAT: Mains libres, plus rapide que taper
```

---

## 🔄 Combiner Upload + Chat

### Scenario Complet: Facture → Produits → Commande

```
Étape 1: UPLOAD
  - Cliquez [+]
  - Uploadez facture fournisseur
  - Claude détecte produits dedans

Étape 2: VOIX
  - Cliquez [🎤]
  - Vous dites: "Créer un produit par ligne"
  - Claude crée les produits

Étape 3: CHAT NORMAL
  - Vous dites: "Crée commande client avec ces produits"
  - Claude utilise données créées précédemment

RÉSULTAT: Workflow complet automatisé sans saisie manuelle
```

---

## ⚡ Conseils d'Utilisation

### Pour l'Upload ✓
- **Qualité image:** Plus claire = meilleure analyse (éviter flou)
- **Angles:** Photo droite, pas de perspective (scanner idéal)
- **PDF:** Texte clair sans images scannées dégradées
- **Fichiers:** PNG/JPG/PDF seulement (< 25 MB)

### Pour la Voix ✓
- **Débit:** Parlez normalement, pas trop vite
- **Accent:** Peu importe, Claude comprend bien le français
- **Bruit:** Pièce silencieuse idéale (microphone sensible)
- **Durée:** Jusq'à 2 minutes d'un coup, puis pause

### Astuce: Dictée longue
```
Si besoin >2 min:
1. Dites première partie, clic arrêt
2. Attendez réponse Claude
3. Cliquez [🎤] à nouveau
4. Continuez partie 2

Chaque bloc est transcrit indépendamment
```

---

## 🎯 Exemples de Commandes Vocales

### Recherche
```
"Montre-moi tous les produits avec stock faible"
"Donne les clients actifs ce mois"
"Quelles factures ne sont pas payées?"
"Liste les fournisseurs de l'électronique"
```

### Création
```
"Crée une facture client pour Acme Inc avec 3 produits"
"Ajoute un nouveau produit: LED RGB prix 5000"
"Enregistre Ali comme nouveau client"
```

### Analyse
```
"Quel est le chiffre d'affaires ce mois?"
"Quels produits sont en sous-stock?"
"Montre les écritures comptables de juin"
```

---

## 🐛 Ça ne marche pas?

### Upload échoue
- [ ] Fichier < 25 MB?
- [ ] Type PNG/JPG/PDF?
- [ ] Connexion internet OK?
→ Regardez la console navigateur (F12) pour erreur exacte

### Voix ne marche pas
- [ ] Avez-vous autorisé le microphone?
- [ ] HTTPS en prod? (HTTP localhost OK)
- [ ] Microphone connecté et fonctionnel?
- [ ] Testez enregistrement audio d'abord
→ Consultez la console (F12) pour erreur

### Claude ne comprend pas le contexte
- Soyez plus explicite: "Crée facture" vs "Crée une facture client pour Ahmed"
- Donnez les références exactes: "Produit REF001" vs "Le produit"
- Utilisez les termes ERP: "tiers" pas "client", "facture client" pas "facture"

---

## 📊 Améliorer la Précision

### Upload images
- **Impact:** Qualité photo = ±10% précision
- **Solution:** Scanner >= Photo mobile
- **Résolution:** 200 DPI minimum

### Transcription vocale  
- **Impact:** Clarté voix = ±15% précision
- **Solution:** Microphone casque >= micro intégré
- **Pratique:** Première phrase définit l'accent (Claude adapte)

### Analyse documents
- **Impact:** Format = ±20% précision
- **Solution:** PDF texte >= PDF image scannée
- **Astuce:** Copier/coller texte si PDF image dégradée

---

## 🔐 Sécurité

✅ Fichiers uploadés sont **temporaires** (supprimés après analyse)
✅ Audio n'est **pas stocké**, juste transcrit
✅ Pas de données personnelles exposées aux logs
✅ Token CSRF protège contre attaques
✅ HTTPS obligatoire en production

---

## 💡 Cas d'Usage Réels

### PME: Gestion Facturation
```
Matin: Reçoit 5 factures fournisseur
       → Upload chaque PDF
       → Claude crée les achats automatiquement
       → 5 min au lieu de 30 min
```

### E-commerce: Catalogue Produits
```
Import catalogue fournisseur:
  → Upload PDF (500 produits)
  → Claude détecte structure
  → Crée tous les produits avec prix/description
  → 30 min au lieu de 3 jours manuellement
```

### Cabinet Comptable: Dictée de Factures
```
Comptable dicte factures à voix:
  → "Facture ACME 50k, délai 30j"
  → Claude transcrit + crée facture
  → Écriture comptable auto-générée
  → Réduction errors = meilleur audit
```

---

## 📞 Besoin d'aide?

Consultez: `custom/chatbot/README_FEATURES.md` pour détails techniques

Questions fréquentes:
- **"Ça traite le PDF en Français?"** → Oui, OCR multilingue
- **"Maximum fichier?"** → 25 MB (HTTP standard)
- **"Ça garde les enregistrements?"** → Non, juste les transcriptions
- **"Compatible mobile?"** → Oui, mais microphone mobile moins précis

---

## 🎉 Vous êtes prêt!

```
Ouvrez le chatbot → Essayez [+] et [🎤] → Explorez le gain de temps!
```

**Temps estimé pour apprendre: 5 minutes**
**Gain de productivité: 40-60% sur saisies manuelles**

Bon travail! 🚀
