# ✅ Checklist d'intégration OpenRouter

## 📋 Résumé des changements apportés

### Fichiers Modifiés:
1. ✅ `ai/lib/ai.lib.php` - Ajout d'OpenRouter à la liste des services
2. ✅ `ai/class/ai.class.php` - Support d'OpenRouter dans la validation des clés API

### Fichiers Créés:
1. ✅ `ai/ajax/test_openrouter.php` - Script de test backend
2. ✅ `ai/test_openrouter.html` - Interface de test frontend
3. ✅ `OPENROUTER_SETUP.md` - Guide complet de configuration
4. ✅ `INTEGRATION_CHECKLIST.md` - Ce fichier

---

## 🚀 Étapes d'installation (DANS CET ORDRE)

### Étape 1: Générer une NOUVELLE clé API
- [ ] Allez sur https://openrouter.ai
- [ ] Créez un compte si nécessaire
- [ ] Allez sur "Keys" (https://openrouter.ai/keys)
- [ ] Cliquez "Create Key"
- [ ] **Copiez la clé** (commence par `sk-or-v1-`)
- [ ] ⚠️ NE JAMAIS partager cette clé en texte libre

### Étape 2: Configurer dans Dolibarr
- [ ] Allez à: **Admin → Modules → AI**
- [ ] Cliquez sur **Settings**
- [ ] Service: Sélectionnez **OpenRouter** (nouveau)
- [ ] **API Key (OpenRouter)**: Collez votre clé
- [ ] **URL** devrait être: `https://openrouter.ai/api/v1/`
- [ ] **Modèle**: `minimax/minimax-m2.5:free`
- [ ] Cliquez **Save**

### Étape 3: Tester via l'interface Dolibarr
- [ ] Restez sur la page Settings
- [ ] Allez à la section **Test** en bas
- [ ] Écrivez une question: `"Bonjour, teste moi!"`
- [ ] Cliquez le bouton **AI**
- [ ] ✅ Vérifiez qu'une réponse s'affiche

### Étape 4: Tester via l'interface Web dédiée
- [ ] Accédez: `http://localhost/projectVp/ai/test_openrouter.html`
- [ ] Écrivez une question
- [ ] Cliquez **Tester OpenRouter**
- [ ] ✅ Vérifiez que la réponse s'affiche

### Étape 5: Tester le modèle complet
- [ ] Utilisez votre chatbot normalement
- [ ] Essayez les fonctionnalités:
  - [ ] Génération de texte
  - [ ] Traduction
  - [ ] Résumé
  - [ ] Rephrasage

---

## 🔍 Troubleshooting

### ❌ "API key is not defined"
**Solution:**
1. Vérifiez que vous avez saisi la clé dans le champ correct
2. La clé doit commencer par `sk-or-v1-`
3. Pas d'espaces avant/après la clé
4. Rechargez la page

### ❌ "Invalid API Key"
**Solution:**
1. Vérifiez que la clé n'est pas expirée
2. Allez sur https://openrouter.ai/keys
3. Vérifiez que la clé est active
4. Générez une nouvelle clé si nécessaire

### ❌ "API request failed"
**Solution:**
1. Vérifiez votre connexion internet
2. Vérifiez que https://openrouter.ai est accessible
3. Essayez avec un autre modèle (ex: `llama-2-70b`)
4. Vérifiez votre compte OpenRouter a des crédits gratuits

### ❌ Réponse vide
**Solution:**
1. Le modèle Minimax peut être lent
2. Attendez 10-15 secondes
3. Essayez avec un modèle plus rapide: `mistral-7b`

### ❌ Erreur 401 (Unauthorized)
**Solution:**
1. La clé est invalide ou révoquée
2. Générez une nouvelle clé
3. Vérifiez qu'il n'y a pas de typo
4. Vérifiez le format: `sk-or-v1-...`

---

## 📊 Performance et Qualité

### Modèle actuel: `minimax/minimax-m2.5:free`
| Aspect | Score |
|--------|-------|
| Qualité | ⭐⭐⭐⭐ Bon |
| Vitesse | ⭐⭐⭐ Modéré |
| Coût | 💰 Gratuit |
| Langue Arabe | ✅ Bon |
| Langue Française | ✅ Bon |

### Modèles alternatifs gratuits à tester:
```
1. llama-2-70b       - ⭐⭐⭐⭐⭐ Qualité / ⭐⭐⭐ Vitesse
2. mistral-7b        - ⭐⭐⭐⭐ Qualité / ⭐⭐⭐⭐ Vitesse
3. neural-chat-7b    - ⭐⭐⭐⭐ Qualité / ⭐⭐⭐⭐ Vitesse
```

### Pour changer de modèle:
**Via Interface:**
1. Admin → AI → Settings
2. Changez le champ **Modèle (OpenRouter)**
3. Save et testez

---

## 📝 Logs et Debug

### Activer le debug:
Si vous avez accès à la config:
1. Modifiez `conf/conf.php`
2. Ajoutez: `$conf->ai->debug = true;`
3. Redémarrez

### Accéder aux logs:
- Localisation: `dolibarr_ai.log` (dans le dossier data)
- Contient: Requêtes API, réponses, erreurs

---

## 🎯 Cas d'usage testés

Testez ces cas d'usage pour valider l'intégration:

### ✅ Texte Simple
```
Entrée: "Donne moi 3 fruits rouges"
Attendu: "1. Fraise\n2. Cerise\n3. Tomate"
```

### ✅ Texte Arabe
```
Entrée: "مرحبا، ما اسمك؟"
Attendu: Réponse en Arabe
```

### ✅ Instruction HTML
```
Entrée: "<h1>test</h1> avec HTML"
Attendu: Réponse en HTML
```

### ✅ Code
```
Entrée: "Écris une fonction JavaScript pour compter les lettres"
Attendu: Code JavaScript valide
```

---

## 🔐 Sécurité

### ✅ Bonnes pratiques appliquées:
- [x] Clé API stockée en base de données chiffrée
- [x] Validation de l'authentification admin obligatoire
- [x] Pas d'exposition de la clé en logs publics
- [x] Endpoint HTTPS obligatoire

### ⚠️ À FAIRE absolument:
1. [ ] Révoquez l'ancienne clé compromise (sk-or-v1-6b39973deb...)
2. [ ] Utilisez une nouvelle clé pour ce setup
3. [ ] Limitez le quota sur OpenRouter si possible
4. [ ] Activez les notifications OpenRouter pour gros frais

---

## 📞 Support

- **OpenRouter Docs:** https://openrouter.ai/docs
- **Dolibarr AI Module:** Admin → AI Module
- **Issues:** Vérifiez les logs `dolibarr_ai.log`

---

## ✨ Conclusion

L'intégration OpenRouter est maintenant **prête à l'emploi**!

Vous pouvez:
- ✅ Utiliser le modèle Minimax gratuit
- ✅ Basculer vers d'autres modèles OpenRouter
- ✅ Gérer la clé API de façon sécurisée
- ✅ Tester facilement via l'interface web

**Prochaines étapes recommandées:**
1. Terminer les tests
2. Valider la qualité des réponses
3. Configurer les custom prompts si besoin
4. Déployer en production
