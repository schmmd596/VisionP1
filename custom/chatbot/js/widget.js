/**
 * Tafkir IA Widget — Multi-conversation system (Claude-like)
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'tafkir_conversations_v2';

    // ── Conversation store ──────────────────────────────────
    // { conversations: [{id,title,history,messages,createdAt}], activeId: string }
    var store = loadStore();

    // ── DOM refs ────────────────────────────────────────────
    var toggle      = document.getElementById('chatbot-toggle');
    var windowEl    = document.getElementById('chatbot-window');
    var msgArea     = document.getElementById('chatbot-messages');
    var inputEl     = document.getElementById('chatbot-input');
    var sendBtn     = document.getElementById('chatbot-send');
    var badge       = document.getElementById('chatbot-badge');
    var convList    = document.getElementById('chatbot-conv-list');
    var newChatBtn  = document.getElementById('chatbot-new-chat');
    var closeBtn    = document.getElementById('chatbot-close');
    var minBtn      = document.getElementById('chatbot-minimize');

    // ── Upload & Audio refs ──────────────────────────────────
    var uploadBtn   = document.getElementById('chatbot-upload-btn') || null;
    var fileInput   = document.getElementById('chatbot-file-input') || null;
    var micBtn      = document.getElementById('chatbot-mic-btn') || null;
    var recordingInd = document.getElementById('chatbot-recording-indicator') || null;
    var recordingTime = document.getElementById('chatbot-recording-time') || null;
    var filePreview = document.getElementById('chatbot-file-preview') || null;

    var isLoading   = false;
    var isMinimized = false;
    var isRecording = false;
    var mediaRecorder = null;
    var audioChunks = [];
    var recordingStartTime = 0;

    // ── Create upload/audio buttons dynamically ───────────────
    function initUploadAudioButtons() {
        var inputArea = document.getElementById('chatbot-input-area');
        if (!inputArea) return;  // Exit if no input area

        // Create upload button
        if (!document.getElementById('chatbot-upload-btn')) {
            var uploadBtn = document.createElement('button');
            uploadBtn.id = 'chatbot-upload-btn';
            uploadBtn.className = 'chatbot-action-btn';
            uploadBtn.type = 'button';
            uploadBtn.title = 'Joindre un fichier (PNG, JPG, PDF)';
            uploadBtn.textContent = '📎';
            inputArea.insertBefore(uploadBtn, inputArea.firstChild);

            var fileInput = document.createElement('input');
            fileInput.id = 'chatbot-file-input';
            fileInput.type = 'file';
            fileInput.accept = '.png,.jpg,.jpeg,.pdf';
            fileInput.style.display = 'none';
            inputArea.appendChild(fileInput);

            uploadBtn.addEventListener('click', function() { fileInput.click(); });
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) handleFileSelect(this.files[0]);
            });
        }

        // Create mic button
        if (!document.getElementById('chatbot-mic-btn')) {
            var micBtn = document.createElement('button');
            micBtn.id = 'chatbot-mic-btn';
            micBtn.className = 'chatbot-action-btn';
            micBtn.type = 'button';
            micBtn.title = 'Enregistrer audio (cliquez pour parler)';
            micBtn.textContent = '🎤';

            var sendBtn = document.getElementById('chatbot-send');
            if (sendBtn) {
                inputArea.insertBefore(micBtn, sendBtn);
            } else {
                inputArea.appendChild(micBtn);
            }

            micBtn.addEventListener('click', toggleAudioRecording);
        }

        // Create recording indicator
        if (!document.getElementById('chatbot-recording-indicator')) {
            var recInd = document.createElement('div');
            recInd.id = 'chatbot-recording-indicator';
            recInd.style.display = 'none';
            recInd.style.padding = '8px';
            recInd.style.textAlign = 'center';
            recInd.style.background = '#fecaca';
            recInd.style.color = '#dc2626';
            recInd.style.fontSize = '12px';
            recInd.innerHTML = '🔴 Enregistrement... <span id="chatbot-recording-time">0:00</span>';
            inputArea.parentNode.insertBefore(recInd, inputArea);
        }
    }

    // ── Init ────────────────────────────────────────────────
    if (!store.activeId || !getConv(store.activeId)) {
        var first = store.conversations[0];
        if (first) {
            store.activeId = first.id;
        } else {
            createNewConversation();
        }
    }
    renderConvList();
    renderMessages();

    // Initialize upload/audio buttons after DOM is ready
    setTimeout(function() {
        initUploadAudioButtons();
        // Re-assign refs after creating buttons
        uploadBtn = document.getElementById('chatbot-upload-btn');
        fileInput = document.getElementById('chatbot-file-input');
        micBtn = document.getElementById('chatbot-mic-btn');
        recordingInd = document.getElementById('chatbot-recording-indicator');
        recordingTime = document.getElementById('chatbot-recording-time');
    }, 100);

    // ── Toggle window ────────────────────────────────────────
    toggle.addEventListener('click', function () {
        var visible = windowEl.style.display !== 'none';
        windowEl.style.display = visible ? 'none' : 'flex';
        toggle.classList.toggle('active', !visible);
        badge.style.display = 'none';
        if (!visible) { inputEl.focus(); scrollToBottom(); }
    });

    closeBtn.addEventListener('click', function () {
        windowEl.style.display = 'none';
        toggle.classList.remove('active');
    });

    minBtn.addEventListener('click', function () {
        isMinimized = !isMinimized;
        document.getElementById('chatbot-main').style.display = isMinimized ? 'none' : 'flex';
        document.getElementById('chatbot-sidebar').style.display = isMinimized ? 'none' : 'flex';
        minBtn.innerHTML = isMinimized ? '&#9633;' : '&#8211;';
        windowEl.style.height = isMinimized ? 'auto' : '';
    });

    // ── New conversation ──────────────────────────────────────
    newChatBtn.addEventListener('click', function () {
        var emptyConv = store.conversations.find(function(c) { return c.messages.length === 0; });
        if (emptyConv) {
            switchConv(emptyConv.id);
        } else {
            createNewConversation();
            renderConvList();
            renderMessages();
            inputEl.focus();
        }
    });

    // ── Input ─────────────────────────────────────────────────
    inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); }
    });
    sendBtn.addEventListener('click', doSend);
    inputEl.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // ── Drag & Drop for messages area ─────────────────────────
    if (msgArea) {
        msgArea.addEventListener('dragover', function (e) { e.preventDefault(); this.style.backgroundColor = 'rgba(79,70,229,.05)'; });
        msgArea.addEventListener('dragleave', function () { this.style.backgroundColor = ''; });
        msgArea.addEventListener('drop', function (e) {
            e.preventDefault();
            this.style.backgroundColor = '';
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });
    }

    // ── Send ──────────────────────────────────────────────────
    function doSend() {
        var text = inputEl.value.trim();
        if (!text || isLoading) return;
        inputEl.value = '';
        inputEl.style.height = 'auto';
        sendMessage(text);
    }

    function sendMessage(text) {
        var conv = getActive();
        if (!conv) return;

        // Auto-title from first user message
        if (!conv.title || conv.title === 'Nouvelle conversation') {
            conv.title = text.length > 40 ? text.substring(0, 40) + '…' : text;
            renderConvList();
        }

        appendMsg('user', text, conv);
        conv.history.push({ role: 'user', content: text });
        saveStore();

        isLoading = true;
        sendBtn.disabled = true;
        var typingId = addTyping();

        // Limit history sent to API (last 16 messages for speed)
        var historyToSend = conv.history.slice(0, -1);
        if (historyToSend.length > 16) historyToSend = historyToSend.slice(-16);

        fetch(CHATBOT_AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: text,
                history: historyToSend,
                token: (typeof CHATBOT_TOKEN !== 'undefined') ? CHATBOT_TOKEN : ''
            })
        }).then(function (response) {
            var ct = response.headers.get('Content-Type') || '';

            if (ct.indexOf('text/event-stream') !== -1) {
                // ── Streaming response (OpenRouter/OpenAI) ──────
                var reader = response.body.getReader();
                var decoder = new TextDecoder();
                var sseBuf = '';
                var fullText = '';
                var botDiv = null, contentDiv = null, timeDiv = null;
                var done = false;

                function finish() {
                    if (done) return;
                    done = true;
                    removeTyping(typingId);
                    isLoading = false;
                    sendBtn.disabled = false;
                    if (fullText && botDiv) {
                        conv.messages.push({ cls: botDiv.className, html: contentDiv.outerHTML + timeDiv.outerHTML });
                        conv.history.push({ role: 'assistant', content: fullText });
                        if (conv.history.length > 20) conv.history = conv.history.slice(-20);
                        saveStore();
                        if (windowEl.style.display === 'none') badge.style.display = 'flex';
                    } else if (!fullText) {
                        appendMsg('error', 'Aucune réponse reçue. Réessayez.', conv);
                    }
                }

                function pump() {
                    return reader.read().then(function (result) {
                        if (result.done) { finish(); return; }
                        sseBuf += decoder.decode(result.value, { stream: true });
                        var lines = sseBuf.split('\n');
                        sseBuf = lines.pop();
                        for (var i = 0; i < lines.length; i++) {
                            var line = lines[i].trim();
                            if (line.indexOf('data: ') !== 0) continue;
                            var data = line.slice(6);
                            if (data === '[DONE]') { finish(); return; }
                            try {
                                var json = JSON.parse(data);
                                if (json.error) {
                                    done = true;
                                    removeTyping(typingId);
                                    isLoading = false; sendBtn.disabled = false;
                                    appendMsg('error', json.error, conv);
                                    return;
                                }
                                if (json.token) {
                                    if (!botDiv) {
                                        removeTyping(typingId);
                                        botDiv = document.createElement('div');
                                        botDiv.className = 'chat-message bot-message';
                                        contentDiv = document.createElement('div');
                                        contentDiv.className = 'message-content';
                                        timeDiv = document.createElement('div');
                                        timeDiv.className = 'message-time';
                                        timeDiv.textContent = getTime();
                                        botDiv.appendChild(contentDiv);
                                        botDiv.appendChild(timeDiv);
                                        msgArea.appendChild(botDiv);
                                    }
                                    fullText += json.token;
                                    contentDiv.innerHTML = renderMarkdown(fullText);
                                    scrollToBottom();
                                }
                            } catch (e) {}
                        }
                        return pump();
                    }).catch(function () {
                        removeTyping(typingId);
                        isLoading = false; sendBtn.disabled = false;
                        if (!done) appendMsg('error', 'Erreur de connexion.', conv);
                        done = true;
                    });
                }
                pump();

            } else {
                // ── JSON response (Anthropic / erreurs) ─────────
                removeTyping(typingId);
                isLoading = false; sendBtn.disabled = false;
                response.json().then(function (data) {
                    if (data.success) {
                        appendMsg('bot', data.message, conv);
                        conv.history.push({ role: 'assistant', content: data.message });
                        if (conv.history.length > 20) conv.history = conv.history.slice(-20);
                        saveStore();
                    } else {
                        appendMsg('error', data.error || 'Une erreur est survenue.', conv);
                    }
                }).catch(function () {
                    appendMsg('error', 'Réponse invalide du serveur.', conv);
                });
            }
        }).catch(function () {
            removeTyping(typingId);
            isLoading = false; sendBtn.disabled = false;
            appendMsg('error', 'Erreur réseau.', conv);
        });
    }

    // ── Conversation helpers ───────────────────────────────────
    function createNewConversation() {
        var id = 'conv_' + Date.now();
        var conv = { id: id, title: 'Nouvelle conversation', history: [], messages: [], createdAt: Date.now() };
        store.conversations.unshift(conv);
        store.activeId = id;
        saveStore();
        return conv;
    }

    function getConv(id) {
        return store.conversations.find(function (c) { return c.id === id; });
    }

    function getActive() { return getConv(store.activeId); }

    function switchConv(id) {
        store.activeId = id;
        saveStore();
        renderConvList();
        renderMessages();
        scrollToBottom();
        inputEl.focus();
    }

    function deleteConv(id) {
        store.conversations = store.conversations.filter(function (c) { return c.id !== id; });
        if (store.activeId === id) {
            if (store.conversations.length === 0) createNewConversation();
            store.activeId = store.conversations[0].id;
        }
        saveStore();
        renderConvList();
        renderMessages();
    }

    // ── Render sidebar ─────────────────────────────────────────
    function renderConvList() {
        convList.innerHTML = '';
        store.conversations.forEach(function (conv) {
            var item = document.createElement('div');
            item.className = 'conv-item' + (conv.id === store.activeId ? ' active' : '');
            item.setAttribute('data-id', conv.id);

            var titleSpan = document.createElement('span');
            titleSpan.className = 'conv-title';
            titleSpan.textContent = conv.title || 'Nouvelle conversation';
            titleSpan.title = conv.title || 'Nouvelle conversation';

            var delBtn = document.createElement('button');
            delBtn.className = 'conv-delete';
            delBtn.innerHTML = '&#10005;';
            delBtn.title = 'Supprimer';
            delBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (confirm('Supprimer cette conversation ?')) deleteConv(conv.id);
            });

            item.appendChild(titleSpan);
            item.appendChild(delBtn);
            item.addEventListener('click', function () { switchConv(conv.id); });
            convList.appendChild(item);
        });
    }

    // ── Render messages for active conv ────────────────────────
    function renderMessages() {
        var conv = getActive();
        if (!conv) return;
        msgArea.innerHTML = '';

        conv.messages.forEach(function (m) {
            var div = document.createElement('div');
            div.className = m.cls;
            div.innerHTML = m.html;
            msgArea.appendChild(div);
        });
        
        scrollToBottom();
    }

    // ── Append a message (display + persist) ───────────────────
    function appendMsg(type, content, conv) {
        var div = document.createElement('div');
        var time = '<div class="message-time">' + getTime() + '</div>';
        var html, cls;

        if (type === 'user') {
            cls = 'chat-message user-message';
            html = '<div class="message-content">' + escapeHtml(content) + '</div>' + time;
        } else if (type === 'bot') {
            cls = 'chat-message bot-message';
            html = '<div class="message-content">' + renderMarkdown(content) + '</div>' + time;
            if (windowEl.style.display === 'none') badge.style.display = 'flex';
        } else {
            cls = 'chat-message error-message';
            html = '<div class="message-content">&#9888; ' + escapeHtml(content) + '</div>';
        }

        div.className = cls;
        div.innerHTML = html;
        msgArea.appendChild(div);

        // Persist rendered message
        conv.messages.push({ cls: cls, html: html });
        scrollToBottom();
    }

    // ── Typing indicator ──────────────────────────────────────
    function addTyping() {
        var id = 'typing-' + Date.now();
        var div = document.createElement('div');
        div.id = id;
        div.className = 'chat-message bot-message';
        div.innerHTML = '<div class="message-content"><span class="typing-dots"><span></span><span></span><span></span></span></div>';
        msgArea.appendChild(div);
        scrollToBottom();
        return id;
    }
    function removeTyping(id) { var el = document.getElementById(id); if (el) el.remove(); }

    // ── Storage ───────────────────────────────────────────────
    function loadStore() {
        try {
            var s = localStorage.getItem(STORAGE_KEY);
            if (s) {
                var parsed = JSON.parse(s);
                if (parsed && Array.isArray(parsed.conversations)) return parsed;
            }
        } catch (e) {}
        return { conversations: [], activeId: null };
    }
    function saveStore() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(store)); } catch (e) {}
    }

    // ── File Upload Handler ─────────────────────────────────
    function handleFileSelect(file) {
        var validExts = ['png', 'jpg', 'jpeg', 'pdf'];
        var ext = file.name.split('.').pop().toLowerCase();
        var maxSize = 25 * 1024 * 1024;

        if (!validExts.includes(ext)) {
            appendMsg('error', '❌ Type de fichier non autorisé. Acceptés: PNG, JPG, JPEG, PDF', getActive());
            return;
        }

        if (file.size > maxSize) {
            appendMsg('error', '❌ Fichier trop volumineux (max 25 MB)', getActive());
            return;
        }

        // Show file preview
        var reader = new FileReader();
        reader.onload = function (e) {
            var previewHtml = '';
            if (ext === 'pdf') {
                previewHtml = '<div class="file-preview-item"><strong>📄 ' + file.name + '</strong> (' + formatFileSize(file.size) + ')</div>';
            } else {
                previewHtml = '<img src="' + e.target.result + '" class="file-preview-img" />';
            }
            if (filePreview) {
                filePreview.innerHTML = previewHtml;
                filePreview.style.display = 'block';
            }

            // Auto-send the file
            sendFile(file, inputEl.value.trim());
        };
        reader.readAsDataURL(file);
    }

    function sendFile(file, message) {
        var conv = getActive();
        if (!conv) return;

        isLoading = true;
        sendBtn.disabled = true;
        var typingId = addTyping();

        var formData = new FormData();
        formData.append('file', file);
        if (message) formData.append('message', message);

        fetch(CHATBOT_AJAX_URL.replace('/chat.php', '/file-handler.php'), {
            method: 'POST',
            body: formData
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            removeTyping(typingId);
            isLoading = false;
            sendBtn.disabled = false;
            filePreview.style.display = 'none';
            inputEl.value = '';
            inputEl.style.height = 'auto';

            if (data.success) {
                // Show file info as user message
                var fileMsg = '📎 Fichier: **' + data.file_name + '** (Type détecté: ' + data.document_type + ')';
                appendMsg('user', fileMsg, conv);
                conv.history.push({ role: 'user', content: fileMsg });

                // Build detailed analysis context for Claude
                var analysisText = 'Document analysé:\n' +
                                 '- Type: ' + data.document_type + '\n' +
                                 '- Description: ' + (data.analysis.description || 'N/A') + '\n' +
                                 '- Données extraites: ' + JSON.stringify(data.analysis.extracted_data || data.analysis);

                // Create message with analysis context
                var contextMsg = analysisText + (message ? '\n\nQuestion de l\'utilisateur: ' + message : '');

                appendMsg('user', 'Analyse en cours...', conv);
                conv.history.push({ role: 'user', content: contextMsg });
                saveStore();

                // Clear preview
                if (filePreview) filePreview.style.display = 'none';

                // Send to Claude with file context
                isLoading = true;
                sendBtn.disabled = true;
                var typingId2 = addTyping();

                var historyToSend = conv.history.slice(0, -1);
                if (historyToSend.length > 16) historyToSend = historyToSend.slice(-16);

                fetch(CHATBOT_AJAX_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: contextMsg,
                        history: historyToSend,
                        file_context: analysisText,
                        token: (typeof CHATBOT_TOKEN !== 'undefined') ? CHATBOT_TOKEN : ''
                    })
                }).then(function (response) {
                    var ct = response.headers.get('Content-Type') || '';
                    if (ct.indexOf('text/event-stream') !== -1) {
                        // Streaming response
                        var reader = response.body.getReader();
                        var decoder = new TextDecoder();
                        var sseBuf = '';
                        var fullText = '';
                        var botDiv = null, contentDiv = null, timeDiv = null;
                        var done = false;

                        function finish() {
                            if (done) return;
                            done = true;
                            removeTyping(typingId2);
                            isLoading = false;
                            sendBtn.disabled = false;
                            if (fullText && botDiv) {
                                conv.messages.push({ cls: botDiv.className, html: contentDiv.outerHTML + timeDiv.outerHTML });
                                conv.history.push({ role: 'assistant', content: fullText });
                                if (conv.history.length > 20) conv.history = conv.history.slice(-20);
                                saveStore();
                            }
                        }

                        function pump() {
                            return reader.read().then(function (result) {
                                if (result.done) { finish(); return; }
                                sseBuf += decoder.decode(result.value, { stream: true });
                                var lines = sseBuf.split('\n');
                                sseBuf = lines.pop();
                                for (var i = 0; i < lines.length; i++) {
                                    var line = lines[i].trim();
                                    if (line.indexOf('data: ') !== 0) continue;
                                    var data = line.slice(6);
                                    if (data === '[DONE]') { finish(); return; }
                                    try {
                                        var json = JSON.parse(data);
                                        if (json.token) {
                                            if (!botDiv) {
                                                removeTyping(typingId2);
                                                botDiv = document.createElement('div');
                                                botDiv.className = 'chat-message bot-message';
                                                contentDiv = document.createElement('div');
                                                contentDiv.className = 'message-content';
                                                timeDiv = document.createElement('div');
                                                timeDiv.className = 'message-time';
                                                timeDiv.textContent = getTime();
                                                botDiv.appendChild(contentDiv);
                                                botDiv.appendChild(timeDiv);
                                                msgArea.appendChild(botDiv);
                                            }
                                            fullText += json.token;
                                            contentDiv.innerHTML = renderMarkdown(fullText);
                                            scrollToBottom();
                                        }
                                    } catch (e) {}
                                }
                                return pump();
                            });
                        }
                        pump();
                    } else {
                        return response.json().then(function (data) {
                            removeTyping(typingId2);
                            isLoading = false;
                            sendBtn.disabled = false;
                            if (data.success) {
                                appendMsg('bot', data.message, conv);
                                conv.history.push({ role: 'assistant', content: data.message });
                                if (conv.history.length > 20) conv.history = conv.history.slice(-20);
                                saveStore();
                            } else {
                                appendMsg('error', '❌ Erreur: ' + (data.error || 'Inconnue'), conv);
                            }
                        });
                    }
                }).catch(function (err) {
                    removeTyping(typingId2);
                    isLoading = false;
                    sendBtn.disabled = false;
                    appendMsg('error', '❌ Erreur réseau: ' + err.message, conv);
                });
            } else {
                appendMsg('error', '❌ Erreur: ' + (data.error || 'Upload échoué'), conv);
            }
        }).catch(function (err) {
            removeTyping(typingId);
            isLoading = false;
            sendBtn.disabled = false;
            appendMsg('error', '❌ Erreur réseau: ' + err.message, conv);
        });
    }

    // ── Audio Recording Handler ──────────────────────────────
    function toggleAudioRecording() {
        if (isRecording) {
            stopAudioRecording();
        } else {
            startAudioRecording();
        }
    }

    function startAudioRecording() {
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            isRecording = true;
            audioChunks = [];
            recordingStartTime = Date.now();

            mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm;codecs=opus' });
            mediaRecorder.ondataavailable = function (event) {
                audioChunks.push(event.data);
            };
            mediaRecorder.onstop = function () {
                var audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                sendAudio(audioBlob);
                stream.getTracks().forEach(function (track) { track.stop(); });
            };

            mediaRecorder.start();
            if (recordingInd) recordingInd.style.display = 'flex';
            if (micBtn) micBtn.classList.add('recording');

            // Update recording time
            var recordingInterval = setInterval(function () {
                if (!isRecording) { clearInterval(recordingInterval); return; }
                var elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
                if (recordingTime) recordingTime.textContent = Math.floor(elapsed / 60) + ':' + String(elapsed % 60).padStart(2, '0');
                if (elapsed > 120) stopAudioRecording();
            }, 100);
        }).catch(function (err) {
            appendMsg('error', '❌ Erreur microphone: ' + err.message, getActive());
        });
    }

    function stopAudioRecording() {
        if (!isRecording || !mediaRecorder) return;
        isRecording = false;
        mediaRecorder.stop();
        if (recordingInd) recordingInd.style.display = 'none';
        if (micBtn) micBtn.classList.remove('recording');
    }

    function sendAudio(audioBlob) {
        var conv = getActive();
        if (!conv) return;

        var reader = new FileReader();
        reader.onload = function (e) {
            var audioBase64 = e.target.result.split(',')[1];
            var duration = (Date.now() - recordingStartTime) / 1000;

            isLoading = true;
            sendBtn.disabled = true;
            var typingId = addTyping();

            fetch(CHATBOT_AJAX_URL.replace('/chat.php', '/audio-handler.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    audio_base64: audioBase64,
                    audio_duration: duration
                })
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                removeTyping(typingId);
                isLoading = false;
                sendBtn.disabled = false;

                if (data.success && data.transcribed_text) {
                    inputEl.value = data.transcribed_text;
                    inputEl.focus();
                    // Auto-send the transcribed message
                    setTimeout(doSend, 200);
                } else {
                    appendMsg('error', '❌ Erreur transcription: ' + (data.error || 'Inconnue'), conv);
                }
            }).catch(function (err) {
                removeTyping(typingId);
                isLoading = false;
                sendBtn.disabled = false;
                appendMsg('error', '❌ Erreur réseau audio: ' + err.message, conv);
            });
        };
        reader.readAsDataURL(audioBlob);
    }

    // ── Utils ─────────────────────────────────────────────────
    function scrollToBottom() {
        setTimeout(function () { msgArea.scrollTop = msgArea.scrollHeight; }, 60);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    function getTime() {
        var d = new Date();
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }
    function escapeHtml(t) {
        return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    // ── Markdown renderer ─────────────────────────────────────
    function renderMarkdown(text) {
        text = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        // Code blocks
        text = text.replace(/```([\s\S]*?)```/g, function(_,c){ return '<pre><code>'+c.trim()+'</code></pre>'; });
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Tables
        text = text.replace(/(\|.+\|\n)(\|[-|: ]+\|\n)((\|.+\|\n?)*)/g, function(match) {
            var lines = match.trim().split('\n').filter(Boolean);
            var html = '<div class="chat-table-wrapper"><table class="chat-table"><thead><tr>';
            lines[0].split('|').filter(function(c){return c.trim()!=='';}).forEach(function(h){html+='<th>'+h.trim()+'</th>';});
            html += '</tr></thead><tbody>';
            for (var i=2;i<lines.length;i++){
                var cells=lines[i].split('|').filter(function(c){return c.trim()!=='';});
                if(!cells.length) continue;
                html+='<tr>';
                cells.forEach(function(c){html+='<td>'+c.trim()+'</td>';});
                html+='</tr>';
            }
            return html+'</tbody></table></div>';
        });
        // Bold / italic
        text = text.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
        text = text.replace(/\*(.+?)\*/g,'<em>$1</em>');
        // Headers
        text = text.replace(/^### (.+)$/gm,'<h4>$1</h4>');
        text = text.replace(/^## (.+)$/gm,'<h3>$1</h3>');
        text = text.replace(/^# (.+)$/gm,'<h2>$1</h2>');
        // Lists
        text = text.replace(/(^[*\-] .+$(\n[*\-] .+$)*)/gm,function(b){
            return '<ul>'+b.split('\n').map(function(l){return '<li>'+l.replace(/^[*\-] /,'')+'</li>';}).join('')+'</ul>';
        });
        text = text.replace(/(^\d+\. .+$(\n\d+\. .+$)*)/gm,function(b){
            return '<ol>'+b.split('\n').map(function(l){return '<li>'+l.replace(/^\d+\. /,'')+'</li>';}).join('')+'</ol>';
        });
        // Paragraphs
        text = text.replace(/\n\n/g,'</p><p>').replace(/\n/g,'<br>');
        text = '<p>'+text+'</p>';
        text = text.replace(/<p><\/p>/g,'');
        text = text.replace(/<p>(<[ht][1-6r]|<ul|<ol|<pre|<div)/g,'$1');
        text = text.replace(/(<\/[ht][1-6r]>|<\/ul>|<\/ol>|<\/pre>|<\/div>)<\/p>/g,'$1');
        return text;
    }

})();
