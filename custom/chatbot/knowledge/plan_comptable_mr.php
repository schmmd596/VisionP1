<?php
/**
 * Plan Comptable Mauritanien (PCM) - Base de connaissances
 * Conforme au Systeme Comptable Mauritanien (SCM)
 * Reference: Reglement ANC Mauritanie
 */

function get_plan_comptable_knowledge() {
    return <<<'PCM'

=== PLAN COMPTABLE MAURITANIEN (PCM) ===

Le Plan Comptable Mauritanien est structure en 8 classes de comptes :

--- CLASSE 1 : COMPTES DE CAPITAUX ---
10 - Capital et reserves
  100 - Capital social
  101 - Capital individuel
  104 - Primes liees au capital
  105 - Ecarts de reevaluation
  106 - Reserves
    1061 - Reserve legale
    1062 - Reserves statutaires
    1063 - Reserves reglementees
    1068 - Autres reserves
  108 - Compte de l'exploitant
  109 - Actionnaires, capital souscrit non appele

11 - Report a nouveau
  110 - Report a nouveau (solde crediteur)
  119 - Report a nouveau (solde debiteur)

12 - Resultat de l'exercice
  120 - Resultat de l'exercice (benefice)
  129 - Resultat de l'exercice (perte)

13 - Subventions d'investissement
  131 - Subventions d'equipement
  138 - Autres subventions d'investissement
  139 - Subventions d'investissement inscrites au compte de resultat

14 - Provisions reglementees
  141 - Provisions pour reconstitution de gisements
  142 - Provisions pour investissement
  148 - Autres provisions reglementees

15 - Provisions pour risques et charges
  151 - Provisions pour risques
    1511 - Provisions pour litiges
    1514 - Provisions pour amendes et penalites
    1515 - Provisions pour pertes de change
    1518 - Autres provisions pour risques
  153 - Provisions pour pensions et obligations similaires
  155 - Provisions pour impots
  156 - Provisions pour renouvellement des immobilisations
  158 - Autres provisions pour charges

16 - Emprunts et dettes assimilees
  161 - Emprunts obligataires
  162 - Emprunts aupres des etablissements de credit
  163 - Autres emprunts
  164 - Dettes sur contrats de location-financement
  165 - Depots et cautionnements recus
  168 - Autres emprunts et dettes assimilees

17 - Dettes rattachees a des participations
  171 - Dettes rattachees a des participations (groupe)
  174 - Dettes rattachees a des participations (hors groupe)

18 - Comptes de liaison des etablissements et succursales
  181 - Comptes de liaison

19 - Provisions financieres pour risques et charges
  191 - Provisions pour risques
  194 - Provisions pour pertes de change
  195 - Provisions pour impots
  196 - Provisions pour pensions et obligations similaires
  197 - Provisions pour charges a repartir sur plusieurs exercices

--- CLASSE 2 : COMPTES D'IMMOBILISATIONS ---
20 - Immobilisations incorporelles
  201 - Frais d'etablissement
  202 - Frais de recherche et de developpement
  203 - Brevets, licences, logiciels
  204 - Fonds commercial (goodwill)
  205 - Avances et acomptes sur immobilisations incorporelles
  206 - Droit au bail
  208 - Autres immobilisations incorporelles

21 - Immobilisations corporelles
  211 - Terrains
  212 - Constructions
  213 - Installations techniques, materiel et outillage
  214 - Materiel de transport
  215 - Materiel de bureau et informatique
  216 - Agencements, amenagements, installations
  218 - Autres immobilisations corporelles

22 - Immobilisations mises en concession
23 - Immobilisations en cours
  231 - Immobilisations corporelles en cours
  232 - Immobilisations incorporelles en cours
  237 - Avances et acomptes verses sur commandes d'immobilisations

24 - (Reserve)
25 - (Reserve)

26 - Participations et creances rattachees
  261 - Titres de participation
  262 - Autres formes de participation
  265 - Creances rattachees a des participations
  266 - Titres de participation evalues par equivalence

27 - Autres immobilisations financieres
  271 - Titres immobilises (droit de propriete)
  272 - Titres immobilises (droit de creance)
  273 - Titres immobilises de l'activite de portefeuille
  274 - Prets
  275 - Depots et cautionnements verses
  276 - Interets courus

28 - Amortissements des immobilisations
  280 - Amortissements des immobilisations incorporelles
  281 - Amortissements des immobilisations corporelles
  282 - Amortissements des immobilisations mises en concession

29 - Depreciations des immobilisations
  290 - Depreciations des immobilisations incorporelles
  291 - Depreciations des immobilisations corporelles
  293 - Depreciations des immobilisations en cours
  296 - Depreciations des participations et creances rattachees
  297 - Depreciations des autres immobilisations financieres

--- CLASSE 3 : COMPTES DE STOCKS ---
31 - Matieres premieres et fournitures
  311 - Matieres premieres (A)
  312 - Matieres premieres (B)
  316 - Matieres et fournitures consommables
  317 - Fournitures de bureau

32 - Autres approvisionnements
  321 - Matieres consommables
  322 - Fournitures consommables
  326 - Emballages

33 - En-cours de production de biens
  331 - Produits en cours
  335 - Travaux en cours

34 - En-cours de production de services
  341 - Etudes en cours
  345 - Prestations de services en cours

35 - Produits finis
  351 - Produits finis (A)
  355 - Produits finis (B)
  358 - Produits residuels

36 - Produits intermediaires
37 - Stocks de marchandises
  371 - Marchandises (A)
  372 - Marchandises (B)

38 - Stocks en cours de route, en depot ou en consignation
39 - Depreciations des stocks
  391 - Depreciations des matieres premieres
  392 - Depreciations des autres approvisionnements
  395 - Depreciations des produits finis
  397 - Depreciations des stocks de marchandises

--- CLASSE 4 : COMPTES DE TIERS ---
40 - Fournisseurs et comptes rattaches
  401 - Fournisseurs
  403 - Fournisseurs, effets a payer
  404 - Fournisseurs d'immobilisations
  405 - Fournisseurs d'immobilisations, effets a payer
  408 - Fournisseurs, factures non parvenues
  409 - Fournisseurs debiteurs (avances, acomptes, RRR a obtenir)

41 - Clients et comptes rattaches
  411 - Clients
  413 - Clients, effets a recevoir
  416 - Clients douteux ou litigieux
  417 - Clients, retenues de garantie
  418 - Clients, produits non encore factures
  419 - Clients crediteurs (avances, acomptes, RRR a accorder)

42 - Personnel et comptes rattaches
  421 - Personnel, remunerations dues
  422 - Comites d'entreprise, d'etablissement
  425 - Personnel, avances et acomptes
  427 - Personnel, oppositions
  428 - Personnel, charges a payer et produits a recevoir

43 - Organismes sociaux
  431 - Securite sociale (CNSS Mauritanie)
  437 - Autres organismes sociaux
  438 - Organismes sociaux, charges a payer et produits a recevoir

44 - Etat et collectivites publiques
  441 - Etat, subventions a recevoir
  442 - Etat, impots et taxes recouvrables sur des tiers
  443 - Operations particulieres avec l'Etat
  444 - Etat, impot sur les benefices (BIC/IS)
  445 - Etat, taxes sur le chiffre d'affaires (TVA)
    4451 - TVA a payer
    4452 - TVA due intracommunautaire
    4455 - TVA a decaisser
    4456 - TVA deductible
      44562 - TVA deductible sur immobilisations
      44566 - TVA deductible sur biens et services
    4457 - TVA collectee
    4458 - TVA a regulariser
  446 - Etat, autres impots, taxes et versements assimiles
  447 - Autres impots, taxes et versements assimiles
  448 - Etat, charges a payer et produits a recevoir
  449 - Quotas d'emission a restituer a l'Etat

45 - Groupe et associes
  451 - Operations groupe
  455 - Associes, comptes courants
  456 - Associes, operations sur le capital
  457 - Associes, dividendes a payer
  458 - Associes, operations faites en commun

46 - Debiteurs divers et crediteurs divers
  461 - Creances sur cessions d'immobilisations
  462 - Creances sur cessions de VMP
  464 - Dettes sur acquisitions de VMP
  465 - Crediteurs divers
  467 - Autres comptes debiteurs ou crediteurs
  468 - Divers, charges a payer et produits a recevoir

47 - Comptes transitoires ou d'attente
  471 - Comptes d'attente
  472 - Comptes d'attente (crediteurs)
  476 - Difference de conversion - Actif
  477 - Difference de conversion - Passif

48 - Comptes de regularisation
  481 - Charges a repartir sur plusieurs exercices
  486 - Charges constatees d'avance
  487 - Produits constates d'avance

49 - Depreciations des comptes de tiers
  491 - Depreciations des comptes clients
  495 - Depreciations des comptes du groupe et des associes
  496 - Depreciations des comptes de debiteurs divers

--- CLASSE 5 : COMPTES FINANCIERS ---
50 - Valeurs mobilieres de placement (VMP)
  501 - Parts dans des entreprises liees
  502 - Actions propres
  503 - Actions
  506 - Obligations
  508 - Autres VMP
  509 - Versements restant a effectuer sur VMP

51 - Banques, etablissements financiers et assimiles
  511 - Valeurs a l'encaissement
  512 - Banques (comptes courants)
    5121 - Banque Mauritanienne (compte principal)
  514 - Cheques postaux
  515 - Tresor public
  516 - Societes de bourse
  517 - Autres organismes financiers
  518 - Interets courus
  519 - Concours bancaires courants (decouvert)

52 - Instruments de tresorerie
53 - Caisse
  531 - Caisse en monnaie nationale (MRU - Ouguiya)
  532 - Caisse en devises

54 - Regies d'avances et accreditifs
  541 - Regies d'avances
  542 - Accreditifs

58 - Virements internes
  581 - Virements de fonds

59 - Depreciations des comptes financiers
  590 - Depreciations des VMP

--- CLASSE 6 : COMPTES DE CHARGES ---
60 - Achats
  601 - Achats de matieres premieres
  602 - Achats d'autres approvisionnements
  604 - Achats d'etudes et prestations de services
  605 - Achats de materiel, equipements et travaux
  606 - Achats non stockes de matieres et fournitures
    6061 - Fournitures non stockables (eau, energie)
    6063 - Fournitures d'entretien et petit equipement
    6064 - Fournitures administratives
  607 - Achats de marchandises
  608 - Frais accessoires d'achat
  609 - Rabais, remises, ristournes obtenus sur achats

61 - Services exterieurs
  611 - Sous-traitance generale
  612 - Redevances de credit-bail (leasing)
  613 - Locations
  614 - Charges locatives et de copropriete
  615 - Entretien et reparations
  616 - Primes d'assurance
  617 - Etudes et recherches
  618 - Divers
  619 - Rabais, remises, ristournes obtenus

62 - Autres services exterieurs
  621 - Personnel exterieur a l'entreprise
  622 - Remunerations d'intermediaires et honoraires
  623 - Publicite, publications, relations publiques
  624 - Transports de biens et transport collectif du personnel
  625 - Deplacements, missions et receptions
  626 - Frais postaux et telecommunications
  627 - Services bancaires et assimiles
  628 - Divers

63 - Impots, taxes et versements assimiles
  631 - Impots, taxes et versements assimiles sur remunerations
  633 - Impots, taxes et versements assimiles sur remunerations (autres)
  635 - Autres impots, taxes et versements assimiles
    6351 - Impots directs (patente, contribution fonciere)
    6352 - Taxes sur le chiffre d'affaires non recuperables
    6353 - Impots indirects
    6354 - Droits d'enregistrement et de timbre
    6358 - Autres droits

64 - Charges de personnel
  641 - Remunerations du personnel
  642 - Remunerations du travail de l'exploitant
  643 - Remunerations du personnel exterieur
  644 - Remunerations des dirigeants
  645 - Charges de securite sociale et de prevoyance (CNSS)
  646 - Cotisations sociales de l'exploitant
  647 - Autres charges sociales
  648 - Autres charges de personnel

65 - Autres charges de gestion courante
  651 - Redevances pour concessions, brevets, licences
  654 - Pertes sur creances irrecouvrables
  655 - Quote-part de resultat sur operations faites en commun
  658 - Charges diverses de gestion courante

66 - Charges financieres
  661 - Charges d'interets
    6611 - Interets des emprunts et dettes
    6615 - Interets des comptes courants et depots
    6616 - Interets bancaires
  664 - Pertes sur creances liees a des participations
  665 - Escomptes accordes
  666 - Pertes de change
  667 - Charges nettes sur cessions de VMP
  668 - Autres charges financieres

67 - Charges exceptionnelles
  671 - Charges exceptionnelles sur operations de gestion
  672 - Charges sur exercices anterieurs
  675 - Valeurs comptables des elements d'actif cedes
  678 - Autres charges exceptionnelles

68 - Dotations aux amortissements, depreciations et provisions
  681 - Dotations aux amortissements, depreciations et provisions (charges d'exploitation)
    6811 - Dotations aux amortissements des immobilisations incorporelles et corporelles
    6812 - Dotations aux amortissements des charges a repartir
    6815 - Dotations aux provisions d'exploitation
    6816 - Dotations aux depreciations des immobilisations incorporelles et corporelles
    6817 - Dotations aux depreciations des actifs circulants
  686 - Dotations aux amortissements, depreciations et provisions (charges financieres)
  687 - Dotations aux amortissements, depreciations et provisions (charges exceptionnelles)

69 - Participation des salaries, impots sur les benefices
  691 - Participation des salaries aux resultats
  695 - Impots sur les benefices (IS ou BIC)
  699 - Produits, report en arriere des deficits

--- CLASSE 7 : COMPTES DE PRODUITS ---
70 - Ventes de produits fabriques, prestations de services, marchandises
  701 - Ventes de produits finis
  702 - Ventes de produits intermediaires
  703 - Ventes de produits residuels
  704 - Travaux
  705 - Etudes
  706 - Prestations de services
  707 - Ventes de marchandises
  708 - Produits des activites annexes
  709 - Rabais, remises, ristournes accordes

71 - Production stockee (variation de stock)
  713 - Variation des en-cours de production
  714 - Variation de production de services

72 - Production immobilisee
  721 - Immobilisations incorporelles
  722 - Immobilisations corporelles

73 - (Reserve)
74 - Subventions d'exploitation
  741 - Subventions d'exploitation
  748 - Autres subventions d'exploitation

75 - Autres produits de gestion courante
  751 - Redevances pour concessions, brevets, licences
  752 - Revenus des immeubles non affectes aux activites professionnelles
  753 - Jetons de presence et remunerations d'administrateurs
  754 - Ristournes percues des cooperatives
  755 - Quote-part de resultat sur operations faites en commun
  758 - Produits divers de gestion courante

76 - Produits financiers
  761 - Produits de participations
  762 - Produits des autres immobilisations financieres
  763 - Revenus des autres creances
  764 - Revenus des VMP
  765 - Escomptes obtenus
  766 - Gains de change
  767 - Produits nets sur cessions de VMP
  768 - Autres produits financiers

77 - Produits exceptionnels
  771 - Produits exceptionnels sur operations de gestion
  772 - Produits sur exercices anterieurs
  775 - Produits des cessions d'elements d'actif
  778 - Autres produits exceptionnels

78 - Reprises sur amortissements, depreciations et provisions
  781 - Reprises sur amortissements, depreciations et provisions (produits d'exploitation)
  786 - Reprises sur depreciations et provisions (produits financiers)
  787 - Reprises sur depreciations et provisions (produits exceptionnels)

79 - Transferts de charges
  791 - Transferts de charges d'exploitation
  796 - Transferts de charges financieres
  797 - Transferts de charges exceptionnelles

--- CLASSE 8 : COMPTES SPECIAUX ---
80 - Engagements hors bilan
  801 - Engagements donnes
  802 - Engagements recus

88 - Resultat en instance d'affectation
  880 - Resultat en instance d'affectation

89 - Bilan
  890 - Bilan d'ouverture
  891 - Bilan de cloture

=== REGLES COMPTABLES IMPORTANTES ===

1. PRINCIPE DE PARTIE DOUBLE :
   - Tout mouvement comptable doit etre enregistre avec au minimum un debit et un credit de montants egaux
   - Le total des debits = le total des credits

2. ECRITURES COURANTES EN MAURITANIE :

   a) Vente de marchandises (avec TVA 16%) :
      Debit  411 (Clients)              = Montant TTC
      Credit 707 (Ventes de marchandises) = Montant HT
      Credit 4457 (TVA collectee)          = TVA

   b) Achat de marchandises (avec TVA deductible) :
      Debit  607 (Achats de marchandises) = Montant HT
      Debit  44566 (TVA deductible)        = TVA
      Credit 401 (Fournisseurs)            = Montant TTC

   c) Paiement client par banque :
      Debit  512 (Banque)    = Montant
      Credit 411 (Clients)   = Montant

   d) Paiement fournisseur par banque :
      Debit  401 (Fournisseurs) = Montant
      Credit 512 (Banque)       = Montant

   e) Paiement salaires :
      Debit  641 (Remunerations)      = Salaire brut
      Credit 431 (CNSS)               = Part salariale
      Credit 442 (Etat, IRPP)         = Retenue IRPP
      Credit 421 (Personnel)          = Net a payer

   f) Charges sociales patronales :
      Debit  645 (Charges CNSS)        = Montant
      Credit 431 (CNSS a payer)        = Montant

   g) Acquisition immobilisation :
      Debit  21x (Immobilisation)       = Montant HT
      Debit  44562 (TVA immobilisations) = TVA
      Credit 404 (Fournisseur immob.)    = Montant TTC

   h) Amortissement :
      Debit  6811 (Dotation amortissement) = Montant
      Credit 28x (Amortissements)          = Montant

3. MONNAIE : Ouguiya mauritanien (MRU) - nouvelle ouguiya depuis 2018 (1 MRU = 10 anciens MRO)

4. EXERCICE COMPTABLE : Du 1er janvier au 31 decembre (sauf derogation)

5. DOCUMENTS OBLIGATOIRES :
   - Livre journal
   - Grand livre
   - Balance generale
   - Bilan
   - Compte de resultat
   - Annexes

PCM;
}
