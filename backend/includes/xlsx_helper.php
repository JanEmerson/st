<?php
/**
 * Lightweight XLSX reader/writer for ST Asset Management.
 * No Composer package and no PHP ZipArchive extension required.
 * Supports simple .xlsx tabular workbooks used by ST import/export.
 */

if (!function_exists('st_xlsx_escape')) {
    /* Purpose: Escape XML text for XLSX generation. */
    function st_xlsx_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /* Purpose: Convert a numeric column index into an Excel column name. */

    function st_xlsx_column_name(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }

    /* Purpose: Pack a 16-bit value for the XLSX ZIP structure. */

    function st_zip_pack_u16(int $value): string
    {
        return pack('v', $value);
    }

    /* Purpose: Pack a 32-bit value for the XLSX ZIP structure. */

    function st_zip_pack_u32(int $value): string
    {
        return pack('V', $value);
    }

    /* Purpose: Add a file entry to the XLSX ZIP archive. */

    function st_zip_add_file(array &$files, string $name, string $data): void
    {
        $files[] = [
            'name' => $name,
            'data' => $data,
            'crc' => crc32($data),
            'size' => strlen($data),
        ];
    }

    /* Purpose: Create an XLSX ZIP archive from XML files. */

    function st_zip_create(array $files, string $outputFile): void
    {
        $binary = '';
        $central = '';
        $offset = 0;
        $date = getdate();
        $dosTime = (($date['hours'] & 31) << 11) | (($date['minutes'] & 63) << 5) | (($date['seconds'] >> 1) & 31);
        $dosDate = ((max(1980, $date['year']) - 1980) << 9) | (($date['mon'] & 15) << 5) | ($date['mday'] & 31);

        foreach ($files as $file) {
            $name = $file['name'];
            $nameBytes = strlen($name);
            $data = $file['data'];
            $compressed = gzdeflate($data, 6);
            if ($compressed === false || strlen($compressed) >= strlen($data)) {
                $compressed = $data;
                $method = 0;
            } else {
                $method = 8;
            }

            $local = "\x50\x4b\x03\x04";
            $local .= st_zip_pack_u16(20); // version needed
            $local .= st_zip_pack_u16(0);  // flags
            $local .= st_zip_pack_u16($method);
            $local .= st_zip_pack_u16($dosTime);
            $local .= st_zip_pack_u16($dosDate);
            $local .= st_zip_pack_u32($file['crc']);
            $local .= st_zip_pack_u32(strlen($compressed));
            $local .= st_zip_pack_u32(strlen($data));
            $local .= st_zip_pack_u16($nameBytes);
            $local .= st_zip_pack_u16(0);
            $local .= $name . $compressed;
            $binary .= $local;

            $central .= "\x50\x4b\x01\x02";
            $central .= st_zip_pack_u16(20); // made by
            $central .= st_zip_pack_u16(20); // needed
            $central .= st_zip_pack_u16(0);
            $central .= st_zip_pack_u16($method);
            $central .= st_zip_pack_u16($dosTime);
            $central .= st_zip_pack_u16($dosDate);
            $central .= st_zip_pack_u32($file['crc']);
            $central .= st_zip_pack_u32(strlen($compressed));
            $central .= st_zip_pack_u32(strlen($data));
            $central .= st_zip_pack_u16($nameBytes);
            $central .= st_zip_pack_u16(0); // extra
            $central .= st_zip_pack_u16(0); // comment
            $central .= st_zip_pack_u16(0); // disk
            $central .= st_zip_pack_u16(0); // internal attrs
            $central .= st_zip_pack_u32(0); // external attrs
            $central .= st_zip_pack_u32($offset);
            $central .= $name;

            $offset = strlen($binary);
        }

        $centralOffset = strlen($binary);
        $binary .= $central;
        $centralSize = strlen($central);
        $count = count($files);

        $eocd = "\x50\x4b\x05\x06";
        $eocd .= st_zip_pack_u16(0);
        $eocd .= st_zip_pack_u16(0);
        $eocd .= st_zip_pack_u16($count);
        $eocd .= st_zip_pack_u16($count);
        $eocd .= st_zip_pack_u32($centralSize);
        $eocd .= st_zip_pack_u32($centralOffset);
        $eocd .= st_zip_pack_u16(0);
        $binary .= $eocd;

        if (file_put_contents($outputFile, $binary) === false) {
            throw new RuntimeException('Unable to write the Excel file.');
        }
    }

    /* Purpose: Read entries from a ZIP archive. */

    function st_zip_read_entries(string $file): array
    {
        $data = file_get_contents($file);
        if ($data === false) throw new RuntimeException('Unable to read the uploaded Excel file.');

        $eocdPos = strrpos($data, "\x50\x4b\x05\x06");
        if ($eocdPos === false) throw new RuntimeException('Invalid XLSX archive.');
        $eocd = substr($data, $eocdPos, 22);
        $parts = unpack('vdisk/vdiskStart/ventriesDisk/ventriesTotal/VcentralSize/VcentralOffset/vcommentLen', substr($eocd, 4));
        if (!$parts || $parts['entriesTotal'] < 1) throw new RuntimeException('The Excel workbook contains no files.');

        $offset = (int)$parts['centralOffset'];
        $count = (int)$parts['entriesTotal'];
        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            if (substr($data, $offset, 4) !== "\x50\x4b\x01\x02") break;
            $header = substr($data, $offset, 46);
            $h = unpack('vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnamelen/vextralen/vcommentlen/vdisk/vinternal/Vexternal/Vlocaloffset', substr($header, 4));
            $name = substr($data, $offset + 46, $h['namelen']);
            $entries[$name] = [
                'method' => (int)$h['method'],
                'compressed_size' => (int)$h['csize'],
                'uncompressed_size' => (int)$h['usize'],
                'local_offset' => (int)$h['localoffset'],
            ];
            $offset += 46 + $h['namelen'] + $h['extralen'] + $h['commentlen'];
        }
        return [$data, $entries];
    }

    /* Purpose: Return one ZIP archive entry by name. */

    function st_zip_get_entry(string $data, array $entries, string $name): ?string
    {
        if (!isset($entries[$name])) return null;
        $entry = $entries[$name];
        $o = $entry['local_offset'];
        if (substr($data, $o, 4) !== "\x50\x4b\x03\x04") return null;
        $h = unpack('vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnamelen/vextralen', substr($data, $o + 4, 26));
        $start = $o + 30 + $h['namelen'] + $h['extralen'];
        $compressed = substr($data, $start, $entry['compressed_size']);
        if ((int)$entry['method'] === 0) return $compressed;
        if ((int)$entry['method'] === 8) {
            $out = gzinflate($compressed);
            return $out === false ? null : $out;
        }
        return null;
    }

    /* Purpose: Build the XLSX style definitions. */

    function st_xlsx_style_xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="10"/><name val="Aptos"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FF092328"/><name val="Aptos"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font>'
            . '</fonts>'
            . '<fills count="7">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF12544F"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFC0392B"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFE6E1"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF6EF"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF4CC"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD5E2DE"/></left><right style="thin"><color rgb="FFD5E2DE"/></right><top style="thin"><color rgb="FFD5E2DE"/></top><bottom style="thin"><color rgb="FFD5E2DE"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="9">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="5" borderId="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="5" borderId="1" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="6" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    /* Purpose: Build a worksheet XML document from rows. */

    function st_xlsx_sheet_xml(array $rows, array $meta = []): string
    {
        $widths = $meta['widths'] ?? [];
        $requiredColumns = $meta['required_columns'] ?? [];
        $headerStyles = $meta['header_styles'] ?? [];
        $inputStyle = (int)($meta['input_style'] ?? 5);
        $requiredInputStyle = (int)($meta['required_input_style'] ?? 4);
        $dateColumns = $meta['date_columns'] ?? [];
        $autoFilter = !empty($meta['auto_filter']);
        $freezeRows = (int)($meta['freeze_rows'] ?? 1);
        $freezeCols = (int)($meta['freeze_cols'] ?? 0);
        $validations = $meta['validations'] ?? [];

        $lastCol = count($rows[0] ?? []);
        $lastRow = count($rows);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetViews><sheetView workbookViewId="0">';
        if ($freezeRows > 0 || $freezeCols > 0) {
            $topLeft = st_xlsx_column_name($freezeCols + 1) . ($freezeRows + 1);
            $ySplit = $freezeRows;
            $xSplit = $freezeCols;
            $activePane = ($freezeRows > 0 && $freezeCols > 0) ? 'bottomRight' : ($freezeRows > 0 ? 'bottomLeft' : 'topRight');
            $xml .= '<pane xSplit="' . $xSplit . '" ySplit="' . $ySplit . '" topLeftCell="' . $topLeft . '" activePane="' . $activePane . '" state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews>';

        if ($widths) {
            $xml .= '<cols>';
            foreach ($widths as $index => $width) {
                $col = (int)$index + 1;
                $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . number_format((float)$width, 2, '.', '') . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($rows as $rIndex => $row) {
            $excelRow = $rIndex + 1;
            $height = ($rIndex === 0 ? 30 : 20);
            $xml .= '<row r="' . $excelRow . '" ht="' . $height . '" customHeight="1">';
            foreach (array_values($row) as $cIndex => $value) {
                $cellRef = st_xlsx_column_name($cIndex + 1) . $excelRow;
                if ($rIndex === 0) {
                    $style = isset($headerStyles[$cIndex]) ? (int)$headerStyles[$cIndex] : 1;
                } elseif (in_array($cIndex, $requiredColumns, true)) {
                    $style = $requiredInputStyle;
                } else {
                    $style = $inputStyle;
                }
                if ($rIndex > 0 && in_array($cIndex, $dateColumns, true) && trim((string)$value) !== '') {
                    $style = 6;
                }
                if ($value === null || $value === '') {
                    $xml .= '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve"></t></is></c>';
                } elseif (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $cellRef . '" s="' . $style . '" t="n"><v>' . st_xlsx_escape($value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cellRef . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . st_xlsx_escape($value) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($autoFilter && $lastCol > 0 && $lastRow > 0) {
            $xml .= '<autoFilter ref="A1:' . st_xlsx_column_name($lastCol) . $lastRow . '"/>';
        }

        if ($validations) {
            $xml .= '<dataValidations count="' . count($validations) . '">';
            foreach ($validations as $validation) {
                $type = $validation['type'] ?? 'list';
                $sqref = st_xlsx_escape($validation['sqref'] ?? '');
                $formula1 = st_xlsx_escape($validation['formula1'] ?? '');
                $allowBlank = !empty($validation['allow_blank']) ? '1' : '0';
                $xml .= '<dataValidation type="' . $type . '" allowBlank="' . $allowBlank . '" showInputMessage="1" showErrorMessage="1" sqref="' . $sqref . '"><formula1>' . $formula1 . '</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }

        $xml .= '</worksheet>';
        return $xml;
    }

    /* Purpose: Build the XLSX workbook XML document. */

    function st_xlsx_workbook_xml(array $sheetNames, array $hiddenSheets = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($sheetNames as $i => $name) {
            $state = in_array($name, $hiddenSheets, true) ? ' state="hidden"' : '';
            $xml .= '<sheet name="' . st_xlsx_escape($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"' . $state . '/>';
        }
        return $xml . '</sheets></workbook>';
    }

    /* Purpose: Build workbook relationship XML. */

    function st_xlsx_workbook_rels_xml(int $count): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $count; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return $xml . '</Relationships>';
    }

    /* Purpose: Build the XLSX content types XML document. */

    function st_xlsx_content_types_xml(int $count): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $count; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return $xml . '</Types>';
    }

    /* Purpose: Handle the file-specific operation represented by this function. */

    function st_xlsx_create(array $sheets, string $outputFile): bool
    {
        $files = [];
        $sheetNames = array_keys($sheets);
        $hiddenSheets = [];
        $sheetRows = [];
        foreach ($sheets as $name => $definition) {
            if (isset($definition['rows'])) {
                $sheetRows[] = $definition['rows'];
                if (!empty($definition['hidden'])) $hiddenSheets[] = $name;
            } else {
                $sheetRows[] = $definition;
            }
        }

        st_zip_add_file($files, '[Content_Types].xml', st_xlsx_content_types_xml(count($sheets)));
        st_zip_add_file($files, '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        st_zip_add_file($files, 'xl/workbook.xml', st_xlsx_workbook_xml($sheetNames, $hiddenSheets));
        st_zip_add_file($files, 'xl/_rels/workbook.xml.rels', st_xlsx_workbook_rels_xml(count($sheets)));
        st_zip_add_file($files, 'xl/styles.xml', st_xlsx_style_xml());

        $i = 0;
        foreach ($sheets as $name => $definition) {
            $i++;
            if (isset($definition['rows'])) {
                $rows = $definition['rows'];
                $meta = $definition['meta'] ?? [];
            } else {
                $rows = $definition;
                $meta = [];
            }
            st_zip_add_file($files, 'xl/worksheets/sheet' . $i . '.xml', st_xlsx_sheet_xml($rows, $meta));
        }
        st_zip_create($files, $outputFile);
        return true;
    }

    /* Purpose: Convert XML text content safely. */

    function st_xlsx_xml_text(string $xml): string
    {
        if ($xml === '') return '';
        preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $xml, $matches);
        if (!empty($matches[1])) {
            return html_entity_decode(implode('', $matches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /* Purpose: Escape an XML attribute for XLSX generation. */

    function st_xlsx_attr(string $attrs, string $name): string
    {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/si', $attrs, $m)) {
            return html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return '';
    }

    /* Purpose: Parse the supported XLSX workbook into application rows. */

    function st_xlsx_parse(string $file, int $sheetNumber = 1): array
    {
        [$data, $entries] = st_zip_read_entries($file);
        $sharedStrings = [];
        $sharedXml = st_zip_get_entry($data, $entries, 'xl/sharedStrings.xml');
        if ($sharedXml !== null) {
            if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $sharedXml, $sis)) {
                foreach ($sis[1] as $si) {
                    $sharedStrings[] = st_xlsx_xml_text($si);
                }
            }
        }

        if ($sheetNumber < 1) $sheetNumber = 1;
        $sheetXml = st_zip_get_entry($data, $entries, 'xl/worksheets/sheet' . $sheetNumber . '.xml');
        if ($sheetXml === null) {
            throw new RuntimeException('The Excel workbook does not contain a readable worksheet.');
        }

        $rows = [];
        if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si', $sheetXml, $rowMatches)) {
            return [];
        }

        foreach ($rowMatches[1] as $rowXml) {
            $values = [];
            preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/si', $rowXml, $cellMatches, PREG_SET_ORDER);
            foreach ($cellMatches as $cellMatch) {
                $attrs = $cellMatch[1] ?? '';
                $body = $cellMatch[2] ?? '';
                $ref = st_xlsx_attr($attrs, 'r');
                $type = st_xlsx_attr($attrs, 't');

                $colIndex = 0;
                if ($ref !== '' && preg_match('/^([A-Z]+)/i', $ref, $m)) {
                    foreach (str_split(strtoupper($m[1])) as $letter) {
                        $colIndex = ($colIndex * 26) + (ord($letter) - 64);
                    }
                    $colIndex--;
                }

                while (count($values) < $colIndex) $values[] = '';

                if ($type === 's') {
                    preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $vm);
                    $idx = isset($vm[1]) ? (int)trim($vm[1]) : 0;
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr') {
                    if (preg_match('/<is\b[^>]*>(.*?)<\/is>/si', $body, $im)) {
                        $value = st_xlsx_xml_text($im[1]);
                    } else {
                        $value = st_xlsx_xml_text($body);
                    }
                } else {
                    preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $vm);
                    $value = isset($vm[1]) ? html_entity_decode(trim($vm[1]), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
                }
                $values[$colIndex] = $value;
            }
            $rows[] = $values;
        }

        return $rows;
    }
}
