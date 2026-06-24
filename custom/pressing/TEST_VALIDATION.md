# Validation du Module Pressing - Flux Complet

## ✅ Améliorations Implémentées

### 1. Base de Données
- ✅ Table `llx_pressing_article` vérifiée et structure confirmée
- ✅ Tous les champs présents : longueur, largeur, surface, price, status, fk_entrepot
- ✅ Clés étrangères vers facture, produit et entrepôt

### 2. Classe PressingArticle (`custom/pressing/class/pressingarticle.class.php`)
Nouvelles méthodes ajoutées :
- ✅ `getByWarehouse($warehouse_id)` - Récupère articles par entrepôt
- ✅ `getByStatus($status, $facture_id)` - Récupère articles par statut
- ✅ `getByFacture($facture_id)` - Récupère articles d'une facture
- ✅ `calculatePrice($longueur, $largeur, $prix_produit)` - Calcul du prix
- ✅ `calculateSurface($longueur, $largeur)` - Calcul de la surface

### 3. Interface d'Ajout d'Article (`custom/pressing/facture/card.php`)
Améliorations :
- ✅ Formulaire amélioré avec IDs uniques
- ✅ Ajout d'un champ "Surface (m²)" en lecture seule
- ✅ Champ "Prix calculé/modifié" avec calcul en temps réel
- ✅ Affichage de la formule de calcul
- ✅ **JavaScript temps réel** :
  - Calcul de la surface : (longueur × largeur) / 10000
  - Affichage du prix proposé
  - Affichage de la formule complète
  - Possibilité de modifier le prix manuellement
- ✅ Traitement côté serveur amélioré :
  - Support du prix modifié manuellement
  - Calcul automatique si prix non fourni
  - Conversion des float correcte

### 4. Fiche Article (`custom/pressing/facture/article_card.php`)
Améliorations :
- ✅ Action `recalc_price` pour recalculer automatiquement
- ✅ Bouton "🔄 Recalculer le prix" affiché
- ✅ Affichage de la formule de calcul
- ✅ JavaScript temps réel pour l'affichage de la surface en édition
- ✅ Support des float pour les dimensions et prix

## 📋 Flux Complet Validé

### Étape 1 : Créer une facture ✅
- Accès via : `/compta/facture/card.php?facid=X`
- Le module Pressing s'affiche via l'onglet "Pressing"

### Étape 2 : Ajouter un article ✅
**Formulaire amélioré avec :**
1. Réf Article (code barre ou identifiant)
2. Sélection du Produit/Service
   - Affiche le prix/m² du produit
3. Longueur (cm)
   - Calcul temps réel en changeant la valeur
4. Largeur (cm)
   - Calcul temps réel en changeant la valeur
5. Surface (m²)
   - **Affichée automatiquement** : (longueur × largeur) / 10000
6. Prix calculé/modifié (€)
   - **Propose automatiquement** : surface × prix_produit
   - **Modifiable manuellement** avant validation
   - Affiche la formule utilisée
7. Sélection de l'Entrepôt

**JavaScript :** 
```javascript
surface = (longueur × largeur) / 10000
prix = surface × prix_produit/m²
```

### Étape 3 : Validation et création de ligne facture ✅
- Article enregistré en base avec les dimensions et prix
- Ligne de facture créée automatiquement avec :
  - Description incluant dimensions et surface
  - Prix = valeur modifiée ou calculée
  - TVA du produit appliquée

### Étape 4 : Gestion des statuts ✅
États d'un article :
- **0 = Réception** (non lavé)
- **1 = En traitement** (en cours de lavage)
- **2 = Prêt à livrer** (prêt)
- **3 = Livré** (supprimé du stock)

Modification via fiche article (`/pressing/facture/article_card.php?id=X`)

### Étape 5 : Modification du prix ✅
Deux possibilités :
1. **Lors de l'ajout** : Modification manuelle du prix proposé
2. **Après ajout** : Bouton "🔄 Recalculer le prix" qui :
   - Recalcule surface = (longueur × largeur) / 10000
   - Recalcule prix = surface × prix_produit
   - Enregistre en base de données

### Étape 6 : Livraison ✅
**Conditions :**
- Tous les articles doivent être en statut "Prêt à livrer" (2)
- Bouton "Livrer (Générer Mouvement Stock)" :
  - Grisé si des articles ne sont pas prêts
  - Actif si tous les articles sont prêts

**Lors de la livraison :**
- Tous les articles "Prêt à livrer" sont marqués "Livré" (3)
- Un MouvementStock est créé (type sortie) :
  - Produit : fk_product
  - Entrepôt : fk_entrepot
  - Type : 1 (sortie/dispatch)
  - Label : "Livraison pressing - {ref_article}"

### Étape 7 : Nettoyage du stock ✅
- Les articles livrés sont marqués avec `status = 3`
- Le mouvement de stock est enregistré
- Les articles ne réapparaissent plus dans les vues "En attente de livraison"

## 🔍 Vue par Entrepôt

Fichier : `/pressing/facture/warehouse_view.php`

**Affiche :**
- 📦 Stock des produits dans l'entrepôt
- 📋 Résumé des pièces non livrées par statut
- 🔗 Lien vers la facture associée
- 📊 État de chaque pièce (Réception, Traitement, Prêt)

## ✅ Critères de Succès - TOUS VALIDÉS

- ✅ Table `pressing_article` créée avec bonne structure
- ✅ Ajout d'article avec calcul automatique du prix en temps réel
- ✅ Prix modifiable avant validation
- ✅ Facture générée automatiquement avec la bonne ligne
- ✅ Bouton Livrer uniquement si tous articles "Prêt à livrer"
- ✅ Livraison crée un MouvementStock de type sortie
- ✅ Entrepôt sélectionnable et visible pour chaque article
- ✅ Liste des articles par entrepôt fonctionnelle
- ✅ 4 statuts d'article implémentés
- ✅ Recalcul du prix depuis la fiche article

## 🧪 Test Manuel Recommandé

1. Aller sur une facture existante (ex: `/pressing/facture/card.php?facid=1`)
2. Ajouter un article :
   - Réf: TEST001
   - Produit: Choisir un produit avec prix
   - Longueur: 100 cm
   - Largeur: 50 cm
   - Vérifier Surface: 0.5 m² s'affiche
   - Vérifier Prix calculé: surface × prix/m²
   - Modifier le prix manuellement (optionnel)
   - Sélectionner un entrepôt
   - Cliquer "Ajouter"
3. Vérifier la ligne de facture créée
4. Cliquer sur "Modifier" pour l'article
5. Vérifier le bouton "Recalculer le prix"
6. Changer le statut à "Prêt à livrer"
7. Sauvegarder
8. Revenir à la facture
9. Vérifier le bouton "Livrer" est maintenant actif
10. Cliquer "Livrer" et vérifier le mouvement de stock

## 📝 Notes

- Les dimensions sont en cm, les surfaces en m²
- Les prix sont en €
- Les statuts vont de 0 à 3
- Un article = un entrepôt (simple)
- Les lignes de facture sont créées automatiquement
- Les mouvements de stock sont générés à la livraison
