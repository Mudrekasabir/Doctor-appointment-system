<?php
require_once __DIR__ . '/../libs/fpdf/fpdf.php';
date_default_timezone_set('Asia/Kolkata');

$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();
$pdf->SetFont('Arial','B',16);
$pdf->Cell(0,10,'FPDF test - Doctor Appointment System',0,1,'C');
$pdf->SetFont('Arial','',12);
$pdf->Cell(0,8,'Time: ' . date('Y-m-d H:i:s'),0,1);
$pdf->Ln(4);
$pdf->Cell(0,6,'This is a test PDF. If you can download it, FPDF setup is OK.',0,1);
$pdf->Output('D','test_fpfd.pdf');
