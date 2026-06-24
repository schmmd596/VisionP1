<?php
/**
 * Class for pressing orders (main entity)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/pressing/class/pressingcommandedet.class.php';

class PressingCommande extends CommonObject
{
	public $element = 'pressingcommande';
	public $table_element = 'pressing_commande';
	public $table_element_line = 'pressing_commandedet';
	public $class_element_line = 'PressingCommandeDet';
	public $fk_element = 'fk_commande';
	public $picto = 'fa-tshirt';

	public $ref;
	public $fk_soc;
	public $fk_facture;
	public $fk_user_author;
	public $date_depot;
	public $date_promesse;
	public $date_livraison;
	public $fk_statut = 0;
	public $montant_ht = 0;
	public $montant_ttc = 0;
	public $note_public;
	public $note_private;
	public $lines = array();

	const STATUS_DRAFT = 0;
	const STATUS_RECEIVED = 1;
	const STATUS_INPROGRESS = 2;
	const STATUS_DONE = 3;
	const STATUS_DELIVERED = 4;

	public function __construct($db)
	{
		$this->db = $db;
		$this->fields = array(
			'rowid' => array('type'=>'integer','label'=>'ID','visible'=>0,'notNull'=>true,'position'=>1),
			'ref' => array('type'=>'varchar(30)','label'=>'Référence','visible'=>1,'showoncombobox'=>1,'notNull'=>true,'position'=>5),
			'fk_soc' => array('type'=>'integer','label'=>'Client','visible'=>1,'notNull'=>true,'position'=>10),
			'fk_facture' => array('type'=>'integer','label'=>'Facture','visible'=>0,'position'=>11),
			'date_depot' => array('type'=>'datetime','label'=>'Date dépôt','visible'=>1,'position'=>20),
			'date_promesse' => array('type'=>'date','label'=>'Date promise','visible'=>1,'position'=>21),
			'date_livraison' => array('type'=>'datetime','label'=>'Date livraison','visible'=>0,'position'=>22),
			'fk_statut' => array('type'=>'smallint','label'=>'Statut','visible'=>1,'notnull'=>1,'position'=>80),
			'montant_ht' => array('type'=>'double','label'=>'Montant HT','visible'=>0,'position'=>200),
			'montant_ttc' => array('type'=>'double','label'=>'Montant TTC','visible'=>0,'position'=>201),
			'note_public' => array('type'=>'html','label'=>'Note publique','visible'=>0),
			'note_private' => array('type'=>'html','label'=>'Note privée','visible'=>0),
		);
	}

	public function create($user)
	{
		global $langs, $conf;

		$this->date_depot = !empty($this->date_depot) ? $this->date_depot : dol_now();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "ref, entity, fk_soc, fk_facture, date_depot, date_promesse, fk_user_author, fk_statut, datec";
		$sql .= ") VALUES (";
		$sql .= "'".str_replace("'", "''", $this->ref)."'";
		$sql .= ", ".$conf->entity;
		$sql .= ", ".((int)$this->fk_soc);
		$sql .= ", ".(!empty($this->fk_facture) ? ((int)$this->fk_facture) : 'NULL');
		$sql .= ", '".date('Y-m-d H:i:s', $this->date_depot)."'";
		$sql .= ", ".(!empty($this->date_promesse) ? "'".date('Y-m-d', $this->date_promesse)."'" : 'NULL');
		$sql .= ", ".((int)$user->id);
		$sql .= ", 0";
		$sql .= ", '".date('Y-m-d H:i:s')."'";
		$sql .= ")";

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			$this->rowid = $this->id;
			return $this->id;
		} else {
			$this->errors[] = $this->db->lasterror();
			dol_syslog(get_class($this)."::create ".$this->db->lasterror(), LOG_ERR);
			return -1;
		}
	}

	public function fetch($id)
	{
		$sql = "SELECT rowid, ref, entity, fk_soc, fk_facture, date_depot, date_promesse, date_livraison,";
		$sql .= " fk_user_author, fk_statut, montant_ht, montant_ttc, note_public, note_private, datec, tms";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid = ".((int)$id);

		$resql = $this->db->query($sql);

		if ($resql) {
			if ($this->db->num_rows($resql) > 0) {
				$obj = $this->db->fetch_object($resql);
				$this->id = $obj->rowid;
				$this->rowid = $obj->rowid;
				$this->ref = $obj->ref;
				$this->fk_soc = $obj->fk_soc;
				$this->fk_facture = $obj->fk_facture;
				$this->date_depot = $this->db->jdate($obj->date_depot);
				$this->date_promesse = $this->db->jdate($obj->date_promesse);
				$this->date_livraison = $this->db->jdate($obj->date_livraison);
				$this->fk_user_author = $obj->fk_user_author;
				$this->fk_statut = $obj->fk_statut;
				$this->montant_ht = $obj->montant_ht;
				$this->montant_ttc = $obj->montant_ttc;
				$this->note_public = $obj->note_public;
				$this->note_private = $obj->note_private;
				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->tms);

				return 1;
			} else {
				$this->errors[] = 'Record not found';
				return 0;
			}
		} else {
			$this->errors[] = $this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->db->lasterror(), LOG_ERR);
			return -1;
		}
	}

	public function update($user)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
		$sql .= "ref = '".str_replace("'", "''", $this->ref)."'";
		$sql .= ", fk_soc = ".((int)$this->fk_soc);
		$sql .= ", fk_facture = ".(!empty($this->fk_facture) ? ((int)$this->fk_facture) : 'NULL');
		$sql .= ", date_depot = '".date('Y-m-d H:i:s', $this->date_depot)."'";
		$sql .= ", date_promesse = ".(!empty($this->date_promesse) ? "'".date('Y-m-d', $this->date_promesse)."'" : 'NULL');
		$sql .= ", montant_ht = ".((float)$this->montant_ht);
		$sql .= ", montant_ttc = ".((float)$this->montant_ttc);
		$sql .= ", note_public = '".str_replace("'", "''", $this->note_public)."'";
		$sql .= ", note_private = '".str_replace("'", "''", $this->note_private)."'";
		$sql .= " WHERE rowid = ".((int)$this->id);

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			return 1;
		} else {
			$this->errors[] = $this->db->lasterror();
			dol_syslog(get_class($this)."::update ".$this->db->lasterror(), LOG_ERR);
			return -1;
		}
	}

	public function delete($user)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element_line;
		$sql .= " WHERE fk_commande = ".((int)$this->id);
		$resql = $this->db->query($sql);

		if (!$resql) {
			return -1;
		}

		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid = ".((int)$this->id);

		dol_syslog(get_class($this)."::delete", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			return 1;
		} else {
			$this->errors[] = $this->db->lasterror();
			dol_syslog(get_class($this)."::delete ".$this->db->lasterror(), LOG_ERR);
			return -1;
		}
	}

	public function addLine($description, $type_article, $couleur, $longueur, $largeur, $fk_entrepot = 0)
	{
		$line = new PressingCommandeDet($this->db);
		$line->fk_commande = $this->id;
		$line->description = $description;
		$line->type_article = $type_article;
		$line->couleur = $couleur;
		$line->longueur = (float)$longueur;
		$line->largeur = (float)$largeur;
		$line->fk_entrepot = (int)$fk_entrepot;
		$line->qty = 1;
		$line->calculerPrix();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element_line." (";
		$sql .= "fk_commande, description, type_article, couleur, longueur, largeur, prix_unitaire, fk_entrepot, fk_statut, datec";
		$sql .= ") VALUES (";
		$sql .= ((int)$this->id);
		$sql .= ", '".str_replace("'", "''", $description)."'";
		$sql .= ", '".str_replace("'", "''", $type_article)."'";
		$sql .= ", '".str_replace("'", "''", $couleur)."'";
		$sql .= ", ".((float)$longueur);
		$sql .= ", ".((float)$largeur);
		$sql .= ", ".((float)$line->prix_unitaire);
		$sql .= ", ".((int)$fk_entrepot);
		$sql .= ", 0";
		$sql .= ", '".date('Y-m-d H:i:s')."'";
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			return $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element_line);
		}
		return -1;
	}

	public function fetchLines()
	{
		$sql = "SELECT rowid, fk_commande, description, type_article, couleur, longueur, largeur,";
		$sql .= " prix_unitaire, tva_tx, total_ht, fk_entrepot, fk_statut";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element_line;
		$sql .= " WHERE fk_commande = ".((int)$this->id);
		$sql .= " ORDER BY rang";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$line = new PressingCommandeDet($this->db);
				$line->rowid = $line->id = $obj->rowid;
				$line->fk_commande = $obj->fk_commande;
				$line->description = $obj->description;
				$line->type_article = $obj->type_article;
				$line->couleur = $obj->couleur;
				$line->longueur = $obj->longueur;
				$line->largeur = $obj->largeur;
				$line->prix_unitaire = $obj->prix_unitaire;
				$line->tva_tx = $obj->tva_tx;
				$line->total_ht = $obj->total_ht;
				$line->fk_entrepot = $obj->fk_entrepot;
				$line->fk_statut = $obj->fk_statut;
				$this->lines[] = $line;
			}
			return count($this->lines);
		}
		return -1;
	}

	public function getLinesStatuts()
	{
		foreach ($this->lines as $line) {
			if ($line->fk_statut != PressingCommandeDet::STATUS_DONE) {
				return false;
			}
		}
		return true;
	}

	public function setStatut($status, $elementId = null, $elementType = '', $trigkey = '', $fieldstatus = 'fk_statut')
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " SET ".$fieldstatus." = ".((int)$status);
		$sql .= " WHERE rowid = ".((int)$this->id);

		$result = $this->db->query($sql);
		if ($result) {
			$this->fk_statut = $status;
			return 1;
		}
		return -1;
	}

	public function livrer($user)
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

		$this->fetchLines();

		foreach ($this->lines as $line) {
			if (!empty($line->fk_entrepot)) {
				$mouvement = new MouvementStock($db);
				$mouvement->livraison($user, 0, (int)$line->fk_entrepot, 1, 0, 'Livraison pressing - '.$this->ref);
			}
			$line->setStatut(PressingCommandeDet::STATUS_DELIVERED, null, '', '', 'fk_statut');
		}

		$this->date_livraison = dol_now();
		$this->fk_statut = self::STATUS_DELIVERED;
		return $this->setStatut(self::STATUS_DELIVERED);
	}

	public function getLibStatut($mode = 0)
	{
		return self::LibStatut($this->fk_statut, $mode);
	}

	public static function LibStatut($status, $mode = 0)
	{
		$colors = array(
			0 => '#999999',  // gris - brouillon
			1 => '#0066cc',  // bleu - reçu
			2 => '#ff8c00',  // orange - en cours
			3 => '#28a745',  // vert - prêt
			4 => '#17a2b8'   // cyan - livré
		);

		$statuses = array(
			0 => 'Brouillon',
			1 => 'Reçu',
			2 => 'En cours',
			3 => 'Prêt à livrer',
			4 => 'Livré'
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

	public function getNomUrl($withpicto = 0)
	{
		global $langs;

		$result = '';
		$label = '<u class="paddingrightonly">'.$langs->trans("ShowPressing").'</u>';

		if ($withpicto) {
			$result .= '<span class="nopadding nomargin">';
		}
		$result .= '<a href="'.DOL_URL_ROOT.'/custom/pressing/card.php?id='.$this->id.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
		$result .= img_object('', $this->picto, 'class="imgobject"').' ';
		$result .= '</a>';
		$result .= '<a href="'.DOL_URL_ROOT.'/custom/pressing/card.php?id='.$this->id.'" title="'.dol_escape_htmltag($label, 1).'">'.$this->ref.'</a>';
		if ($withpicto) {
			$result .= '</span>';
		}

		return $result;
	}
}
