<?php
include "header.php";
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session for CSRF protection if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sanitize inputs
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$csrfToken = isset($_GET['csrf_token']) ? trim($_GET['csrf_token']) : '';

// CSRF validation
if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token. Access denied.');
}

// Validate invoice ID
if ($invoiceId <= 0) {
    die('Invalid invoice ID.');
}

// Sanitize user ID
$uid = (int)$user->id;

// Fetch invoice details (removed description and payment_method)
$invoiceQuery = "
    SELECT f.id AS invoice_no, f.tutar AS amount, f.tarih AS date, f.durum AS status,
           COALESCE(p.baslik, 'Custom Invoice') AS package_name
    FROM k_faturalar f
    LEFT JOIN k_paketler p ON p.id = f.paket_id
    WHERE f.id = $invoiceId AND f.uid = $uid
";
$invoice = $db->get_row($invoiceQuery);

// Check if invoice exists and belongs to user
if (!$invoice) {
    die('Invoice not found or access denied.');
}

// Database optimization: Ensure indexes exist
$db->query("CREATE INDEX IF NOT EXISTS idx_uid_invoice ON k_faturalar (uid, id)");

// Invoice status labels
$statusLabel = '';
switch ($invoice->status) {
    case 1:
        $statusLabel = 'Paid';
        break;
    case 0:
        $statusLabel = 'Pending';
        break;
    case -1:
        $statusLabel = 'Cancelled';
        break;
    default:
        $statusLabel = 'Unknown';
}

// Generate PDF using TCPDF (assuming TCPDF is installed; if not, use HTML/CSS for print-friendly page)
require_once('tcpdf/tcpdf.php'); // Adjust path if TCPDF is installed elsewhere

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('HackLink Panel');
$pdf->SetTitle('Invoice #' . $invoice->invoice_no);
$pdf->SetSubject('Invoice Details');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Title
$pdf->Cell(0, 10, 'Invoice #' . $invoice->invoice_no, 0, 1, 'C', 0, '', 0, false, 'T', 'M');
$pdf->Ln(5);

// Company details (HackLink Panel)
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 5, 'HackLink Panel', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 4, 'Systemadminbd', 0, 1, 'C');
$pdf->Cell(0, 4, 'Email: support@hacklink.com', 0, 1, 'C');
$pdf->Cell(0, 4, 'Date: ' . date('d.m.Y'), 0, 1, 'C');
$pdf->Ln(5);

// Invoice details table
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 6, 'Bill To:', 0, 0, 'L');
$pdf->Cell(50, 6, 'Invoice Details:', 0, 1, 'R');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(50, 4, $user->username ?? 'User', 0, 0, 'L'); // Assuming $user has username
$pdf->Cell(50, 4, 'Invoice No: #' . $invoice->invoice_no, 0, 1, 'R');
$pdf->Cell(50, 4, 'Email: ' . ($user->email ?? 'N/A'), 0, 0, 'L');
$pdf->Cell(50, 4, 'Date: ' . date("d.m.Y H:i", strtotime($invoice->date)), 0, 1, 'R');
$pdf->Cell(50, 4, 'Package: ' . $invoice->package_name, 0, 0, 'L');
$pdf->Cell(50, 4, 'Status: ' . $statusLabel, 0, 1, 'R');
$pdf->Ln(5);

// Items table (single item for simplicity)
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(120, 6, 'Description', 1, 0, 'L');
$pdf->Cell(30, 6, 'Amount', 1, 0, 'R');
$pdf->Cell(30, 6, 'Total', 1, 1, 'R');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(120, 6, $invoice->package_name, 1, 0, 'L');
$pdf->Cell(30, 6, '', 1, 0, 'R');
$pdf->Cell(30, 6, number_format($invoice->amount, 2, '.', ',') . ' ৳', 1, 1, 'R');

$pdf->Ln(10);

// Footer note
$pdf->SetFont('helvetica', '', 8);
$pdf->MultiCell(0, 4, 'Thank you for your business! For questions, contact support@hacklink.com', 0, 'C');

// Output PDF
$pdf->Output('Invoice_' . $invoice->invoice_no . '_' . date('Ymd') . '.pdf', 'D'); // 'D' forces download

// If TCPDF is not available, fallback to HTML print-friendly page
/*
if (!class_exists('TCPDF')) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Invoice #<?=$invoice->invoice_no?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Invoice #<?=$invoice->invoice_no?></h1>
            <p>HackLink Panel - Systemadminbd</p>
        </div>
        <table>
            <tr><td><strong>Bill To:</strong></td><td><?=$user->username ?? 'User'?></td></tr>
            <tr><td><strong>Email:</strong></td><td><?=$user->email ?? 'N/A'?></td></tr>
            <tr><td><strong>Package:</strong></td><td><?=$invoice->package_name?></td></tr>
            <tr><td><strong>Date:</strong></td><td><?=date("d.m.Y H:i", strtotime($invoice->date))?></td></tr>
            <tr><td><strong>Amount:</strong></td><td><?=number_format($invoice->amount, 2)?> ৳</td></tr>
            <tr><td><strong>Status:</strong></td><td><?=$statusLabel?></td></tr>
        </table>
        <p style="text-align: center;">Thank you for your business!</p>
        <script>window.print();</script>
    </body>
    </html>
    <?php
}
*/
?>