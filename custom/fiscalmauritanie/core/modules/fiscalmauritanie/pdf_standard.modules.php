<?php
$res = 0;
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res && file_exists('../../../../main.inc.php')) $res = @include '../../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/custom/fiscalmauritanie/class/fiscalmauritaniedeclaration.class.php';

$langs->loadLangs(array('fiscalmauritanie@fiscalmauritanie'));
if (!$user->rights->fiscalmauritanie->declaration->read) accessforbidden();

$id = GETPOSTINT('id');
$object = new FiscalMauritanieDeclaration($db);
if ($object->fetch($id) <= 0) accessforbidden('Déclaration introuvable');

$tcpdf = DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf.php';
if (!file_exists($tcpdf)) {
    header('Content-Type: text/plain; charset=utf-8');
    print "TCPDF non disponible dans cette installation Dolibarr.";
    exit;
}
require_once $tcpdf;

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Dolibarr - Fiscal Mauritanie');
$pdf->SetAuthor(getDolGlobalString('MAIN_INFO_SOCIETE_NOM'));
$pdf->SetTitle('Déclaration fiscale '.$object->ref);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Déclaration fiscale Mauritanie', 0, 1, 'C');
$pdf->Ln(3);
$pdf->SetFont('helvetica', '', 10);

$html = '<table border="1" cellpadding="5">';
$html .= '<tr><td width="35%"><b>Référence</b></td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
$html .= '<tr><td><b>Type impôt</b></td><td>'.dol_escape_htmltag($object->tax_type).'</td></tr>';
$html .= '<tr><td><b>Période</b></td><td>'.dol_escape_htmltag($object->period).'</td></tr>';
$html .= '<tr><td><b>Mode déclaration</b></td><td>'.dol_escape_htmltag($object->mode_declaration).'</td></tr>';
$html .= '<tr><td><b>Montant système</b></td><td>'.price($object->amount_system).' '.$object->currency_code.'</td></tr>';
$html .= '<tr><td><b>Pourcentage déclaré</b></td><td>'.price($object->declared_percentage).' %</td></tr>';
$html .= '<tr><td><b>Montant déclaré</b></td><td>'.price($object->declared_amount).' '.$object->currency_code.'</td></tr>';
$html .= '<tr><td><b>Ajustements</b></td><td>'.price($object->adjustment_amount).' '.$object->currency_code.'</td></tr>';
$html .= '<tr><td><b>Pénalités</b></td><td>'.price($object->penalty_amount).' '.$object->currency_code.'</td></tr>';
$html .= '<tr><td><b>Total à payer</b></td><td><b>'.price($object->total_amount).' '.$object->currency_code.'</b></td></tr>';
$html .= '<tr><td><b>Date échéance</b></td><td>'.dol_print_date($db->jdate($object->due_date), 'day').'</td></tr>';
$html .= '</table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(8);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 5, 'Document généré automatiquement par le module Fiscal Mauritanie. Les taux et montants doivent être vérifiés selon le paramétrage fiscal applicable.', 0, 'L');

$filename = dol_sanitizeFileName('declaration_'.$object->ref.'.pdf');
$pdf->Output($filename, 'I');
exit;
