<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class FiscalMauritanieRule extends CommonObject
{
    public $element = 'fiscalmauritanie_rule';
    public $table_element = 'fiscalmauritanie_rule';
    public $picto = 'generic';

    public $id;
    public $rowid;
    public $entity;
    public $code;
    public $label;
    public $tax_type;
    public $rate;
    public $minimum_amount;
    public $maximum_amount;
    public $ceiling_amount;
    public $frequency;
    public $calculation_method;
    public $declared_percentage;
    public $due_day;
    public $due_month;
    public $active;
    public $note;
    public $date_start;
    public $date_end;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($user, $notrigger = false)
    {
        global $conf;
        $now = dol_now();
        $this->entity = empty($this->entity) ? $conf->entity : $this->entity;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (entity, code, label, tax_type, rate, minimum_amount, maximum_amount, ceiling_amount, frequency, calculation_method, declared_percentage, due_day, due_month, active, note, date_start, date_end, date_creation, fk_user_creat) VALUES (";
        $sql .= ((int) $this->entity).", ";
        $sql .= "'".$this->db->escape($this->code)."', '".$this->db->escape($this->label)."', '".$this->db->escape($this->tax_type)."', ";
        $sql .= price2num($this->rate).", ".price2num($this->minimum_amount).", ".($this->maximum_amount === '' || $this->maximum_amount === null ? 'NULL' : price2num($this->maximum_amount)).", ";
        $sql .= ($this->ceiling_amount === '' || $this->ceiling_amount === null ? 'NULL' : price2num($this->ceiling_amount)).", '".$this->db->escape($this->frequency)."', '".$this->db->escape($this->calculation_method)."', ";
        $sql .= price2num($this->declared_percentage ?: 100).", ".($this->due_day ? (int) $this->due_day : 'NULL').", ".($this->due_month ? (int) $this->due_month : 'NULL').", ".(int) $this->active.", ";
        $sql .= "'".$this->db->escape($this->note)."', ".($this->date_start ? "'".$this->db->idate($this->date_start)."'" : 'NULL').", ".($this->date_end ? "'".$this->db->idate($this->date_end)."'" : 'NULL').", ";
        $sql .= "'".$this->db->idate($now)."', ".(int) $user->id.")";
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        return $this->id;
    }

    public function fetch($id, $code = '')
    {
        global $conf;
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE entity IN (".getEntity('fiscalmauritanie').")";
        if ($id > 0) $sql .= " AND rowid = ".(int) $id;
        elseif ($code) $sql .= " AND code = '".$this->db->escape($code)."'";
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

    public function fetchByTaxType($tax_type)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE tax_type = '".$this->db->escape($tax_type)."' AND active = 1 AND entity IN (".getEntity('fiscalmauritanie').") ORDER BY rowid DESC";
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        if ($obj = $this->db->fetch_object($resql)) return $this->fetch($obj->rowid);
        return 0;
    }

    public function update($user, $notrigger = false)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
        $sql .= "code='".$this->db->escape($this->code)."', label='".$this->db->escape($this->label)."', tax_type='".$this->db->escape($this->tax_type)."', ";
        $sql .= "rate=".price2num($this->rate).", minimum_amount=".price2num($this->minimum_amount).", maximum_amount=".($this->maximum_amount === '' || $this->maximum_amount === null ? 'NULL' : price2num($this->maximum_amount)).", ";
        $sql .= "ceiling_amount=".($this->ceiling_amount === '' || $this->ceiling_amount === null ? 'NULL' : price2num($this->ceiling_amount)).", frequency='".$this->db->escape($this->frequency)."', calculation_method='".$this->db->escape($this->calculation_method)."', ";
        $sql .= "declared_percentage=".price2num($this->declared_percentage ?: 100).", due_day=".($this->due_day ? (int) $this->due_day : 'NULL').", due_month=".($this->due_month ? (int) $this->due_month : 'NULL').", active=".(int) $this->active.", note='".$this->db->escape($this->note)."', fk_user_modif=".(int) $user->id;
        $sql .= " WHERE rowid=".(int) $this->id;
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        return 1;
    }

    public function delete($user, $notrigger = false)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid=".(int) $this->id;
        return $this->db->query($sql) ? 1 : -1;
    }

    public function getRules($activeOnly = true)
    {
        $rules = array();
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE entity IN (".getEntity('fiscalmauritanie').")";
        if ($activeOnly) $sql .= " AND active = 1";
        $sql .= " ORDER BY tax_type, code";
        $resql = $this->db->query($sql);
        if (!$resql) return $rules;
        while ($obj = $this->db->fetch_object($resql)) {
            $rule = new self($this->db);
            $rule->fetch($obj->rowid);
            $rules[] = $rule;
        }
        return $rules;
    }
}
