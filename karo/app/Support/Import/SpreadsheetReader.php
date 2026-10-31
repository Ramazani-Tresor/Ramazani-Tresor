<?php

namespace App\Support\Import;

use App\Exceptions\BusinessException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lecture des fichiers importés comme DONNÉES NON FIABLES : seules les valeurs des cellules sont lues
 * (aucune formule évaluée, aucune macro, aucun script exécuté). Les cellules commençant par =, +, -, @
 * sont neutralisées à l'export pour éviter l'injection de formules dans Excel.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 20000;

    /** @return array<int, array<int, string>> première feuille, valeurs texte */
    public static function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if ($extension === 'xlsx') {
            $reader = new XlsxReader;
        } elseif (in_array($extension, ['csv', 'txt'], true)) {
            $handle = fopen($path, 'r');
            $first = (string) fgets($handle);
            fclose($handle);
            $options = new CsvOptions;
            $options->FIELD_DELIMITER = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
            $reader = new CsvReader($options);
        } else {
            throw new BusinessException('Format non pris en charge : utilisez un fichier .xlsx ou .csv.');
        }
        $rows = [];
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(function ($c) {
                    if ($c instanceof \DateTimeInterface) {
                        return $c->format('Y-m-d');
                    }

                    return trim(str_replace("\u{FEFF}", '', (string) $c));
                }, $row->toArray());
                if (count($rows) > self::MAX_ROWS + 1) {
                    $reader->close();
                    throw new BusinessException('Fichier trop volumineux : '.self::MAX_ROWS.' lignes maximum par import.');
                }
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    public static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));
        $h = strtr($h, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ç' => 'c', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u']);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $h), '_');
    }

    /** Valeur sûre pour un tableur (anti-injection de formule). */
    public static function safeCell(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && ! is_numeric($v)) {
            return "'".$v;
        }

        return $v;
    }

    /** Date au format AAAA-MM-JJ, JJ/MM/AAAA ou JJ-MM-AAAA ; null si non interprétable. */
    public static function parseDate(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})$/', $v, $m)) {
            $y = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
            }
        }
        if (is_numeric($v) && (int) $v > 20000 && (int) $v < 80000) { // numéro de série Excel
            return date('Y-m-d', ((int) $v - 25569) * 86400);
        }

        return null;
    }

    /** Montant « 1 250,50 » ou « 1,250.50 » → « 1250.50 » ; null si invalide. */
    public static function parseAmount(?string $v): ?string
    {
        $v = trim(str_replace(["\u{00A0}", "\u{202F}", ' '], '', (string) $v));
        if ($v === '') {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $v)) {
            $v = str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $v) ? $v : null;
    }
}
