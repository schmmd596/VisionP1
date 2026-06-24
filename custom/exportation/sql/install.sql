-- Create expenses table
CREATE TABLE IF NOT EXISTS `llxyv_exportation_expenses` (
  `rowid` int(11) NOT NULL AUTO_INCREMENT,
  `fk_user` int(11) NOT NULL COMMENT 'User ID who created the expense',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount in local currency',
  `description` text COMMENT 'Expense description',
  `category` varchar(255) DEFAULT NULL COMMENT 'Expense category',
  `expense_date` date NOT NULL COMMENT 'Date of the expense',
  `status` varchar(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, VALIDATED, REFUSED',
  `created_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When created',
  `validated_date` datetime DEFAULT NULL COMMENT 'When validated/refused',
  `entity` int(11) NOT NULL DEFAULT 1 COMMENT 'Entity ID',
  `fk_bank` int(11) DEFAULT NULL COMMENT 'Bank/cash account the expense is withdrawn from',
  `fk_bank_line` int(11) DEFAULT NULL COMMENT 'Linked bank transaction line (llxyv_bank)',
  PRIMARY KEY (`rowid`),
  KEY `fk_user` (`fk_user`),
  KEY `expense_date` (`expense_date`),
  KEY `status` (`status`),
  KEY `entity` (`entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Employee expenses module table';
