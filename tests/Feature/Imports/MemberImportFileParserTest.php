<?php

namespace Tests\Feature\Imports;

use App\Support\MemberImportFileParser;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class MemberImportFileParserTest extends TestCase
{
    public function test_canonical_header_maps_human_headers_to_importer_columns(): void
    {
        $this->assertSame('first_name', MemberImportFileParser::canonicalHeader('First Name'));
        $this->assertSame('first_name', MemberImportFileParser::canonicalHeader('first_name'));
        $this->assertSame('last_name', MemberImportFileParser::canonicalHeader('Surname'));
        $this->assertSame('group', MemberImportFileParser::canonicalHeader('Group Name'));
        $this->assertSame('phone_number', MemberImportFileParser::canonicalHeader('Mobile'));
        $this->assertSame('email', MemberImportFileParser::canonicalHeader('E-mail'));
    }

    public function test_it_parses_csv_with_human_headers(): void
    {
        $path = sys_get_temp_dir() . '/imp_' . uniqid() . '.csv';
        file_put_contents($path, "First Name,Last Name,Email,Group Name\nAda,Lovelace,ada@example.com,Tienen\n");

        [$headers, $rows] = app(MemberImportFileParser::class)->parse($path);
        @unlink($path);

        $this->assertSame(['first_name', 'last_name', 'email', 'group'], $headers);
        $this->assertCount(1, $rows);
        $this->assertSame('Ada', $rows[0]['first_name']);
        $this->assertSame('Tienen', $rows[0]['group']);
    }

    public function test_it_parses_xlsx_detected_by_content(): void
    {
        $path = sys_get_temp_dir() . '/imp_' . uniqid() . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['First Name', 'Last Name', 'Email', 'Group Name']));
        $writer->addRow(Row::fromValues(['Grace', 'Hopper', 'grace@example.com', 'Leuven']));
        $writer->close();

        [$headers, $rows] = app(MemberImportFileParser::class)->parse($path);
        @unlink($path);

        $this->assertSame(['first_name', 'last_name', 'email', 'group'], $headers);
        $this->assertCount(1, $rows);
        $this->assertSame('grace@example.com', $rows[0]['email']);
        $this->assertSame('Leuven', $rows[0]['group']);
    }
}
