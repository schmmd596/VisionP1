# Correctif de création automatique des tables

**Module : Fiscal Mauritanie**  
**Correctif : création explicite des tables SQL à l’activation**  
**Auteur : Manus AI**

## Cause de l’erreur

L’erreur `Table 'dolibarr.llx_fiscalmauritanie_rule' doesn't exist` signifie que Dolibarr a tenté d’utiliser la table des règles fiscales avant qu’elle ne soit créée. Dans la version corrigée, le descripteur du module exécute explicitement le chargement des scripts SQL du dossier `sql/` avant l’insertion des règles fiscales par défaut.

Le correctif modifie la méthode `init()` du fichier `core/modules/modFiscalMauritanie.class.php` afin d’appeler la création des tables avec `_load_tables('/fiscalmauritanie/sql/')` avant tout `INSERT`. Les scripts de création sont également devenus idempotents grâce à `CREATE TABLE IF NOT EXISTS`, ce qui évite l’échec si une table existe déjà partiellement.

## Tables créées par le module

| Table | Rôle |
|---|---|
| `llx_fiscalmauritanie_rule` | Règles fiscales paramétrables, taux, méthodes de calcul et échéances. |
| `llx_fiscalmauritanie_barreme` | Tranches de barème, notamment pour l’ITS progressif. |
| `llx_fiscalmauritanie_declaration` | Déclarations fiscales et sociales par période. |
| `llx_fiscalmauritanie_payment` | Suivi des paiements rattachés aux déclarations. |
| `llx_fiscalmauritanie_notification` | Notifications et alertes d’échéance. |
| `llx_fiscalmauritanie_audit` | Journal d’audit des changements métier. |

## Procédure recommandée

Si le module n’est pas encore utilisé en production, remplacez simplement le dossier `htdocs/custom/fiscalmauritanie` par la version corrigée, puis désactivez et réactivez le module depuis l’administration Dolibarr. À l’activation, les tables seront créées automatiquement.

Si le module est déjà activé mais incomplet, copiez la version corrigée, puis exécutez le script `repair/create_tables_fiscalmauritanie.sql` dans la base Dolibarr. Ce script crée les tables manquantes avec `IF NOT EXISTS` et insère les règles initiales avec `INSERT IGNORE`. Si votre installation utilise un préfixe différent de `llx_`, remplacez d’abord `llx_` par votre préfixe réel dans le script.

## Vérification après correction

Après activation ou réparation, vérifiez que la table `llx_fiscalmauritanie_rule` existe et contient des règles initiales. Vous pouvez ensuite ouvrir le menu **Fiscalité Mauritanie**, accéder aux règles fiscales, puis modifier les taux selon les valeurs validées par votre comptable ou fiscaliste.
