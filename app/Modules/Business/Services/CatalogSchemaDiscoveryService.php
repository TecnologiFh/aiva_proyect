<?php

namespace App\Modules\Business\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatalogSchemaDiscoveryService
{
    /**
     * Descubre la estructura del archivo físico (XLSX o CSV).
     *
     * Retorna:
     * - header_row_index: int (1-based index)
     * - detected_columns: array con nombres originales
     * - column_count: int
     * - columns_metadata: array detallado [name, index, normalized, inferred_type]
     * - schema_signature: string (hash SHA-256)
     * - sample_rows: array de filas asociativas
     * - total_data_rows: int
     *
     * @param string $filePath Ruta absoluta al archivo
     * @return array
     */
    public function discoverFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Archivo no encontrado en {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $rawRows = ($extension === 'csv' || $extension === 'txt')
            ? $this->extractRowsFromCsv($filePath)
            : $this->extractRowsFromXlsx($filePath);

        if (empty($rawRows)) {
            throw new \RuntimeException("El archivo no contiene filas legibles");
        }

        return $this->analyzeRows($rawRows);
    }

    /**
     * Extrae filas de archivo XLSX soportando sharedStrings e inlineStr
     */
    public function extractRowsFromXlsx(string $filePath): array
    {
        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException("La extensión ZipArchive no está habilitada en PHP.");
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("No se pudo abrir el archivo XLSX: {$filePath}");
        }

        // 1. Extraer Shared Strings
        $sharedStrings = [];
        $sharedXmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXmlContent !== false) {
            $sharedXml = @simplexml_load_string($sharedXmlContent);
            if ($sharedXml) {
                foreach ($sharedXml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $tPart = '';
                        foreach ($si->r as $r) {
                            $tPart .= (string)$r->t;
                        }
                        $sharedStrings[] = $tPart;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Extraer hoja de cálculo
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (str_starts_with($stat['name'], 'xl/worksheets/sheet') && str_ends_with($stat['name'], '.xml')) {
                    $sheetXmlContent = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXmlContent === false) {
            throw new \RuntimeException("No se encontró ninguna hoja de cálculo en el archivo XLSX.");
        }

        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (!$sheetXml || !isset($sheetXml->sheetData)) {
            throw new \RuntimeException("XML de la hoja de cálculo corrupto o inválido.");
        }

        $rawRows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $rowNum = (int)$row['r']; // 1-based index de fila de Excel
            $rowCells = [];

            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                $colLetter = preg_replace('/[0-9]+/', '', $ref);
                $colIndex = $this->columnLetterToIndex($colLetter);

                $cellType = (string)$cell['t'];
                $val = (string)$cell->v;

                if ($cellType === 's') {
                    $idx = (int)$val;
                    $cellValue = $sharedStrings[$idx] ?? '';
                } elseif ($cellType === 'inlineStr' && isset($cell->is->t)) {
                    $cellValue = (string)$cell->is->t;
                } else {
                    $cellValue = $val;
                }

                $rowCells[$colIndex] = trim($cellValue);
            }

            if (!empty($rowCells)) {
                $maxCol = max(array_keys($rowCells));
                $denseRow = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $denseRow[$c] = $rowCells[$c] ?? '';
                }
                $rawRows[$rowNum] = $denseRow;
            }
        }

        return $rawRows;
    }

    /**
     * Extrae filas de archivo CSV
     */
    public function extractRowsFromCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("No se pudo leer el archivo CSV.");
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $rawRows = [];
        $lineIndex = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $trimmed = array_map('trim', $row);
            $rawRows[$lineIndex] = $trimmed;
            $lineIndex++;
        }
        fclose($handle);

        return $rawRows;
    }

    /**
     * Analiza las filas para detectar la fila de cabecera, tipos y firma
     */
    public function analyzeRows(array $rawRows): array
    {
        // 1. Encontrar la fila de cabecera más probable (entre las primeras 10 filas)
        $headerRowIndex = null;
        $maxHeaderScore = -1;
        $candidateColumns = [];

        foreach ($rawRows as $rowIndex => $cells) {
            if ($rowIndex > 15) {
                break;
            }

            $nonEmptyCells = array_filter($cells, fn($c) => $c !== '' && $c !== null);
            $count = count($nonEmptyCells);

            if ($count < 3) {
                // Títulos de una o dos celdas no son cabeceras
                continue;
            }

            // Evaluar características de cabecera:
            // - Mayoría de celdas son cadenas de texto (no números puros)
            // - Sin saltos masivos de celdas vacías intercaladas
            $textCount = 0;
            foreach ($nonEmptyCells as $cellVal) {
                if (!is_numeric($cellVal) && strlen($cellVal) <= 60) {
                    $textCount++;
                }
            }

            $score = ($textCount * 2) + $count;

            // Si tiene palabras típicas de cabecera, sumamos puntos
            $joined = strtoupper(implode(' ', $nonEmptyCells));
            if (str_contains($joined, 'PRECIO') || str_contains($joined, 'PRODUCTO') || str_contains($joined, 'SKU') || str_contains($joined, 'CATEGORIA') || str_contains($joined, 'MODELO')) {
                $score += 20;
            }

            if ($score > $maxHeaderScore) {
                $maxHeaderScore = $score;
                $headerRowIndex = $rowIndex;
                $candidateColumns = $cells;
            }
        }

        if ($headerRowIndex === null || empty($candidateColumns)) {
            // Fallback a primera fila disponible
            $headerRowIndex = array_key_first($rawRows);
            $candidateColumns = $rawRows[$headerRowIndex];
        }

        // 2. Normalizar columnas detectadas y podar columnas vacías al final
        $detectedColumns = [];
        $columnsMetadata = [];

        // Determinar última columna con nombre válido
        $lastValidCol = 0;
        foreach ($candidateColumns as $idx => $colName) {
            if (trim($colName) !== '') {
                $lastValidCol = $idx;
            }
        }

        for ($c = 0; $c <= $lastValidCol; $c++) {
            $rawName = trim($candidateColumns[$c] ?? '');
            $displayName = $rawName !== '' ? $rawName : "COL_" . ($c + 1);
            $slug = Str::of($displayName)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

            $detectedColumns[$c] = $displayName;
            $columnsMetadata[$c] = [
                'index' => $c,
                'name' => $displayName,
                'normalized' => $slug,
                'inferred_type' => 'STRING', // provisional
            ];
        }

        // 3. Muestrear filas de datos posteriores a la cabecera para inferir tipos de datos
        $dataRows = [];
        $sampleAssociativeRows = [];
        $typeSamples = array_fill_keys(array_keys($detectedColumns), []);

        foreach ($rawRows as $rowIndex => $cells) {
            if ($rowIndex <= $headerRowIndex) {
                continue;
            }

            // Ignorar filas totalmente vacías
            $hasData = false;
            foreach ($detectedColumns as $cIdx => $name) {
                if (!empty($cells[$cIdx])) {
                    $hasData = true;
                    break;
                }
            }
            if (!$hasData) {
                continue;
            }

            $dataRows[] = $cells;
            if (count($sampleAssociativeRows) < 5) {
                $assoc = [];
                foreach ($detectedColumns as $cIdx => $name) {
                    $assoc[$name] = $cells[$cIdx] ?? null;
                }
                $sampleAssociativeRows[] = $assoc;
            }

            if (count($dataRows) <= 30) {
                foreach ($detectedColumns as $cIdx => $name) {
                    $val = trim($cells[$cIdx] ?? '');
                    if ($val !== '') {
                        $typeSamples[$cIdx][] = $val;
                    }
                }
            }
        }

        // 4. Inferir tipos para cada columna
        foreach ($columnsMetadata as $cIdx => $meta) {
            $samples = $typeSamples[$cIdx] ?? [];
            $columnsMetadata[$cIdx]['inferred_type'] = $this->inferColumnType($meta['name'], $samples);
        }

        // 5. Calcular firma SHA-256 determinista del esquema
        $signatureComponents = [];
        foreach ($columnsMetadata as $meta) {
            $signatureComponents[] = "{$meta['index']}:{$meta['normalized']}:{$meta['inferred_type']}";
        }
        $schemaSignature = hash('sha256', implode('|', $signatureComponents));

        return [
            'header_row_index' => $headerRowIndex,
            'detected_columns' => array_values($detectedColumns),
            'column_count' => count($detectedColumns),
            'columns_metadata' => array_values($columnsMetadata),
            'schema_signature' => $schemaSignature,
            'sample_rows' => $sampleAssociativeRows,
            'total_data_rows' => count($dataRows),
        ];
    }

    /**
     * Infiere el tipo de dato físico de la columna a partir de su nombre y valores muestreados
     */
    protected function inferColumnType(string $headerName, array $samples): string
    {
        $headerUpper = strtoupper($headerName);

        // Nombres de cabecera fuertemente sugerentes
        if (str_contains($headerUpper, 'PRECIO') || str_contains($headerUpper, 'VALOR') || str_contains($headerUpper, 'TOTAL') || str_contains($headerUpper, 'COSTO')) {
            return 'MONEY';
        }
        if (str_contains($headerUpper, 'STOCK') || str_contains($headerUpper, 'CANTIDAD') || str_contains($headerUpper, 'INVENTARIO')) {
            return 'NUMERIC';
        }
        if (str_contains($headerUpper, 'SKU') || str_contains($headerUpper, 'CODIGO') || str_contains($headerUpper, 'CÓDIGO')) {
            return 'CODE';
        }
        if (str_contains($headerUpper, 'AÑO') || str_contains($headerUpper, 'ANO') || str_contains($headerUpper, 'YEAR')) {
            return 'YEAR_RANGE';
        }

        if (empty($samples)) {
            return 'STRING';
        }

        // Inspeccionar muestras
        $numericCount = 0;
        $moneyCount = 0;
        $yearRangeCount = 0;
        $codeCount = 0;
        $totalSamples = count($samples);

        foreach ($samples as $sample) {
            $s = trim($sample);
            // Year range e.g. 2016-2020 o 2018
            if (preg_match('/^(19|20)\d{2}(\s*[-–\/]\s*(19|20)?\d{2})?$/', $s)) {
                $yearRangeCount++;
            }
            // Money e.g. S/ 250.00 o 250.50
            if (preg_match('/^(S\/|\$|€)?\s*[\d,]+(\.\d{1,2})?$/', $s)) {
                $moneyCount++;
            }
            // Pure integer
            if (preg_match('/^\d+$/', $s)) {
                $numericCount++;
            }
            // Code e.g. CAR-XR-001
            if (preg_match('/^[A-Z0-9_\-]+$/i', $s) && preg_match('/\d/', $s) && preg_match('/[A-Z]/i', $s)) {
                $codeCount++;
            }
        }

        if ($yearRangeCount / $totalSamples >= 0.7) {
            return 'YEAR_RANGE';
        }
        if ($numericCount / $totalSamples >= 0.8) {
            return 'NUMERIC';
        }
        if ($moneyCount / $totalSamples >= 0.8) {
            return 'MONEY';
        }
        if ($codeCount / $totalSamples >= 0.7) {
            return 'CODE';
        }

        return 'STRING';
    }

    /**
     * Convierte letras de columna de Excel (A, B, ..., Z, AA, AB) a índice 0-based
     */
    protected function columnLetterToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }
}
