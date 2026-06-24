# 📖 Guide Utilisateur - Module Pressing

## 🚀 Démarrage Rapide

### Accès au Module
```
Dolibarr → Menu principal "Pressing" (haut gauche)
           ├─ Articles (liste)
           └─ Vue par Entrepôt
```

### Accès à une Facture Pressing
```
Compta → Factures
         ├─ Sélectionner une facture
         └─ Onglet "Pressing" (à côté de "Card")
```

---

## 📝 Ajouter un Article à une Facture

### 1. Localiser le formulaire
- Aller à une facture → Onglet "Pressing"
- Scroller vers le bas
- Section "**Ajouter un article**"

### 2. Remplir les champs

#### 🏷️ Réf Article
**Quoi :** Identifiant unique de l'article  
**Exemple :** CODE123, BARCODE001, ART-2024-0001  
**Requis :** ✅ Oui  

#### 📦 Produit/Service
**Quoi :** Type de service (définit le prix/m²)  
**Exemple :** Pressing M², Nettoyage textile, Repassage m²  
**Affiche :** Prix au m² du produit sélectionné  
**Requis :** ✅ Oui  

#### 📏 Dimensions (cm)
**Longueur :** _________ cm  
**Largeur :** _________ cm  

⚠️ **Attention :** Entrer en **CENTIMÈTRES**, pas en mètres  
**Exemple :** Chemise = 80 × 60 cm (pas 0.80 × 0.60)

#### 📊 Surface (m²)
**Affichage automatique :** (Longueur × Largeur) / 10000  
**Exemple :** (80 × 60) / 10000 = 0.48 m²  
**Ne pas modifier :** Calculée automatiquement ✅

#### 💰 Prix Calculé/Modifié (€)
**Calcul automatique :**
```
Prix = Surface × Prix du produit/m²
Exemple: 0.48 m² × 25.00 €/m² = 12.00 €
```

**Vous pouvez modifier :**
```
✅ 12.00 € → 10.00 € (rabais)
✅ 12.00 € → 15.00 € (supplément)
```

🔍 **Affichage de la formule :** 
```
"(80 × 60) / 10000 × 25.00 €/m² = 12.00 €"
```

#### 🏪 Entrepôt
**Quoi :** Localisation physique de l'article  
**Exemple :** Entrepôt Principal, Pressing Nord, Stock Temporaire  
**Requis :** ✅ Oui (pour la livraison)  

### 3. Valider
Cliquer le bouton **"Ajouter"**

✅ **Résultat :**
- Article enregistré en base
- Ligne facture créée automatiquement
- Statut initial : "Réception" (non lavé)

---

## ✏️ Modifier un Article

### Depuis la facture
1. Aller à la facture → Onglet "Pressing"
2. Cliquer "**Modifier**" sur l'article voulu
3. Page "Fiche Article" s'ouvre

### Champs modifiables

#### 📏 Longueur / Largeur (cm)
- Modifier les dimensions
- La **surface s'affiche en temps réel**
- Cliquer "**Recalculer le prix**" pour mettre à jour

#### 💰 Prix Calculé/Manuel (€)
- **Modifier manuellement** le prix
- Le prix reste tel que modifié
- Bouton "**Recalculer**" pour réinitialiser

#### 🎯 Statut
```
0 = Réception (non lavé) ← Initial
1 = En traitement (en cours de lavage)
2 = Prêt à livrer (prêt)
3 = Livré (supprimé du stock)
```

#### 🏪 Entrepôt
- Changer l'entrepôt de stockage
- Obligatoire pour la livraison

#### 📝 Notes
- Ajouter des notes privées
- Visible que par vous

### Enregistrer
Cliquer **"Enregistrer"**

---

## 🚚 Livraison d'Articles

### Conditions
```
✅ TOUS les articles doivent être en statut "Prêt à livrer" (2)
❌ Si un article est en "Réception" ou "En traitement" → Bouton Livrer grisé
```

### Étapes

#### 1. Préparer les articles
- Aller à chaque article
- Changer le statut de 0→1→2 au fur et à mesure

#### 2. Vérifier le résumé
```
Résumé de l'état de lavage/pressing:
├─ Pièces en réception (non lavées): 0
├─ Pièces en cours de lavage: 0
├─ Pièces prêtes à être livrées: 3 ✅
└─ Pièces livrées au client: 0
```

#### 3. Livrer
- Cliquer **"Livrer (Générer Mouvement Stock)"**
- Button actif = tous les articles sont prêts ✅

### Résultat
```
✅ Tous les articles passent à statut 3 (Livré)
✅ Mouvement de stock créé (type sortie)
✅ Stock automatiquement décrémenté
✅ Articles supprimés des listes "En attente"
```

---

## 📊 Consulter les Articles

### Liste complète
**Chemin :** Pressing (menu) → Articles

**Affiche :**
- Réf Article
- Facture associée
- Dimensions (L × l)
- Surface (m²)
- Statut actuel
- Lien pour modifier

### Par Entrepôt
**Chemin :** Pressing (menu) → Vue par Entrepôt

**Affiche :**
1. **Stock des produits** (quantité réelle)
2. **Résumé des pièces** non livrées :
   - Pièces en réception
   - Pièces en traitement
   - Pièces prêtes

---

## 💡 Astuces

### Calcul prix fois réel
- Remplissez **Longueur** et **Largeur**
- Le **prix s'affiche automatiquement** avant cliquer "Ajouter"
- Pas besoin de rechargement 🚀

### Modifi prix
- Prix proposé peut être **modifié avant l'ajout** ✏️
- Après ajout, utilisez **"Recalculer le prix"** 🔄
- Bouton visible dans la **fiche article**

### Statuts
```
Progression naturelle :
0 (Réception) → 1 (Traitement) → 2 (Prêt) → 3 (Livré)

Vous pouvez :
✅ Revenir en arrière (2 → 1 → 0)
✅ Sauter des étapes (0 → 2 directement)
```

### Entrepôts
- Un article = **un seul entrepôt**
- Obligatoire pour livrer
- Change la localisation du stock

---

## ⚠️ Points Importants

### Dimensions
- **Entrer en CENTIMÈTRES** (100 cm, pas 1.00 m)
- Surface calculée automatiquement en m²
- (100 × 50) / 10000 = 0.5 m²

### Prix
- **En EUROS** (12.50 €)
- Calculé = surface × prix_produit/m²
- **Modifiable** avant et après ajout

### Livraison
- Impossible si articles pas "Prêts"
- Crée automatiquement mouvement stock
- Décrémente le stock du produit

### Stock
- Diminue seulement à la livraison (statut 3)
- Consulter via "Vue par Entrepôt"
- Articles en Réception/Traitement "bloquent" du stock

---

## 🔄 Flux Complet Exemple

### Jour 1 : Client apporte du linge
```
1. Créer/ouvrir facture
2. Aller à "Pressing"
3. Ajouter article:
   - Réf: CHEMISE001
   - Produit: Pressing M² (25.00 €/m²)
   - Dim: 100 × 50 cm
   - Surface: 0.5000 m² (auto)
   - Prix: 12.50 € (auto, modifiable)
   - Entrepôt: Pressing Nord
4. Cliquer "Ajouter"
✅ Facture créée avec ligne
```

### Jour 2 : Linge en lavage
```
1. Aller Pressing → Articles
2. Cliquer "Modifier" sur CHEMISE001
3. Changer statut: 0 → 1 (En traitement)
4. Sauvegarder
✅ Statut mis à jour
```

### Jour 3 : Linge prêt
```
1. Aller Pressing → Articles
2. Cliquer "Modifier" sur CHEMISE001
3. Changer statut: 1 → 2 (Prêt à livrer)
4. Sauvegarder
✅ Statut mis à jour
```

### Jour 4 : Livraison client
```
1. Aller facture → Pressing
2. Vérifier: tous articles en "Prêt" (2) ✅
3. Cliquer "Livrer"
✅ Statut: 2 → 3 (Livré)
✅ Stock décrémenté
✅ Mouvement créé
```

---

## ❓ FAQ

**Q: Le prix ne s'affiche pas?**  
R: Vérifiez que le produit a un prix configuré en base.

**Q: Le bouton "Livrer" est grisé?**  
R: Au moins un article n'est pas "Prêt à livrer". Vérifiez les statuts.

**Q: Puis-je modifier une facture après livraison?**  
R: Non, un article livré (3) disparaît des listes actives.

**Q: Comment recalculer le prix?**  
R: Cliquer "🔄 Recalculer le prix" dans la fiche article.

**Q: Un article peut être dans 2 entrepôts?**  
R: Non, un article = un entrepôt.

---

## 📞 Support

Pour plus d'informations :
- 📄 Lire `IMPLEMENTATION_SUMMARY.md`
- 📄 Lire `TEST_VALIDATION.md`
- 💻 Vérifier la base `llx_pressing_article`

**Version:** 1.0 ✅  
**Dernière mise à jour:** 2026-05-06
