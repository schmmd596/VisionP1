# ⚡ Setup Rapide - Ollama pour Chatbot

## 🎯 En 5 minutes

### 1️⃣ Vérifier qu'Ollama tourne (Windows)
- Cherchez **Ollama** dans la taskbar en bas à droite
- Si absent, lancez `ollama.exe`

### 2️⃣ Télécharger un modèle
**OPTION A** (Plus facile) :
```
Ouvrez dans votre navigateur :
http://localhost/custom/chatbot/scripts/download-models.php
```
Laissez tourner ~10-30 minutes (dépend de votre connexion)

**OPTION B** (Si A ne marche pas) :
Ouvrez PowerShell en admin et collez :
```powershell
cd "c:\xampp\htdocs\projectVp\custom\chatbot"
powershell -ExecutionPolicy Bypass -File "scripts\setup-ollama.ps1"
```

### 3️⃣ Configurer le chatbot
1. Allez à : http://localhost/admin/module.php (ou cherchez "Chatbot" dans Administration)
2. Cliquez sur "Configuration du Tafkir AI"
3. Sélectionnez **"Ollama (Local)"** dans le dropdown
4. Cliquez sur **"Tester la connexion"** → doit afficher ✅
5. Appuyez sur **"Sauvegarder"**

### 4️⃣ Tester
- Ouvrez le chatbot (doit y avoir un widget quelque part sur le site)
- Posez une question : "Bonjour, comment ça marche ?"
- Attendez la réponse (10-30 secondes au premier appel)

## ✅ C'est prêt !

---

## 🆘 Ça ne marche pas ?

### ❌ "Ollama n'est pas accessible"
```
→ Ollama n'est pas lancé
→ Cliquez sur Ollama dans la taskbar ou relancez-le
```

### ❌ "Modèle non disponible"
```
→ Le modèle n'a pas fini de télécharger
→ Relancez le script download-models.php
→ Vérifiez dans une CMD : curl http://localhost:11434/api/tags
```

### ❌ Réponse très lente (30+ sec)
```
→ Normal au premier appel (charge le modèle en RAM)
→ Les appels suivants sont plus rapides
→ Sur PC faible : utilisez "neural-chat" au lieu de "mistral"
```

---

## 📚 Documentation complète
Lisez : `INSTALLATION_OLLAMA.md`

---

## 🔧 Fichiers ajoutés/modifiés

```
custom/chatbot/
├── class/
│   └── OllamaClient.php          ← Nouvelle classe pour Ollama
├── ajax/
│   ├── chat.php                  ← Modifié (supporte Ollama)
│   └── chat-ollama.php           ← Nouveau (handler Ollama)
├── admin/
│   └── setup.php                 ← Modifié (interface Ollama)
├── scripts/
│   ├── download-models.php       ← Nouveau (télécharger modèles)
│   └── setup-ollama.ps1          ← Nouveau (PowerShell script)
├── INSTALLATION_OLLAMA.md        ← Documentation complète
└── SETUP_RAPIDE.md              ← Ce fichier
```

---

## 💡 Conseils

- **Première fois** : Attendez que le modèle se charge en RAM (10-30 sec)
- **Vitesse** : Après premier chargement, c'est très rapide (1-3 sec/réponse)
- **Confidentialité** : Vos données restent 100% locales
- **Coût** : 0€ (zéro frais API)
- **Offline** : Fonctionne sans internet après première utilisation

---

## 🚀 C'est fini !

Votre chatbot utilise maintenant Ollama - un LLM puissant, gratuit et auto-hébergé.

Amusez-vous bien ! 🎉
