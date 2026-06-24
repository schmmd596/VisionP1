# 🚀 DÉPART IMMÉDIAT - Ollama pour Chatbot

## 📍 Vous êtes ici

Une **solution LLM auto-hébergée Ollama** a été installée pour votre chatbot.

Avant d'aller plus loin, faites ces 4 étapes :

---

## ✅ Étape 1 : Vérifier Ollama (1 min)

**Action :**
1. Regardez la **taskbar Windows** (coin bas-droit)
2. Cherchez l'icône **Ollama**

**Résultat attendu :**
- Vous voyez l'icône Ollama → **C'est bon, continuez**
- Vous ne voyez pas l'icône → **Lancez `ollama.exe`**

Si Ollama n'est pas installé :
- Téléchargez depuis https://ollama.ai/download
- Installez
- Relancez

---

## ✅ Étape 2 : Télécharger un modèle (10-30 min)

**Action :**

Ouvrez cette URL dans votre navigateur :
```
http://localhost/custom/chatbot/scripts/download-models.php
```

**Attendez :**
- Le script affiche "⬇️ Téléchargement du modèle..."
- Puis affiche des ✅ quand c'est fini
- Enfin affiche "✨ Configuration Ollama terminée !"

**Temps :**
- Rapide (10 min) : Connexion fibre/ADSL rapide
- Normal (20 min) : Connexion standard
- Lent (30+ min) : Connexion lente ou PC faible

**Si ça échoue :**
- Attendez quelques minutes
- Rafraîchissez la page (F5)
- Relancez le script

---

## ✅ Étape 3 : Configurer le chatbot (2 min)

**Action :**

Allez à cette URL :
```
http://localhost/custom/chatbot/admin/setup.php
```

**Dans le formulaire :**

1. **"Source LLM"** = Sélectionnez **🖥️ Ollama (Local - Auto-hébergé)**
   - (Au lieu de "☁️ API externe")

2. Vérifiez que vous voyez **✅ "Ollama est connecté et prêt"**
   - Si vous voyez ❌ : attendez un peu et rafraîchissez

3. **"Modèle"** = Sélectionnez **Mistral**
   - (Ou un autre modèle si vous en avez téléchargé plusieurs)

4. **"Tokens maximum"** = Laissez à **2048**

5. **"Activer le chatbot"** = Cochez la case

6. Cliquez **"🔌 Tester la connexion"**
   - Attendez 5 sec
   - Doit afficher ✅ **"Connexion réussie !"**

7. Cliquez **"Sauvegarder la configuration"**
   - Doit afficher ✅ **"Configuration sauvegardée avec succès."**

---

## ✅ Étape 4 : Tester le chatbot (2 min)

**Action :**

1. Trouvez le **widget chatbot** sur votre site
   - Généralement dans le coin bas-droit
   - Peut être un bouton 💬 ou 🤖

2. Cliquez dessus pour ouvrir le chat

3. Écrivez une question simple :
   ```
   Bonjour
   ```

4. Appuyez sur **Entrée**

**Attendez :**
- ⏱️ **10-30 secondes** pour la première réponse (C'EST NORMAL !)
   - Le modèle se charge en RAM au premier appel
- ⏱️ **2-5 secondes** pour les réponses suivantes

**Résultat :**
- Vous devriez voir une réponse en français
- Si c'est pertinent → **Ça marche ! 🎉**

---

## 🎉 C'EST FAIT !

Votre chatbot utilise maintenant **Ollama** - un LLM gratuit et auto-hébergé !

**Avantages :**
- ✅ Gratuit (0€)
- ✅ Privé (données locales)
- ✅ Rapide (2-5 sec/réponse)
- ✅ Sans clé API

---

## 🆘 Si quelque chose échoue

### ❌ "Ollama n'est pas accessible"

**Cause :** Ollama.exe n'est pas lancé

**Solution :**
1. Lancez `ollama.exe`
2. Attendez 10 secondes
3. Réessayez l'étape 3

### ❌ "Modèle non disponible"

**Cause :** Le téléchargement n'a pas fini

**Solution :**
1. Relancez le script : http://localhost/custom/chatbot/scripts/download-models.php
2. Attendez la fin du téléchargement
3. Vérifiez que vous voyez "✨ Configuration Ollama terminée !"

### ❌ "Réponse très lente (30+ sec)"

**Cause :** Chargement du modèle en RAM au premier appel

**Solution :**
1. C'est normal au PREMIER appel
2. Attendez 30 sec
3. Les appels suivants seront rapides (2-5 sec)

### ❌ "Le chatbot ne répond pas"

**Cause :** Configuration non sauvegardée ou Ollama arrêté

**Solution :**
1. Vérifiez qu'Ollama.exe tourne
2. Retournez à l'étape 3
3. Cliquez "Tester la connexion" → doit afficher ✅
4. Sauvegardez

---

## 📚 Pour plus d'informations

Si vous avez besoin de plus de détails, lisez ces fichiers :

| Fichier | Quoi ? |
|---------|--------|
| **CHECKLIST.md** | Checklist détaillée avec cases à cocher |
| **SETUP_RAPIDE.md** | Guide 5 min + FAQ |
| **INSTALLATION_OLLAMA.md** | Doc complète (10 pages) |
| **README_OLLAMA.md** | Infos techniques |

Tous les fichiers sont dans : `custom/chatbot/`

---

## 💡 Points clés à retenir

1. **Ollama doit tourner** (ollama.exe lancé)
2. **Un modèle doit être téléchargé** (5 GB environ)
3. **Le chatbot doit être configuré** (sélectionner Ollama)
4. **La première réponse sera lente** (10-30 sec, normal !)
5. **Les réponses suivantes seront rapides** (2-5 sec)

---

## ✨ Félicitations !

Vous avez un **chatbot IA professionnel** qui :
- Fonctionne 100% localement
- Ne coûte rien en API
- Est ultra-rapide
- Respecte votre confidentialité

**Amusez-vous bien ! 🚀**

---

**Questions ?** → Consultez `INSTALLATION_OLLAMA.md` (troubleshooting complet)
