<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/includes/tecnickcom/tcpdf/tcpdf.php';

$id = GETPOST('id', 'int');
if ($id <= 0) {
    die('ID manquant');
}

$fac = new FactureFournisseur($db);
$fac->fetch($id);
$fac->fetch_lines();
$fac->fetch_thirdparty();
$ri = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_info WHERE fk_facture_fourn = " . (int) $id);
$ii = ($ri !== false) ? $db->fetch_object($ri) : null;
$CC = ($ii && $ii->currency_code) ? $ii->currency_code : 'MRU';
$ER = ($ii && $ii->exchange_rate > 0) ? (float) $ii->exchange_rate : 1;
$total_dev = ($ER > 0) ? ($fac->total_ht / $ER) : $fac->total_ht;

// Expenses
$rese = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_expenses WHERE fk_facture_fourn = " . (int) $id);
$expenses = array();
$total_exp = 0;
$total_exp_dev = 0;
while ($rese !== false && ($e = $db->fetch_object($rese))) {
    $total_exp += (float) $e->amount_local;
    $total_exp_dev += ($e->currency_code == $CC) ? (float) $e->amount : (($ER > 0) ? ((float) $e->amount_local / $ER) : (float) $e->amount);
    $expenses[] = $e;
}

// Payments
$rp = $db->query("SELECT p.*, ba.label as bank_label FROM " . MAIN_DB_PREFIX . "exportation_invoice_payments p LEFT JOIN " . MAIN_DB_PREFIX . "bank_account ba ON ba.rowid = p.fk_bank WHERE p.fk_facture_fourn = " . (int) $id . " ORDER BY p.datep ASC");
$payments = array();
$total_paid = 0;
$total_paid_dev = 0;
while ($rp !== false && ($py = $db->fetch_object($rp))) {
    $total_paid += (float) $py->amount_local;
    $total_paid_dev += (float) $py->amount;
    $payments[] = $py;
}

$grand_dev = $total_dev + $total_exp_dev;
$remaining_dev = $grand_dev - $total_paid_dev;
$grand_mru = $fac->total_ht + $total_exp;

// ═══════════════════════════════════════════════
// PDF GENERATION
// ═══════════════════════════════════════════════
class InvoicePDF extends TCPDF
{
    public $inv_ref = '';
    public function Header()
    {
        $this->SetFont('dejavusans', 'B', 18);
        $this->SetTextColor(30, 42, 58);
        $this->Cell(0, 12, 'FACTURE FOURNISSEUR', 0, 1, 'C');
        $this->SetFont('dejavusans', '', 10);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 6, $this->inv_ref, 0, 1, 'C');
        $this->Line(10, $this->GetY() + 2, 200, $this->GetY() + 2);
        $this->Ln(6);
    }
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' — Généré le ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new InvoicePDF();
$pdf->inv_ref = $fac->ref;
$pdf->SetCreator('Dolibarr Exportation');
$pdf->SetTitle('Facture ' . $fac->ref);
$pdf->SetMargins(10, 35, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Helper
function pdfPrice($v)
{
    return number_format((float) $v, 2, ',', ' ');
}

// ── Info box
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetTextColor(30, 42, 58);
$pdf->Cell(95, 7, 'Fournisseur: ' . $fac->thirdparty->name, 0, 0);
$pdf->Cell(95, 7, 'Date: ' . dol_print_date($fac->date, 'day'), 0, 1, 'R');
$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(95, 6, 'Réf Pro-forma: ' . ($fac->ref_supplier ?: '-'), 0, 0);
$pdf->Cell(95, 6, 'Devise: ' . $CC . '  |  Taux: ' . number_format($ER, 4), 0, 1, 'R');
$pdf->Ln(4);

// ── Products table
$pdf->SetFillColor(248, 250, 252);
$pdf->SetFont('dejavusans', 'B', 8);
$pdf->SetTextColor(100, 116, 139);
$cols = array('Produit' => 46, 'CBM/Crt' => 16, 'U/Crt' => 14, 'Nb Crt' => 14, 'Qté' => 16, 'P.U (' . $CC . ')' => 24, 'Total (' . $CC . ')' => 26, 'Expédié' => 24);
foreach ($cols as $h => $w) {
    $pdf->Cell($w, 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('dejavusans', '', 8);
$pdf->SetTextColor(30, 42, 58);
foreach ($fac->lines as $line) {
    $rli = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "exportation_invoice_line_info WHERE fk_facture_fourn_det = " . (int) $line->id);
    $li = ($rli !== false) ? $db->fetch_object($rli) : null;
    $cbm_c = $li ? (float) $li->cbm_carton : 0;
    $qty_c = $li ? (int) $li->qty_carton : 1;
    $nb_c = $li ? (float) $li->nb_cartons : 0;
    $pu_d = $li ? (float) $li->pu_devise : (($ER > 0) ? ($line->pu_ht / $ER) : $line->pu_ht);
    $total_d = $pu_d * $line->qty;
    $rsh = $db->query("SELECT COALESCE(SUM(sl.nb_cartons), 0) as s FROM " . MAIN_DB_PREFIX . "exportation_shipment_lines sl WHERE sl.fk_facture_fourn_det = " . (int) $line->id);
    $shd = ($rsh !== false) ? $db->fetch_object($rsh) : null;
    $sc = $shd ? (float) $shd->s : 0;
    $ship_txt = ($nb_c > 0) ? ((int) $sc . '/' . (int) $nb_c) : '-';

    $label = mb_substr($line->product_label ?: $line->description, 0, 28);
    $pdf->Cell(46, 6, $label, 'LR', 0);
    $pdf->Cell(16, 6, ($cbm_c > 0 ? number_format($cbm_c, 3) : '-'), 'LR', 0, 'C');
    $pdf->Cell(14, 6, ($qty_c > 0 ? $qty_c : '-'), 'LR', 0, 'C');
    $pdf->Cell(14, 6, ($nb_c > 0 ? (int) $nb_c : '-'), 'LR', 0, 'C');
    $pdf->Cell(16, 6, number_format($line->qty, 0), 'LR', 0, 'C');
    $pdf->Cell(24, 6, pdfPrice($pu_d), 'LR', 0, 'R');
    $pdf->Cell(26, 6, pdfPrice($total_d), 'LR', 0, 'R');
    $pdf->Cell(24, 6, $ship_txt, 'LR', 0, 'C');
    $pdf->Ln();
}
// Total products
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(130, 7, 'TOTAL PRODUITS', 1, 0, 'R', true);
$pdf->Cell(26, 7, pdfPrice($total_dev) . ' ' . $CC, 1, 0, 'R', true);
$pdf->Cell(24, 7, '', 1, 1, '', true);
$pdf->Ln(4);

// ── Expenses
if (count($expenses) > 0) {
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetTextColor(30, 42, 58);
    $pdf->Cell(0, 7, 'DEPENSES', 0, 1);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(60, 6, 'Description', 1, 0, '', true);
    $pdf->Cell(35, 6, 'Montant', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Devise', 1, 0, 'C', true);
    $pdf->Cell(25, 6, 'Taux', 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'Total (MRU)', 1, 1, 'C', true);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(30, 42, 58);
    foreach ($expenses as $e) {
        $pdf->Cell(60, 6, $e->label, 'LR', 0);
        $pdf->Cell(35, 6, pdfPrice($e->amount), 'LR', 0, 'R');
        $pdf->Cell(25, 6, $e->currency_code, 'LR', 0, 'C');
        $pdf->Cell(25, 6, number_format($e->exchange_rate, 4), 'LR', 0, 'C');
        $pdf->Cell(35, 6, pdfPrice($e->amount_local), 'LR', 1, 'R');
    }
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(145, 7, 'TOTAL DEPENSES', 1, 0, 'R', true);
    $pdf->Cell(35, 7, pdfPrice($total_exp_dev) . ' ' . $CC, 1, 1, 'R', true);
    $pdf->Ln(4);
}

// ── Payments
if (count($payments) > 0) {
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetTextColor(30, 42, 58);
    $pdf->Cell(0, 7, 'PAIEMENTS', 0, 1);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->Cell(25, 6, 'Date', 1, 0, 'C', true);
    $pdf->Cell(50, 6, 'Banque', 1, 0, '', true);
    $pdf->Cell(30, 6, 'Montant (' . $CC . ')', 1, 0, 'C', true);
    $pdf->Cell(22, 6, 'Taux', 1, 0, 'C', true);
    $pdf->Cell(30, 6, 'Montant (MRU)', 1, 0, 'C', true);
    $pdf->Cell(23, 6, 'Note', 1, 1, '', true);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(30, 42, 58);
    foreach ($payments as $py) {
        $pdf->Cell(25, 6, dol_print_date($db->jdate($py->datep), 'day'), 'LR', 0, 'C');
        $pdf->Cell(50, 6, mb_substr($py->bank_label ?: '-', 0, 25), 'LR', 0);
        $pdf->Cell(30, 6, pdfPrice($py->amount), 'LR', 0, 'R');
        $pdf->Cell(22, 6, number_format($py->exchange_rate, 4), 'LR', 0, 'C');
        $pdf->Cell(30, 6, pdfPrice($py->amount_local), 'LR', 0, 'R');
        $pdf->Cell(23, 6, mb_substr($py->note ?: '', 0, 15), 'LR', 1);
    }
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(105, 7, 'TOTAL PAYE', 1, 0, 'R', true);
    $pdf->Cell(75, 7, pdfPrice($total_paid_dev) . ' ' . $CC . '  |  ' . pdfPrice($total_paid) . ' MRU', 1, 1, 'R', true);
    $pdf->Ln(4);
}

// ── Grand total summary
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetTextColor(30, 42, 58);
$pdf->SetFillColor(236, 253, 245);

$y = $pdf->GetY();
$pdf->SetDrawColor(16, 185, 129);
$pdf->RoundedRect(10, $y, 190, 30, 3, '1111', 'D');

$pdf->SetXY(15, $y + 3);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(55, 6, 'Total Produits + Dépenses:', 0, 0);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->Cell(40, 6, pdfPrice($grand_dev) . ' ' . $CC, 0, 0, 'R');
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(35, 6, 'Payé:', 0, 0);
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetTextColor(16, 185, 129);
$pdf->Cell(35, 6, pdfPrice($total_paid_dev) . ' ' . $CC, 0, 1, 'R');

$pdf->SetXY(15, $y + 12);
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(55, 6, '(' . pdfPrice($grand_mru) . ' MRU)', 0, 0);
$pdf->Cell(40, 6, '', 0, 0);
$pdf->Cell(10, 6, '', 0, 0);
$pdf->Cell(35, 6, '', 0, 0);
$pdf->Cell(35, 6, '(' . pdfPrice($total_paid) . ' MRU)', 0, 1, 'R');

$pdf->SetXY(15, $y + 20);
$pdf->SetFont('dejavusans', 'B', 12);
if ($remaining_dev > 0.01) {
    $pdf->SetTextColor(239, 68, 68);
    $pdf->Cell(0, 7, 'RESTE A PAYER: ' . pdfPrice($remaining_dev) . ' ' . $CC, 0, 1, 'C');
} else {
    $pdf->SetTextColor(16, 185, 129);
    $pdf->Cell(0, 7, 'FACTURE SOLDEE', 0, 1, 'C');
}

// Output
$pdf->Output('Facture_' . $fac->ref . '.pdf', 'I');
$db->close();
