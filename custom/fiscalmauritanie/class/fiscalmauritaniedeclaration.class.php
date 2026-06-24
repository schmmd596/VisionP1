<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritanierule.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniecalculator.class.php';

class FiscalMauritanieDeclaration extends CommonObject
{
    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_CANCELLED = -1;
    const PAYMENT_UNPAID = 0;
    const PAYMENT_PARTIAL = 1;
    const PAYMENT_PAID = 2;

    public $element = 'fiscalmauritanie_declaration';
    public $table_element = 'fiscalmauritanie_declaration';
    public $picto = 'generic';

    public $id;
    public $ref;
    public $entity;
    public $fk_soc;
    public $company_nif;
    public $tax_type;
    public $period;
    public $period_start;
    public $period_end;
    public $mode_declaration;
    public $amount_system;
    public $declared_percentage;
    public $declared_amount;
    public $adjustment_amount;
    public $penalty_amount;
    public $total_amount;
    public $currency_code;
    public $status;
    public $payment_status;
    public $due_date;
    public $payment_date;
    public $fk_rule;
    public $note_private;
    public $note_public;
    public $model_pdf;
    public $last_main_doc;

    public function __construct($db)
    {
        $this->db = $db;
        $this->status = self::STATUS_DRAFT;
        $this->payment_status = self::PAYMENT_UNPAID;
        $this->currency_code = 'MRU';
        $this->declared_percentage = 100;
    }

    public function create($user, $notrigger = false)
    {
        global $conf;
        $now = dol_now();
        $this->entity = empty($this->entity) ? $conf->entity : $this->entity;
        if (empty($this->ref)) $this->ref = $this->getNextRef();
        $this->recalculateTotals();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (ref, entity, fk_soc, company_nif, tax_type, period, period_start, period_end, mode_declaration, amount_system, declared_percentage, declared_amount, adjustment_amount, penalty_amount, total_amount, currency_code, status, payment_status, due_date, payment_date, fk_rule, note_private, note_public, model_pdf, date_creation, fk_user_creat) VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."', ".(int) $this->entity.", ".($this->fk_soc ? (int) $this->fk_soc : 'NULL').", '".$this->db->escape($this->company_nif)."', '".$this->db->escape($this->tax_type)."', '".$this->db->escape($this->period)."', ";
        $sql .= ($this->period_start ? "'".$this->db->idate($this->period_start)."'" : 'NULL').", ".($this->period_end ? "'".$this->db->idate($this->period_end)."'" : 'NULL').", '".$this->db->escape($this->mode_declaration ?: 'real')."', ";
        $sql .= price2num($this->amount_system).", ".price2num($this->declared_percentage).", ".price2num($this->declared_amount).", ".price2num($this->adjustment_amount).", ".price2num($this->penalty_amount).", ".price2num($this->total_amount).", '".$this->db->escape($this->currency_code ?: 'MRU')."', ";
        $sql .= (int) $this->status.", ".(int) $this->payment_status.", ".($this->due_date ? "'".$this->db->idate($this->due_date)."'" : 'NULL').", ".($this->payment_date ? "'".$this->db->idate($this->payment_date)."'" : 'NULL').", ".($this->fk_rule ? (int) $this->fk_rule : 'NULL').", ";
        $sql .= "'".$this->db->escape($this->note_private)."', '".$this->db->escape($this->note_public)."', '".$this->db->escape($this->model_pdf)."', '".$this->db->idate($now)."', ".(int) $user->id.")";

        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        return $this->id;
    }

    public function fetch($id, $ref = '')
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE entity IN (".getEntity('fiscalmauritanie').")";
        if ($id > 0) $sql .= " AND rowid = ".(int) $id;
        elseif ($ref) $sql .= " AND ref = '".$this->db->escape($ref)."'";
        else return -1;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        if ($obj = $this->db->fetch_object($resql)) {
            foreach ($obj as $key => $value) $this->$key = $value;
            $this->id = $obj->rowid;
            return 1;
        }
        return 0;
    }

    public function update($user, $notrigger = false)
    {
        $this->recalculateTotals();
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
        $sql .= "fk_soc=".($this->fk_soc ? (int) $this->fk_soc : 'NULL').", company_nif='".$this->db->escape($this->company_nif)."', tax_type='".$this->db->escape($this->tax_type)."', period='".$this->db->escape($this->period)."', ";
        $sql .= "period_start=".($this->period_start ? "'".$this->db->idate($this->period_start)."'" : 'NULL').", period_end=".($this->period_end ? "'".$this->db->idate($this->period_end)."'" : 'NULL').", mode_declaration='".$this->db->escape($this->mode_declaration)."', ";
        $sql .= "amount_system=".price2num($this->amount_system).", declared_percentage=".price2num($this->declared_percentage).", declared_amount=".price2num($this->declared_amount).", adjustment_amount=".price2num($this->adjustment_amount).", penalty_amount=".price2num($this->penalty_amount).", total_amount=".price2num($this->total_amount).", ";
        $sql .= "currency_code='".$this->db->escape($this->currency_code)."', due_date=".($this->due_date ? "'".$this->db->idate($this->due_date)."'" : 'NULL').", fk_rule=".($this->fk_rule ? (int) $this->fk_rule : 'NULL').", note_private='".$this->db->escape($this->note_private)."', note_public='".$this->db->escape($this->note_public)."', fk_user_modif=".(int) $user->id;
        $sql .= " WHERE rowid=".(int) $this->id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function validate($user)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET status=".self::STATUS_VALIDATED.", date_validation='".$this->db->idate(dol_now())."', fk_user_valid=".(int) $user->id." WHERE rowid=".(int) $this->id;
        if (!$this->db->query($sql)) { $this->error = $this->db->lasterror(); return -1; }
        $this->status = self::STATUS_VALIDATED;
        return 1;
    }

    public function delete($user, $notrigger = false)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid=".(int) $this->id." AND status=".self::STATUS_DRAFT;
        return $this->db->query($sql) ? 1 : -1;
    }

    public function recalculateTotals()
    {
        if ($this->mode_declaration === 'percentage') {
            $this->declared_amount = FiscalMauritanieCalculator::calculatePercentageDeclaration($this->amount_system, $this->declared_percentage);
        } elseif ($this->mode_declaration === 'manual') {
            $this->declared_amount = price2num($this->declared_amount);
        } else {
            $this->declared_amount = price2num($this->amount_system);
            $this->declared_percentage = 100;
        }
        $this->total_amount = price2num($this->declared_amount) + price2num($this->adjustment_amount) + price2num($this->penalty_amount);
    }

    public function computeFromRule($user, $baseData = array())
    {
        $rule = new FiscalMauritanieRule($this->db);
        if ($this->fk_rule) $rule->fetch($this->fk_rule);
        else $rule->fetchByTaxType($this->tax_type);
        if (empty($rule->id)) { $this->error = 'Aucune règle fiscale active trouvée'; return -1; }
        $this->fk_rule = $rule->id;
        $this->amount_system = FiscalMauritanieCalculator::computeByRule($this->db, $rule, $baseData);
        $this->declared_percentage = $rule->declared_percentage ?: 100;
        $this->mode_declaration = $this->mode_declaration ?: 'real';
        $this->recalculateTotals();
        return 1;
    }

    public function getNextRef()
    {
        return 'FM-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
    }

    public function getLibStatut($mode = 0)
    {
        if ($this->status == self::STATUS_VALIDATED) return '<span class="fiscalmauritanie-badge fiscalmauritanie-badge-validated">Validée</span>';
        if ($this->status == self::STATUS_CANCELLED) return '<span class="fiscalmauritanie-badge fiscalmauritanie-badge-late">Annulée</span>';
        return '<span class="fiscalmauritanie-badge fiscalmauritanie-badge-draft">Brouillon</span>';
    }
}
