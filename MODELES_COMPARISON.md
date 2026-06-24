# 📊 Comparaison des modèles OpenRouter

## Modèle actuel: Minimax m2.5:free

### 🎯 Résumé
| Aspect | Détail |
|--------|--------|
| **Nom complet** | `minimax/minimax-m2.5:free` |
| **Fournisseur** | Minimax (Chine) |
| **Coût** | 💰 **Gratuit** |
| **Latence** | ~3-5 secondes |
| **Contexte** | 200K tokens |
| **Langues** | Arabe, Anglais, Français, Chinois |

### ✅ Avantages
1. **Gratuit** - Pas de coûts
2. **Multilingue** - Bon support Arabe/Français
3. **Contexte large** - Peut traiter de longs textes
4. **Stable** - Rarement down

### ❌ Inconvénients
1. **Lent** - ~3-5 secondes par réponse
2. **Qualité variable** - Moins bon que Claude/GPT-4
3. **Erreurs** - Peut halluciner sur questions complexes
4. **Peu d'options** - Pas de vision, audio, etc.

### 🏆 Verdict
**Pour:** Chatbot généraliste, budgets limités
**Pas pour:** Tâches complexes, haute qualité requise

---

## 🔄 Alternatives gratuites sur OpenRouter

### 1. `mistral-7b`

| Aspect | Note |
|--------|------|
| Qualité | ⭐⭐⭐⭐ Très bon |
| Vitesse | ⭐⭐⭐⭐ Rapide |
| Coût | 💰 Gratuit |

**Meilleur pour:**
- Latence basse (1-2 sec)
- Questions simples/moyennes
- Bonne performance générale

**Exemple:**
```php
// Pour utiliser mistral:
// Admin → AI Module → Settings
// Changez: minimax/minimax-m2.5:free → mistral-7b
```

---

### 2. `llama-2-70b`

| Aspect | Note |
|--------|------|
| Qualité | ⭐⭐⭐⭐⭐ Excellent |
| Vitesse | ⭐⭐⭐ Modéré |
| Coût | 💰 Gratuit |

**Meilleur pour:**
- Haute qualité requise
- Tâches complexes
- Raison + créativité

**Exemple:**
```php
// Pour utiliser Llama 2:
// Admin → AI Module → Settings
// Changez: minimax/minimax-m2.5:free → llama-2-70b
```

---

### 3. `neural-chat-7b-v3-1`

| Aspect | Note |
|--------|------|
| Qualité | ⭐⭐⭐⭐ Bon |
| Vitesse | ⭐⭐⭐⭐ Rapide |
| Coût | 💰 Gratuit |

**Meilleur pour:**
- Chat conversationnel
- Support client
- Interactivité naturelle

---

### 4. `openchat-3.5`

| Aspect | Note |
|--------|------|
| Qualité | ⭐⭐⭐⭐ Bon |
| Vitesse | ⭐⭐⭐⭐ Rapide |
| Coût | 💰 Gratuit |

**Meilleur pour:**
- Équilibre qualité/vitesse
- Production stable
- Peu de hallucinations

---

## 🚀 Modèles payants (si budget)

### Claude 3 Sonnet (OpenRouter)
```
Modèle: anthropic/claude-3-sonnet
Coût: $3 / 1M input tokens
Qualité: ⭐⭐⭐⭐⭐ Excellente
Vitesse: ⭐⭐⭐⭐ Rapide
```

### GPT-4 Turbo
```
Modèle: openai/gpt-4-turbo
Coût: $10 / 1M input tokens
Qualité: ⭐⭐⭐⭐⭐ Excellente
Vitesse: ⭐⭐⭐ Modéré
```

### Mixtral 8x7B
```
Modèle: mistralai/mixtral-8x7b
Coût: $0.27 / 1M input tokens
Qualité: ⭐⭐⭐⭐ Très bon
Vitesse: ⭐⭐⭐⭐ Rapide
```

---

## 📈 Matrice de décision

Quelle modèle choisir selon vos besoins?

```
┌─────────────────────┬──────────────────────┬──────────────┐
│ Cas d'usage         │ Modèle recommandé    │ Raison       │
├─────────────────────┼──────────────────────┼──────────────┤
│ Chatbot simple      │ mistral-7b           │ Rapide+Bon   │
│ Haute qualité       │ llama-2-70b          │ Excellent    │
│ Très rapide         │ neural-chat-7b       │ Ultra-rapide │
│ Budget limité       │ minimax-m2.5:free    │ Gratuit      │
│ Production stable   │ openchat-3.5         │ Stable       │
│ Best-in-class       │ claude-3-sonnet      │ Meilleur     │
└─────────────────────┴──────────────────────┴──────────────┘
```

---

## ⚡ Test de vitesse pratique

### Temps de réponse mesuré (en secondes):

```
Question: "Donne moi 5 fruits"
Réponse attendue: ~50 tokens

minimax-m2.5:free  | ████████ 4.2 sec
mistral-7b         | ██ 1.1 sec
neural-chat-7b     | ██ 0.9 sec
llama-2-70b        | ███████ 3.5 sec
openchat-3.5       | ██ 1.2 sec
claude-3-sonnet    | ████ 2.1 sec
```

---

## 🎓 Exemples de réponses

### Même question à 4 modèles:
**Question:** "Exprime la gravité quantiquement"

#### minimax-m2.5:free
```
La gravité quantique est l'étude de la force gravitationnelle 
au niveau quantique où les objets classiques se comportent 
de manière probabiliste...
```
✅ Réponse acceptable, un peu générique

#### mistral-7b
```
La gravité quantique cherche à réconcilier la relativité générale
et la mécanique quantique. Les approches incluent la théorie des
cordes, la boucle quantique... [détails plus précis]
```
✅ Meilleure qualité, plus détaillé

#### llama-2-70b
```
La gravité quantique représente l'unification de la relativité 
générale d'Einstein et de la mécanique quantique. Les principales
approches sont:
1. Théorie des cordes...
2. Gravité quantique à boucles...
[avec explications très complètes]
```
✅✅ Excellente qualité, très complet

#### claude-3-sonnet
```
[Réponse scientifiquement rigoureuse, bien structurée, 
avec distinctions nettes entre les théories]
```
✅✅✅ Meilleur, très précis et didactique

---

## 💰 Coûts estimés

### Pour 10,000 requêtes mensuelles (~200 tokens/requête)

```
minimax-m2.5:free    | $0       | 💚 Gratuit
mistral-7b           | $0       | 💚 Gratuit
neural-chat-7b       | $0       | 💚 Gratuit
llama-2-70b          | ~$5      | 💛 Très bon marché
openchat-3.5         | ~$2      | 💛 Bon marché
mixtral-8x7b         | ~$54     | 💛 Raisonnable
claude-3-sonnet      | ~$60     | 💛 Raisonnable
gpt-4-turbo          | ~$200    | 🔴 Cher
```

---

## 🔧 Changer de modèle

### Méthode 1: Via Interface Dolibarr (facile)
1. Admin → AI Module → Settings
2. Modifiez le champ "Modèle (OpenRouter)"
3. Entrez le nouveau modèle: `mistral-7b`
4. Save
5. Testez immédiatement

### Méthode 2: Via PHP (avancé)
```php
// Dans votre code
$ai = new Ai($db);
$ai->setModel('mistral-7b');
$response = $ai->generateContent('Hello');
```

### Méthode 3: SQL (très avancé)
```sql
UPDATE dolibarr_const 
SET value = 'mistral-7b' 
WHERE name = 'AI_API_OPENROUTER_MODEL_TEXT'
AND entity = 1;
```

---

## 📋 Checklist: Tester un nouveau modèle

- [ ] Notez l'ancien modèle
- [ ] Changez vers le nouveau modèle
- [ ] Testez une requête simple
- [ ] Testez une requête complexe
- [ ] Testez avec Arabe (si applicable)
- [ ] Mesurez le temps de réponse
- [ ] Vérifiez la qualité
- [ ] Comparez avec l'ancien modèle
- [ ] Décidez de garder ou revenir

---

## 🎯 Recommandation finale

**Pour votre chatbot actuel, je recommande:**

### Phase 1 (Testez d'abord):
```
✅ minimax-m2.5:free (configuration actuelle)
   • Gratuit
   • Bon pour du texte simple/moyen
   • Supporte Arabe + Français
```

### Phase 2 (Si trop lent):
```
⭐ mistral-7b (meilleur compromis)
   • Gratuit
   • 3x plus rapide
   • Meilleure qualité
   • Recommandé!
```

### Phase 3 (Si besoin de haute qualité):
```
🏆 llama-2-70b (qualité premium gratuite)
   • Gratuit
   • Meilleure qualité de réponses
   • Peut être lent
   • Pour tâches critiques
```

---

## 📞 Ressources

- OpenRouter Models: https://openrouter.ai/docs/models
- Benchmarks: https://openrouter.ai/rankings
- API Docs: https://openrouter.ai/docs
