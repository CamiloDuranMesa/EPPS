<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader;


$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
if ($empleado_id <= 0) die('Empleado no especificado.');


$stmtEmp = $conn->prepare("SELECT nombre, cedula, cargo, area FROM empleados WHERE id = ?");
$stmtEmp->bind_param("i", $empleado_id);
$stmtEmp->execute();
$stmtEmp->bind_result($empNombre, $empCedula, $empCargo, $empArea);
if (!$stmtEmp->fetch()) { $stmtEmp->close(); die('Empleado no encontrado.'); }
$stmtEmp->close();


$query = "SELECT e.id, e.fecha_entrega, e.numero_dotacion, e.pdf_file, e.firma_empleado,
                 u_resp.nombre AS responsable_nombre,
                 u_sst.nombre  AS sst_nombre
          FROM entregas e
          LEFT JOIN usuarios u_resp ON u_resp.id = e.responsable_entrega
          LEFT JOIN empleados u_sst  ON u_sst.id  = e.sst_id
          WHERE e.empleado_id = ?
          ORDER BY e.fecha_entrega DESC, e.id DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $empleado_id);
$stmt->execute();
$res = $stmt->get_result();

$entregas = [];
$pdf_guardado = null;
while ($row = $res->fetch_assoc()) {
    $entregas[] = $row;
    if (!$pdf_guardado && !empty($row['pdf_file'])) $pdf_guardado = $row['pdf_file'];
}
$stmt->close();


$tempDir = __DIR__ . '/../temp';
if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);


$logoFile = __DIR__ . '/../assets/img/agro-logo.png';
$logoData = base64_encode(file_get_contents($logoFile));
$logoSrc = 'data:image/png;base64,' . $logoData;

$html = '
<style>
body { font-family: Arial, sans-serif; font-size: 12px; }
.header { text-align: center; margin-bottom: 20px; }
.logo { width: 100px; margin-bottom: 10px; }
h2 { margin: 0; font-size: 16px; }
.employee-info { margin-bottom: 15px; }
table { border-collapse: collapse; width: 100%; }
table th, table td { border: 1px solid #000; padding: 6px; text-align: left; }
table th { background-color: #f0f0f0; }
.footer { text-align: center; font-size: 10px; margin-top: 20px; }
</style>

<div class="header">
    <img src="' . $logoSrc . '" class="logo">
    <h2>Informe de entregas - Agro S.A.</h2>
</div>

<div class="employee-info">
    <strong>Empleado:</strong> ' . htmlspecialchars($empNombre) . '<br>
    <strong>Cédula:</strong> ' . htmlspecialchars($empCedula) . '<br>
    <strong>Cargo:</strong> ' . htmlspecialchars($empCargo) . '<br>
    <strong>Área:</strong> ' . htmlspecialchars($empArea) . '
</div>

<hr>

<table>
<thead>
<tr>
<th>Ítem entregado</th>
<th>Observación</th>
<th>Fecha entrega</th>
<th>Responsable entrega</th>
<th>Representante SST</th>
<th>Firma empleado</th>
</tr>
</thead>
<tbody>';

foreach ($entregas as $entrega) {
    $firmaEmpleadoSrc = null;
    if (!empty($entrega['firma_empleado']) && file_exists(__DIR__ . '/../firmas/' . $entrega['firma_empleado'])) {
        $firmaData = base64_encode(file_get_contents(__DIR__ . '/../firmas/' . $entrega['firma_empleado']));
        $firmaEmpleadoSrc = 'data:image/png;base64,' . $firmaData;
    }
    $eid = $entrega['id'];
    $q = $conn->prepare("SELECT elemento, observaciones FROM entregas_detalle WHERE entrega_id = ?");
    $q->bind_param("i", $eid);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $html .= '<tr>
            <td>' . htmlspecialchars($row['elemento']) . '</td>
            <td>' . htmlspecialchars($row['observaciones']) . '</td>
            <td>' . htmlspecialchars($entrega['fecha_entrega']) . '</td>
            <td>' . htmlspecialchars($entrega['responsable_nombre'] ?? '—') . '</td>
            <td>' . htmlspecialchars($entrega['sst_nombre'] ?? '—') . '</td>
            <td>';

        if ($firmaEmpleadoSrc) {
            $html .= '<img src="' . $firmaEmpleadoSrc .'" style="max: height 40px; max-width:120px; margin-top:5px;">';
        } else {
            $html .= '-';
        }

        $html .= '</td>                                    
        </tr>';
    }
    $q->close();
}

$html .= '</tbody></table>

<hr>

<table width="100%" style="margin-top:30px;">
<tr>
<td style="border:1px solid #000; width:33%; height:60px; text-align:center; vertical-align:bottom;">Valor<br><div style="height:40px;"></div></td>
<td style="border:1px solid #000; width:33%; height:60px; text-align:center; vertical-align:bottom;">Firma almacen<br><div style="height:40px;"></div></td>
<td style="border:1px solid #000; width:33%; height:60px; text-align:center; vertical-align:bottom;">Firma Empleado<br><div style="height:40px;"></div></td>
</tr>
</table>

<div class="footer">
Agro S.A. &copy; ' . date('Y') . '. Todos los derechos reservados.
</div>';


$generatedPdf = $tempDir . '/informe_generado.pdf';
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
file_put_contents($generatedPdf, $dompdf->output());

$finalPdf = $tempDir . '/informe_final.pdf';
$pdf = new Fpdi();

$pageCount = $pdf->setSourceFile($generatedPdf);
for ($i = 1; $i <= $pageCount; $i++) {
    $tplId = $pdf->importPage($i);
    $size = $pdf->getTemplateSize($tplId);
    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $pdf->useTemplate($tplId);
}

if ($pdf_guardado) {
    $pdfEscaneado = realpath(__DIR__ . '/../uploads/' . $pdf_guardado);
    if ($pdfEscaneado && file_exists($pdfEscaneado)) {
        $pageCountScan = $pdf->setSourceFile($pdfEscaneado);
        for ($i = 1; $i <= $pageCountScan; $i++) {
            $tplId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($tplId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);
        }
    }
}

$pdf->Output($finalPdf, 'F');

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="informe_entregas.pdf"');
readfile($finalPdf);


unlink($generatedPdf);
unlink($finalPdf);
exit;
