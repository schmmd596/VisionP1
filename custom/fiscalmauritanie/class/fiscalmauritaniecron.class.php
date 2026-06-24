<?php
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritanienotification.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniedeclaration.class.php';

class FiscalMauritanieCron
{
    public $db;
    public $error;
    public $output;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function runNotifications($parameters = '', &$object = null, &$action = '', $hookmanager = null)
    {
        global $conf, $user;
        $count = 0;
        $days = explode(',', getDolGlobalString('FISCALMAURITANIE_NOTIFY_DAYS', '30,15,7,3,0'));
        $days = array_map('intval', $days);
        foreach ($days as $day) {
            $target = dol_time_plus_duree(dol_now(), $day, 'd');
            $date = dol_print_date($target, '%Y-%m-%d');
            $sql = "SELECT rowid, tax_type, ref, due_date FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration WHERE entity IN (".getEntity('fiscalmauritanie').") AND payment_status <> 2 AND due_date='".$this->db->escape($date)."'";
            $resql = $this->db->query($sql);
            while ($resql && ($obj = $this->db->fetch_object($resql))) {
                if ($this->alreadyNotified($obj->rowid, $day)) continue;
                $notif = new FiscalMauritanieNotification($this->db);
                $notif->fk_declaration = $obj->rowid;
                $notif->tax_type = $obj->tax_type;
                $notif->channel = 'internal';
                $notif->subject = 'Échéance fiscale '.$obj->tax_type.' - '.$obj->ref;
                $notif->message = 'La déclaration '.$obj->ref.' arrive à échéance le '.dol_print_date($this->db->jdate($obj->due_date), 'day').'. Délai restant : '.$day.' jour(s).';
                $notif->sendInternal($user);
                $count++;
            }
        }
        $this->output = $count.' notification(s) fiscale(s) générée(s).';
        return 0;
    }

    public function prepareDraftDeclarations($parameters = '', &$object = null, &$action = '', $hookmanager = null)
    {
        global $conf, $user;
        $created = 0;
        $month = dol_print_date(dol_now(), '%Y-%m');
        $sql = "SELECT rowid, tax_type, frequency, due_day, declared_percentage FROM ".MAIN_DB_PREFIX."fiscalmauritanie_rule WHERE active=1 AND entity IN (".getEntity('fiscalmauritanie').")";
        $resql = $this->db->query($sql);
        while ($resql && ($rule = $this->db->fetch_object($resql))) {
            if ($rule->frequency !== 'monthly') continue;
            if ($this->draftExists($rule->tax_type, $month)) continue;
            $decl = new FiscalMauritanieDeclaration($this->db);
            $decl->tax_type = $rule->tax_type;
            $decl->period = $month;
            $decl->period_start = dol_mktime(0, 0, 0, (int) substr($month, 5, 2), 1, (int) substr($month, 0, 4));
            $decl->period_end = dol_get_last_day((int) substr($month, 0, 4), (int) substr($month, 5, 2));
            $decl->due_date = dol_mktime(0, 0, 0, (int) substr($month, 5, 2), (int) ($rule->due_day ?: 15), (int) substr($month, 0, 4));
            $decl->mode_declaration = 'real';
            $decl->amount_system = 0;
            $decl->declared_percentage = $rule->declared_percentage ?: 100;
            $decl->fk_rule = $rule->rowid;
            if ($decl->create($user) > 0) $created++;
        }
        $this->output = $created.' brouillon(s) de déclaration préparé(s).';
        return 0;
    }

    protected function alreadyNotified($fk_declaration, $day)
    {
        $pattern = 'Délai restant : '.$day.' jour';
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."fiscalmauritanie_notification WHERE fk_declaration=".(int) $fk_declaration." AND message LIKE '%".$this->db->escape($pattern)."%' LIMIT 1";
        $resql = $this->db->query($sql);
        return ($resql && $this->db->num_rows($resql) > 0);
    }

    protected function draftExists($taxType, $period)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."fiscalmauritanie_declaration WHERE tax_type='".$this->db->escape($taxType)."' AND period='".$this->db->escape($period)."' AND entity IN (".getEntity('fiscalmauritanie').") LIMIT 1";
        $resql = $this->db->query($sql);
        return ($resql && $this->db->num_rows($resql) > 0);
    }
}
