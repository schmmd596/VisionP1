# Module Dolibarr Fiscal Mauritanie

**Auteur : Manus AI**  
**Version : 1.0.0**  
**Compatibilité visée : Dolibarr 15+ à 21+**  
**Répertoire cible : `htdocs/custom/fiscalmauritanie`**

## Présentation

Le module **Fiscal Mauritanie** est un module externe Dolibarr destiné à centraliser le suivi des obligations fiscales et sociales d’une entreprise opérant en Mauritanie. Il couvre les déclarations liées à l’**ITS**, la **CNSS**, la **CNAM**, l’**IS**, la **taxe d’apprentissage**, la **patente** et l’**IMF**, avec un tableau de bord, des règles paramétrables, des déclarations périodiques, des notifications d’échéance, des exports CSV et une génération PDF.

Ce module respecte la logique d’extension recommandée par Dolibarr, qui documente l’usage de modules externes, de fichiers de description, de répertoires `custom`, de droits, de menus et de scripts SQL pour ajouter des fonctionnalités au progiciel.[1] [2]

> Les taux fournis dans l’initialisation sont **indicatifs**. Ils doivent être validés et ajustés par un expert fiscal ou comptable selon la réglementation applicable et la période concernée.

## Fonctionnalités livrées

| Domaine | Fonctionnalités incluses |
|---|---|
| Tableau de bord | Indicateurs sur les montants à payer, déclarations en retard, brouillons, déclarations validées et derniers échéanciers. |
| Déclarations | Création, modification, validation, consultation et export CSV des déclarations fiscales. |
| Calculs | Classe de calcul prenant en charge le taux simple, le barème progressif, le pourcentage déclaré, le manuel, le minimum forfaitaire et les retenues. |
| Règles fiscales | Paramétrage des règles par type d’impôt, taux, seuils, plafond, fréquence, échéance et méthode de calcul. |
| Notifications | Journal des notifications et génération automatique d’alertes internes selon les jours configurés. |
| PDF | Génération d’un PDF de déclaration à partir de la fiche déclaration. |
| CRON | Classe de traitement périodique et script CLI pour notifications et préparation des brouillons mensuels. |
| Sécurité | Droits Dolibarr dédiés pour la lecture, l’écriture, la validation, l’export et l’administration. |
| Multi-entité | Usage du champ `entity` et de `getEntity('fiscalmauritanie')` pour respecter les contextes multi-sociétés Dolibarr. |

## Arborescence principale

| Chemin | Rôle |
|---|---|
| `core/modules/modFiscalMauritanie.class.php` | Descripteur du module : menus, droits, constantes, activation et données initiales. |
| `sql/` | Scripts SQL d’installation et de désinstallation. |
| `class/` | Classes métier : règles, calculs, déclarations, paiements, notifications, CRON. |
| `declaration/` | Liste et fiche des déclarations. |
| `rules/` | Liste et fiche des règles fiscales. |
| `notification/` | Journal des notifications. |
| `admin/` | Configuration du module. |
| `core/modules/fiscalmauritanie/` | Générateur PDF standard. |
| `cron/` | Script CLI facultatif pour exécution périodique. |
| `langs/fr_FR/` | Traductions françaises. |
| `css/` et `js/` | Styles et interactions légères. |

## Installation

Copiez le dossier `fiscalmauritanie` dans le répertoire `htdocs/custom/` de votre installation Dolibarr. Le chemin final doit donc être `htdocs/custom/fiscalmauritanie`. Connectez-vous ensuite en administrateur, ouvrez la page des modules Dolibarr, recherchez **Fiscal Mauritanie**, puis activez le module. Dolibarr exécutera les scripts SQL du module lors de l’activation, conformément au mécanisme usuel des modules externes.[1]

Après activation, ouvrez le menu **Fiscalité Mauritanie**, puis accédez à **Configuration** pour vérifier la devise, les canaux de notification et les jours d’alerte. Les règles fiscales initiales sont créées automatiquement à l’activation, mais elles doivent être revues avant toute utilisation opérationnelle.

## Configuration recommandée

| Paramètre | Valeur initiale | Recommandation |
|---|---:|---|
| Devise | `MRU` | À conserver sauf installation multi-devise spécifique. |
| Jours de notification | `30,15,7,3,0` | Ajuster selon les procédures internes. |
| Email | Activable | Prévoir une configuration SMTP Dolibarr fonctionnelle avant usage. |
| Notification interne | Activable | Recommandé pour un premier déploiement. |
| SMS / WhatsApp | Désactivable | Nécessite un connecteur externe à développer ou brancher ultérieurement. |

## Utilisation

Depuis le tableau de bord, l’utilisateur visualise les principales échéances et les montants fiscaux non payés. Les déclarations peuvent être créées manuellement depuis **Déclarations > Créer une déclaration**. Le formulaire permet de choisir le type d’impôt, la période, le mode de déclaration, le montant système, le pourcentage déclaré, les ajustements, les pénalités et la date d’échéance.

Le mode de déclaration permet de gérer plusieurs cas pratiques. En mode **réel**, le montant déclaré suit le montant calculé par le système. En mode **pourcentage**, le module applique un pourcentage au montant système. En mode **manuel**, le montant saisi par l’utilisateur prévaut. Cette flexibilité répond aux cas où l’entreprise ne déclare qu’une partie du montant calculé ou doit corriger une base imposable avant dépôt.

## Contrôle qualité effectué

Un contrôle de syntaxe PHP a été exécuté sur l’ensemble des fichiers PHP du module avec PHP CLI. Le résultat ne signale aucune erreur de syntaxe sur les fichiers générés.

| Contrôle | Résultat |
|---|---|
| Syntaxe PHP | Réussi. |
| Présence des pages principales | Réussi. |
| Présence des classes métier | Réussi. |
| Présence du générateur PDF | Réussi. |
| Présence du script CRON | Réussi. |

## Points à valider en environnement Dolibarr

Le module a été préparé et contrôlé hors instance Dolibarr complète. Avant mise en production, il faut effectuer une activation sur une instance de test, vérifier la création effective des tables, contrôler les droits utilisateurs, tester la création d’une déclaration, générer un PDF, valider les exports CSV, puis ajuster les taux et échéances selon le cabinet comptable.

| Point de recette | Critère attendu |
|---|---|
| Activation du module | Tables créées et menu visible. |
| Droits | Les profils peuvent lire, écrire, valider ou administrer selon les permissions attribuées. |
| Déclaration | Création, modification et validation fonctionnent. |
| PDF | Le document s’ouvre depuis la fiche déclaration. |
| Export CSV | Le fichier se télécharge depuis la liste des déclarations. |
| CRON | Les notifications sont générées pour les échéances ciblées. |

## Références

[1]: https://wiki.dolibarr.org/index.php/Module_development "Dolibarr ERP CRM Wiki — Module development"  
[2]: https://www.dolibarr.org/documentation-home.php "Dolibarr.org — Documentation officielle"
