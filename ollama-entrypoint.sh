#!/bin/bash

# 🆓 Ollama Docker Entrypoint
# Télécharge automatiquement les modèles spécifiés

# Configuration
MODELS_TO_PULL=${OLLAMA_MODELS:-"mistral"}  # Default: mistral
MODEL_ARRAY=(${MODELS_TO_PULL//,/ })

echo "=========================================="
echo "🆓 Ollama Entrypoint"
echo "=========================================="

# Start Ollama in background
echo "🚀 Démarrage d'Ollama..."
ollama serve &
OLLAMA_PID=$!

# Wait for Ollama to be ready
echo "⏳ Attente du démarrage..."
for i in {1..30}; do
    if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
        echo "✅ Ollama est prêt!"
        break
    fi
    echo "  Tentative $i/30..."
    sleep 2
done

# Pull models
echo ""
echo "📥 Téléchargement des modèles..."
for MODEL in "${MODEL_ARRAY[@]}"; do
    MODEL=$(echo "$MODEL" | xargs)  # Trim whitespace
    if [ -n "$MODEL" ]; then
        echo "  → Téléchargement: $MODEL"
        ollama pull "$MODEL"
        if [ $? -eq 0 ]; then
            echo "  ✅ $MODEL installé"
        else
            echo "  ❌ Erreur avec $MODEL"
        fi
    fi
done

echo ""
echo "=========================================="
echo "✅ Démarrage complété!"
echo "=========================================="
echo ""
echo "Modèles disponibles:"
ollama list
echo ""

# Wait for Ollama process
wait $OLLAMA_PID
