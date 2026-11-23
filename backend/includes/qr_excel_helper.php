<?php
/**
 * Standalone QR-rich XLSX export. Does not modify the re-importable export.
 * Uses the same external QR rendering service as the existing label printer.
 */
require_once __DIR__ . "/xlsx_helper.php";
require_once __DIR__ . "/app_config.php";

function st_qr_excel_export(PDO $conn, string $outputFile): void
{
    $assets = $conn->query("SELECT asset_code, asset_name, serial_number, status, qr_token FROM assets ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (count($assets) > 300) {
        throw new RuntimeException("QR image export is limited to 300 assets per workbook to avoid timeouts. Use the standard Excel export for larger inventories.");
    }
    $rows = [['Asset Code', 'Asset Name', 'Serial Number', 'Status', 'QR Token', 'QR Link', 'QR Image']];
    $images = [];
    foreach ($assets as $asset) {
        $token = (string)($asset['qr_token'] ?? '');
        $link = $token !== '' ? asset_qr_url($conn, $token) : '';
        $rows[] = [$asset['asset_code'], $asset['asset_name'], $asset['serial_number'] ?? '', $asset['status'], $token, $link, ''];
        if ($link === '') continue;
        $url = asset_qr_image_url($link, 160);
        $context = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true, 'user_agent' => 'ST-Asset-QR-Export/1.0']]);
        $png = @file_get_contents($url, false, $context, 0, 1024 * 1024);
        if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new RuntimeException("Could not retrieve QR images. Check your internet connection and PHP HTTPS configuration. No incomplete workbook was exported.");
        }
        $images[] = ['row' => count($rows) - 1, 'png' => $png];
    }
    $files = [];
    $content = st_xlsx_content_types_xml(1);
    $content = str_replace('</Types>', '<Default Extension="png" ContentType="image/png"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>', $content);
    st_zip_add_file($files, '[Content_Types].xml', $content);
    st_zip_add_file($files, '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    st_zip_add_file($files, 'xl/workbook.xml', st_xlsx_workbook_xml(['Assets with QR'], []));
    st_zip_add_file($files, 'xl/_rels/workbook.xml.rels', st_xlsx_workbook_rels_xml(1));
    st_zip_add_file($files, 'xl/styles.xml', st_xlsx_style_xml());
    $sheet = st_xlsx_sheet_xml($rows, ['widths' => [18, 34, 23, 17, 39, 70, 21], 'freeze_rows' => 1, 'auto_filter' => true]);
    // Increase row height for embedded QR images, leaving header untouched.
    $sheet = preg_replace_callback('/<row r="(\d+)" ht="20" customHeight="1">/', static function ($m) {
        return '<row r="' . $m[1] . '" ht="92" customHeight="1">';
    }, $sheet);
    if ($images) {
        $sheet = str_replace('</worksheet>', '<drawing xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="rId1"/></worksheet>', $sheet);
        st_zip_add_file($files, 'xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
        $drawing = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($images as $i => $img) {
            $num = $i + 1;
            st_zip_add_file($files, "xl/media/qr$num.png", $img['png']);
            $rels .= '<Relationship Id="rId' . $num . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/qr' . $num . '.png"/>';
            $drawing .= '<xdr:oneCellAnchor><xdr:from><xdr:col>6</xdr:col><xdr:colOff>40000</xdr:colOff><xdr:row>' . $img['row'] . '</xdr:row><xdr:rowOff>40000</xdr:rowOff></xdr:from><xdr:ext cx="1143000" cy="1143000"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $num . '" name="QR ' . $num . '"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId' . $num . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1143000" cy="1143000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        }
        $drawing .= '</xdr:wsDr>';
        $rels .= '</Relationships>';
        st_zip_add_file($files, 'xl/drawings/drawing1.xml', $drawing);
        st_zip_add_file($files, 'xl/drawings/_rels/drawing1.xml.rels', $rels);
    }
    st_zip_add_file($files, 'xl/worksheets/sheet1.xml', $sheet);
    st_zip_create($files, $outputFile);
}
