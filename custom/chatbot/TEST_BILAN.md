# 🧪 Test Cases for Bilan (Balance Sheet) Feature

## Prerequisite Setup
Run these tests in order to build up accounting data.

### 1. ✅ Test: Create Supplier Invoice (should create accounting entries)
```
Command: "créer facture fournisseur Ali 100 stylos prix 2 MRU paye par Bankily"
Expected Response: ✓ Facture fourn. [REF] créée (1 lignes, 200 MRU) + paiement Bankily

Verify:
- Go to: Comptabilité → Écritures comptables
- Should see 2 entries: Débit 601 (Achats) and Crédit 401 (Fournisseurs)
- Total = 200 MRU
```

### 2. ✅ Test: Create Customer Invoice (should create accounting entries)
```
Command: "créer facture client Bob 50 cahiers prix 5 MRU"
Expected Response: ✓ Facture client [REF] créée (1 lignes, 250 MRU)

Verify:
- Go to: Comptabilité → Écritures comptables
- Should see 2 new entries: Débit 411 (Clients) and Crédit 701 (Ventes)
- Total = 250 MRU
```

### 3. ✅ Test: Create Another Supplier Invoice
```
Command: "créer facture fournisseur Khaled 200 oranges prix 1 MRU paye Sedad"
Expected Response: ✓ Facture fourn. [REF] créée (1 lignes, 200 MRU) + paiement Sedad

Verify:
- Total accounting entries should now be 6 (2 for each invoice)
```

### 4. ✅ Test: Check Balance Sheet (MAIN TEST)
```
Command: "affiche mon bilan"
Expected Response:
Actif: 450 MRU (411=250 + 601=200)
Passif: 450 MRU (401=200 + 701=250)
Équilibre: ✓

If timeout occurs:
- Check MySQL performance: mysql> SELECT COUNT(*) FROM llx_accounting_bookkeeping;
- Should show: 6 entries (or however many you created)
```

### 5. ✅ Test: Check Income Statement
```
Command: "résultat comptable"
Expected Response:
Produits: 250 MRU (account 701)
Charges: 200 MRU (account 601)
Résultat: 50 MRU
IS 25%: 12 MRU
Net: 38 MRU
```

### 6. ✅ Test: List Bank Accounts
```
Command: "quelles sont mes banques ?"
Expected Response:
- Bankily (should exist or be auto-created)
- Sedad (auto-created from payment)
- [other existing banks]
```

---

## Database Quick Check

To verify entries are being created, run directly in MySQL/Database:

```sql
-- Count entries
SELECT COUNT(*) as total FROM llx_accounting_bookkeeping;

-- List all entries
SELECT 
    numero_compte,
    label_compte,
    label_operation,
    debit,
    credit
FROM llx_accounting_bookkeeping
ORDER BY doc_date DESC;

-- Check balance (Actif vs Passif)
SELECT 
    SUBSTRING(numero_compte, 1, 1) as classe,
    SUM(IF(debit IS NOT NULL, debit, 0)) - SUM(IF(credit IS NOT NULL, credit, 0)) as solde
FROM llx_accounting_bookkeeping
GROUP BY classe;
```

---

## Expected Results After All Tests

| Account | Description | Debit | Credit | Balance |
|---------|---|---|---|---|
| 411 | Clients | 250 | - | 250 |
| 401 | Fournisseurs | - | 200 | (200) |
| 601 | Achats | 200 | - | 200 |
| 701 | Ventes | - | 250 | (250) |
| **TOTALS** | | 450 | 450 | 0 ✓ |

---

## Troubleshooting

### If bilan still times out:
1. Check if BookKeeping entries are being created at all
2. Verify that `auto_create_accounting_entries_for_invoice()` is being called
3. Check error logs: tail -f /var/log/apache2/error.log
4. Verify BookKeeping class has `createFromValues()` method

### If entries are not being created:
1. Verify invoices are being validated properly
2. Check that $user object is valid
3. Ensure entity is set correctly

### If bilan shows ❌ (unbalanced):
1. Check account classification (1-4 = Actif, 5-7 = Passif)
2. Ensure debit/credit entries are correct
3. Verify accounting_bookkeeping.debit and .credit columns contain correct values

---

**Last Updated:** 2026-04-20  
**Feature:** Automatic Accounting Entry Creation for Invoices
