# Configuration OpenRouter pour le Chatbot

## 1. ⚠️ Sécurité - API Key Management

### IMPORTANT: JAMAIS partager votre clé API en texte libre !

**Votre ancienne clé est compromise.**

### Étapes:
1. Allez sur https://openrouter.ai/keys
2. Supprimez l'ancienne clé (sk-or-v1-6b39973deb...)
3. Cliquez sur "Create Key"
4. Copiez la nouvelle clé
5. Collez-la UNIQUEMENT dans l'interface setup du Dolibarr (pas en texte libre!)

---

## 2. Installation dans Dolibarr

### Étape 1: Accès à l'administration
- Allez à: **Admin → Modules → AI Module**
- Cliquez sur **Settings**

### Étape 2: Configuration
1. Sélectionnez le service: **OpenRouter** (nouveau service ajouté)
2. Dans le champ "API Key (OpenRouter)" collez votre nouvelle clé
3. L'URL sera automatiquement: `https://openrouter.ai/api/v1/`
4. Le modèle par défaut sera: `minimax/minimax-m2.5:free`

### Étape 3: Test
1. Restez sur la même page
2. Allez à la section **Test**
3. Écrivez une question simple: "Bonjour, test du modèle Minimax"
4. Cliquez sur le bouton AI pour générer la réponse
5. Vérifiez que la réponse s'affiche correctement

---

## 3. Modèle Minimax m2.5 Free

### Caractéristiques
- **Gratuit**: Oui, pas de coûts
- **Langue**: Arabe, Anglais, Français, Chinois, etc.
- **Latence**: Modérée (~2-5 secondes)
- **Qualité**: Bonne pour la plupart des usages

### Limitations
- Moins capable que GPT-4 ou Claude
- Peut faire des erreurs sur les tâches complexes
- Limite de requêtes selon votre plan OpenRouter

### Alternatives sur OpenRouter (aussi free)
```
- llama-2-70b (très bon, rapide)
- mistral-7b (très bon)
- neural-chat-7b-v3-1
```

---

## 4. Résolution des problèmes

### ❌ "API key is not defined"
- Vérifiez que vous avez collé la clé dans le champ "API Key (OpenRouter)"
- La clé doit commencer par `sk-or-v1-`

### ❌ "API request failed"
- Vérifiez votre connexion internet
- Vérifiez que OpenRouter.ai est accessible
- Regardez les logs à: `/var/dolibarr_ai.log` (si Debug activé)

### ❌ "Invalid API Key"
- La clé a peut-être été révoquée
- Générez une nouvelle clé
- Vérifiez qu'il n'y a pas d'espaces avant/après la clé

### ❌ Réponses lentes
- OpenRouter peut avoir des pics de charge
- Essayez un autre modèle free comme `llama-2-70b`

---

## 5. Changer de modèle

### Option 1: Via Interface (recommandé)
- Admin → AI Module → Custom Prompt
- Modifiez le modèle pour chaque fonction

### Option 2: Via Base de données (avancé)
Pour utiliser un autre modèle free sur OpenRouter:

```sql
-- Exemple: utiliser Llama 2 au lieu de Minimax
UPDATE dolibarr_const 
SET value = 'llama-2-70b' 
WHERE name = 'AI_API_OPENROUTER_MODEL_TEXT';
```

### Modèles OpenRouter Testés
```
minimax/minimax-m2.5:free
llama-2-70b
mistral-7b
meta-llama/llama-3-8b
neural-chat-7b-v3-1
```

---

## 6. Monitoring & Logs

Pour activer le debug:
1. Admin → AI Module → Settings
2. Cherchez "AI Debug" (si disponible)
3. Les logs se trouveront dans `/var/dolibarr_ai.log`

---

## Support
- Documentation OpenRouter: https://openrouter.ai/docs
- Issues du module AI: Checkit dans l'interface admin
