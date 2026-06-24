# ✅ Mistral Integration Verification Checklist

## 📋 Pre-Configuration Checklist

### Pre-req 1: Mistral Account
- [ ] Created account at https://console.mistral.ai
- [ ] API key generated: `eHr3hK0DatGmpuhZqleYj4FCRZF03g1l`
- [ ] API key tested on Mistral console (works)
- [ ] Account has credit/quota available

### Pre-req 2: Dolibarr Setup
- [ ] Dolibarr installed and running
- [ ] Chatbot IA module installed
- [ ] Admin user logged in
- [ ] Database accessible
- [ ] PHP cURL extension enabled

## 🔧 Configuration Checklist

### Step 1: Web Interface Configuration
- [ ] Navigate to Admin → Chatbot IA → Configuration
- [ ] Setup.php page loads without errors
- [ ] Provider dropdown appears
- [ ] All 4 providers visible (Anthropic, Mistral, OpenAI, OpenRouter)

### Step 2: Select Mistral
- [ ] Select "Mistral AI" from provider dropdown
- [ ] API key placeholder changes to "Clé Mistral API"
- [ ] Help text shows "console.mistral.ai"
- [ ] Model dropdown updates to show Mistral models

### Step 3: Enter Credentials
- [ ] Paste API key: `eHr3hK0DatGmpuhZqleYj4FCRZF03g1l`
- [ ] Key appears as dots (password field)
- [ ] Click "Afficher" button
- [ ] Key becomes visible
- [ ] Click "Afficher" again to hide

### Step 4: Select Model
- [ ] Model dropdown shows Mistral models
- [ ] Models include:
  - [ ] mistral-small-latest
  - [ ] mistral-nemo
  - [ ] mistral-medium-latest
  - [ ] mistral-large-2411
  - [ ] pixtral-12b-2409
- [ ] Select: `mistral-small-latest`

### Step 5: Configure Options
- [ ] Max Tokens: Set to 2048 (or preferred value)
- [ ] Enabled checkbox: CHECKED ✓
- [ ] All fields filled correctly

## 🧪 Testing Checklist

### Test 1: Web Interface Test
- [ ] Click button: "🔌 Tester la connexion API"
- [ ] Status shows: "Test en cours..."
- [ ] Wait 5-10 seconds
- [ ] Result appears: ✅ Connexion réussie !
- [ ] Result is GREEN

### Test 2: Diagnostic API
```
Method: GET
URL: /custom/chatbot/admin/test_mistral.php
```
- [ ] Page loads (HTTP 200)
- [ ] JSON response received
- [ ] `"configured": true`
- [ ] `"provider": "mistral"`
- [ ] `"is_mistral": true`
- [ ] `"success": true`

### Test 3: Save Configuration
- [ ] Click: "Sauvegarder la configuration"
- [ ] Message appears: "Configuration sauvegardée avec succès"
- [ ] Browser redirects to setup.php
- [ ] All values still filled

## 🗄️ Database Verification

### Check 1: Database Constants
```sql
SELECT name, value FROM llx_const 
WHERE name LIKE 'CHATBOT_%'
```
- [ ] CHATBOT_PROVIDER = 'mistral'
- [ ] CHATBOT_API_KEY = 'eHr3hK0DatGmpuhZqleYj4FCRZF03g1l'
- [ ] CHATBOT_MODEL = 'mistral-small-latest' (or your choice)
- [ ] CHATBOT_ENABLED = '1'
- [ ] CHATBOT_MAX_TOKENS = '2048' (or your setting)

### Check 2: Entity Configuration
```sql
SELECT entity, name, value FROM llx_const 
WHERE name = 'CHATBOT_PROVIDER'
```
- [ ] Entity is 1 (or your multi-entity ID)
- [ ] Provider is 'mistral'

## 🔄 Chat API Test

### Test 1: Direct API Call
```bash
curl -X POST http://localhost/custom/chatbot/ajax/chat.php \
  -H "Content-Type: application/json" \
  -d '{"message": "Dis juste OK", "history": []}'
```
- [ ] No 401 (Unauthorized) error
- [ ] No 403 (Forbidden) error
- [ ] Response is JSON or event-stream
- [ ] Contains message from Mistral

### Test 2: Chat Widget (if available)
- [ ] Navigate to any Dolibarr page
- [ ] Chat widget appears (bottom right)
- [ ] Type test message
- [ ] Send message
- [ ] Response appears from Mistral
- [ ] Not generic error message

## 🔍 Code Review

### Check 1: setup.php
```php
<?php
// Expected:
// - Provider selector with 4 options
// - Dynamic placeholder updates
// - Model list per provider
// - Test button
```
- [ ] File exists: `custom/chatbot/admin/setup.php`
- [ ] Contains provider selection code
- [ ] Contains model arrays for each provider
- [ ] Contains JavaScript for dynamic updates

### Check 2: chat.php
```php
<?php
// Expected:
// - Provider detection from CHATBOT_PROVIDER
// - Fallback auto-detection
// - Mistral URL: https://api.mistral.ai/v1/chat/completions
```
- [ ] File exists: `custom/chatbot/ajax/chat.php`
- [ ] Line ~42: `$provider = $conf->global->CHATBOT_PROVIDER ?? '';`
- [ ] Line ~50-70: Provider detection logic includes Mistral
- [ ] Mistral API URL is correct

### Check 3: test_mistral.php
- [ ] File exists: `custom/chatbot/admin/test_mistral.php`
- [ ] Contains diagnostic logic
- [ ] Performs actual API call
- [ ] Returns detailed JSON

## 📁 File Verification

### Created Files
- [ ] `admin/setup.php` - Updated ✓
- [ ] `admin/test_mistral.php` - Created ✓
- [ ] `ajax/chat.php` - Updated ✓
- [ ] `MISTRAL_SETUP.md` - Created ✓
- [ ] `MISTRAL_INTEGRATION_SUMMARY.md` - Created ✓
- [ ] `config_mistral.sql` - Created ✓
- [ ] `README_MISTRAL.md` - Created ✓
- [ ] `VERIFICATION_CHECKLIST.md` - This file ✓

### File Sizes (Approximate)
- [ ] setup.php > 10 KB
- [ ] chat.php > 40 KB
- [ ] test_mistral.php > 2 KB
- [ ] Documentation > 3 KB each

## 🚀 Post-Configuration

### After Successful Test
- [ ] Close setup page
- [ ] Go to any Dolibarr page
- [ ] Use the chatbot widget
- [ ] Ask a simple question
- [ ] Verify Mistral responds (not Anthropic)
- [ ] Check response quality
- [ ] Check response speed

### Monitor Usage
- [ ] Log into https://console.mistral.ai
- [ ] Check API usage dashboard
- [ ] Verify requests are being made
- [ ] Check estimated cost
- [ ] Monitor quota/credits

## 🔐 Security Verification

### Check 1: API Key Security
- [ ] Key is not in logs
- [ ] Key is hidden in password field
- [ ] Key is only visible when "Afficher" is clicked
- [ ] Key is not in URLs
- [ ] Key is not in HTML source (unless password visible)

### Check 2: Input Validation
- [ ] Invalid model name shows error
- [ ] Empty API key shows error
- [ ] Very long API key (> 200 chars) is handled
- [ ] Special characters don't break config

## 🎯 Functional Tests

### Test 1: Simple Message
```
Message: "Bonjour"
Expected: Mistral responds in French
```
- [ ] Message sent successfully
- [ ] Response received from Mistral
- [ ] Not a generic error message
- [ ] French language detected

### Test 2: Complex Query
```
Message: "Explique les modèles Mistral en détail"
Expected: Detailed response about Mistral models
```
- [ ] Request processed
- [ ] Response is detailed
- [ ] Response is relevant to query
- [ ] No API errors

### Test 3: Multiple Exchanges
```
1. Ask question 1
2. Ask follow-up question
3. Ask another question
Expected: Context maintained, responses consistent
```
- [ ] All messages processed
- [ ] No cumulative errors
- [ ] Chat history works
- [ ] Context preserved

## 📊 Performance Tests

### Benchmark 1: Response Time
```
Measure time from message send to first response
Expected: < 2 seconds with mistral-small-latest
```
- [ ] Measure: _____ ms
- [ ] Status: PASS / FAIL

### Benchmark 2: Streaming Speed
```
Monitor token streaming speed
Expected: ~60-100 tokens/sec
```
- [ ] Tokens appear in real-time
- [ ] No long gaps between tokens
- [ ] Final response complete

### Benchmark 3: Load Test
```
Send 5 messages rapidly
Expected: All processed, no timeout
```
- [ ] Message 1: OK
- [ ] Message 2: OK
- [ ] Message 3: OK
- [ ] Message 4: OK
- [ ] Message 5: OK

## 📝 Documentation Verification

### Check 1: README Files
- [ ] README_MISTRAL.md exists and readable
- [ ] MISTRAL_SETUP.md exists and readable
- [ ] MISTRAL_INTEGRATION_SUMMARY.md exists and readable
- [ ] All contain accurate information

### Check 2: SQL Script
- [ ] config_mistral.sql exists
- [ ] Contains correct API key
- [ ] Contains valid model names
- [ ] Can be executed without errors

## ⚠️ Known Issues / Caveats

- [ ] Note any issues encountered:
  ```
  _______________________________________________
  ```

- [ ] Mistral-specific quirks:
  - [ ] Model name changes (unstable model names)
  - [ ] Rate limiting behavior
  - [ ] Timeout settings

## ✨ Final Sign-Off

### Overall Status
- [ ] Configuration: ✅ PASS / ❌ FAIL
- [ ] API Connectivity: ✅ PASS / ❌ FAIL
- [ ] Chat Functionality: ✅ PASS / ❌ FAIL
- [ ] Database: ✅ PASS / ❌ FAIL
- [ ] Security: ✅ PASS / ❌ FAIL

### Ready for Production?
- [ ] All tests passed
- [ ] Documentation reviewed
- [ ] Performance acceptable
- [ ] No security issues
- [ ] Backup taken before deployment
- [ ] Users trained (if applicable)

**Date Verified**: _____________  
**Verified By**: _____________  
**Notes**: _____________________

---

## 🆘 If Something Fails

1. Check specific failed checklist item
2. Review corresponding documentation
3. Check error message details
4. Run `test_mistral.php` for diagnostics
5. Review PHP error logs
6. Check Mistral console for API status
7. Verify database was updated correctly

**Need Help?**
- See: README_MISTRAL.md (Troubleshooting section)
- See: MISTRAL_SETUP.md (Configuration guide)
- Check: Mistral docs at https://docs.mistral.ai
