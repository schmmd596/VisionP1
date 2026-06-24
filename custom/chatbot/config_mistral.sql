-- Mistral API Configuration Script for Tafkir IA Chatbot
-- This SQL script configures Mistral API integration in Dolibarr
-- Replace ENTITY_ID with your actual entity ID (usually 1)
-- Replace YOUR_MISTRAL_API_KEY with your actual API key from console.mistral.ai

-- Configuration for Entity 1 (default)
REPLACE INTO llx_const (name, value, type, note, visible, entity) VALUES
  ('CHATBOT_PROVIDER', 'mistral', 'chaine', 'Mistral AI Provider', 0, 1),
  ('CHATBOT_API_KEY', 'eHr3hK0DatGmpuhZqleYj4FCRZF03g1l', 'chaine', 'Mistral API Key', 0, 1),
  ('CHATBOT_MODEL', 'mistral-small-latest', 'chaine', 'Mistral Model', 0, 1),
  ('CHATBOT_ENABLED', '1', 'chaine', 'Enable Chatbot', 0, 1),
  ('CHATBOT_MAX_TOKENS', '2048', 'chaine', 'Max Tokens', 0, 1);

-- Verify the configuration was saved
SELECT name, value FROM llx_const
WHERE name IN ('CHATBOT_PROVIDER', 'CHATBOT_API_KEY', 'CHATBOT_MODEL', 'CHATBOT_ENABLED', 'CHATBOT_MAX_TOKENS')
AND entity = 1;

-- Optional: Clear configuration (if you need to reset)
-- DELETE FROM llx_const WHERE name LIKE 'CHATBOT_%' AND entity = 1;

-- Notes:
-- 1. Replace 'eHr3hK0DatGmpuhZqleYj4FCRZF03g1l' with your actual API key
-- 2. Available models:
--    - mistral-small-latest (recommended for speed & cost)
--    - mistral-nemo (very fast)
--    - mistral-medium-latest (balanced)
--    - mistral-large-2411 (most powerful)
--    - pixtral-12b-2409 (with vision)
-- 3. max_tokens range: 256-8192 (default: 2048)
-- 4. entity = 1 is the default; change if you have multiple entities
