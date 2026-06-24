# ✅ Checklist d'Installation Ollama

## 🎯 Phase 1 : Préparation (1 min)

- [ ] Vous êtes connecté à Internet
- [ ] Vous avez XAMPP/Apache lancé
- [ ] Le répertoire `custom/chatbot/` existe
- [ ] Vous avez environ **15 GB d'espace disque libre**

## 🚀 Phase 2 : Vérification Ollama (2 min)

- [ ] Ollama est lancé (cherchez-le dans la taskbar Windows)
- [ ] Vous pouvez accéder à : http://localhost:11434/api/tags
- [ ] Vous voyez une réponse JSON (peut être vide)

**Si Ollama n'est pas installé :**
```
→ Téléchargez depuis https://ollama.ai/download
→ Lancez l'installateur
→ Redémarrez
```

## 📥 Phase 3 : Télécharger un modèle (5-30 min)

**Méthode A (Recommandée - Simple)**
- [ ] Ouvrez cette URL : http://localhost/custom/chatbot/scripts/download-models.php
- [ ] Attendez que le script finisse (page "Configuration Ollama terminée")
- [ ] C'est normal que ce prenne 10-30 minutes

**Méthode B (PowerShell - Si A échoue)**
- [ ] Ouvrez **PowerShell en mode Administrateur**
- [ ] Collez et exécutez :
  ```powershell
  cd c:\xampp\htdocs\projectVp
  powershell -ExecutionPolicy Bypass -File custom/chatbot/scripts/setup-ollama.ps1
  ```
- [ ] Attendez la fin

**Vérification :**
- [ ] Visitez http://localhost/custom/chatbot/scripts/download-models.php
- [ ] Vous voyez au moins un modèle dans "Modèles disponibles après setup"

## ⚙️ Phase 4 : Configuration du Chatbot (2 min)

- [ ] Allez sur **Administration → Chatbot IA → Setup**  
  (ou : http://localhost/custom/chatbot/admin/setup.php)

- [ ] Dans "Source LLM" : Sélectionnez **🖥️ Ollama (Local - Auto-hébergé)**

- [ ] Vérifiez que vous voyez : **✅ Ollama est connecté et prêt**

- [ ] Dans "Modèle" : Sélectionnez **"Mistral"** (ou le modèle que vous avez téléchargé)

- [ ] "Tokens maximum" : Laissez à **2048** (ou moins si PC faible)

- [ ] Cochez **"Activer le chatbot"**

- [ ] Cliquez **"Sauvegarder la configuration"**

- [ ] Vérifiez le message ✅ "Configuration sauvegardée avec succès"

## 🔌 Phase 5 : Test de Connexion (1 min)

- [ ] Restez sur la même page d'administration
- [ ] Cliquez le bouton **"🔌 Tester la connexion"**
- [ ] Attendez quelques secondes
- [ ] Vous voyez ✅ **"Connexion réussie !"** en vert

**Si test échoue :**
- [ ] Vérifiez qu'Ollama est toujours lancé
- [ ] Rafraîchissez la page
- [ ] Relancez le test

## 🤖 Phase 6 : Test du Chatbot (1 min)

- [ ] Trouvez le **widget chatbot** sur votre site (généralement coin bas droit)
- [ ] Cliquez dessus
- [ ] Écrivez une question simple : **"Bonjour"**
- [ ] Attendez la réponse (10-30 sec au premier appel, normal !)

**Attendez-vous à :**
- ⏱️ Premier appel : 10-30 secondes (charge le modèle en RAM)
- ⏱️ Appels suivants : 2-5 secondes (très rapide)

## 🎉 Phase 7 : Vérification Complète

- [ ] Le chatbot répond à vos questions
- [ ] Les réponses sont en français
- [ ] Le contenu semble pertinent

## ✨ C'est fait !

Vous avez maintenant un **chatbot IA fonctionnel, gratuit et auto-hébergé** ! 🚀

---

## 🚨 Si quelque chose échoue

### ❌ "Ollama n'est pas accessible"
```
1. Vérifiez qu'Ollama.exe tourne (taskbar)
2. Ouvrez : http://localhost:11434/api/tags
3. Si erreur, relancez Ollama
4. Attendez 10 sec et réessayez
```

### ❌ "Modèle non disponible"
```
1. Relancez : http://localhost/custom/chatbot/scripts/download-models.php
2. Vérifiez la fin du script (cherchez "✨ Configuration Ollama terminée")
3. Attendez 5-30 minutes (selon connexion)
```

### ❌ "Première réponse très lente (30+ sec)"
```
→ C'est normal ! (charge le modèle)
→ Attendez
→ Les réponses suivantes sont rapides
```

### ❌ "PC très ralenti pendant réponse"
```
→ Normal aussi (utilise CPU/RAM)
→ Sur PC faible : utilisez "neural-chat" au lieu de "mistral"
→ Ou limitez "Tokens maximum" à 1024
```

### ❌ "Erreur 'Non authentifié'"
```
→ Reconnectez-vous à votre compte
→ Le chatbot nécessite une session active
```

---

## 📞 Besoin d'aide ?

1. Lisez : `INSTALLATION_OLLAMA.md` (doc complète)
2. Vérifiez les logs Apache
3. Testez directement Ollama :
   ```bash
   curl http://localhost:11434/api/tags
   ```

---

## 📊 État du système

Après avoir suivi cette checklist, vous aurez :

| Élément | Status |
|--------|--------|
| Ollama lancé | ✅ |
| Modèle téléchargé | ✅ |
| Chatbot configuré | ✅ |
| Connexion testée | ✅ |
| Chatbot fonctionnel | ✅ |

**Félicitations ! Tout fonctionne ! 🎉**

---

## 🔄 Prochaines étapes (optionnel)

- [ ] Explorer d'autres modèles (llama2, neural-chat)
- [ ] Optimiser les tokens max
- [ ] Ajouter des contexts spécialisés
- [ ] Configurer des prompts système custom
- [ ] Mettre en place un backup

---

**Notes personnelles :**
```
Modèle choisi : _______________
Date installation : _______________
Temps de téléchargement : _______________
Observations : _______________
```

---

**Bon utilisation du Tafkir IA ! 🚀**
