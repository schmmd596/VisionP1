<?php
/**
 * Class for pressing order lines (articles/items)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

class PressingCommandeDet extends CommonObjectLine
{
	public $element = 'pressing_commandedet';
	public $table_element = 'pressing_commandedet';

	public $fk_commande;
	public $rang = 0;
	public $description;
	public $type_article;
	public $couleur;
	public $longueur = 0;
	public $largeur = 0;
	public $prix_unitaire = 0;
	public $tva_tx = 0;
	public $total_ht = 0;
	public $fk_entrepot;
	public $fk_statut = 0;

	const STATUS_PENDING = 0;
	const STATUS_INPROGRESS = 1;
	const STATUS_DONE = 2;
	const STATUS_DELIVERED = 3;

	public function __construct($db)
	{
		$this->db = $db;
		$this->fields = array(
			'rowid' => array('type'=>'integer','label'=>'ID','visible'=>0,'notNull'=>true),
			'fk_commande' => array('type'=>'integer','label'=>'Commande','visible'=>-1,'notNull'=>true),
			'description' => array('type'=>'text','label'=>'Description'),
			'type_article' => array('type'=>'varchar(100)','label'=>'Type article'),
			'couleur' => array('type'=>'varchar(50)','label'=>'Couleur'),
			'longueur' => array('type'=>'double','label'=>'Longueur (cm)'),
			'largeur' => array('type'=>'double','label'=>'Largeur (cm)'),
			'prix_unitaire' => array('type'=>'double','label'=>'Prix unitaire HT'),
			'tva_tx' => array('type'=>'double','label'=>'Taux TVA'),
			'total_ht' => array('type'=>'double','label'=>'Total HT'),
			'fk_entrepot' => array('type'=>'integer','label'=>'Entrepôt'),
			'fk_statut' => array('type'=>'smallint','label'=>'Statut'),
		);
	}

	public function calculerPrix()
	{
		if ($this->longueur > 0 && $this->largeur > 0) {
			$this->prix_unitaire = round($this->longueur * $this->largeur / 100, 2);
		}
		$this->total_ht = round($this->prix_unitaire * $this->qty, 2);
		return $this->prix_unitaire;
	}

	public function setStatut($status, $elementId = null, $elementType = '', $trigkey = '', $fieldstatus = 'fk_statut')
	{
		if (!in_array($status, array(self::STATUS_PENDING, self::STATUS_INPROGRESS, self::STATUS_DONE, self::STATUS_DELIVERED))) {
			return -1;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET ".$fieldstatus." = ".((int)$status);
		$sql .= " WHERE rowid = ".((int)$this->rowid);

		$result = $this->db->query($sql);
		if ($result) {
			$this->fk_statut = $status;
			return 1;
		}
		return -1;
	}

	public function delete($user = null)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid = ".((int)$this->rowid);

		return $this->db->query($sql) ? 1 : -1;
	}

	public function update($user = null)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
		$sql .= "description = '".$this->db->escape($this->description)."',";
		$sql .= "type_article = '".$this->db->escape($this->type_article)."',";
		$sql .= "couleur = '".$this->db->escape($this->couleur)."',";
		$sql .= "longueur = ".((float)$this->longueur).",";
		$sql .= "largeur = ".((float)$this->largeur).",";
		$sql .= "prix_unitaire = ".((float)$this->prix_unitaire).",";
		$sql .= "fk_entrepot = ".(!empty($this->fk_entrepot) ? ((int)$this->fk_entrepot) : "NULL").",";
		$sql .= "fk_statut = ".((int)$this->fk_statut);
		$sql .= " WHERE rowid = ".((int)$this->rowid);

		return $this->db->query($sql) ? 1 : -1;
	}

	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->fk_statut, $mode);
	}

	public static function LibStatut($status, $mode = 0)
	{
		$label = '';
		$colors = array(
			0 => '#999999',  // gris - en attente
			1 => '#ff8c00',  // orange - en cours
			2 => '#28a745',  // vert - prêt
			3 => '#0066cc'   // bleu - livré
		);

		$statuses = array(
			0 => 'En attente de lavage',
			1 => 'En cours de traitement',
			2 => 'Prêt à livrer',
			3 => 'Livré'
		);

		if (!isset($statuses[$status])) {
			return 'Statut inconnu';
		}

		if ($mode == 0) {
			return '<span style="background-color: '.$colors[$status].'; color: white; padding: 2px 6px; border-radius: 3px; white-space: nowrap;">'.$statuses[$status].'</span>';
		} else {
			return $statuses[$status];
		}
	}
}
