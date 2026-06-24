<?php

class FiscalMauritanieCalculator
{
    public static function calculateRate($base, $rate, $minimum = 0, $maximum = null, $ceiling = null)
    {
        $taxable = (float) $base;
        if ($ceiling !== null && $ceiling !== '') $taxable = min($taxable, (float) $ceiling);
        $amount = $taxable * ((float) $rate / 100);
        if ($minimum > 0) $amount = max($amount, (float) $minimum);
        if ($maximum !== null && $maximum !== '') $amount = min($amount, (float) $maximum);
        return round($amount, 2);
    }

    public static function calculatePercentageDeclaration($systemAmount, $percentage)
    {
        return round((float) $systemAmount * ((float) $percentage / 100), 2);
    }

    public static function calculateITS($db, $salary, $entity = 1)
    {
        $amount = 0;
        $sql = "SELECT tranche_min, tranche_max, rate, fixed_amount FROM ".MAIN_DB_PREFIX."fiscalmauritanie_barreme WHERE tax_type='ITS' AND active=1 AND entity=".(int) $entity." ORDER BY tranche_min ASC";
        $resql = $db->query($sql);
        if (!$resql) return 0;
        while ($obj = $db->fetch_object($resql)) {
            $min = (float) $obj->tranche_min;
            $max = $obj->tranche_max === null ? null : (float) $obj->tranche_max;
            if ($salary <= $min) continue;
            $slice = ($max === null) ? ($salary - $min) : (min($salary, $max) - $min);
            if ($slice > 0) $amount += ($slice * ((float) $obj->rate / 100)) + (float) $obj->fixed_amount;
        }
        return round($amount, 2);
    }

    public static function calculateCNSS($salary, $rate, $ceiling = null)
    {
        return self::calculateRate($salary, $rate, 0, null, $ceiling);
    }

    public static function calculateCNAM($salary, $rate, $ceiling = null)
    {
        return self::calculateRate($salary, $rate, 0, null, $ceiling);
    }

    public static function calculateTA($payroll, $rate)
    {
        return self::calculateRate($payroll, $rate);
    }

    public static function calculateIS($products, $charges, $rate)
    {
        $profit = (float) $products - (float) $charges;
        return round(max(0, $profit) * ((float) $rate / 100), 2);
    }

    public static function calculateIMF($turnoverOrMinimum, $isAmount, $rate = 0, $minimum = 0)
    {
        $imf = $rate > 0 ? self::calculateRate($turnoverOrMinimum, $rate, $minimum) : (float) $minimum;
        return round(max($imf, (float) $isAmount), 2);
    }

    public static function calculateHonoraryWithholding($invoiceAmount, $rate)
    {
        return self::calculateRate($invoiceAmount, $rate);
    }

    public static function computeByRule($db, $rule, $baseData)
    {
        $base = isset($baseData['base']) ? (float) $baseData['base'] : 0;
        switch ($rule->calculation_method) {
            case 'progressive':
                return self::calculateITS($db, $base, $rule->entity);
            case 'profit_rate':
                return self::calculateIS($baseData['products'] ?? 0, $baseData['charges'] ?? 0, $rule->rate);
            case 'minimum_compare':
                return self::calculateIMF($base, $baseData['is_amount'] ?? 0, $rule->rate, $rule->minimum_amount);
            case 'withholding':
                return self::calculateHonoraryWithholding($base, $rule->rate);
            case 'manual':
                return round($base, 2);
            case 'rate':
            default:
                return self::calculateRate($base, $rule->rate, $rule->minimum_amount, $rule->maximum_amount, $rule->ceiling_amount);
        }
    }
}
