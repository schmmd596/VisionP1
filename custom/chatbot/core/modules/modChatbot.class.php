<?php
/**
 * Module Chatbot IA - Dolibarr Integration
 * Integrates an AI chatbot powered by Claude API with real-time Dolibarr data access
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modChatbot extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Module ID (use a number > 500000 for custom modules)
        $this->numero = 502024;
        $this->rights_class = 'chatbot';
        $this->family = 'technic';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Assistant IA pour Dolibarr - Réponses en temps réel et création de factures/produits';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-robot';
        $this->editor_name = 'Custom';
        $this->editor_url = '';

        // Depends on modules
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array();

        // Config page
        $this->config_page_url = array('setup.php@chatbot');

        // Constants
        $this->const = array(
            0 => array(
                'CHATBOT_API_KEY',
                'chaine',
                '',
                'Clé API Claude (Anthropic)',
                0,
                'current',
                0
            ),
            1 => array(
                'CHATBOT_MODEL',
                'chaine',
                'claude-sonnet-4-6',
                'Modèle Claude à utiliser',
                0,
                'current',
                0
            ),
            2 => array(
                'CHATBOT_ENABLED',
                'chaine',
                '1',
                'Activer le chatbot',
                0,
                'current',
                0
            ),
            3 => array(
                'CHATBOT_MAX_TOKENS',
                'chaine',
                '2048',
                'Nombre maximum de tokens',
                0,
                'current',
                0
            ),
        );

        // Hooks - Dolibarr charge automatiquement chatbot/class/actions_chatbot.class.php
        $this->module_parts = array(
            'hooks' => array('main', 'globalcard', 'thirdpartycard', 'invoicecard', 'productcard'),
        );

        // Rights
        $this->rights = array();
        $this->rights[1][0] = $this->numero + 1;
        $this->rights[1][1] = 'Utiliser le chatbot IA';
        $this->rights[1][2] = 'r';
        $this->rights[1][3] = 1;
        $this->rights[1][4] = 'use';

        $this->rights[2][0] = $this->numero + 2;
        $this->rights[2][1] = 'Administrer le chatbot IA';
        $this->rights[2][2] = 'a';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'admin';

        // Menu
        $this->menus = array();
    }
}
