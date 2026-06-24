<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class FiscalMauritanieNotification extends CommonObject
{
    public $element = 'fiscalmauritanie_notification';
    public $table_element = 'fiscalmauritanie_notification';

    public $id;
    public $entity;
    public $fk_declaration;
    public $fk_user;
    public $tax_type;
    public $channel;
    public $subject;
    public $message;
    public $notification_date;
    public $status;
    public $error_message;

    public function __construct($db)
    {
        $this->db = $db;
        $this->channel = 'internal';
        $this->status = 0;
    }

    public function create($user, $notrigger = false)
    {
        global $conf;
        $now = dol_now();
        $entity = empty($this->entity) ? $conf->entity : $this->entity;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (entity, fk_declaration, fk_user, tax_type, channel, subject, message, notification_date, status, error_message, date_creation, fk_user_creat) VALUES (";
        $sql .= (int) $entity.", ".($this->fk_declaration ? (int) $this->fk_declaration : 'NULL').", ".($this->fk_user ? (int) $this->fk_user : 'NULL').", '".$this->db->escape($this->tax_type)."', '".$this->db->escape($this->channel)."', ";
        $sql .= "'".$this->db->escape($this->subject)."', '".$this->db->escape($this->message)."', ".($this->notification_date ? "'".$this->db->idate($this->notification_date)."'" : 'NULL').", ".(int) $this->status.", '".$this->db->escape($this->error_message)."', '".$this->db->idate($now)."', ".(int) $user->id.")";
        $resql = $this->db->query($sql);
        if (!$resql) { $this->error = $this->db->lasterror(); return -1; }
        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        return $this->id;
    }

    public function markSent()
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET status=1, notification_date='".$this->db->idate(dol_now())."' WHERE rowid=".(int) $this->id;
        return $this->db->query($sql) ? 1 : -1;
    }

    public function sendInternal($user)
    {
        $this->status = 1;
        $this->notification_date = dol_now();
        return $this->create($user);
    }

    public function sendEmail($user, $to)
    {
        if (empty($to)) { $this->error_message = 'Destinataire email manquant'; $this->status = -1; return $this->create($user); }
        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
        $mail = new CMailFile($this->subject, $to, getDolGlobalString('MAIN_INFO_SOCIETE_MAIL'), $this->message);
        $result = $mail->sendfile();
        $this->status = $result ? 1 : -1;
        $this->error_message = $result ? '' : $mail->error;
        $this->notification_date = dol_now();
        return $this->create($user);
    }
}

class FiscalMauritanieAudit
{
    public static function log($db, $user, $objectType, $objectId, $action, $field = '', $oldValue = '', $newValue = '', $reason = '')
    {
        global $conf;
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."fiscalmauritanie_audit (entity, object_type, fk_object, field_name, old_value, new_value, reason, action, date_action, fk_user, ip_address) VALUES (";
        $sql .= (int) $conf->entity.", '".$db->escape($objectType)."', ".(int) $objectId.", '".$db->escape($field)."', '".$db->escape($oldValue)."', '".$db->escape($newValue)."', '".$db->escape($reason)."', '".$db->escape($action)."', '".$db->idate(dol_now())."', ".(int) $user->id.", '".$db->escape($_SERVER['REMOTE_ADDR'] ?? '')."')";
        return $db->query($sql) ? 1 : -1;
    }
}
