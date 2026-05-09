#!/bin/bash

# 🆓 Installation Automatique Ollama pour Tafkir
# Usage: chmod +x install-ollama.sh && sudo ./install-ollama.sh

set -e

echo "=========================================="
echo "🆓 Ollama Installation pour Tafkir"
echo "=========================================="

# Déterminer l'OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
else
    echo "❌ OS non détecté"
    exit 1
fi

echo "📦 OS détecté: $OS"

# Installation Ollama
echo ""
echo "📥 Installation d'Ollama..."
curl -fsSL https://ollama.ai/install.sh | sh

# Démarrer le service
echo ""
echo "🚀 Démarrage du service..."
systemctl start ollama
systemctl enable ollama

# Vérifier
echo ""
echo "✅ Vérification..."
systemctl status ollama

# Menu de sélection du modèle
echo ""
echo "=========================================="
echo "📥 Quel modèle voulez-vous installer?"
echo "=========================================="
echo "1) Mistral 7B (Recommandé - 4GB)"
echo "2) Neural Chat 7B (Meilleur - 5GB)"
echo "3) TinyLlama (Ultra-léger - 2GB)"
echo "4) Aucun (installer plus tard)"
echo ""

read -p "Sélectionnez (1-4): " MODEL_CHOICE

case $MODEL_CHOICE in
    1)
        echo "📥 Téléchargement Mistral 7B..."
        ollama pull mistral:latest
        echo "✅ Mistral installé avec succès!"
        ;;
    2)
        echo "📥 Téléchargement Neural Chat 7B..."
        ollama pull neural-chat:latest
        echo "✅ Neural Chat installé avec succès!"
        ;;
    3)
        echo "📥 Téléchargement TinyLlama..."
        ollama pull tinyllama:latest
        echo "✅ TinyLlama installé avec succès!"
        ;;
    4)
        echo "⏭️  Skipped. Installez un modèle plus tard:"
        echo "   ollama pull mistral:latest"
        ;;
    *)
        echo "❌ Choix invalide"
        exit 1
        ;;
esac

# Configuration pour accès distant
echo ""
echo "=========================================="
echo "🌐 Configuration Réseau"
echo "=========================================="
read -p "Accepter les connexions distantes? (y/n): " DISTANT

if [ "$DISTANT" = "y" ]; then
    echo "📝 Configuration Ollama pour accès distant..."

    # Éditer le service
    SERVICE_FILE="/etc/systemd/system/ollama.service"

    if grep -q "ExecStart=.*--host" "$SERVICE_FILE"; then
        echo "✅ Déjà configuré pour accès distant"
    else
        sed -i 's/ExecStart=\/usr\/bin\/ollama serve/ExecStart=\/usr\/bin\/ollama serve --host 0.0.0.0:11434/' "$SERVICE_FILE"

        systemctl daemon-reload
        systemctl restart ollama

        echo "✅ Service redémarré avec accès distant"
    fi

    echo ""
    echo "🌐 URL Ollama pour votre application:"
    echo "   http://$(hostname -I | awk '{print $1}'):11434"
fi

# Test
echo ""
echo "=========================================="
echo "🔌 Test de Connexion"
echo "=========================================="

if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo "✅ Ollama est accessible!"
    echo ""
    echo "Modèles disponibles:"
    curl -s http://localhost:11434/api/tags | grep -o '"name":"[^"]*' | cut -d'"' -f4
else
    echo "⚠️  Ollama n'est pas encore prêt, attendez quelques secondes..."
    sleep 5
    curl -s http://localhost:11434/api/tags
fi

echo ""
echo "=========================================="
echo "✅ Installation Complète!"
echo "=========================================="
echo ""
echo "Prochaines étapes:"
echo "1. Allez à: Configuration → Chatbot IA → Setup"
echo "2. Sélectionnez un modèle Ollama"
echo "3. URL: http://localhost:11434 (ou votre IP VPS)"
echo "4. Laissez la clé API vide"
echo "5. Sauvegardez et testez!"
echo ""
echo "Pour plus d'infos:"
echo "   cat OLLAMA_INSTALLATION.md"
