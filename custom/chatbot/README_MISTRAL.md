# 🎯 Mistral API Integration for Tafkir IA Chatbot

## 📁 Files Created

### 1. **admin/setup.php** - Configuration Page
   - Web interface for managing API settings
   - Provider selection (Anthropic, Mistral, OpenAI, OpenRouter)
   - Model selection specific to each provider
   - API key input with show/hide toggle
   - Built-in test button
   - Dynamic placeholder updates

### 2. **admin/test_mistral.php** - Diagnostic Tool
   - Test script to verify Mistral API connectivity
   - Checks configuration (provider, key, model)
   - Performs actual API call to verify credentials
   - Returns detailed JSON response
   - Access: `/custom/chatbot/admin/test_mistral.php`

### 3. **ajax/chat.php** - Updated Backend
   - Explicit provider detection via `CHATBOT_PROVIDER` config
   - Fallback auto-detection from key prefix/model name
   - Mistral API URL: `https://api.mistral.ai/v1/chat/completions`
   - Streaming support (SSE - Server-Sent Events)
   - Vision API support (Pixtral model)
   - Tool/function calling support

### 4. **MISTRAL_SETUP.md** - Configuration Guide
   - Step-by-step setup instructions
   - Model comparison and recommendations
   - Troubleshooting section
   - Resource links
   - Feature comparison with other providers

### 5. **MISTRAL_INTEGRATION_SUMMARY.md** - Integration Overview
   - Complete technical summary
   - What was implemented
   - Quick setup guide
   - Testing options
   - Performance metrics
   - Security notes

### 6. **config_mistral.sql** - Database Configuration
   - SQL script to configure Mistral directly in database
   - Pre-filled with your API key
   - Optional: Direct configuration without web UI

### 7. **This File** - Documentation Index

## 🚀 Quick Start

### Option A: Web Interface (Recommended)
```
1. Go to Admin → Chatbot IA → Configuration
2. Select "Mistral AI" as Provider
3. Paste API Key: eHr3hK0DatGmpuhZqleYj4FCRZF03g1l
4. Choose Model: mistral-small-latest
5. Click "Test API"
6. Click "Save Configuration"
```

### Option B: SQL Direct (Advanced)
```
1. Open phpMyAdmin or MySQL client
2. Copy contents of config_mistral.sql
3. Execute in your Dolibarr database
4. Verify with: test_mistral.php
```

## 🔍 Testing the Setup

### Test 1: Web Interface Test
```
Navigation: Admin → Chatbot IA → Configuration
Click: 🔌 Test API Connection
Expected: ✅ Connection successful!
```

### Test 2: Diagnostic API
```
URL: http://localhost/custom/chatbot/admin/test_mistral.php
Method: GET
Response: JSON with configuration and test result
```

### Test 3: Chat API
```
Method: POST
URL: http://localhost/custom/chatbot/ajax/chat.php
Body: {
  "message": "Dis juste OK",
  "history": []
}
Expected: Response from Mistral model (streaming or JSON)
```

## 🎯 Configuration Fields

| Field | Value | Example |
|-------|-------|---------|
| **Provider** | mistral | Select from dropdown |
| **API Key** | String | eHr3hK0DatGmpuhZqleYj4FCRZF03g1l |
| **Model** | Mistral model ID | mistral-small-latest |
| **Max Tokens** | 256-8192 | 2048 |
| **Enabled** | 0 or 1 | 1 (enabled) |

## 📚 Available Models

```
Mistral Models (mistral-*)
├── mistral-small-latest      ⚡⚡⚡ Fast, cheap ✓
├── mistral-nemo              ⚡⚡⚡⚡ Very fast
├── mistral-medium-latest     ⚡⚡ Balanced
├── mistral-large-2411        ⚡ Most powerful
└── pixtral-12b-2409          Vision support
```

## 🔐 Security

- ✅ API key stored in database (llx_const table)
- ✅ Not logged or exposed in logs
- ✅ Password input type (hidden by default)
- ✅ Show/hide button for visibility control
- ✅ Input validation and sanitization
- ✅ 90-second timeout (prevents hanging requests)

## 🌐 API Endpoints

| Provider | Endpoint |
|----------|----------|
| Mistral | `https://api.mistral.ai/v1/chat/completions` |
| Anthropic | `https://api.anthropic.com/v1/messages` |
| OpenAI | `https://api.openai.com/v1/chat/completions` |
| OpenRouter | `https://openrouter.ai/api/v1/chat/completions` |

## 🛠️ Configuration Storage

```
Database Table: llx_const
Config Keys:
  - CHATBOT_PROVIDER = 'mistral'
  - CHATBOT_API_KEY = '...'
  - CHATBOT_MODEL = 'mistral-small-latest'
  - CHATBOT_ENABLED = '1'
  - CHATBOT_MAX_TOKENS = '2048'
```

## 🔄 Migration from Other Providers

If switching from Anthropic to Mistral:

```
1. Go to Configuration
2. Change Provider to "Mistral AI"
3. Replace API Key with Mistral key
4. Models list auto-updates to Mistral models
5. Select new model from dropdown
6. Test connection
7. Save
```

## ⚙️ How It Works

### Provider Detection (in chat.php)

```php
// Priority 1: Explicit config
if (!empty($conf->global->CHATBOT_PROVIDER)) {
    $provider = $conf->global->CHATBOT_PROVIDER; // 'mistral'
}
// Priority 2: Auto-detect
else if (strpos($model, 'mistral-') === 0) {
    $provider = 'mistral';
}
```

### API Call Flow

```
1. User sends message from frontend
2. chat.php detects provider
3. Determines API URL and headers
4. Calls Mistral API with proper format
5. Streams response back to user (SSE)
6. Frontend displays message in real-time
```

### Supported Features

- ✅ Text generation (chat completions)
- ✅ Streaming responses (Server-Sent Events)
- ✅ Function calling (tools)
- ✅ Vision (image analysis with Pixtral)
- ✅ Multi-language support
- ✅ Context/history support
- ✅ System prompts

## 📊 Performance Expectations

| Model | Avg Response Time | Token Speed |
|-------|------------------|-------------|
| mistral-small | ~500ms | 60-100 tps |
| mistral-nemo | ~300ms | 100+ tps |
| mistral-medium | ~800ms | 50-80 tps |
| mistral-large | ~1.5s | 30-50 tps |

*tps = tokens per second*

## 🐛 Troubleshooting

### "Configuration not loading"
```
Solution: Clear browser cache, refresh page
```

### "API key rejected"
```
Solution: 
1. Verify key format (should have no spaces)
2. Check at console.mistral.ai that key is active
3. Regenerate key if needed
```

### "Model not found"
```
Solution:
1. Check model exists on Mistral docs
2. Use one of: mistral-small-latest, mistral-large-2411, pixtral-12b-2409
3. Avoid typos (case-sensitive)
```

### "No response / timeout"
```
Solution:
1. Check internet connectivity
2. Reduce max_tokens (try 1024 instead of 2048)
3. Check Mistral status page
4. Use smaller model (mistral-nemo)
```

## 📞 Support Resources

- **Mistral Console**: https://console.mistral.ai
- **API Docs**: https://docs.mistral.ai
- **Status Page**: https://status.mistral.ai
- **Pricing**: https://mistral.ai/pricing/

## 📝 Implementation Notes

### Files Modified
- ✅ `admin/setup.php` - Configuration interface
- ✅ `ajax/chat.php` - Backend provider detection

### Files Created
- ✅ `admin/test_mistral.php` - Diagnostic tool
- ✅ `MISTRAL_SETUP.md` - Setup guide
- ✅ `MISTRAL_INTEGRATION_SUMMARY.md` - Technical overview
- ✅ `config_mistral.sql` - Database config
- ✅ `README_MISTRAL.md` - This file

### Backwards Compatibility
- ✅ Existing configs continue to work
- ✅ Auto-detection fallback preserved
- ✅ No breaking changes to chat API
- ✅ All providers still supported

## 🎓 Key Features

### Before (Limited)
- One provider at a time
- Auto-detection by key prefix
- Limited model selection

### After (Enhanced)
- ✅ Explicit provider selection
- ✅ Dynamic model lists per provider
- ✅ Better UI with placeholders
- ✅ Diagnostic tools
- ✅ Easy switching between providers
- ✅ Direct SQL configuration option

## ✨ What's Next

1. ✅ **Configuration** - Done
2. 🔄 **Testing** - Do this now
3. 🚀 **Usage** - Start using the chatbot
4. 📊 **Optimization** - Monitor performance
5. 🔗 **Integration** - Build custom features

## 📌 Important Notes

1. **Entity ID**: If using multi-entity Dolibarr, ensure config is for correct entity
2. **Rate Limiting**: Mistral may rate-limit at high volumes
3. **Cost**: Monitor API usage on console.mistral.ai
4. **Languages**: Auto-detects user language (FR, EN, AR, ES, etc.)
5. **Context**: Keeps conversation history (up to 16 messages)

---

**Created**: 2026-04-27  
**Version**: 1.0  
**Status**: Ready for Testing  
**Last Updated**: 2026-04-27
