# 🤖 Ollama Integration pour Chatbot Tafkir IA

## 📖 Documentation

### 🚀 **COMMENCER ICI** → [CHECKLIST.md](CHECKLIST.md)
Guide étape-par-étape avec cases à cocher

### ⚡ Guides rapides
- [SETUP_RAPIDE.md](SETUP_RAPIDE.md) - 5 minutes pour fonctionner
- [INSTALLATION_OLLAMA.md](INSTALLATION_OLLAMA.md) - Documentation complète

### 📊 Ressources supplémentaires
- [../OLLAMA_SOLUTION.md](../OLLAMA_SOLUTION.md) - Vue d'ensemble architecturale

---

## 🎯 Résumé rapide

Votre chatbot supporte maintenant **Ollama** - un LLM open source auto-hébergé :

✅ **Gratuit** (0€ coûts API)  
✅ **Privé** (données locales)  
✅ **Rapide** (zéro latence)  
✅ **Facile** (installation 1-click)  

---

## 📦 Qu'est-ce qui a changé ?

### Fichiers créés
```
✨ class/OllamaClient.php          - Client Ollama
✨ ajax/chat-ollama.php            - Handler Ollama
✨ scripts/download-models.php     - Télécharger modèles
✨ scripts/setup-ollama.ps1        - Setup PowerShell
✨ CHECKLIST.md                    - Checklist installation
✨ SETUP_RAPIDE.md                 - Guide rapide
✨ INSTALLATION_OLLAMA.md          - Doc complète
✨ README_OLLAMA.md                - Ce fichier
```

### Fichiers modifiés
```
🔧 ajax/chat.php                   - Détecte provider (Ollama vs API)
🔧 admin/setup.php                 - Interface Ollama
```

---

## 🚀 Démarrage rapide

### 1. Installer Ollama (si pas déjà fait)
Téléchargez depuis https://ollama.ai/download

### 2. Télécharger un modèle
Ouvrez dans le navigateur :
```
http://localhost/custom/chatbot/scripts/download-models.php
```
Attendez 10-30 minutes

### 3. Configurer le chatbot
- Allez à : Admin → Chatbot IA → Setup
- Sélectionnez "Ollama (Local)"
- Testez la connexion
- Sauvegardez

### 4. Utiliser
- Ouvrez le chatbot
- Posez une question
- Attendez la réponse

---

## 💻 Système requis

| Aspect | Minimum | Recommandé |
|--------|---------|-----------|
| RAM | 6 GB | 16 GB |
| CPU | i5 2018+ | i7/Ryzen7 |
| Disque | 15 GB | SSD |
| Connexion | Pour DL initial | - |

---

## 🔄 Architecture

```
                    ┌─ Ollama (Local)
                    │  ├─ Mistral
                    │  ├─ Llama2
                    │  └─ etc.
                    │
Chatbot UI ──→ chat.php ──→ {
                    │
                    └─ API Cloud
                       ├─ OpenRouter
                       ├─ OpenAI
                       ├─ Anthropic
                       └─ Mistral
```

---

## 🛠️ Architecture du code

### OllamaClient (classe PHP)
```php
// Initialiser
$client = new OllamaClient('mistral');

// Chat avec streaming
$response = $client->chat($message, $history, $max_tokens, $callback);

// Vérifier disponibilité
if ($client->isAvailable()) { ... }

// Lister modèles
$models = $client->listModels();
```

### Routage dans chat.php
```php
// Détecte provider
$provider = $conf->global->CHATBOT_PROVIDER ?? 'ollama';

// Route vers handler approprié
if ($provider === 'ollama') {
    include 'chat-ollama.php'; // Handler Ollama simplifié
} else {
    // Code existant pour APIs externes
}
```

### Interface setup.php
- Sélecteur de provider (Ollama vs API)
- Liste des modèles Ollama vs API selon choix
- Statut de connexion en temps réel
- Test de connexion intégré

---

## 📊 Comparaison

| Critère | Ollama Local | APIs Cloud |
|---------|--------------|-----------|
| **Coût** | 0€ | €€€ |
| **Confidentialité** | ✅ Local | ❌ Cloud |
| **Vitesse** | ⚡⚡⚡ | ⚡⚡ |
| **Setup** | Facile | Clé API |
| **Offline** | Après DL | Non |
| **Capacités** | Bonnes | Excellentes+ |

---

## 🎓 Modèles disponibles

### Mistral (Recommandé)
- **Taille** : 5 GB
- **Vitesse** : ⚡⚡⚡ Rapide
- **Français** : Excellent
- **Cas d'usage** : 90% des besoins

### Llama 2
- **Taille** : 7 GB
- **Vitesse** : ⚡⚡ Bon
- **Français** : Bon
- **Cas d'usage** : Tâches complexes

### Neural Chat
- **Taille** : 5 GB
- **Vitesse** : ⚡⚡⚡ Rapide
- **Français** : Bon
- **Cas d'usage** : Chat pur

### Dolphin Mixtral
- **Taille** : 10 GB
- **Vitesse** : ⚡ Lent
- **Français** : Excellent
- **Cas d'usage** : Max qualité

---

## 📈 Performance

| Scénario | Temps |
|----------|-------|
| 1ère requête (cold start) | 10-30 sec |
| Requêtes suivantes | 2-5 sec |
| Longue réponse (2048 tokens) | 5-10 sec |
| Latence réseau | 0 ms (local) |

---

## 🔐 Sécurité

✅ **Avantages**
- Zéro donnée vers internet
- Contrôle complet
- Pas de clé API
- Fonctionne offline

⚠️ **Considérations**
- Ressources locales consommées
- Peut ralentir PC faible
- Nécessite espace disque

---

## 🚨 Troubleshooting

| Problème | Cause | Solution |
|----------|-------|----------|
| "Ollama non accessible" | Service pas lancé | Lancez ollama.exe |
| "Modèle non disponible" | Pas téléchargé | Relancez download-models.php |
| Très lent (30+ sec) | Cold start normal | Attendez, c'est normal |
| PC ralenti | Charge CPU/RAM | Réduisez tokens max |

---

## 🔄 Switcher de provider

Pour revenir aux APIs externes :
1. Admin → Setup
2. Sélectionnez "API externe"
3. Entrez votre clé API
4. Sauvegardez

---

## 📚 Fichiers principaux

```
custom/chatbot/
├── class/
│   └── OllamaClient.php          ← Client Ollama
├── ajax/
│   ├── chat.php                  ← Gateway (détecte provider)
│   └── chat-ollama.php           ← Handler Ollama
├── admin/
│   └── setup.php                 ← UI configuration
└── scripts/
    ├── download-models.php       ← Télécharger modèles
    └── setup-ollama.ps1          ← Setup PowerShell
```

---

## 🎯 Prochaines étapes

### Immédiat
1. Télécharger un modèle (download-models.php)
2. Configurer le chatbot (setup.php)
3. Tester et utiliser

### Court terme
- Essayer différents modèles
- Optimiser les paramètres
- Améliorer le contexte système

### Long terme
- Fine-tuning sur données métier
- GPU NVIDIA pour performance
- Multi-instance load balancing

---

## 💡 Tips & Tricks

- **Performance** : SSD > HDD (beaucoup plus rapide)
- **RAM insuffisante** : Utilisez "neural-chat" (5GB min)
- **Réponses lentes** : C'est normal au premier appel
- **Offline** : Fonctionne sans internet après setup
- **Coûts** : Zéro ! (sauf électricité)

---

## 🤝 Support

- **Installation** : Consultez CHECKLIST.md
- **Problèmes** : Lisez INSTALLATION_OLLAMA.md
- **Technique** : Consultez le code (bien commenté)

---

## 📊 État de la solution

- [x] **OllamaClient** - Classe PHP complète
- [x] **Integration** - Adapté dans chat.php
- [x] **Admin UI** - Interface de configuration
- [x] **Download** - Script de téléchargement
- [x] **Documentation** - Guides complets
- [ ] **GPU Support** - Futur (vLLM)
- [ ] **Fine-tuning** - Futur

---

## ✨ Conclusion

Vous avez maintenant un **chatbot IA professionnel** qui :
- ✅ Fonctionne localement
- ✅ Coûte 0€
- ✅ Respecte votre confidentialité
- ✅ Est ultra-rapide
- ✅ Peut switcher vers cloud si besoin

**Bienvenue dans l'ère des LLMs auto-hébergés !** 🚀

---

**Pour commencer → Ouvrez [CHECKLIST.md](CHECKLIST.md) 📋**
