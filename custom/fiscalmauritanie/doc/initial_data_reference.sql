INSERT IGNORE INTO llx_fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, active, note, date_creation)
VALUES
(1, 'ITS_DEFAULT', 'Impôt sur Traitements et Salaires', 'ITS', 0, 0, NULL, NULL, 'monthly', 'progressive', 100, 15, 1, 'Barème ITS configurable dans les tranches.', NOW()),
(1, 'CNSS_EMPLOYEE', 'CNSS part salarié', 'CNSS', 1, 0, NULL, NULL, 'monthly', 'rate', 100, 15, 1, 'Taux à ajuster selon la réglementation applicable.', NOW()),
(1, 'CNSS_EMPLOYER', 'CNSS part employeur', 'CNSS', 2, 0, NULL, NULL, 'monthly', 'rate', 100, 15, 1, 'Taux à ajuster selon la réglementation applicable.', NOW()),
(1, 'CNAM_DEFAULT', 'Assurance maladie CNAM', 'CNAM', 0, 0, NULL, NULL, 'monthly', 'rate', 100, 15, 1, 'Taux à configurer par l’administrateur.', NOW()),
(1, 'TA_DEFAULT', 'Taxe d’apprentissage', 'TA', 0.6, 0, NULL, NULL, 'annual', 'rate', 100, 31, 1, 'Calcul basé sur la masse salariale.', NOW()),
(1, 'IS_DEFAULT', 'Impôt sur les sociétés', 'IS', 25, 0, NULL, NULL, 'annual', 'profit_rate', 100, 31, 1, 'Calcul résultat fiscal x taux IS.', NOW()),
(1, 'IMF_DEFAULT', 'Impôt Minimum Forfaitaire', 'IMF', 0, 0, NULL, NULL, 'annual', 'minimum_compare', 100, 31, 1, 'Comparer l’IMF avec l’IS selon le paramétrage.', NOW()),
(1, 'PATENTE_DEFAULT', 'Taxe professionnelle / Patente', 'PATENTE', 0, 0, NULL, NULL, 'annual', 'manual', 100, 31, 1, 'Montant généralement paramétré ou saisi manuellement.', NOW()),
(1, 'IMF_HONORAIRES', 'Retenue IMF sur honoraires', 'IMF_HONORAIRES', 3, 0, NULL, NULL, 'monthly', 'withholding', 100, 15, 1, 'Retenue automatique sur factures fournisseurs.', NOW());
