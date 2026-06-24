<?php
/**
 *	\file       custom/pressing/admin/setup.php
 *	\ingroup    pressing
 *	\brief      Setup page for pressing module
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/pressing.lib.php';

// Access control
if (!$user->admin) accessforbidden();

$langs->loadLangs(array("admin", "pressing@pressing"));

$action = GETPOST('action', 'aZ09');

// Actions

if ($action == 'update') {
	// Setup parameters update here
	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

// View

$page_name = "PressingSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans("PressingSetup"), $linkback, 'title_setup');

$head = pressing_admin_prepare_head();

print dol_get_fiche_head($head, 'settings', $langs->trans("PressingSetup"), -1, 'pressing@pressing');

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("PressingFeatureDemo").'</td>';
print '<td>Actif</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="button" value="' . $langs->trans("Save") . '">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
