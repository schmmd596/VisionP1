<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class FiscalMauritaniePayment extends CommonObject
{
    public $element = 'fiscalmauritanie_payment';
    public $table_element = 'fiscalmauritanie_payment';

    public $id;
    public $entity;
    public $fk_declaration;
    public $ref;
    public $amount;
    public $currency_code;
    public $payment_date;
    public $payment_mode;
    public $payment_reference;
    public $status;
    public $note;

    public function __construct($db)
    {
        $this->db = $db;
        $this->currency_code = 'MRU';
        $this->status = 0;
    }

    public function create($user, $notrigger = false)
    {
        global $conf;
        $this->entity = empty($this->entity) ? $conf->entity : $this->entity;
        if (empty($this->ref)) $this->ref = 'PAY-FM-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (entity, fk_declaration, ref, amount, currency_code, payment_date, payment_mode, payment_reference, status, note, date_creation, fk_user_creat) VALUES (";
        $sql .= (int) $this->entity.", ".(int) $this->fk_declaration.", '".$this->db->escape($this->ref)."', ".price2num($this->amount).", '".$this->db->escape($this->currency_code)."', ";
        $sql .= ($this->payment_date ? "'".$this->db->idate($this->payment_date)."'" : 'NULL').", '".$this->db->escape($this->payment_mode)."', '".$this->db->escape($this->payment_reference)."', ".(int) $this->status.", '".$this->db->escape($this->note)."', '".$this->db->idate(dol_now())."', ".(int) $user->id.")";
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        $this->refreshDeclarationPaymentStatus($this->fk_declaration);
        return $this->id;
    }

    public function refreshDeclarationPaymentStatus($fk_declaration)
    {
        $sql = "SELECT d.total_amount, COALESCE(SUM(p.amount), 0) as paid FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration d LEFT JOIN ".MAIN_DB_PREFIX."fiscalmauritanie_payment p ON p.fk_declaration=d.rowid WHERE d.rowid=".(int) $fk_declaration." GROUP BY d.total_amount";
        $resql = $this->db->query($sql);
        if (!$resql || !($obj = $this->db->fetch_object($resql))) return -1;
        $status = 0;
        if ((float) $obj->paid >= (float) $obj->total_amount && (float) $obj->total_amount > 0) $status = 2;
        elseif ((float) $obj->paid > 0) $status = 1;
        $sql = "UPDATE ".MAIN_DB_PREFIX."fiscalmauritanie_declaration SET payment_status=".(int) $status.", payment_date=".($status == 2 ? "'".$this->db->idate(dol_now())."'" : 'NULL')." WHERE rowid=".(int) $fk_declaration;
        return $this->db->query($sql) ? 1 : -1;
    }
}
