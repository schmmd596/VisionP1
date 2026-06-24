-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : mer. 24 juin 2026 à 11:40
-- Version du serveur : 11.4.12-MariaDB
-- Version de PHP : 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";




DROP TABLE IF EXISTS `llx_exportation_account_operations`;
CREATE TABLE `llx_exportation_account_operations` (
  `rowid` int(11) NOT NULL,
  `fk_soc` int(11) NOT NULL,
  `operation_date` date NOT NULL,
  `operation_type` varchar(20) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `debit` double(24,8) DEFAULT 0.00000000,
  `credit` double(24,8) DEFAULT 0.00000000,
  `currency_code` varchar(10) DEFAULT 'MRU',
  `exchange_rate` double(24,12) DEFAULT 1.000000000000,
  `amount_local` double(24,8) DEFAULT 0.00000000,
  `fk_origin` int(11) DEFAULT 0,
  `origin_type` varchar(50) DEFAULT NULL,
  `fk_user_creat` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `fk_bank` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_employees`
--

DROP TABLE IF EXISTS `llx_exportation_employees`;
CREATE TABLE `llx_exportation_employees` (
  `rowid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `salary` double(24,8) DEFAULT 0.00000000,
  `fk_user` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `date_creation` datetime DEFAULT NULL,
  `fk_user_creat` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Déchargement des données de la table `llx_exportation_employees`
--

INSERT INTO `llx_exportation_employees` (`rowid`, `name`, `salary`, `fk_user`, `status`, `date_creation`, `fk_user_creat`) VALUES
(1, 'SIDI ALI', 0.00000000, 5, 1, '2026-05-19 16:07:11', 1),
(2, 'SIDI N', 0.00000000, 6, 1, '2026-05-19 16:07:11', 1),
(3, '???? ????', 60000.00000000, NULL, 1, '2026-05-30 12:17:40', 1),
(4, 'Beddah', 150000.00000000, NULL, 1, '2026-06-10 13:02:54', 1),
(5, 'Med lemin', 60000.00000000, NULL, 1, '2026-06-10 13:04:34', 1);

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_employee_ops`
--

DROP TABLE IF EXISTS `llx_exportation_employee_ops`;
CREATE TABLE `llx_exportation_employee_ops` (
  `rowid` int(11) NOT NULL,
  `fk_user` int(11) NOT NULL,
  `op_date` date NOT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `op_type` varchar(20) DEFAULT 'SALAIRE',
  `label` varchar(255) DEFAULT NULL,
  `fk_bank` int(11) DEFAULT 0,
  `fk_user_creat` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `fk_employee` int(11) DEFAULT NULL,
  `cycle_close` tinyint(4) DEFAULT 0,
  `fk_bank_line` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `llx_exportation_employee_ops`
--

INSERT INTO `llx_exportation_employee_ops` (`rowid`, `fk_user`, `op_date`, `amount`, `op_type`, `label`, `fk_bank`, `fk_user_creat`, `date_creation`, `fk_employee`, `cycle_close`, `fk_bank_line`) VALUES
(1, 0, '2026-05-31', 75000.00000000, 'SALAIRE', 'salaire du 5 mois', 1, 1, '2026-06-10 13:07:17', 4, 0, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_expenses`
--

DROP TABLE IF EXISTS `llx_exportation_expenses`;
CREATE TABLE `llx_exportation_expenses` (
  `rowid` int(11) NOT NULL,
  `fk_user` int(11) NOT NULL COMMENT 'User ID who created the expense',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount in local currency',
  `description` text DEFAULT NULL COMMENT 'Expense description',
  `category` varchar(255) DEFAULT NULL COMMENT 'Expense category',
  `expense_date` date NOT NULL COMMENT 'Date of the expense',
  `status` varchar(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, VALIDATED, REFUSED',
  `created_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When created',
  `validated_date` datetime DEFAULT NULL COMMENT 'When validated/refused',
  `entity` int(11) NOT NULL DEFAULT 1 COMMENT 'Entity ID',
  `fk_bank` int(11) DEFAULT NULL COMMENT 'Bank/cash account the expense is withdrawn from',
  `fk_bank_line` int(11) DEFAULT NULL COMMENT 'Linked bank transaction line (llx_bank)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='Employee expenses module table';

--
-- Déchargement des données de la table `llx_exportation_expenses`
--

INSERT INTO `llx_exportation_expenses` (`rowid`, `fk_user`, `amount`, `description`, `category`, `expense_date`, `status`, `created_date`, `validated_date`, `entity`, `fk_bank`, `fk_bank_line`) VALUES
(1, 1, 27000.00, 'المصاريف اليومية', 'المصاريف اليومية', '2026-06-09', 'VALIDATED', '2026-06-09 14:51:36', NULL, 1, NULL, NULL),
(2, 1, 3000.00, 'المصاريف اليومية', 'المصاريف اليومية', '2026-06-10', 'VALIDATED', '2026-06-10 12:57:15', NULL, 1, NULL, NULL),
(3, 1, 280000.00, 'الايجار', 'الايجار', '2026-06-10', 'VALIDATED', '2026-06-10 15:48:40', NULL, 1, NULL, NULL),
(4, 1, 3000.00, 'الحارس', 'الحارس', '2026-06-10', 'VALIDATED', '2026-06-10 15:50:36', NULL, 1, NULL, NULL),
(5, 1, 15000.00, 'wifi', 'wifi', '2026-06-10', 'VALIDATED', '2026-06-10 15:51:01', NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_invoice_expenses`
--

DROP TABLE IF EXISTS `llx_exportation_invoice_expenses`;
CREATE TABLE `llx_exportation_invoice_expenses` (
  `rowid` int(11) NOT NULL,
  `fk_facture_fourn` int(11) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `currency_code` varchar(10) DEFAULT 'MRU',
  `exchange_rate` double(24,12) DEFAULT 1.000000000000,
  `amount_local` double(24,8) DEFAULT 0.00000000,
  `fk_facture_fourn_det` int(11) DEFAULT 0,
  `fk_bank` int(11) DEFAULT 0,
  `expense_target` varchar(20) DEFAULT 'NOUS',
  `fk_line` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_invoice_info`
--

DROP TABLE IF EXISTS `llx_exportation_invoice_info`;
CREATE TABLE `llx_exportation_invoice_info` (
  `fk_facture_fourn` int(11) NOT NULL,
  `currency_code` varchar(10) DEFAULT 'MRU',
  `exchange_rate` double(24,12) DEFAULT 1.000000000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_invoice_line_info`
--

DROP TABLE IF EXISTS `llx_exportation_invoice_line_info`;
CREATE TABLE `llx_exportation_invoice_line_info` (
  `fk_facture_fourn_det` int(11) NOT NULL,
  `cbm_carton` double(24,8) DEFAULT 0.00000000,
  `qty_carton` int(11) DEFAULT 1,
  `nb_cartons` double(24,8) DEFAULT 0.00000000,
  `pu_devise` double(24,8) DEFAULT 0.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_invoice_payments`
--

DROP TABLE IF EXISTS `llx_exportation_invoice_payments`;
CREATE TABLE `llx_exportation_invoice_payments` (
  `rowid` int(11) NOT NULL,
  `fk_facture_fourn` int(11) NOT NULL,
  `datep` date NOT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `currency_code` varchar(10) DEFAULT 'MRU',
  `exchange_rate` double(24,12) DEFAULT 1.000000000000,
  `amount_local` double(24,8) DEFAULT 0.00000000,
  `note` text DEFAULT NULL,
  `fk_bank` int(11) DEFAULT 0,
  `fk_paiementfourn` int(11) DEFAULT 0,
  `fk_paiement_fourn` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_offset`
--

DROP TABLE IF EXISTS `llx_exportation_offset`;
CREATE TABLE `llx_exportation_offset` (
  `rowid` int(11) NOT NULL,
  `fk_soc` int(11) NOT NULL,
  `amount` double(24,8) NOT NULL,
  `date_offset` datetime DEFAULT NULL,
  `fk_user_creat` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_partners`
--

DROP TABLE IF EXISTS `llx_exportation_partners`;
CREATE TABLE `llx_exportation_partners` (
  `rowid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `percentage` double(10,4) DEFAULT 0.0000,
  `fk_user` int(11) DEFAULT 0,
  `active` int(11) DEFAULT 1,
  `note` text DEFAULT NULL,
  `capital_initial` double(24,8) DEFAULT 0.00000000,
  `date_creation` datetime DEFAULT NULL,
  `tms` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_effective` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_partner_distributions`
--

DROP TABLE IF EXISTS `llx_exportation_partner_distributions`;
CREATE TABLE `llx_exportation_partner_distributions` (
  `rowid` int(11) NOT NULL,
  `fk_partner` int(11) NOT NULL,
  `dist_date` date NOT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `dist_type` varchar(20) DEFAULT 'RETRAIT',
  `label` varchar(255) DEFAULT NULL,
  `fk_user_creat` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL,
  `fk_bank` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_shipment`
--

DROP TABLE IF EXISTS `llx_exportation_shipment`;
CREATE TABLE `llx_exportation_shipment` (
  `rowid` int(11) NOT NULL,
  `ref` varchar(50) NOT NULL,
  `container_number` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `shipping_date` date DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `status` int(11) DEFAULT 0,
  `fk_warehouse` int(11) DEFAULT NULL,
  `note_public` text DEFAULT NULL,
  `note_private` text DEFAULT NULL,
  `entity` int(11) DEFAULT 1,
  `date_creation` datetime DEFAULT NULL,
  `tms` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fk_user_creat` int(11) DEFAULT NULL,
  `prix_cbm` double(24,8) DEFAULT 0.00000000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_shipment_docs`
--

DROP TABLE IF EXISTS `llx_exportation_shipment_docs`;
CREATE TABLE `llx_exportation_shipment_docs` (
  `rowid` int(11) NOT NULL,
  `fk_shipment` int(11) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `date_upload` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_shipment_expenses`
--

DROP TABLE IF EXISTS `llx_exportation_shipment_expenses`;
CREATE TABLE `llx_exportation_shipment_expenses` (
  `rowid` int(11) NOT NULL,
  `fk_shipment` int(11) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `currency_code` varchar(3) DEFAULT 'USD',
  `exchange_rate` double(24,12) DEFAULT 1.000000000000,
  `amount_local` double(24,8) DEFAULT 0.00000000,
  `beneficiary` varchar(255) DEFAULT NULL,
  `fk_bank` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_shipment_lines`
--

DROP TABLE IF EXISTS `llx_exportation_shipment_lines`;
CREATE TABLE `llx_exportation_shipment_lines` (
  `rowid` int(11) NOT NULL,
  `fk_shipment` int(11) NOT NULL,
  `fk_facture_fourn_det` int(11) NOT NULL,
  `cbm` double(24,8) DEFAULT 0.00000000,
  `qty_shipped` double(24,8) DEFAULT 0.00000000,
  `cbm_carton` double(24,8) DEFAULT 0.00000000,
  `qty_carton` int(11) DEFAULT 1,
  `nb_cartons` double(24,8) DEFAULT 0.00000000,
  `fk_warehouse` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_shipment_payments`
--

DROP TABLE IF EXISTS `llx_exportation_shipment_payments`;
CREATE TABLE `llx_exportation_shipment_payments` (
  `rowid` int(11) NOT NULL,
  `fk_shipment` int(11) NOT NULL,
  `fk_bank` int(11) DEFAULT 0,
  `datep` date NOT NULL,
  `amount` double(24,8) DEFAULT 0.00000000,
  `currency_code` varchar(10) DEFAULT 'MRU',
  `exchange_rate` double(24,12) DEFAULT 1.000000000000,
  `amount_local` double(24,8) DEFAULT 0.00000000,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `llx_exportation_supplier_type`
--

DROP TABLE IF EXISTS `llx_exportation_supplier_type`;
CREATE TABLE `llx_exportation_supplier_type` (
  `fk_soc` int(11) NOT NULL,
  `type` varchar(20) DEFAULT 'INTERNE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `llx_exportation_account_operations`
--
ALTER TABLE `llx_exportation_account_operations`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_employees`
--
ALTER TABLE `llx_exportation_employees`
  ADD PRIMARY KEY (`rowid`),
  ADD UNIQUE KEY `uk_emp_fk_user` (`fk_user`);

--
-- Index pour la table `llx_exportation_employee_ops`
--
ALTER TABLE `llx_exportation_employee_ops`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_expenses`
--
ALTER TABLE `llx_exportation_expenses`
  ADD PRIMARY KEY (`rowid`),
  ADD KEY `fk_user` (`fk_user`),
  ADD KEY `expense_date` (`expense_date`),
  ADD KEY `status` (`status`),
  ADD KEY `entity` (`entity`);

--
-- Index pour la table `llx_exportation_invoice_expenses`
--
ALTER TABLE `llx_exportation_invoice_expenses`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_invoice_payments`
--
ALTER TABLE `llx_exportation_invoice_payments`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_offset`
--
ALTER TABLE `llx_exportation_offset`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_partners`
--
ALTER TABLE `llx_exportation_partners`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_partner_distributions`
--
ALTER TABLE `llx_exportation_partner_distributions`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_shipment`
--
ALTER TABLE `llx_exportation_shipment`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_shipment_docs`
--
ALTER TABLE `llx_exportation_shipment_docs`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_shipment_lines`
--
ALTER TABLE `llx_exportation_shipment_lines`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_shipment_payments`
--
ALTER TABLE `llx_exportation_shipment_payments`
  ADD PRIMARY KEY (`rowid`);

--
-- Index pour la table `llx_exportation_supplier_type`
--
ALTER TABLE `llx_exportation_supplier_type`
  ADD PRIMARY KEY (`fk_soc`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `llx_exportation_account_operations`
--
ALTER TABLE `llx_exportation_account_operations`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_employees`
--
ALTER TABLE `llx_exportation_employees`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `llx_exportation_employee_ops`
--
ALTER TABLE `llx_exportation_employee_ops`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `llx_exportation_expenses`
--
ALTER TABLE `llx_exportation_expenses`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `llx_exportation_invoice_expenses`
--
ALTER TABLE `llx_exportation_invoice_expenses`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_invoice_payments`
--
ALTER TABLE `llx_exportation_invoice_payments`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_offset`
--
ALTER TABLE `llx_exportation_offset`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_partners`
--
ALTER TABLE `llx_exportation_partners`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_partner_distributions`
--
ALTER TABLE `llx_exportation_partner_distributions`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_shipment`
--
ALTER TABLE `llx_exportation_shipment`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_shipment_docs`
--
ALTER TABLE `llx_exportation_shipment_docs`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_shipment_lines`
--
ALTER TABLE `llx_exportation_shipment_lines`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_shipment_payments`
--
ALTER TABLE `llx_exportation_shipment_payments`
  MODIFY `rowid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `llx_exportation_supplier_type`
--
ALTER TABLE `llx_exportation_supplier_type`
  MODIFY `fk_soc` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

COMMIT;

