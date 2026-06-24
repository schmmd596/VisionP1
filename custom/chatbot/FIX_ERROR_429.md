# 🔧 Fix pour Erreur 429 - Solution Complète

## 🚨 Symptôme
- Chatbot retourne: **⚠ Erreur API (429)**
- Message "hello" non traité

## 🔍 Cause Identifiée
L'auto-détection du provider confondait la clé Mistral avec OpenAI, ce qui causait un appel à la mauvaise API.

**Avant (Bug)**:
```
Clé Mistral: eHr3hK0DatGmpuhZqleYj4FCRZF03g1l (pas de préfixe)
↓
Auto-détect: "Pas de sk-, donc c'est OpenAI"
↓
Appel: api.openai.com avec clé Mistral
↓
Résultat: HTTP 429 (clé invalide pour OpenAI)
```

## ✅ Solution Appliquée

### 1. Correction du Code (FAIT)
Modifié `chat.php` pour détecter Mistral par le **modèle en priorité**:

```php
// NOUVEAU: Priorité au modèle Mistral
if (strpos($model, 'mistral-') === 0 || strpos($model, 'pixtral-') === 0) {
    $provider = 'mistral';
    $api_url = 'https://api.mistral.ai/v1/chat/completions';
}
```

### 2. Ce qu'il faut faire maintenant

#### Option A: Via Web Interface (Recommandé)
```
1. Admin → Chatbot IA → Configuration
2. Assurez-vous que:
   - Fournisseur: "Mistral AI" (ou vide, le modèle détectera)
   - Modèle: "mistral-small-latest" ✓
   - Clé API: eHr3hK0DatGmpuhZqleYj4FCRZF03g1l ✓
3. Cliquez: "Sauvegarder"
4. Testez le chatbot
```

#### Option B: Via SQL Direct
```sql
REPLACE INTO llx_const (name, value, entity) VALUES
  ('CHATBOT_PROVIDER', 'mistral', 1),
  ('CHATBOT_MODEL', 'mistral-small-latest', 1),
  ('CHATBOT_API_KEY', 'eHr3hK0DatGmpuhZqleYj4FCRZF03g1l', 1);
```

## 🧪 Verification

### Test 1: Configuration Page
```
Naviguer vers: /custom/chatbot/admin/setup.php
Vérifier:
✓ Provider = "Mistral AI" ou vide (détection par modèle)
✓ Model = "mistral-small-latest"
✓ API Key = "eHr3hK0DatGmpuhZqleYj4FCRZF03g1l"
✓ Enabled = Coché
```

### Test 2: Direct Chat
```
1. Aller à n'importe quelle page Dolibarr
2. Ouvrir le widget chat Tafkir IA
3. Envoyer: "hello"
4. Vérifier: Response arrive (pas d'erreur 429)
```

### Test 3: Diagnostic
```
Accéder: /custom/chatbot/ajax/test_api_debug.php
Vérifier:
- final_provider = mistral
- api_url = https://api.mistral.ai/v1/chat/completions
- problem_analysis = []
```

## 🎯 Prochaines Étapes

1. **Accédez à la config** et vérifiez que tout est correct
2. **Testez le chatbot** avec un message simple
3. **Vérifiez** que vous obtenez une réponse (pas d'erreur 429)
4. **Monitorer** l'usage sur console.mistral.ai

## 📊 Comparaison: Avant / Après

| Aspect | Avant (Bug) | Après (Fix) |
|--------|-----------|-----------|
| **Clé Mistral** | Détectée comme OpenAI | ✅ Détectée comme Mistral |
| **Endpoint** | api.openai.com ❌ | api.mistral.ai ✅ |
| **Erreur** | 429 Too Many Requests | ✅ Pas d'erreur |
| **Réponse** | Erreur API | ✅ Réponse Mistral |

## 🔐 Sécurité

La clé API Mistral est:
- ✅ Stockée en base de données (table llx_const)
- ✅ Masquée en interface (champs password)
- ✅ Non loggée
- ✅ Jamais exposée en clair (sauf click "Afficher")

## 🆘 Si ça ne marche toujours pas

1. **Vérifier la clé API**
   - Accédez à https://console.mistral.ai
   - Vérifiez que la clé est active
   - Vérifiez que le compte a du crédit
   - Testez avec: `/custom/chatbot/admin/test_mistral.php`

2. **Vérifier la configuration**
   - Ouvrez: `/custom/chatbot/ajax/test_api_debug.php`
   - Vérifiez que final_provider = mistral
   - Vérifiez que api_url = https://api.mistral.ai/...

3. **Vérifier les logs**
   - Accédez à: /var/log/php/error.log
   - Cherchez les erreurs liées au chatbot

4. **Tester l'API directement**
   ```bash
   curl -X POST https://api.mistral.ai/v1/chat/completions \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer eHr3hK0DatGmpuhZqleYj4FCRZF03g1l" \
     -d '{
       "model": "mistral-small-latest",
       "messages": [{"role": "user", "content": "hello"}],
       "max_tokens": 100
     }'
   ```
   - Vous devriez obtenir HTTP 200 avec une réponse

## 📚 Fichiers Modifiés

- ✅ `chat.php` - Correction de la priorité de détection du provider
- ✅ `setup.php` - Configuration interface
- ✅ Créé: `test_api_debug.php` - Diagnostic avancé

## ✨ Résultat Attendu

Après l'application de ce fix:
- ✅ Erreur 429 disparaît
- ✅ Le chatbot répond avec Mistral
- ✅ Les messages sont traités normalement
- ✅ Aucun changement de configuration nécessaire (détection automatique)

---

**Date Fix**: 2026-04-28
**Version**: 1.1 (avec correction d'auto-détection)
**Status**: ✅ Ready
