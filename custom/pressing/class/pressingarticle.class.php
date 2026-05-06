<?php
/**
 *	\file       custom/pressing/class/pressingarticle.class.php
 *	\ingroup    pressing
 *	\brief      This file is a CRUD class file for Pressing Article
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

/**
 *	Class for Pressing Article
 */
class PressingArticle extends CommonObject
{
	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'pressing_article';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'pressing_article';

	public $id;
	public $fk_bon_entree;
	public $fk_facture;
	public $fk_product;
	public $ref_article;
	public $fk_entrepot;
	public $longueur;
	public $largeur;
	public $surface;
	public $price;
	public $status;
	public $date_reception;
	public $date_livraison;
	public $note_private;

	/**
	 *  Constructor
	 *
	 *  @param      DoliDb		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->status = 0; // 0: En attente, 1: En traitement, 2: Prêt à livrer, 3: Livré
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

		if (empty($this->date_reception)) {
			$this->date_reception = dol_now();
		}

		$sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . " (";
		$sql .= "fk_bon_entree, fk_facture, fk_product, ref_article, fk_entrepot, longueur, largeur, surface, price, status, date_reception, note_private";
		$sql .= ") VALUES (";
		$sql .= (empty($this->fk_bon_entree) ? "NULL" : (int) $this->fk_bon_entree) . ", ";
		$sql .= (empty($this->fk_facture) ? "NULL" : (int) $this->fk_facture) . ", ";
		$sql .= (int) $this->fk_product . ", ";
		$sql .= "'" . $this->db->escape($this->ref_article) . "', ";
		$sql .= (empty($this->fk_entrepot) ? "NULL" : (int) $this->fk_entrepot) . ", ";
		$sql .= (empty($this->longueur) ? "NULL" : (double) $this->longueur) . ", ";
		$sql .= (empty($this->largeur) ? "NULL" : (double) $this->largeur) . ", ";
		$sql .= (empty($this->surface) ? "NULL" : (double) $this->surface) . ", ";
		$sql .= (empty($this->price) ? "NULL" : (double) $this->price) . ", ";
		$sql .= (int) $this->status . ", ";
		$sql .= "'" . $this->db->idate($this->date_reception) . "', ";
		$sql .= "'" . $this->db->escape($this->note_private) . "'";
		$sql .= ")";

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);

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
			$sql .= " WHERE ref_article = '" . $this->db->escape($ref) . "'";
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;
				$this->fk_bon_entree = $obj->fk_bon_entree;
				$this->fk_facture = $obj->fk_facture;
				$this->fk_product = $obj->fk_product;
				$this->ref_article = $obj->ref_article;
				$this->fk_entrepot = $obj->fk_entrepot;
				$this->longueur = $obj->longueur;
				$this->largeur = $obj->largeur;
				$this->surface = $obj->surface;
				$this->price = $obj->price;
				$this->status = $obj->status;
				$this->date_reception = $this->db->jdate($obj->date_reception);
				$this->date_livraison = $this->db->jdate($obj->date_livraison);
				$this->note_private = $obj->note_private;

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
		$sql .= "fk_bon_entree = " . (empty($this->fk_bon_entree) ? "NULL" : (int) $this->fk_bon_entree) . ", ";
		$sql .= "fk_facture = " . (empty($this->fk_facture) ? "NULL" : (int) $this->fk_facture) . ", ";
		$sql .= "fk_product = " . (int) $this->fk_product . ", ";
		$sql .= "ref_article = '" . $this->db->escape($this->ref_article) . "', ";
		$sql .= "fk_entrepot = " . (empty($this->fk_entrepot) ? "NULL" : (int) $this->fk_entrepot) . ", ";
		$sql .= "longueur = " . (empty($this->longueur) ? "NULL" : (double) $this->longueur) . ", ";
		$sql .= "largeur = " . (empty($this->largeur) ? "NULL" : (double) $this->largeur) . ", ";
		$sql .= "surface = " . (empty($this->surface) ? "NULL" : (double) $this->surface) . ", ";
		$sql .= "price = " . (empty($this->price) ? "NULL" : (double) $this->price) . ", ";
		$sql .= "status = " . (int) $this->status . ", ";
		$sql .= "date_reception = " . (empty($this->date_reception) ? "NULL" : "'" . $this->db->idate($this->date_reception) . "'") . ", ";
		$sql .= "date_livraison = " . (empty($this->date_livraison) ? "NULL" : "'" . $this->db->idate($this->date_livraison) . "'") . ", ";
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
			0 => 'En attente',
			1 => 'En traitement',
			2 => 'Prêt à livrer',
			3 => 'Livré'
		);
		return isset($labels[$status]) ? $labels[$status] : 'Inconnu';
	}

	/**
	 * Get articles by warehouse
	 *
	 * @param int $warehouse_id Warehouse ID
	 * @param int $exclude_status Status to exclude
	 * @return array Array of PressingArticle objects
	 */
	public function getByWarehouse($warehouse_id, $exclude_status = null)
	{
		$articles = array();
		$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $this->table_element;
		$sql .= " WHERE fk_entrepot = " . (int) $warehouse_id;
		if (!is_null($exclude_status)) {
			$sql .= " AND status != " . (int) $exclude_status;
		}
		$sql .= " ORDER BY date_reception DESC";

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
	 * Calculate surface from dimensions
	 *
	 * @param double $longueur Length in cm
	 * @param double $largeur Width in cm
	 * @return double Surface in m²
	 */
	public function calculateSurface($longueur, $largeur)
	{
		if (empty($longueur) || empty($largeur)) {
			return 0;
		}
		return round(($longueur * $largeur) / 10000, 4);
	}

	/**
	 * Calculate price from dimensions and product
	 *
	 * @param double $longueur Length in cm
	 * @param double $largeur Width in cm
	 * @param double $prix_produit Product price (price per m²)
	 * @return double Calculated price
	 */
	public function calculatePrice($longueur, $largeur, $prix_produit)
	{
		if (empty($longueur) || empty($largeur) || empty($prix_produit)) {
			return 0;
		}
		$surface = $this->calculateSurface($longueur, $largeur);
		return round($surface * $prix_produit, 2);
	}
}
