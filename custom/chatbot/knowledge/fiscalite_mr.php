<?php
/**
 * Fiscalite Mauritanienne - Base de connaissances
 * Reference: Code General des Impots (CGI) de Mauritanie
 * Mise a jour selon les dernieres lois de finances
 */

function get_fiscalite_knowledge() {
    return <<<'FISC'

=== FISCALITE MAURITANIENNE - GUIDE COMPLET ===

--- 1. IMPOT SUR LES SOCIETES (IS) ---

Taux normal : 25% du benefice imposable
Minimum fiscal (IMF) : 2.5% du chiffre d'affaires (minimum 150 000 MRU/an)
  - Le plus eleve entre l'IS calcule et l'IMF est du
  - L'IMF constitue un minimum de perception

Regime d'imposition :
  - Regime reel : CA > 30 000 000 MRU (obligatoire)
  - Regime simplifie : CA entre 3 000 000 et 30 000 000 MRU
  - Regime forfaitaire : CA < 3 000 000 MRU

Benefice imposable = Produits - Charges deductibles
Charges deductibles :
  - Charges d'exploitation reelles et justifiees
  - Amortissements (lineaire ou degressif selon le bien)
  - Provisions pour risques et charges probables
  - Interets d'emprunts (sous conditions)
  - Dons aux organismes reconnus (max 0.5% du CA)

Charges NON deductibles :
  - Amendes et penalites fiscales
  - Impot sur les societes lui-meme
  - Depenses somptuaires
  - Charges non justifiees
  - Amortissements excessifs

Acomptes : 3 acomptes provisionnels (mars, juin, septembre)
Declaration annuelle : avant le 31 mars de l'annee N+1

--- 2. IMPOT SUR LE REVENU DES PERSONNES PHYSIQUES (IRPP) ---

Bareme progressif (revenus annuels) :
  - 0 a 60 000 MRU         : 0% (exonere)
  - 60 001 a 150 000 MRU    : 15%
  - 150 001 a 300 000 MRU   : 25%
  - 300 001 a 500 000 MRU   : 30%
  - Au-dela de 500 000 MRU  : 40%

Impot sur les Traitements et Salaires (ITS) :
  - Retenu a la source par l'employeur
  - Meme bareme que l'IRPP
  - Base = Salaire brut - Cotisations sociales obligatoires - Abattement forfaitaire (20%)

--- 3. TAXE SUR LA VALEUR AJOUTEE (TVA) ---

Taux normal : 16%
Taux zero (0%) : Exportations de biens et services
Exonerations :
  - Produits alimentaires de premiere necessite (riz, lait, farine, sucre, huile, the)
  - Medicaments essentiels
  - Materiel agricole
  - Livres et fournitures scolaires
  - Operations bancaires et financieres (certaines)
  - Loyers d'habitation
  - Services medicaux et hospitaliers

Seuil d'assujettissement : CA annuel > 30 000 000 MRU
  - En dessous, option possible pour le regime de la TVA

Declaration et paiement :
  - Mensuelle : avant le 15 du mois suivant
  - TVA a payer = TVA collectee - TVA deductible
  - Credit de TVA reportable (pas de remboursement sauf exportateurs)

TVA sur importations :
  - Percue par la douane au moment de l'importation
  - Deductible pour les assujettis
  - Base = Valeur en douane + Droits de douane + Taxes d'importation

Factures : Doivent obligatoirement mentionner :
  - Numero sequentiel, date
  - Identite vendeur/acheteur (NIF obligatoire)
  - Montant HT, taux TVA, montant TVA, montant TTC
  - Description precise des biens/services

--- 4. CONTRIBUTION FONCIERE (Impot Foncier) ---

Proprietes baties :
  - Taux : 3% a 10% de la valeur locative
  - Base : valeur locative estimee par l'administration
  - Exonerations temporaires : constructions neuves (2 ans)

Proprietes non baties :
  - Taux variable selon la localisation
  - Terrains urbains non construits soumis a une taxe specifique

--- 5. PATENTE (Contribution des Patentes) ---

Due par toute personne physique ou morale exercant une activite commerciale, industrielle ou de prestations de services.
  - Droit fixe : selon la categorie d'activite (tableau officiel)
  - Droit proportionnel : base sur la valeur locative des locaux professionnels
  - Paiement : annuel, avant le 31 mars
  - Exoneration : premiere annee d'activite pour les entreprises nouvelles

--- 6. TAXE SUR LES ACTIVITES FINANCIERES (TAF) ---

  - Taux : 14%
  - S'applique aux etablissements bancaires et financiers
  - Base : produits financiers, commissions, interets

--- 7. DROITS D'ENREGISTREMENT ET TIMBRE ---

Mutations d'immeubles :
  - Taux : 3% a 6% selon la nature et la valeur
  - Apports en societe : 1% (capital initial), 2% (augmentation)
  - Baux : droit fixe + proportionnel

Timbre :
  - Effets de commerce : 0.1% du montant
  - Actes notaries et judiciaires : droit fixe
  - Quittances : droit fixe

--- 8. DROITS DE DOUANE ---

Tarif Exterieur Commun (TEC) de la CEDEAO (en cours d'application) :
  - Categorie 0 : 0% (biens sociaux essentiels)
  - Categorie 1 : 5% (biens de premiere necessite, matieres premieres, biens d'equipement)
  - Categorie 2 : 10% (produits intermediaires)
  - Categorie 3 : 20% (biens de consommation finale)
  - Categorie 4 : 35% (biens specifiques pour le developpement economique)

Redevance statistique : 1%
Prelevement communautaire de solidarite : 1%
Prelevement communautaire CEDEAO : 0.5%

--- 9. RETENUES A LA SOURCE ---

Revenus des capitaux mobiliers :
  - Dividendes : 10%
  - Interets : 15%
  - Jetons de presence : 15%

Paiements aux non-residents :
  - Prestations de services : 15%
  - Redevances (royalties) : 15%
  - Interets : 15%
  - Dividendes : 10%

Marches publics : 3% (retenue liberatoire pour les non-domicilies)

--- 10. COTISATIONS SOCIALES (CNSS) ---

Caisse Nationale de Securite Sociale :
  Part patronale :
  - Allocations familiales : 8% (plafond : 70 000 MRU/mois)
  - Accidents du travail : 2% a 5% selon le secteur (plafond : 70 000 MRU/mois)
  - Retraite : 6% (plafond : 70 000 MRU/mois)
  Total patronal : 16% a 19%

  Part salariale :
  - Retraite : 1% (plafond : 70 000 MRU/mois)
  Total salarial : 1%

CNAM (Caisse Nationale d'Assurance Maladie) :
  - Part patronale : 4%
  - Part salariale : 2%

--- 11. CONVENTIONS FISCALES ---

La Mauritanie a signe des conventions de non-double imposition avec :
  - France, Tunisie, Senegal, Algerie, Maroc, Libye
  - Emirats Arabes Unis, Arabie Saoudite, Kowei
  - Belgique, Espagne, Italie

--- 12. OBLIGATIONS DECLARATIVES ---

Declarations mensuelles :
  - TVA (avant le 15 du mois suivant)
  - ITS retenue a la source (avant le 15 du mois suivant)
  - Retenues a la source (avant le 15 du mois suivant)

Declarations annuelles :
  - IS / IRPP (avant le 31 mars N+1)
  - Bilan et comptes annuels (avant le 31 mars N+1)
  - Etat des salaires (avant le 31 janvier N+1)
  - Patente (avant le 31 mars)
  - Contribution fonciere (avant le 31 mars)
  - Liasse fiscale complete

--- 13. PENALITES ET SANCTIONS ---

Retard de declaration :
  - 5% par mois de retard (max 50%)
  - Interets de retard : 1% par mois
  - Astreinte journaliere possible

Insuffisance de declaration :
  - Majoration de 10% a 80% selon la gravite
  - 40% en cas de mauvaise foi
  - 80% en cas de manoeuvres frauduleuses

Defaut de facturation :
  - Amende de 500 000 MRU par infraction
  - Interdiction de deduire la TVA correspondante

--- 14. NUMERO D'IDENTIFICATION FISCALE (NIF) ---

  - Obligatoire pour toute entite juridique
  - Requis sur toutes les factures et declarations
  - Delivre par la Direction Generale des Impots (DGI)

--- 15. REGIMES SPECIAUX ET INCITATIONS ---

Code des Investissements :
  - Zone franche de Nouadhibou : exoneration totale pendant 25 ans
  - Regime d'agrement : exonerations temporaires (IS, TVA, droits de douane)
  - Secteurs prioritaires : mines, peche, agriculture, tourisme, BTP

Micro-entreprises :
  - Regime simplifie pour CA < 3 000 000 MRU
  - Forfait fiscal unique

=== ECRITURES FISCALES TYPES ===

a) Paiement TVA mensuelle :
   Debit  4455 (TVA a decaisser) = TVA collectee - TVA deductible
   Credit 512 (Banque)           = Montant paye

   Calcul TVA :
   4457 (TVA collectee) - 4456 (TVA deductible) = 4455 (TVA a decaisser)
   Si negatif = credit de TVA reportable

b) Provision pour impot IS :
   Debit  695 (Impot sur les benefices) = IS estime
   Credit 444 (Etat, IS)                = IS estime

c) Paiement acompte IS :
   Debit  444 (Etat, IS)  = Montant acompte
   Credit 512 (Banque)    = Montant acompte

d) Charges sociales CNSS :
   Debit  645 (Charges CNSS)    = Part patronale
   Debit  421 (Personnel)       = Part salariale (retenue)
   Credit 431 (CNSS a payer)    = Total

e) Paiement patente :
   Debit  6351 (Impots directs - Patente) = Montant
   Credit 512 (Banque)                    = Montant

f) Amortissement fiscal :
   Debit  6811 (Dotation amortissements) = Annuite
   Credit 281x (Amortissements)          = Annuite

   Durees fiscales recommandees en Mauritanie :
   - Constructions : 20-25 ans (4-5%)
   - Materiel industriel : 10 ans (10%)
   - Materiel de transport : 4-5 ans (20-25%)
   - Materiel de bureau : 5-10 ans (10-20%)
   - Materiel informatique : 3-5 ans (20-33%)
   - Mobilier : 10 ans (10%)
   - Logiciels : 3 ans (33%)
   - Fonds commercial : non amortissable (depreciable si perte de valeur)

FISC;
}
