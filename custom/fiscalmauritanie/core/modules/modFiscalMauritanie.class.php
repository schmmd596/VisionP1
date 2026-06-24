<?php
/* Copyright (C) 2026 VisionPro ERP
 * Module Fiscal Mauritanie for Dolibarr ERP/CRM
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module FiscalMauritanie.
 */
class modFiscalMauritanie extends DolibarrModules
{
    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 540500;
        $this->rights_class = 'fiscalmauritanie';
        $this->family = 'financial';
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'ModuleFiscalMauritanieDesc';
        $this->descriptionlong = 'ModuleFiscalMauritanieDescLong';
        $this->editor_name = 'VisionPro ERP';
        $this->editor_url = 'https://visionpro-erp.com';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fiscalmauritanie@fiscalmauritanie';
        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 1,
            'theme' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 1,
            'css' => array('/fiscalmauritanie/css/fiscalmauritanie.css.php'),
            'js' => array('/fiscalmauritanie/js/fiscalmauritanie.js.php'),
            'hooks' => array('invoicecard', 'supplierinvoicecard', 'thirdpartycard', 'main'),
        );

        $this->dirs = array('/fiscalmauritanie/temp');
        $this->config_page_url = array('setup.php@fiscalmauritanie');
        $this->langfiles = array('fiscalmauritanie@fiscalmauritanie');
        $this->depends = array('modSociete');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(14, 0);
        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();
        $this->const = array(
            1 => array('FISCALMAURITANIE_DEFAULT_CURRENCY', 'chaine', 'MRU', 'Default fiscal currency', 0, 'current', 1),
            2 => array('FISCALMAURITANIE_NOTIFY_DAYS', 'chaine', '30,15,7,3,0', 'Notification days before due date', 0, 'current', 1),
            3 => array('FISCALMAURITANIE_ENABLE_EMAIL', 'yesno', '1', 'Enable email notifications', 0, 'current', 1),
            4 => array('FISCALMAURITANIE_ENABLE_INTERNAL', 'yesno', '1', 'Enable internal notifications', 0, 'current', 1),
            5 => array('FISCALMAURITANIE_ENABLE_WHATSAPP', 'yesno', '0', 'Enable WhatsApp notifications', 0, 'current', 1),
            6 => array('FISCALMAURITANIE_ENABLE_SMS', 'yesno', '0', 'Enable SMS notifications', 0, 'current', 1),
            7 => array('FISCALMAURITANIE_PDF_MODEL', 'chaine', 'standard', 'Default PDF model', 0, 'current', 1),
        );

        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();
        $this->cronjobs = array(
            0 => array(
                'label' => 'FiscalMauritanieCronNotifications',
                'jobtype' => 'method',
                'class' => '/fiscalmauritanie/class/fiscalmauritaniecron.class.php',
                'objectname' => 'FiscalMauritanieCron',
                'method' => 'runNotifications',
                'parameters' => '',
                'comment' => 'Check fiscal deadlines and send notifications',
                'frequency' => 86400,
                'unitfrequency' => 3600,
                'status' => 1,
                'test' => '$conf->fiscalmauritanie->enabled',
                'priority' => 50,
            ),
            1 => array(
                'label' => 'FiscalMauritanieCronDrafts',
                'jobtype' => 'method',
                'class' => '/fiscalmauritanie/class/fiscalmauritaniecron.class.php',
                'objectname' => 'FiscalMauritanieCron',
                'method' => 'prepareDraftDeclarations',
                'parameters' => '',
                'comment' => 'Prepare recurring draft fiscal declarations',
                'frequency' => 86400,
                'unitfrequency' => 3600,
                'status' => 0,
                'test' => '$conf->fiscalmauritanie->enabled',
                'priority' => 60,
            ),
        );

        $r = 0;
        $this->rights[$r][0] = 540501;
        $this->rights[$r][1] = 'Lire les déclarations fiscales';
        $this->rights[$r][4] = 'declaration';
        $this->rights[$r][5] = 'read';
        $r++;
        $this->rights[$r][0] = 540502;
        $this->rights[$r][1] = 'Créer les déclarations fiscales';
        $this->rights[$r][4] = 'declaration';
        $this->rights[$r][5] = 'write';
        $r++;
        $this->rights[$r][0] = 540503;
        $this->rights[$r][1] = 'Valider les déclarations fiscales';
        $this->rights[$r][4] = 'declaration';
        $this->rights[$r][5] = 'validate';
        $r++;
        $this->rights[$r][0] = 540504;
        $this->rights[$r][1] = 'Supprimer les déclarations fiscales';
        $this->rights[$r][4] = 'declaration';
        $this->rights[$r][5] = 'delete';
        $r++;
        $this->rights[$r][0] = 540505;
        $this->rights[$r][1] = 'Administrer le paramétrage fiscal';
        $this->rights[$r][4] = 'setup';
        $this->rights[$r][5] = 'admin';
        $r++;
        $this->rights[$r][0] = 540506;
        $this->rights[$r][1] = 'Consulter l’audit fiscal';
        $this->rights[$r][4] = 'audit';
        $this->rights[$r][5] = 'read';

        $r = 0;
        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'FiscalMauritanie',
            'mainmenu' => 'fiscalmauritanie',
            'leftmenu' => '',
            'url' => '/fiscalmauritanie/index.php',
            'langs' => 'fiscalmauritanie@fiscalmauritanie',
            'position' => 100,
            'enabled' => '$conf->fiscalmauritanie->enabled',
            'perms' => '$user->rights->fiscalmauritanie->declaration->read',
            'target' => '',
            'user' => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=fiscalmauritanie',
            'type' => 'left',
            'titre' => 'Dashboard',
            'mainmenu' => 'fiscalmauritanie',
            'leftmenu' => 'dashboard',
            'url' => '/fiscalmauritanie/index.php',
            'langs' => 'fiscalmauritanie@fiscalmauritanie',
            'position' => 101,
            'enabled' => '$conf->fiscalmauritanie->enabled',
            'perms' => '$user->rights->fiscalmauritanie->declaration->read',
            'target' => '',
            'user' => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=fiscalmauritanie',
            'type' => 'left',
            'titre' => 'Declarations',
            'mainmenu' => 'fiscalmauritanie',
            'leftmenu' => 'declarations',
            'url' => '/fiscalmauritanie/declaration/list.php',
            'langs' => 'fiscalmauritanie@fiscalmauritanie',
            'position' => 102,
            'enabled' => '$conf->fiscalmauritanie->enabled',
            'perms' => '$user->rights->fiscalmauritanie->declaration->read',
            'target' => '',
            'user' => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=fiscalmauritanie',
            'type' => 'left',
            'titre' => 'TaxRules',
            'mainmenu' => 'fiscalmauritanie',
            'leftmenu' => 'rules',
            'url' => '/fiscalmauritanie/rules/list.php',
            'langs' => 'fiscalmauritanie@fiscalmauritanie',
            'position' => 103,
            'enabled' => '$conf->fiscalmauritanie->enabled',
            'perms' => '$user->rights->fiscalmauritanie->setup->admin',
            'target' => '',
            'user' => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=fiscalmauritanie',
            'type' => 'left',
            'titre' => 'Notifications',
            'mainmenu' => 'fiscalmauritanie',
            'leftmenu' => 'notifications',
            'url' => '/fiscalmauritanie/notification/list.php',
            'langs' => 'fiscalmauritanie@fiscalmauritanie',
            'position' => 104,
            'enabled' => '$conf->fiscalmauritanie->enabled',
            'perms' => '$user->rights->fiscalmauritanie->declaration->read',
            'target' => '',
            'user' => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=fiscalmauritanie',
            'type' => 'left',
            'titre' => 'Setup',
            'mainmenu' => 'fiscalmauritanie',
            'leftmenu' => 'setup',
            'url' => '/fiscalmauritanie/admin/setup.php',
            'langs' => 'fiscalmauritanie@fiscalmauritanie',
            'position' => 199,
            'enabled' => '$conf->fiscalmauritanie->enabled',
            'perms' => '$user->rights->fiscalmauritanie->setup->admin',
            'target' => '',
            'user' => 2,
        );
    }

    /**
     * Function called when module is enabled.
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf;

        // Création explicite des tables du module avant toute insertion de données.
        // Sans cet appel, certaines installations Dolibarr n’exécutent pas automatiquement
        // les scripts SQL du dossier /sql lors de l’activation d’un module externe.
        $result = $this->_load_tables('/fiscalmauritanie/sql/');
        if ($result < 0) {
            return -1;
        }

        $sql = array();
        $entity = empty($conf->entity) ? 1 : (int) $conf->entity;
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES (".$entity.", 'ITS_PROGRESSIF', 'Impôt sur les traitements et salaires - barème progressif', 'ITS', 0, 0, NULL, NULL, 'monthly', 'progressive', 100, 15, NULL, 1, 'Barème à ajuster selon la réglementation en vigueur', NOW())";
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES (".$entity.", 'CNSS_EMPLOYEUR', 'Cotisation CNSS employeur', 'CNSS', 15, 0, NULL, NULL, 'monthly', 'rate', 100, 15, NULL, 1, 'Taux indicatif à vérifier', NOW())";
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES (".$entity.", 'CNAM_EMPLOYEUR', 'Cotisation CNAM employeur', 'CNAM', 5, 0, NULL, NULL, 'monthly', 'rate', 100, 15, NULL, 1, 'Taux indicatif à vérifier', NOW())";
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES (".$entity.", 'IS_STANDARD', 'Impôt sur les sociétés', 'IS', 25, 0, NULL, NULL, 'annual', 'profit_rate', 100, 31, 3, 1, 'Taux indicatif à vérifier', NOW())";
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES (".$entity.", 'IMF_STANDARD', 'Impôt minimum forfaitaire', 'IMF', 2.5, 0, NULL, NULL, 'annual', 'minimum_compare', 100, 31, 3, 1, 'Taux indicatif à vérifier', NOW())";
        $sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."fiscalmauritanie_rule (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_creation) VALUES (".$entity.", 'TA_STANDARD', 'Taxe d’apprentissage', 'TA', 0.6, 0, NULL, NULL, 'monthly', 'rate', 100, 15, NULL, 1, 'Taux indicatif à vérifier', NOW())";

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled.
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
