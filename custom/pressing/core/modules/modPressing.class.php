<?php
/**
 *	\file       custom/pressing/core/modules/modPressing.class.php
 *	\ingroup    pressing
 *	\brief      Description and activation file for module Pressing
 */

include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';

/**
 *	Class to describe and enable module Pressing
 */
class modPressing extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		// Id for module (must be unique).
		$this->numero = 104500; // Choose a unique number

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		$this->family = "other";
		
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module pour la gestion du Pressing : suivi des articles, statuts, entrepôts, et facturation au mètre carré.";
		
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.0';
		
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 2;
		
		// Name of image file used for this module.
		$this->picto = 'generic';

		// Data directories to create when module is enabled
		$this->dirs = array(
			"/pressing/temp"
		);

		// Config pages. Put here list of php page names stored in admim directory used to setup module
		$this->config_page_url = array("setup.php@pressing");

		// Dependencies
		$this->depends = array("modFacture", "modStock");
		$this->requiredby = array();
		$this->phpmin = array(7, 0);
		$this->need_dolibarr_version = array(10, 0);
		
		// Constants
		$this->const = array();

		// New pages on menus
		$this->menu = array();

		$r=0;

		// Top Menu
		$this->menu[$r]=array('fk_menu'=>0, 'type'=>'top', 'titre'=>'Pressing', 'mainmenu'=>'pressing', 'leftmenu'=>'', 'url'=>'/custom/pressing/bon_entree/list.php', 'langs'=>'pressing@pressing', 'position'=>1000, 'enabled'=>'1', 'perms'=>'1', 'target'=>'', 'user'=>2);
		$r++;

		// Left Menus - Bons d'Entrée
		$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=pressing', 'type'=>'left', 'titre'=>'Bons d\'Entrée', 'mainmenu'=>'pressing', 'leftmenu'=>'pressing_bon_entree', 'url'=>'/custom/pressing/bon_entree/list.php', 'langs'=>'pressing@pressing', 'position'=>10, 'enabled'=>'1', 'perms'=>'1', 'target'=>'', 'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=pressing', 'type'=>'left', 'titre'=>'Nouveau Bon', 'mainmenu'=>'pressing', 'leftmenu'=>'pressing_bon_entree', 'url'=>'/custom/pressing/bon_entree/card.php?action=create', 'langs'=>'pressing@pressing', 'position'=>15, 'enabled'=>'1', 'perms'=>'1', 'target'=>'', 'user'=>2);
		$r++;

		// Left Menus - Articles
		$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=pressing', 'type'=>'left', 'titre'=>'Articles', 'mainmenu'=>'pressing', 'leftmenu'=>'pressing_articles', 'url'=>'/custom/pressing/article/list.php', 'langs'=>'pressing@pressing', 'position'=>20, 'enabled'=>'1', 'perms'=>'1', 'target'=>'', 'user'=>2);
		$r++;

		// Left Menus - Entrepôts
		$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=pressing', 'type'=>'left', 'titre'=>'Liste Entrepôts', 'mainmenu'=>'pressing', 'leftmenu'=>'pressing_warehouse_list', 'url'=>'/custom/pressing/entrepot/list.php', 'langs'=>'pressing@pressing', 'position'=>30, 'enabled'=>'1', 'perms'=>'1', 'target'=>'', 'user'=>2);
		$r++;

		$this->menu[$r]=array('fk_menu'=>'fk_mainmenu=pressing', 'type'=>'left', 'titre'=>'Vue par Entrepôt', 'mainmenu'=>'pressing', 'leftmenu'=>'pressing_warehouse', 'url'=>'/custom/pressing/entrepot/view.php', 'langs'=>'pressing@pressing', 'position'=>35, 'enabled'=>'1', 'perms'=>'1', 'target'=>'', 'user'=>2);
		$r++;

		// Permissions
		$this->rights = array();
		$this->rights_class = 'pressing';
		
		$r=0;
		$this->rights[$r][0] = 104501;
		$this->rights[$r][1] = 'Read Pressing articles';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';
		$r++;

		$this->rights[$r][0] = 104502;
		$this->rights[$r][1] = 'Create/modify Pressing articles';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write';
		$r++;

		$this->rights[$r][0] = 104503;
		$this->rights[$r][1] = 'Delete Pressing articles';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete';
		$r++;
		
		$this->rights[$r][0] = 104504;
		$this->rights[$r][1] = 'Deliver Pressing articles';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'deliver';
		$r++;

		// Main initialization
	}

	/**
	 *	Function called when module is enabled.
	 *	The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *	It also creates data directories
	 *
	 *	@param      string	$options    Options when enabling module ('', 'noboxes')
	 *	@return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();

		return $this->_init($sql, $options);
	}
}
