<?php

namespace App\Modules\Business\Services;

use Illuminate\Support\Facades\Log;

class ExcelMenuParserService
{
    /**
     * Parsea un archivo Excel (.xlsx) o CSV y retorna un arreglo de ítems extraídos.
     *
     * Cada ítem contiene:
     * - name: string
     * - category: ?string
     * - price: float
     * - description: ?string
     * - confidence: float
     *
     * @param string $filePath Ruta absoluta al archivo físico
     * @return array
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            Log::warning("ExcelMenuParserService: Archivo no encontrado en {$filePath}");
            return [];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($filePath);
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsx($filePath);
        }

        return $this->parseXlsx($filePath);
    }

    /**
     * Extrae filas de un archivo .xlsx usando ZipArchive y SimpleXML nativos.
     */
    public function parseXlsx(string $filePath): array
    {
        if (!class_exists('\ZipArchive')) {
            Log::error("ExcelMenuParserService: ZipArchive no disponible en PHP.");
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            Log::error("ExcelMenuParserService: No se pudo abrir el archivo zip en {$filePath}");
            return [];
        }

        // 1. Leer Shared Strings (si existen)
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

        // 2. Leer la primera hoja (sheet1.xml)
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
            Log::error("ExcelMenuParserService: No se encontró hoja de cálculo en {$filePath}");
            return [];
        }

        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (!$sheetXml || !isset($sheetXml->sheetData)) {
            Log::error("ExcelMenuParserService: XML de hoja inválido en {$filePath}");
            return [];
        }

        $rawRows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $rowCells = [];
            foreach ($row->c as $cell) {
                $ref = (string)$cell['r']; // ej: A1, B2, C10
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
                $rawRows[] = $denseRow;
            }
        }

        return $this->processRawRowsToItems($rawRows);
    }

    /**
     * Parsea un archivo CSV delimitado por comas, punto y coma o tabulaciones.
     */
    public function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
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
        while (($data = fgetcsv($handle, 4096, $delimiter)) !== false) {
            $denseRow = array_map('trim', $data);
            if (!empty(array_filter($denseRow))) {
                $rawRows[] = $denseRow;
            }
        }
        fclose($handle);

        return $this->processRawRowsToItems($rawRows);
    }

    /**
     * Convierte filas tabulares crudas a ítems de producto estructurados.
     */
    protected function processRawRowsToItems(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // 1. Buscar la fila de encabezados entre las primeras 5 filas
        $headerRowIdx = null;
        $colMap = [
            'name' => null,
            'category' => null,
            'price' => null,
            'description' => null,
        ];

        for ($r = 0; $r < min(5, count($rows)); $r++) {
            $rowCandidate = $rows[$r];
            $tempMap = ['name' => null, 'category' => null, 'price' => null, 'description' => null];
            $matches = 0;

            foreach ($rowCandidate as $idx => $cell) {
                $hNorm = \Illuminate\Support\Str::ascii(strtolower(trim((string)$cell)));
                if (preg_match('/\b(nombre|producto|plato|item|articulo|platillo)\b/i', $hNorm)) {
                    $tempMap['name'] = $idx;
                    $matches++;
                } elseif (preg_match('/\b(categoria|seccion|tipo|grupo)\b/i', $hNorm)) {
                    $tempMap['category'] = $idx;
                    $matches++;
                } elseif (preg_match('/\b(precio|costo|p\.?v\.?p?|valor|importe|s\/)\b/i', $hNorm)) {
                    $tempMap['price'] = $idx;
                    $matches++;
                } elseif (preg_match('/\b(descripcion|detalle|ingredientes|notas)\b/i', $hNorm)) {
                    $tempMap['description'] = $idx;
                    $matches++;
                }
            }

            if ($matches >= 2 && ($tempMap['name'] !== null || $tempMap['price'] !== null)) {
                $headerRowIdx = $r;
                $colMap = $tempMap;
                break;
            }
        }

        $startIdx = ($headerRowIdx !== null) ? $headerRowIdx + 1 : 0;
        $items = [];
        $currentCategory = 'General';

        for ($r = $startIdx; $r < count($rows); $r++) {
            $row = $rows[$r];
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            // Ignorar filas de resumen / pie de página
            $rowStr = \Illuminate\Support\Str::ascii(strtolower(implode(' ', $row)));
            if (preg_match('/^(?:total|precio promedio|precio minimo|precio maximo|resumen|subtotal)\b/i', trim($rowStr))) {
                continue;
            }

            // Si se detectaron encabezados explícitos
            if ($colMap['name'] !== null && $colMap['price'] !== null) {
                $name = trim($row[$colMap['name']] ?? '');
                $catRaw = ($colMap['category'] !== null && !empty($row[$colMap['category']])) ? trim($row[$colMap['category']]) : null;
                $priceRaw = $row[$colMap['price']] ?? '0';
                $desc = ($colMap['description'] !== null && !empty($row[$colMap['description']])) ? trim($row[$colMap['description']]) : null;

                if (!empty($catRaw)) {
                    $currentCategory = $catRaw;
                }

                $priceClean = $this->cleanPrice((string)$priceRaw);

                $normName = \Illuminate\Support\Str::ascii(strtolower($name));
                if (!empty($name) && !preg_match('/^(?:producto|nombre|plato|categoria|total)\b/i', $normName)) {
                    $items[] = [
                        'name' => $name,
                        'category' => !empty($catRaw) ? $catRaw : $currentCategory,
                        'price' => $priceClean,
                        'description' => $desc,
                        'confidence' => 0.98,
                    ];
                }
                continue;
            }

            // Detección heurística si no se identificaron encabezados formales
            $nonEmpty = array_values(array_filter($row, fn($v) => trim((string)$v) !== ''));
            if (count($nonEmpty) === 1 && !is_numeric($nonEmpty[0]) && strlen($nonEmpty[0]) < 40) {
                $currentCategory = trim($nonEmpty[0]);
                continue;
            }

            $foundPrice = null;
            $nameCandidates = [];

            foreach ($row as $cIdx => $cellVal) {
                $trimmed = trim((string)$cellVal);
                if ($trimmed === '') continue;

                $parsedPrice = $this->extractPriceFromCell($trimmed);
                if ($parsedPrice !== null && $foundPrice === null) {
                    $foundPrice = $parsedPrice;
                } else {
                    $nameCandidates[] = $trimmed;
                }
            }

            if (!empty($nameCandidates) && $foundPrice !== null) {
                $name = $nameCandidates[0];
                $normName = \Illuminate\Support\Str::ascii(strtolower($name));
                if (!preg_match('/^(?:total|promedio|minimo|maximo)\b/i', $normName)) {
                    $desc = count($nameCandidates) > 1 ? implode(' - ', array_slice($nameCandidates, 1)) : null;
                    $items[] = [
                        'name' => $name,
                        'category' => $currentCategory,
                        'price' => $foundPrice,
                        'description' => $desc,
                        'confidence' => 0.95,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Limpia un valor de precio extrayendo números flotantes ("S/ 25.00" -> 25.0).
     */
    protected function cleanPrice(string $priceRaw): float
    {
        $cleaned = preg_replace('/[^0-9.,]/', '', $priceRaw);
        if (str_contains($cleaned, ',') && !str_contains($cleaned, '.')) {
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
            $cleaned = str_replace(',', '', $cleaned);
        }
        return (float)$cleaned;
    }

    /**
     * Intenta extraer un precio de una celda si luce como precio monetario.
     */
    protected function extractPriceFromCell(string $val): ?float
    {
        if (is_numeric($val) && (float)$val > 0) {
            return (float)$val;
        }

        if (preg_match('/^(?:s\/\.?\s*|\$\s*)?([0-9]+(?:[\.,][0-9]{1,2})?)$/i', trim($val), $m)) {
            return $this->cleanPrice($m[1]);
        }

        return null;
    }

    /**
     * Convierte letras de columna de Excel a índice 0-based (A=0, B=1, Z=25, AA=26).
     */
    protected function columnLetterToIndex(string $col): int
    {
        $col = strtoupper($col);
        $len = strlen($col);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index *= 26;
            $index += (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
