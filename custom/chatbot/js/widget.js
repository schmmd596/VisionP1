/**
 * Chatbot IA Widget - Dolibarr
 * Floating chat interface with markdown rendering support
 */
(function () {
    'use strict';

    // ── State ──────────────────────────────────────────────
    var history = [];     // [{role, content}] sent to backend
    var isLoading = false;
    var isMinimized = false;

    // ── DOM refs ────────────────────────────────────────────
    var toggle   = document.getElementById('chatbot-toggle');
    var window_  = document.getElementById('chatbot-window');
    var messages = document.getElementById('chatbot-messages');
    var input    = document.getElementById('chatbot-input');
    var sendBtn  = document.getElementById('chatbot-send');
    var clearBtn = document.getElementById('chatbot-clear');
    var closeBtn = document.getElementById('chatbot-close');
    var minBtn   = document.getElementById('chatbot-minimize');
    var badge    = document.getElementById('chatbot-badge');
    var suggBtns = document.querySelectorAll('.suggestion-btn');

    // ── Toggle window ────────────────────────────────────────
    toggle.addEventListener('click', function () {
        var isVisible = window_.style.display !== 'none';
        window_.style.display = isVisible ? 'none' : 'flex';
        toggle.classList.toggle('active', !isVisible);
        badge.style.display = 'none';
        if (!isVisible) {
            input.focus();
            scrollToBottom();
        }
    });

    closeBtn.addEventListener('click', function () {
        window_.style.display = 'none';
        toggle.classList.remove('active');
    });

    minBtn.addEventListener('click', function () {
        isMinimized = !isMinimized;
        messages.style.display = isMinimized ? 'none' : '';
        document.getElementById('chatbot-suggestions').style.display = isMinimized ? 'none' : '';
        document.getElementById('chatbot-input-area').style.display = isMinimized ? 'none' : '';
        minBtn.innerHTML = isMinimized ? '&#9633;' : '&#8211;';
    });

    // ── Clear / new conversation ──────────────────────────────
    clearBtn.addEventListener('click', function () {
        history = [];
        messages.innerHTML = '';
        addBotMessage('Nouvelle conversation démarrée. Comment puis-je vous aider ?');
        document.getElementById('chatbot-suggestions').style.display = 'flex';
    });

    // ── Suggestion buttons ────────────────────────────────────
    suggBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var msg = this.getAttribute('data-msg');
            document.getElementById('chatbot-suggestions').style.display = 'none';
            sendMessage(msg);
        });
    });

    // ── Send on Enter (Shift+Enter = newline) ─────────────────
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            doSend();
        }
    });
    sendBtn.addEventListener('click', doSend);

    // Auto-resize textarea
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // ── Core send logic ───────────────────────────────────────
    function doSend() {
        var text = input.value.trim();
        if (!text || isLoading) return;
        input.value = '';
        input.style.height = 'auto';
        document.getElementById('chatbot-suggestions').style.display = 'none';
        sendMessage(text);
    }

    function sendMessage(text) {
        addUserMessage(text);
        history.push({ role: 'user', content: text });

        isLoading = true;
        sendBtn.disabled = true;
        var typingId = addTypingIndicator();

        var xhr = new XMLHttpRequest();
        xhr.open('POST', CHATBOT_AJAX_URL, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.timeout = 90000;

        xhr.onload = function () {
            removeTypingIndicator(typingId);
            isLoading = false;
            sendBtn.disabled = false;

            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    addBotMessage(data.message);
                    history.push({ role: 'assistant', content: data.message });
                    // Keep history at max 20 exchanges to avoid huge payloads
                    if (history.length > 40) history = history.slice(-40);
                } else {
                    addErrorMessage(data.error || 'Une erreur est survenue.');
                }
            } catch (e) {
                addErrorMessage('Réponse invalide du serveur.');
            }
        };

        xhr.onerror = function () {
            removeTypingIndicator(typingId);
            isLoading = false;
            sendBtn.disabled = false;
            addErrorMessage('Erreur réseau. Vérifiez votre connexion.');
        };

        xhr.ontimeout = function () {
            removeTypingIndicator(typingId);
            isLoading = false;
            sendBtn.disabled = false;
            addErrorMessage('Délai d\'attente dépassé. Réessayez.');
        };

        // Send history WITHOUT the last user message (already in new_message)
        var historyToSend = history.slice(0, -1);

        xhr.send(JSON.stringify({
            message: text,
            history: historyToSend,
            token: (typeof CHATBOT_TOKEN !== 'undefined') ? CHATBOT_TOKEN : '',
        }));
    }

    // ── DOM helpers ───────────────────────────────────────────
    function addUserMessage(text) {
        var div = document.createElement('div');
        div.className = 'chat-message user-message';
        div.innerHTML = '<div class="message-content">' + escapeHtml(text) + '</div>'
                      + '<div class="message-time">' + getTime() + '</div>';
        messages.appendChild(div);
        scrollToBottom();
    }

    function addBotMessage(text) {
        var div = document.createElement('div');
        div.className = 'chat-message bot-message';
        div.innerHTML = '<div class="message-content">' + renderMarkdown(text) + '</div>'
                      + '<div class="message-time">' + getTime() + '</div>';
        messages.appendChild(div);
        scrollToBottom();

        // Show badge if window is hidden
        if (window_.style.display === 'none') {
            badge.style.display = 'flex';
        }
    }

    function addErrorMessage(text) {
        var div = document.createElement('div');
        div.className = 'chat-message error-message';
        div.innerHTML = '<div class="message-content">⚠️ ' + escapeHtml(text) + '</div>';
        messages.appendChild(div);
        scrollToBottom();
    }

    function addTypingIndicator() {
        var id = 'typing-' + Date.now();
        var div = document.createElement('div');
        div.className = 'chat-message bot-message typing-message';
        div.id = id;
        div.innerHTML = '<div class="message-content"><span class="typing-dots"><span></span><span></span><span></span></span></div>';
        messages.appendChild(div);
        scrollToBottom();
        return id;
    }

    function removeTypingIndicator(id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    }

    function scrollToBottom() {
        setTimeout(function () {
            messages.scrollTop = messages.scrollHeight;
        }, 50);
    }

    function getTime() {
        var d = new Date();
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }

    // ── Security: HTML escape ──────────────────────────────────
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Minimal Markdown renderer (tables, bold, italic, code, lists) ──
    function renderMarkdown(text) {
        // Sanitize first
        text = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        // Code blocks
        text = text.replace(/```([\s\S]*?)```/g, function(_, code) {
            return '<pre><code>' + code.trim() + '</code></pre>';
        });
        // Inline code
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Tables (GFM)
        text = text.replace(/(\|.+\|\n)(\|[-|: ]+\|\n)((\|.+\|\n)*)/g, function(match) {
            var lines = match.trim().split('\n').filter(Boolean);
            var html = '<table class="chat-table"><thead><tr>';
            var headers = lines[0].split('|').filter(function(c){ return c.trim() !== ''; });
            headers.forEach(function(h){ html += '<th>' + h.trim() + '</th>'; });
            html += '</tr></thead><tbody>';
            for (var i = 2; i < lines.length; i++) {
                var cells = lines[i].split('|').filter(function(c){ return c.trim() !== ''; });
                html += '<tr>';
                cells.forEach(function(c){ html += '<td>' + c.trim() + '</td>'; });
                html += '</tr>';
            }
            html += '</tbody></table>';
            return html;
        });

        // Bold
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Italic
        text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');

        // Headers
        text = text.replace(/^### (.+)$/gm, '<h4>$1</h4>');
        text = text.replace(/^## (.+)$/gm, '<h3>$1</h3>');
        text = text.replace(/^# (.+)$/gm, '<h2>$1</h2>');

        // Unordered lists
        text = text.replace(/(^[*\-] .+$(\n[*\-] .+$)*)/gm, function(block) {
            var items = block.split('\n').map(function(l){ return '<li>' + l.replace(/^[*\-] /, '') + '</li>'; });
            return '<ul>' + items.join('') + '</ul>';
        });

        // Ordered lists
        text = text.replace(/(^\d+\. .+$(\n\d+\. .+$)*)/gm, function(block) {
            var items = block.split('\n').map(function(l){ return '<li>' + l.replace(/^\d+\. /, '') + '</li>'; });
            return '<ol>' + items.join('') + '</ol>';
        });

        // Line breaks
        text = text.replace(/\n\n/g, '</p><p>');
        text = text.replace(/\n/g, '<br>');
        text = '<p>' + text + '</p>';

        // Clean empty paragraphs
        text = text.replace(/<p><\/p>/g, '');
        text = text.replace(/<p>(<[ht][1-6r]|<ul|<ol|<pre|<table)/g, '$1');
        text = text.replace(/(<\/[ht][1-6r]>|<\/ul>|<\/ol>|<\/pre>|<\/table>)<\/p>/g, '$1');

        return text;
    }

})();
