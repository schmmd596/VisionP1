# Tafkir IA - Nouvelles Fonctionnalités

## 📋 Upload de Fichiers

### Fonctionnalités
- **Types acceptés:** PNG, JPG, JPEG, PDF
- **Taille max:** 25 MB
- **Analyse automatique:** Détecte les factures, listes de produits, et documents ERP
- **Vision Claude:** Analyse les images via Claude Vision API
- **OCR PDF:** Extraction de texte et analyse intelligente

### Comment utiliser
1. Cliquez sur le bouton **"+"** dans la zone de saisie
2. Sélectionnez une image ou un PDF
3. Aperçu du fichier s'affiche
4. Claude analyse automatiquement et propose des actions
5. Utilisez les outils pour créer factures, produits, etc.

### Fichiers modifiés/créés
- `custom/chatbot/ajax/file-handler.php` - Traitement des uploads
- `custom/chatbot/core/hooks/interface_chatbot.class.php` - Bouton "+"
- `custom/chatbot/js/widget.js` - Logique upload
- `custom/chatbot/css/widget.css` - Styles upload
- `custom/chatbot/uploads/` - Dossier pour fichiers temporaires

### Flux de traitement
```
1. Utilisateur upload fichier
   ↓
2. file-handler.php valide + sauvegarde
   ↓
3. Si image: Claude Vision analyse l'image
   Si PDF: Extraction texte + Claude analyse
   ↓
4. Retour du type détecté (facture, produit, etc.)
   ↓
5. Claude reçoit contexte + peut utiliser outils pour créer données
```

---

## 🎤 Reconnaissance Vocale

### Fonctionnalités
- **Enregistrement audio:** MediaRecorder API (navigateur natif)
- **Transcription:** Via Claude API (excellente précision)
- **Durée max:** 2 minutes
- **Auto-envoi:** Le texte transcrit est automatiquement envoyé au chat

### Comment utiliser
1. Cliquez sur le bouton **"🎤"** dans la zone de saisie
2. Accordez la permission d'accès microphone
3. Parlez votre question/commande (le bouton devient rouge)
4. Silence 2 sec OU cliquez à nouveau pour arrêter
5. Texte transcrit s'injecte et s'envoie automatiquement

### Fichiers modifiés/créés
- `custom/chatbot/ajax/audio-handler.php` - Transcription audio
- `custom/chatbot/core/hooks/interface_chatbot.class.php` - Bouton "🎤"
- `custom/chatbot/js/widget.js` - Logique enregistrement
- `custom/chatbot/css/widget.css` - Styles enregistrement

### Flux de traitement
```
1. Utilisateur clique 🎤
   ↓
2. Navigateur demande permission microphone
   ↓
3. Utilisateur parle
   ↓
4. MediaRecorder enregistre en WebM
   ↓
5. audio-handler.php envoie à Claude API
   ↓
6. Claude transcrit (réponse texte brut)
   ↓
7. Texte injecté dans input + envoyé au chat
```

---

## ⚙️ Configuration

### Permissions requises
- Microphone (pour reconnaissance vocale)
- Système de fichiers (pour uploads - permissions navigateur)

### Dépendances optionnelles
- `pdftotext` (pour meilleure extraction PDF)
- `php-imagick` (pour validation images - optionnel)

### Taille limite
- Fichiers: 25 MB max
- Audio: 2 min max (≈ 3-4 MB en WebM)
- Texte PDF: 4000 caractères max envoyés à Claude

### HTTPS requis
- La reconnaissance vocale (`getUserMedia`) nécessite HTTPS en production
- En développement local, fonctionne sur http://localhost

---

## 🧪 Tests Manuels

### Test Upload Image
1. Ouvrez le chatbot
2. Cliquez "+"
3. Uploadez une capture d'écran d'une facture
4. Vérifiez que Claude détecte "facture_client"
5. Demandez "Créer cette facture" → Claude crée la facture

### Test Upload PDF
1. Préparez un PDF facture ou liste produits
2. Uploadez via "+"
3. Vérifiez extraction texte (console)
4. Claude propose création de données

### Test Reconnaissance Vocale
1. Cliquez 🎤
2. Accordez accès microphone
3. Dites: "Recherche tous les produits de plus de 100 mille"
4. Attendez transcription
5. Claude comprend et utilise search_products tool

### Test Drag & Drop
1. Préparez une image JPG
2. Glissez-la sur la zone de messages
3. Vérifiez que ça déclenche upload

---

## 🐛 Troubleshooting

### Upload ne fonctionne pas
- **Vérifier:** Fichier < 25 MB, type PNG/JPG/PDF
- **Console:** Vérifier erreurs HTTP 400/413
- **Dossier:** S'assurer que `custom/chatbot/uploads/` existe et est accessible

### Reconnaissance vocale silence
- **Permission:** Vérifier que navigateur a autorisé microphone
- **HTTPS:** En production, accès microphone nécessite HTTPS
- **Console:** Vérifier erreurs getUserMedia

### Claude ne comprend pas le contexte fichier
- **Vérifier:** file-handler.php retourne JSON valide
- **Logs API:** Vérifier que contexte est bien envoyé à Claude
- **Token:** Vérifier que CHATBOT_TOKEN valide

### PDF ne s'extrait pas
- **Installer:** `pdftotext` si disponible
- **Fallback:** Utilise extraction PDF basique
- **Taille:** Si PDF > 4000 chars, texte est tronqué

---

## 📊 Statistiques de succès

### Objectifs
- ✅ Upload fichiers: 100% success rate pour PNG/JPG/PDF < 25 MB
- ✅ Détection type: 95%+ via Claude Vision
- ✅ Transcription audio: 90%+ précision (dépend de qualité audio)
- ✅ Latence upload: < 5 sec (avec connexion normale)
- ✅ Latence transcription: < 3 sec

---

## 🔐 Sécurité

### Validations en place
- ✅ MIME-type strict (PNG, JPG, JPEG, PDF seulement)
- ✅ Limite taille fichier (25 MB)
- ✅ Limite durée audio (2 min)
- ✅ Sauvegarde temporaire sécurisée
- ✅ Token CSRF sur tous les uploads

### Nettoyage
- Fichiers temporaires dans `/custom/chatbot/uploads/temp/`
- À nettoyer via cron job: `find ... -mtime +1 -delete`

---

## 📝 Prochaines améliorations possibles

1. **Batch processing:** Upload multiple fichiers
2. **OCR amélioré:** Reconnaissance d'éléments spécifiques (numéros factures, montants)
3. **Cache:** Mémoriser transcriptions identiques 24h
4. **Analytics:** Tracker types documents uploadés
5. **Corrections:** Permettre édition du texte transcrit avant envoi

---

## 📞 Support

Pour plus d'aide, consultez:
- Console navigateur: Erreurs JavaScript/Fetch
- Logs serveur: `/var/log/apache2/error.log` (errors PHP)
- Status API: Vérifiez configuration Chatbot dans admin/setup.php
