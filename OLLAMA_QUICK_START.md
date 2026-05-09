# 🚀 Quick Start - Ollama + Tafkir Chatbot

## En 5 minutes ⏱️

### Sur votre VPS Linux:

```bash
# 1. Installation Ollama (automatisée)
curl -fsSL https://ollama.ai/install.sh | sh

# 2. Démarrer le service
sudo systemctl start ollama
sudo systemctl enable ollama

# 3. Télécharger un modèle (choisissez l'un):
ollama pull mistral:latest          # ⭐ Recommandé (4GB)
# OU
ollama pull neural-chat:latest      # 🏆 Meilleur (5GB)
# OU
ollama pull tinyllama:latest        # 🚀 Ultra-léger (2GB)

# 4. Tester la connexion
curl http://localhost:11434/api/tags
```

---

## Configuration Dolibarr

### 1️⃣ Allez à:
**Configuration** → **Chatbot IA** → **Setup**

### 2️⃣ Sélectionnez un modèle Ollama:
- `[🆓 Ollama] Mistral 7B` **← Recommandé**
- `[🆓 Ollama] Neural Chat 7B`
- `[🆓 Ollama] TinyLlama 1.1B`

### 3️⃣ URL Ollama:
- **Local**: `http://localhost:11434`
- **Distant**: `http://votre-vps.com:11434`

### 4️⃣ Clé API:
- **Laissez VIDE** (Ollama n'en a pas besoin)

### 5️⃣ Sauvegardez et testez!

---

## Pour accès distant (VPS différent de Dolibarr):

```bash
# Sur le VPS Ollama:
sudo nano /etc/systemd/system/ollama.service

# Changez cette ligne:
# ExecStart=/usr/bin/ollama serve
# En:
# ExecStart=/usr/bin/ollama serve --host 0.0.0.0:11434

# Redémarrez:
sudo systemctl daemon-reload
sudo systemctl restart ollama

# Vérifiez depuis votre Dolibarr:
curl http://votre-vps-ip:11434/api/tags
```

---

## 📊 Comparaison des Modèles

| Modèle | RAM | Taille | Vitesse | Qualité | FR/AR |
|--------|-----|--------|---------|---------|-------|
| **TinyLlama** | 2GB | 1.1GB | ⚡⚡⚡ Rapide | ⭐⭐ | ✅ |
| **Mistral 7B** | 4GB | 4GB | ⚡⚡ Rapide | ⭐⭐⭐ | ✅✅ |
| **Neural Chat** | 5GB | 5GB | ⚡ Moyen | ⭐⭐⭐⭐ | ✅✅✅ |

**Recommandation**: Mistral 7B = meilleur rapport qualité/RAM

---

## 🔍 Vérifier que ça marche

### Test 1: Connexion

```bash
curl http://localhost:11434/api/tags
```

**Doit répondre avec la liste des modèles en JSON**

### Test 2: Modèle

```bash
curl -X POST http://localhost:11434/api/chat \
  -H "Content-Type: application/json" \
  -d '{
    "model": "mistral",
    "messages": [{"role": "user", "content": "Dis OK"}],
    "stream": false
  }'
```

**Doit répondre avec "OK" ou quelque chose de similaire**

### Test 3: Dolibarr

Dans l'interface Setup, cliquez sur **"🔌 Tester la connexion API"**

---

## 🎯 Résultats attendus

✅ **Après installation**:
- Ollama demarre automatiquement avec le système
- Les requêtes au chatbot vont à Ollama (gratuit)
- Pas de frais mensuels d'API
- Réponses en 1-5 secondes selon le modèle
- Fonctionne en français et arabe

---

## 🆘 Problèmes courants

### ❌ "Connexion refusée"
```bash
# Vérifier que le service est actif:
sudo systemctl status ollama

# Redémarrer:
sudo systemctl restart ollama

# Voir les logs:
sudo journalctl -u ollama -f
```

### ❌ "Out of memory"
- Votre modèle est trop gros
- Solution: utilisez TinyLlama au lieu de Mistral

### ❌ "Pas de réponse du modèle"
- Le modèle n'est pas encore téléchargé
- Installez-le: `ollama pull mistral:latest`

---

## 💾 Espace disque nécessaire

- **Mistral 7B**: 4GB
- **Neural Chat**: 5GB
- **TinyLlama**: 1GB
- **Ollama system**: ~500MB

**Total**: 5-10GB selon votre choix

---

## 📚 Documentation complète

Pour plus de détails, voir: `OLLAMA_INSTALLATION.md`

---

## 🎉 C'est tout!

Votre chatbot Tafkir fonctionne maintenant **GRATUITEMENT** en local! 🚀

**Questions?** Consultez la documentation complète ou les logs Ollama.
