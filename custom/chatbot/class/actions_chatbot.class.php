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

        // Echo directement - printCommonFooter() ne lit pas $hookmanager->resPrint
        echo '
        <!-- ===== Chatbot IA Widget ===== -->
        <link rel="stylesheet" href="'.$url_base.'/css/widget.css?v=1.0.3">

        <div id="chatbot-toggle" title="Assistant IA">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span id="chatbot-badge" style="display:none">1</span>
        </div>

        <div id="chatbot-window" style="display:none;">
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
                    <button id="chatbot-clear" title="Nouvelle conversation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="1 4 1 10 7 10"></polyline>
                            <path d="M3.51 15a9 9 0 1 0 .49-3.85"></path>
                        </svg>
                    </button>
                    <button id="chatbot-minimize" title="Reduire">&#8211;</button>
                    <button id="chatbot-close" title="Fermer">&#215;</button>
                </div>
            </div>

            <div id="chatbot-messages">
                <div class="chat-message bot-message">
                    <div class="message-content">
                        Bonjour ! Je suis votre Tafkir IA.<br>
                        Comment puis-je vous aider ?
                    </div>
                    <div class="message-time">Maintenant</div>
                </div>
            </div>

            <div id="chatbot-suggestions">
                <button class="suggestion-btn" data-msg="Montre-moi la liste des produits">Produits</button>
                <button class="suggestion-btn" data-msg="Liste des clients">Clients</button>
                <button class="suggestion-btn" data-msg="Creer une facture client">Facture client</button>
                <button class="suggestion-btn" data-msg="Statistiques du mois">Stats</button>
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

        <script>
            var CHATBOT_AJAX_URL = "' . $ajax_url . '";
            var CHATBOT_TOKEN   = "' . newToken() . '";
        </script>
        <script src="' . $url_base . '/js/widget.js?v=1.0.3"></script>
        <!-- ===== Fin Chatbot IA Widget ===== -->
        ';

        return 0;
    }
}
