# 🚀 Installation & Configuration Ollama pour le Chatbot

## 📋 Vue d'ensemble

Ce guide explique comment configurer votre chatbot pour utiliser **Ollama** - un LLM auto-hébergé totalement GRATUIT et sans clé API requise.

### Avantages d'Ollama
✅ **Gratuit** - Aucun coût API  
✅ **Privé** - Les données restent sur votre serveur  
✅ **Rapide** - Zéro latence réseau  
✅ **Facile** - Installation 1-click  
✅ **Flexible** - Changez de modèle quand vous voulez  

---

## 🔧 Étape 1 : Vérifier qu'Ollama tourne

Ollama devrait déjà être installé et fonctionnel. Vérifiez :

```bash
# Tester que le service répond
curl http://localhost:11434/api/tags
```

Vous devriez voir une réponse JSON avec la liste des modèles (vide au début).

---

## 📥 Étape 2 : Télécharger un modèle LLM

### Option A : Via PHP (Recommandé pour Windows)

Accédez à cette URL dans votre navigateur :
```
http://localhost/custom/chatbot/scripts/download-models.php
```

Cela téléchargera automatiquement **Mistral** (recommandé).

**Temps estimé** : 5-30 minutes selon votre connexion  
**Taille** : ~5 GB

### Option B : Via PowerShell (Pour administrateurs)

```powershell
# Exécutez ce script (depuis PowerShell en admin)
cd c:\xampp\htdocs\projectVp
.\custom\chatbot\scripts\setup-ollama.ps1
```

### Option C : Téléchargement manuel via ligne de commande

Sur Windows, trouvez le répertoire d'installation d'Ollama (généralement dans `%APPDATA%\Ollama`) et ouvrez un PowerShell dans ce dossier :

```powershell
# Les commandes ollama doivent être dans le PATH
ollama pull mistral
ollama pull neural-chat    # (optionnel, bon pour conversation)
```

### Models disponibles

| Modèle | Taille | Vitesse | Capacités | Recommandé pour |
|--------|--------|---------|-----------|-----------------|
| **mistral** | 5 GB | ⚡⚡ | Bon français | **Recommandé** |
| **neural-chat** | 5 GB | ⚡⚡ | Excellente conversation | Chat intensif |
| **llama2** | 7 GB | ⚡ | Très capable | Tâches complexes |
| **dolphin-mixtral** | 10 GB | ⚡ | Ultra-puissant | Tâches difficiles |

---

## ⚙️ Étape 3 : Configurer le Chatbot

1. Allez dans **Administration → Chatbot IA → Setup**  
   (ou : `http://localhost/custom/chatbot/admin/setup.php`)

2. Dans le formulaire "Configuration LLM", sélectionnez :
   - **Source LLM** : `🖥️ Ollama (Local - Auto-hébergé)`

3. Vérifiez que le statut affiche ✅ "Ollama est connecté"

4. Sélectionnez le modèle téléchargé (ex: **Mistral**)

5. Réglez le **Tokens maximum** (recommandé: 2048)

6. Activez le chatbot avec **"Activer le chatbot"**

7. Cliquez **"Sauvegarder la configuration"**

8. Testez avec le bouton **🔌 "Tester la connexion"**

---

## ✅ Vérification

Pour vérifier que tout fonctionne :

1. **Via l'admin** : Le bouton "Tester la connexion" affiche ✅
2. **Via curl** : 
   ```bash
   curl http://localhost:11434/api/tags
   # Doit afficher vos modèles chargés
   ```

3. **Via le chatbot** : Posez une question simple
   - "Bonjour"
   - "Dis juste OK"
   - "Quel est la capitale de la Mauritanie?"

---

## 🎯 Utilisation dans le Chatbot

Une fois configuré, votre chatbot :

✅ Utilise Ollama automatiquement  
✅ Ne nécessite aucune clé API  
✅ Stocke les données localement  
✅ Fonctionne offline (après chargement du modèle)  
✅ Supporte les mêmes outils que avant (produits, clients, comptabilité, etc.)

---

## 🚨 Troubleshooting

### ❌ "Ollama n'est pas accessible"
- Vérifiez que **Ollama.exe tourne** (cherchez-le dans la taskbar)
- Relancez Ollama s'il ne répond pas
- Vérifiez le port : `netstat -an | findstr 11434` (Windows)

### ❌ "Modèle non disponible"
- Exécutez le script `download-models.php` pour télécharger un modèle
- Attendez la fin du téléchargement (5-30 min)
- Vérifiez avec : `curl http://localhost:11434/api/tags`

### ❌ "Réponse très lente"
- C'est normal pour le premier appel (chargement du modèle en RAM)
- Les appels suivants sont plus rapides
- Sur un PC faible, considérez **neural-chat** (plus léger)

### ❌ Le chatbot retourne des erreurs
- Vérifiez la **console de développement** (F12)
- Vérifiez les **logs du serveur** (`Apache Error log`)
- Testez directement : `curl -X POST http://localhost:11434/api/chat -H "Content-Type: application/json" -d '{"model":"mistral","messages":[{"role":"user","content":"Hi"}]}'`

---

## 📊 Comparaison : Ollama vs APIs externes

| Critère | Ollama Local | OpenRouter/Anthropic |
|---------|--------------|---------------------|
| **Coût** | 0€ | €€€ (par requête) |
| **Confidentialité** | Serveur local | Cloud externe |
| **Vitesse** | ⚡⚡⚡ (local) | ⚡⚡ (réseau) |
| **Capacités** | Bonnes | Excellentes+ |
| **Setup** | Facile | Clé API requise |
| **Disponibilité** | Offline OK | Besoin internet |

---

## 🔄 Changer de modèle

Pour utiliser un modèle différent :

1. Téléchargez-le : 
   ```
   http://localhost/custom/chatbot/scripts/download-models.php
   ```

2. Allez dans **Administration → Chatbot IA → Setup**

3. Sélectionnez le nouveau modèle

4. Sauvegardez

---

## 📈 Performance & Ressources

**Ressources requises**

| Modèle | RAM | Disque | CPU |
|--------|-----|--------|-----|
| Mistral | 5 GB | 5 GB | 2+ cores |
| LLama 2 | 8 GB | 7 GB | 4+ cores |
| Dolphin-Mixtral | 12 GB | 10 GB | 4+ cores |

**Conseils pour performance**

- Fermez autres applications lourdes
- Utilisez un **disque SSD** (plus rapide que HDD)
- Sur machine faible : utilisez **Mistral** (plus léger)
- Le **premier appel** charge le modèle en RAM (5-30 sec) → normal

---

## 🆚 Switcher entre Ollama et APIs externes

Pour repasser aux APIs (OpenAI, Anthropic, etc.) :

1. Allez dans **Administration → Chatbot IA → Setup**
2. Sélectionnez **☁️ API externe**
3. Entrez votre clé API
4. Sélectionnez le modèle
5. Sauvegardez

Le chatbot basculera automatiquement.

---

## 📞 Support

**Ressources**
- Documentation Ollama : https://ollama.ai/
- Models disponibles : https://ollama.ai/library
- Issues : Vérifiez les logs dans `admin/logs/`

**Questions**
- Consultez le script PHP : `scripts/download-models.php`
- Ou le code adapter : `class/OllamaClient.php`

---

## ✨ Prochaines étapes

1. ✅ Télécharger un modèle
2. ✅ Configurer le chatbot
3. ✅ Tester la connexion
4. ✅ Utiliser le chatbot normalement

Bienvenue dans l'ère des **LLMs auto-hébergés gratuits** ! 🎉
