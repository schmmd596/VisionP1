<?php
/**
 * Hook class - Injecte le widget Chatbot IA dans toutes les pages Dolibarr
 * Nom de fichier requis par Dolibarr : actions_{module}.class.php
 * Nom de classe requis : Actions{Module}
 */

class ActionsChatbot
{
    public $db;
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Appelé en bas de chaque page Dolibarr - injecte le widget HTML/JS
     */
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user;

        // Ne pas afficher si module désactivé, utilisateur non connecté, ou clé API manquante
        if (empty($conf->chatbot->enabled)) return 0;
        if (empty($user->id)) return 0;
        if (empty($conf->global->CHATBOT_API_KEY)) return 0;
        if (isset($conf->global->CHATBOT_ENABLED) && $conf->global->CHATBOT_ENABLED == '0') return 0;

        $url_base = dol_buildpath('/chatbot', 1);
        $ajax_url = dol_buildpath('/chatbot/ajax/chat.php', 1);

        echo '
        <!-- ===== Tafkir IA Widget ===== -->
        <link rel="stylesheet" href="'.$url_base.'/css/widget.css?v=3.0.0">

        <!-- Bouton flottant -->
        <div id="chatbot-toggle" title="Tafkir IA">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span id="chatbot-badge" style="display:none">1</span>
        </div>

        <!-- Fenetre principale -->
        <div id="chatbot-window" style="display:none;">

            <!-- Header -->
            <div id="chatbot-header">
                <div id="chatbot-header-info">
                    <div id="chatbot-avatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                    </div>
                    <div>
                        <div id="chatbot-name">Tafkir IA</div>
                        <div id="chatbot-status"><span class="status-dot"></span> En ligne</div>
                    </div>
                </div>
                <div id="chatbot-header-actions">
                    <button id="chatbot-minimize" title="Reduire">&#8211;</button>
                    <button id="chatbot-close" title="Fermer">&#215;</button>
                </div>
            </div>

            <!-- Corps : sidebar + zone messages -->
            <div id="chatbot-body">

                <!-- Sidebar conversations -->
                <div id="chatbot-sidebar">
                    <button id="chatbot-new-chat" title="Nouvelle conversation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Nouveau chat
                    </button>
                    <div id="chatbot-conv-list"></div>
                </div>

                <!-- Zone principale -->
                <div id="chatbot-main">
                    <div id="chatbot-messages"></div>

                    <div id="chatbot-suggestions">
          
                    </div>

                    <div id="chatbot-input-area">
                        <textarea id="chatbot-input" placeholder="Ecrivez votre message..." rows="1"></textarea>
                        <button id="chatbot-send" title="Envoyer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>

            </div><!-- end chatbot-body -->
        </div><!-- end chatbot-window -->

        <script>
            var CHATBOT_AJAX_URL = "' . $ajax_url . '";
            var CHATBOT_TOKEN   = "' . newToken() . '";
        </script>
        <script src="' . $url_base . '/js/widget.js?v=3.0.0"></script>
        <!-- ===== Fin Tafkir IA Widget ===== -->
        ';

        return 0;
    }
}
