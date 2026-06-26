<?php

namespace App\Support;

use App\Services\MemberImportSanityChecker;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a member-import file (CSV or XLSX) into header-mapped rows.
 *
 * Headers are canonicalised to the snake_case keys MemberImporter expects, so
 * a sheet with human headers ("First Name", "Group Name") works the same as a
 * ready-made CSV. The file type is detected by content (xlsx is a zip), not by
 * the extension, so it is robust to however the upload was named.
 */
class MemberImportFileParser
{
    /** Common human header spellings → the importer's canonical column. */
    public const HEADER_ALIASES = [
        'first name' => 'first_name', 'firstname' => 'first_name', 'first' => 'first_name', 'forename' => 'first_name',
        'last name' => 'last_name', 'lastname' => 'last_name', 'surname' => 'last_name', 'last' => 'last_name',
        'email address' => 'email', 'e-mail' => 'email', 'mail' => 'email',
        'phone' => 'phone_number', 'phone no' => 'phone_number', 'mobile' => 'phone_number',
        'telephone' => 'phone_number', 'contact' => 'phone_number', 'contact number' => 'phone_number', 'tel' => 'phone_number',
        'dob' => 'date_of_birth', 'date of birth' => 'date_of_birth', 'birthday' => 'date_of_birth', 'birth date' => 'date_of_birth',
        'sex' => 'gender',
        'occupation / school' => 'occupation', 'school' => 'occupation', 'job' => 'occupation', 'profession' => 'occupation',
        'marital status' => 'marital_status',
        'nbs status' => 'nbs_status', 'nbs' => 'nbs_status',
        'holy ghost baptism' => 'holy_ghost_baptism', 'holy spirit baptism' => 'holy_ghost_baptism', 'hgb' => 'holy_ghost_baptism',
        'water baptism' => 'water_baptism', 'baptised' => 'water_baptism', 'baptized' => 'water_baptism',
        'member type' => 'member_type', 'type of member' => 'member_type', 'type' => 'member_type',
        'member since' => 'member_since', 'date joined' => 'member_since', 'joined' => 'member_since',
        'note' => 'notes', 'comment' => 'notes', 'comments' => 'notes', 'remarks' => 'notes',
        'group name' => 'group', 'group (bacenta)' => 'group', 'bacenta' => 'group', 'cell' => 'group', 'cell group' => 'group',
    ];

    /**
     * @return array{0: array<int,string>, 1: array<int, array<string,string>>}
     */
    public function parse(string $path): array
    {
        return $this->isXlsx($path) ? $this->parseXlsx($path) : $this->parseCsv($path);
    }

    public static function canonicalHeader(string $raw): string
    {
        $h = preg_replace('/\s+/', ' ', mb_strtolower(trim($raw)));
        $h = (string) preg_replace('/^\xEF\xBB\xBF/', '', $h); // strip UTF-8 BOM

        $underscored = str_replace(' ', '_', $h);
        if (in_array($underscored, MemberImportSanityChecker::EXPECTED_HEADERS, true)) {
            return $underscored;
        }
        if (isset(self::HEADER_ALIASES[$h])) {
            return self::HEADER_ALIASES[$h];
        }
        $spaced = str_replace('_', ' ', $h);
        if (isset(self::HEADER_ALIASES[$spaced])) {
            return self::HEADER_ALIASES[$spaced];
        }

        return $underscored;
    }

    private function isXlsx(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $magic = (string) fread($handle, 4);
        fclose($handle);

        return str_starts_with($magic, "PK\x03\x04"); // xlsx is a zip archive
    }

    /**
     * @return array{0: array<int,string>, 1: array<int, array<string,string>>}
     */
    private function parseCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [[], []];
        }

        $headers = [];
        $rows = [];
        $isHeader = true;

        while (($cols = fgetcsv($handle)) !== false) {
            $cols = array_map(fn ($c) => (string) $c, $cols);
            if ($isHeader) {
                $headers = array_map(fn ($h) => self::canonicalHeader($h), $cols);
                $isHeader = false;

                continue;
            }
            if ($this->isBlankRow($cols)) {
                continue;
            }
            $rows[] = $this->mapRow($headers, $cols);
        }

        fclose($handle);

        return [$headers, $rows];
    }

    /**
     * @return array{0: array<int,string>, 1: array<int, array<string,string>>}
     */
    private function parseXlsx(string $path): array
    {
        $headers = [];
        $rows = [];

        $reader = new XlsxReader();
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $isHeader = true;
            foreach ($sheet->getRowIterator() as $row) {
                $cols = array_map(fn ($v) => $this->stringify($v), $row->toArray());
                if ($isHeader) {
                    $headers = array_map(fn ($h) => self::canonicalHeader($h), $cols);
                    $isHeader = false;

                    continue;
                }
                if ($this->isBlankRow($cols)) {
                    continue;
                }
                $rows[] = $this->mapRow($headers, $cols);
            }

            break; // only the first sheet
        }

        $reader->close();

        return [$headers, $rows];
    }

    /**
     * @param  array<int,string>  $headers
     * @param  array<int,string>  $cols
     * @return array<string,string>
     */
    private function mapRow(array $headers, array $cols): array
    {
        $row = [];
        foreach ($headers as $idx => $header) {
            $row[$header] = trim((string) ($cols[$idx] ?? ''));
        }

        return $row;
    }

    /**
     * @param  array<int,string>  $cols
     */
    private function isBlankRow(array $cols): bool
    {
        return count(array_filter($cols, fn ($c) => trim((string) $c) !== '')) === 0;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_float($value)) {
            return $value == floor($value) ? (string) (int) $value : (string) $value;
        }

        return (string) $value;
    }
}
