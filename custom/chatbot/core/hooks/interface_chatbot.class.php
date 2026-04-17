<?php
/**
 * Hook class to inject the AI Chatbot Widget into all Dolibarr pages
 */

class interface_chatbot
{
    public $db;
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^interface_/i', '', get_class($this));
        $this->family = 'technic';
        $this->description = 'Injecte le widget chatbot IA dans toutes les pages';
        $this->version = '1.0.0';
        $this->picto = 'fa-robot';
    }

    /**
     * Executed at end of page body - injects the chatbot widget HTML + JS
     */
    public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user;

        // Only show if module enabled, user logged in, and API key set
        if (empty($conf->chatbot->enabled)) return 0;
        if (empty($user->id)) return 0;
        if (empty($conf->global->CHATBOT_API_KEY)) return 0;
        if (!empty($conf->global->CHATBOT_ENABLED) && $conf->global->CHATBOT_ENABLED == '0') return 0;

        $url_base = dol_buildpath('/chatbot', 1);

        // Inject widget HTML + load assets
        $this->resprints = '
        <!-- Chatbot IA Widget -->
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <link rel="stylesheet" href="'.$url_base.'/css/widget.css?v='.time().'">

        <!-- Chat Toggle Button -->
        <div id="chatbot-toggle" title="Assistant IA">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span id="chatbot-badge" style="display:none">1</span>
        </div>

        <!-- Chat Window -->
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
                    <button id="chatbot-minimize" title="Réduire">&#8211;</button>
                    <button id="chatbot-close" title="Fermer">&#215;</button>
                </div>
            </div>

            <div id="chatbot-messages">
                <div class="chat-message bot-message">
                    <div class="message-content">
                        Bonjour ! Je suis votre Tafkir IA. Je peux vous aider à :<br><br>
                        Comment puis-je vous aider ?
                    </div>
                    <div class="message-time">Maintenant</div>
                </div>
            </div>

            <div id="chatbot-suggestions">
                <button class="suggestion-btn" data-msg="Montre-moi la liste des produits"> Produits</button>
                <button class="suggestion-btn" data-msg="Liste des clients">Clients</button>
                <button class="suggestion-btn" data-msg="Créer une facture client">Facture client</button>
                <button class="suggestion-btn" data-msg="Statistiques du mois">Stats</button>
            </div>

            <div id="chatbot-input-area">
                <button type="button" id="chatbot-upload-btn" class="chatbot-action-btn" title="Joindre fichier">📎</button>
                <input type="file" id="chatbot-file-input" accept=".png,.jpg,.jpeg,.pdf" style="display:none;">
                <textarea id="chatbot-input" placeholder="Écrivez votre message..." rows="1"></textarea>
                <button type="button" id="chatbot-mic-btn" class="chatbot-action-btn" title="Enregistrer audio">🎤</button>
                <button id="chatbot-send" title="Envoyer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>

            <!-- Recording indicator -->
            <div id="chatbot-recording-indicator" style="display:none; padding:8px; text-align:center; background:#fecaca; color:#dc2626; font-size:12px;">
                <span>🔴 Enregistrement... <span id="chatbot-recording-time">0:00</span></span>
            </div>

            <!-- File preview -->
            <div id="chatbot-file-preview" style="display:none; padding:10px; border-top:1px solid #e2e8f0;">
                <div id="chatbot-preview-content"></div>
            </div>
        </div>

        <script>
            var CHATBOT_AJAX_URL = "'.dol_buildpath('/chatbot/ajax/chat.php', 1).'";
            var CHATBOT_TOKEN = "'.newToken().'";
        </script>
        <script src="'.$url_base.'/js/widget.js?v='.time().'"></script>
        <!-- End Chatbot IA Widget -->
        ';

        return 0;
    }
}
