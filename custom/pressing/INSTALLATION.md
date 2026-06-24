# Guide d'Installation - Module Pressing

## Prérequis

- **Dolibarr 15.0+** installé et fonctionnel
- **PHP 7.4+**
- **MySQL 5.7+** ou MariaDB
- Droits **administrateur** sur Dolibarr
- Accès SFTP ou direct au serveur

## Étapes d'Installation

### Étape 1 : Déployer les fichiers

#### Option A : Via SFTP/FTP
1. Télécharger le dossier `custom/pressing/`
2. Uploader vers : `/htdocs/custom/pressing/`
3. Vérifier les permissions : `755` pour les dossiers, `644` pour les fichiers

#### Option B : Via git
```bash
cd /chemin/vers/dolibarr/htdocs/custom
git clone https://repo-url/pressing.git pressing
```

### Étape 2 : Vérifier la structure

```
custom/pressing/
├── admin/
│   └── setup.php
├── class/
│   ├── pressingcommande.class.php
│   └── pressingcommandedet.class.php
├── core/
│   └── modules/
│       └── modPressing.class.php
├── lang/
│   └── fr_FR/
│       └── pressing.lang
├── lib/
│   └── pressing.lib.php
├── sql/
│   ├── llx_pressing_commande.sql
│   └── llx_pressing_commandedet.sql
├── card.php
├── entrepots.php
├── index.php
├── list.php
└── README.md
```

### Étape 3 : Activer le module

1. Se connecter à Dolibarr avec un compte **administrateur**
2. Aller à : **Administration → Modules**
3. Chercher **"Pressing"** dans la barre de recherche
4. Cliquer sur le **module Pressing**
5. Cliquer sur le bouton **"Activer"** (vert)
6. ✅ Le système créera automatiquement les tables SQL

### Étape 4 : Vérifier l'Installation

1. Aller à : **Compta → Pressing**
2. Vous devez voir les sous-menus :
   - Commandes
   - Nouvelle
   - Entrepôts
3. Cliquer sur **Commandes** pour voir la liste (vide au départ)

### Étape 5 : Configurer les permissions (optionnel)

1. Aller à : **Administration → Utilisateurs → Permissions**
2. Pour chaque utilisateur, attribuer :
   - ✓ Lire les commandes pressing
   - ✓ Créer/modifier les commandes pressing
   - ✓ Supprimer les commandes pressing (optionnel)

## Création Manuelle des Tables (Avancé)

Si le module ne crée pas les tables automatiquement :

### Option 1 : Via phpMyAdmin

1. Ouvrir **phpMyAdmin**
2. Sélectionner la base de données de Dolibarr
3. Aller à l'onglet **SQL**
4. Copier-coller le contenu de `sql/llx_pressing_commande.sql`
5. Cliquer **Exécuter**
6. Répéter avec `sql/llx_pressing_commandedet.sql`

### Option 2 : Via Ligne de Commande

```bash
mysql -u username -p database_name < /path/to/sql/llx_pressing_commande.sql
mysql -u username -p database_name < /path/to/sql/llx_pressing_commandedet.sql
```

## Configuration de l'Environnement

### Ajouter le module custom dans conf.php

Si ce n'est pas déjà fait, éditez `/htdocs/conf/conf.php` et vérifiez :

```php
// Configuration des modules custom
$dolibarr_main_document_root_alt = '/var/www/dolibarr/htdocs/custom';
```

**Note :** Dolibarr scanne automatiquement le dossier `custom/` pour les modules.

## Vérification Post-Installation

### 1. Tables créées
```bash
mysql -u user -p database_name -e "SHOW TABLES LIKE '%pressing%';"
```
Vous devez voir :
- `llx_pressing_commande`
- `llx_pressing_commandedet`

### 2. Accès au module
- [ ] Menu "Pressing" visible dans Compta
- [ ] Able to access `/custom/pressing/list.php`
- [ ] Able to access `/custom/pressing/card.php?action=create`

### 3. Intégration facture
1. Ouvrir une facture client (ou en créer une)
2. Chercher le bouton **"Livrer commande pressing"** dans la barre d'actions
3. Le bouton doit être **grisé** jusqu'à ce qu'une commande pressing soit liée

## Désinstallation

### Pour désactiver le module

1. **Administration → Modules**
2. Chercher **Pressing**
3. Cliquer sur **Désactiver**

### Pour supprimer complètement

1. Désactiver le module (voir ci-dessus)
2. **Via SFTP :** Supprimer le dossier `/custom/pressing/`
3. **Via Base de Données (optionnel) :**
   ```sql
   DROP TABLE llx_pressing_commande;
   DROP TABLE llx_pressing_commandedet;
   ```
   ⚠️ Cette action est **irréversible**. Sauvegardez d'abord vos données.

## Dépannage Installation

### Problème : Module n'apparait pas dans la liste

**Solution :**
1. Vérifier la structure des dossiers
2. Vérifier que `modPressing.class.php` existe
3. Vérifier les permissions de fichier (755 pour dossiers)
4. Vider le cache Dolibarr : **Administration → Tools → Cache**
5. Redémarrer le serveur Web

### Problème : "Class modPressing not found"

**Solution :**
1. Vérifier le chemin complet : `custom/pressing/core/modules/modPressing.class.php`
2. Vérifier la classe commence par `class modPressing extends DolibarrModules`
3. Rechargement du cache

### Problème : Tables SQL non créées automatiquement

**Solution :**
1. Créer manuellement les tables (voir section "Création Manuelle")
2. Vérifier les droits de la base de données
3. Vérifier les logs : `var/log/dolibarr.log`

### Problème : Erreur "Module pressing not enabled"

**Solution :**
1. Module non activé → Aller à Administration → Modules → Activer
2. Vérifier conf.php contient le dossier custom
3. Redémarrer le serveur Web

## Vérification des Traductions

1. Aller à : **Administration → Languages**
2. Chercher la langue (ex: Français)
3. Fichier doit être à : `custom/pressing/lang/fr_FR/pressing.lang`
4. Le module charge automatiquement les traductions

## Logs

Les erreurs sont enregistrées dans :
```
/var/log/dolibarr.log
```

Pour déboguer, augmentez le niveau de log :
**Administration → Configuration → Logs** → `DEBUG`

## Support Technique

### Fichier de configuration
- Location : `/htdocs/conf/conf.php`
- Vérifier : `$dolibarr_main_document_root_alt`

### Fichier de démarrage du module
- Location : `custom/pressing/core/modules/modPressing.class.php`
- Numéro de module : `502025`

### Requête SQL de démarrage
Tables créées lors de l'activation du module.

## Sécurité

- ✓ Permissions d'accès au module basées sur les droits Dolibarr
- ✓ Validation des entrées utilisateur
- ✓ Authentification requise
- ✓ Echappement des caractères spéciaux en SQL
- ⚠️ Ne pas modifier les fichiers de classe sans comprendre les implications

## Mise à Jour

Pour mettre à jour le module :
1. **Backup** de la base de données
2. **Backup** du dossier `/custom/pressing/`
3. Télécharger la nouvelle version
4. Remplacer les fichiers
5. Vérifier dans Administration → Modules que le module est toujours actif
6. Tester les fonctionnalités

## Après Installation

### Recommandé

1. Créer des **entrepôts** si nécessaire : Produits → Stock → Entrepôts
2. Configurer les **permissions** par profil utilisateur
3. Tester avec une **commande test**
4. Former les utilisateurs à l'utilisation

### Optionnel

1. Personnaliser les traductions : `lang/fr_FR/pressing.lang`
2. Ajouter un logo personnalisé
3. Configurer les notifications par email

---

**Installation réussie !** 🎉

Le module Pressing est maintenant prêt à être utilisé. Consultez le fichier [README.md](README.md) pour l'utilisation complète.
