<?php
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modExportation extends DolibarrModules
{
	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
		$this->numero = 500500;
		$this->family = "projects";
		$this->module_position = 50;
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion Avancée d'Import/Export, CBM, Compensation et Profiling Client";
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->picto = 'technic';

		$this->const = array();
		$this->tabs = array();

		// Create Top Menu
		$this->menu = array();
		$r = 0;
		$this->menu[$r] = array(
			'fk_menu' => '0',
			'type' => 'top',
			'titre' => 'Exportation',
			'mainmenu' => 'exportation',
			'prefix' => img_picto('', 'technic', 'class="pictofixedwidth em075"'),
			'leftmenu' => '0',
			'url' => '/custom/exportation/seller_dashboard.php',
			'langs' => 'companies',
			'position' => 1000,
			'enabled' => '1',
			'perms' => '1',
			'target' => '',
			'user' => '2'
		);
		$r++;
		
		// Submenus
		$submenus = array(
			array('title' => 'Tableau de Bord', 'url' => 'seller_dashboard.php', 'pos' => 5, 'pic' => 'home', 'enabled' => '$user->admin'),
			array('title' => 'Dossier d\'achat', 'url' => 'supplier_invoice.php', 'pos' => 10, 'pic' => 'file', 'enabled' => '$user->admin'),
			array('title' => 'Imports & Conteneurs', 'url' => 'shipment_card.php', 'pos' => 20, 'pic' => 'box', 'enabled' => '$user->admin'),
			array('title' => 'Mon Espace ( لوحة الموظف )', 'url' => 'employee_dashboard.php', 'pos' => 30, 'pic' => 'user', 'enabled' => '$user->admin == 0'),
			array('title' => 'Dashboard Client', 'url' => 'customer_dashboard.php', 'pos' => 40, 'pic' => 'user', 'enabled' => '1'),
			array('title' => 'Dépenses ( المصاريف )', 'url' => 'expenses.php', 'pos' => 45, 'pic' => 'receipt', 'enabled' => '$user->admin'),
			array('title' => 'Mes Dépenses', 'url' => 'employee_expenses.php', 'pos' => 46, 'pic' => 'receipt', 'enabled' => '$user->admin == 0'),
			array('title' => 'Rapports ( التقارير )', 'url' => 'reports.php', 'pos' => 50, 'pic' => 'report', 'enabled' => '$user->admin'),
			array('title' => 'Gestion Employés ( الموظفين )', 'url' => 'admin_employees.php', 'pos' => 65, 'pic' => 'user', 'enabled' => '$user->admin'),
			array('title' => 'Fournisseur Interne ( الموردين الداخليين )', 'url' => 'unified_account.php', 'pos' => 60, 'pic' => 'user', 'enabled' => '1'),
			array('title' => 'Fournisseur Externe ( الموردين الخارجيين )', 'url' => 'external_supplier.php', 'pos' => 70, 'pic' => 'customer', 'enabled' => '$user->admin'),
			array('title' => 'Partenaires ( الشركاء )', 'url' => 'partner_dashboard.php', 'pos' => 80, 'pic' => 'group', 'enabled' => '$user->admin'),
		);

		foreach ($submenus as $s) {
			$this->menu[$r] = array(
				'fk_menu' => 'fk_mainmenu=exportation',
				'type' => 'left',
				'titre' => $s['title'],
				'mainmenu' => 'exportation',
				'leftmenu' => '1',
				'prefix' => img_picto('', $s['pic'], 'class="pictofixedwidth em075"'),
				'url' => '/custom/exportation/' . $s['url'],
				'langs' => 'companies',
				'position' => $s['pos'],
				'enabled' => $s['enabled'],
				'perms' => $s['enabled'],
				'user' => 2,
			);
			$r++;
		}
	}

	/**
	 * Function called when module is enabled.
	 * The init function is mandatory to handle activation correctly.
	 */
	public function init($options = '')
	{
		$sql = array();
		$result = $this->_load_tables($sql);
		if ($result < 0) return -1;
		return $this->_init($this->const, $options);
	}

	/**
	 * Function called when module is disabled.
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
