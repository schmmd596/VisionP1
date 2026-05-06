<?php
/**
 *	\file       custom/pressing/class/pressingbonentree.class.php
 *	\ingroup    pressing
 *	\brief      Class for Pressing Bon d'Entrée (Reception Order)
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

/**
 *	Class for Pressing Bon d'Entrée
 */
class PressingBonEntree extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'pressing_bon_entree';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'pressing_bon_entree';

	public $id;
	public $ref;
	public $entity;
	public $fk_soc;
	public $date_entree;
	public $date_validation;
	public $status;
	public $fk_user_author;
	public $fk_user_valid;
	public $note_private;
	public $payment_status;
	public $payment_amount;
	public $fk_bank_account;
	public $date_payment;

	/**
	 *  Constructor
	 *
	 *  @param      DoliDb		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = 0;
	}

	/**
	 *  Create object into database
	 *
	 *  @param      User	$user        User that creates
	 *  @param      bool	$notrigger   false=launch triggers after, true=disable triggers
	 *  @return     int      		   	 <0 if KO, Id of created object if OK
	 */
	public function create($user, $notrigger = false)
	{
		global $conf, $langs;

		$this->db->begin();

		if (empty($this->ref)) {
			$this->ref = $this->getNextRef();
		}

		if (empty($this->date_entree)) {
			$this->date_entree = dol_now();
		}

		$sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . " (";
		$sql .= "ref, entity, fk_soc, date_entree, status, fk_user_author, note_private";
		$sql .= ") VALUES (";
		$sql .= "'" . $this->db->escape($this->ref) . "', ";
		$sql .= (int) $this->entity . ", ";
		$sql .= (int) $this->fk_soc . ", ";
		$sql .= "'" . $this->db->idate($this->date_entree) . "', ";
		$sql .= (int) $this->status . ", ";
		$sql .= (int) $user->id . ", ";
		$sql .= "'" . $this->db->escape($this->note_private) . "'";
		$sql .= ")";

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);
			$this->fk_user_author = $user->id;
			$this->db->commit();
			return $this->id;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *  Load object in memory from database
	 *
	 *  @param      int		$id    Id object
	 *  @param      string	$ref   Ref object
	 *  @return     int          	 <0 if KO, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$sql = "SELECT * FROM " . MAIN_DB_PREFIX . $this->table_element;
		if ($id) {
			$sql .= " WHERE rowid = " . ((int) $id);
		} elseif ($ref) {
			$sql .= " WHERE ref = '" . $this->db->escape($ref) . "'";
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;
				$this->ref = $obj->ref;
				$this->entity = $obj->entity;
				$this->fk_soc = $obj->fk_soc;
				$this->date_entree = $this->db->jdate($obj->date_entree);
				$this->date_validation = $this->db->jdate($obj->date_validation);
				$this->status = $obj->status;
				$this->fk_user_author = $obj->fk_user_author;
				$this->fk_user_valid = $obj->fk_user_valid;
				$this->note_private = $obj->note_private;
				$this->payment_status = $obj->payment_status ?? 0;
				$this->payment_amount = $obj->payment_amount ?? 0;
				$this->fk_bank_account = $obj->fk_bank_account ?? null;
				$this->date_payment = $this->db->jdate($obj->date_payment ?? null);

				return 1;
			}
			return 0;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			return -1;
		}
	}

	/**
	 *  Update object into database
	 *
	 *  @param      User	$user        User that modify
	 *  @param      bool	$notrigger   false=launch triggers after, true=disable triggers
	 *  @return     int     		   	 <0 if KO, >0 if OK
	 */
	public function update($user, $notrigger = false)
	{
		$this->db->begin();

		$sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET ";
		$sql .= "ref = '" . $this->db->escape($this->ref) . "', ";
		$sql .= "fk_soc = " . (int) $this->fk_soc . ", ";
		$sql .= "date_entree = " . (empty($this->date_entree) ? "NULL" : "'" . $this->db->idate($this->date_entree) . "'") . ", ";
		$sql .= "status = " . (int) $this->status . ", ";
		$sql .= "payment_status = " . (int) ($this->payment_status ?? 0) . ", ";
		$sql .= "payment_amount = " . (float) ($this->payment_amount ?? 0) . ", ";
		$sql .= "fk_bank_account = " . (empty($this->fk_bank_account) ? "NULL" : (int) $this->fk_bank_account) . ", ";
		$sql .= "date_payment = " . (empty($this->date_payment) ? "NULL" : "'" . $this->db->idate($this->date_payment) . "'") . ", ";
		$sql .= "note_private = '" . $this->db->escape($this->note_private) . "'";
		$sql .= " WHERE rowid = " . ((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *  Delete object in database
	 *
	 *  @param      User	$user        User that delete
	 *  @param      bool	$notrigger   false=launch triggers after, true=disable triggers
	 *  @return     int					 <0 if KO, >0 if OK
	 */
	public function delete($user, $notrigger = false)
	{
		$this->db->begin();

		$sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element . " WHERE rowid = " . ((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = "Error " . $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Get next reference number
	 *
	 * @return string Reference
	 */
	public function getNextRef()
	{
		$year = date('Y');
		$num = 1;

		// Get all refs for this year
		$sql = "SELECT ref FROM " . MAIN_DB_PREFIX . $this->table_element;
		$sql .= " WHERE ref LIKE 'ENT-" . $year . "-%'";
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj && $obj->ref) {
				// Extract number from 'ENT-2026-00001'
				$parts = explode('-', $obj->ref);
				if (count($parts) >= 3) {
					$num = intval($parts[2]) + 1;
				}
			}
		}

		return 'ENT-' . $year . '-' . str_pad($num, 5, '0', STR_PAD_LEFT);
	}

	/**
	 * Get articles of this bon d'entrée
	 *
	 * @return array Array of PressingArticle objects
	 */
	public function getArticles()
	{
		require_once __DIR__ . '/pressingarticle.class.php';

		$articles = array();
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "pressing_article";
		$sql .= " WHERE fk_bon_entree = " . (int) $this->id;
		$sql .= " ORDER BY rowid DESC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$article = new PressingArticle($this->db);
				$article->fetch($obj->rowid);
				$articles[] = $article;
			}
		}
		return $articles;
	}

	/**
	 * Check if all articles are ready for delivery
	 *
	 * @return bool True if all articles are status 2 (Prêt à livrer)
	 */
	public function canDeliver()
	{
		$articles = $this->getArticles();
		if (empty($articles)) {
			return false;
		}

		foreach ($articles as $art) {
			if ($art->status != 2) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get status label
	 *
	 * @param int $status
	 * @return string
	 */
	public function getStatusLabel($status = null)
	{
		if (is_null($status)) {
			$status = $this->status;
		}

		$labels = array(
			0 => 'Brouillon',
			1 => 'Validé',
			2 => 'Livré'
		);
		return isset($labels[$status]) ? $labels[$status] : 'Inconnu';
	}

	/**
	 * Get count of articles by status
	 *
	 * @return array Array with status count
	 */
	public function getArticleStats()
	{
		$stats = array(0 => 0, 1 => 0, 2 => 0, 3 => 0);
		$articles = $this->getArticles();

		foreach ($articles as $art) {
			if (isset($stats[$art->status])) {
				$stats[$art->status]++;
			}
		}

		return $stats;
	}
}
