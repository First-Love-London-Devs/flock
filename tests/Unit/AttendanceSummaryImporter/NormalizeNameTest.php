<?php

namespace Tests\Unit\AttendanceSummaryImporter;

use App\Filament\Imports\AttendanceSummaryImporter;
use Tests\TestCase;

class NormalizeNameTest extends TestCase
{
    /** @dataProvider equivalentNamesProvider */
    public function test_equivalent_names_normalize_to_the_same_value(string $a, string $b): void
    {
        $this->assertSame(
            AttendanceSummaryImporter::normalizeName($a),
            AttendanceSummaryImporter::normalizeName($b),
        );
    }

    public static function equivalentNamesProvider(): array
    {
        return [
            'trailing whitespace' => ['Fruitfulness 1', 'Fruitfulness 1   '],
            'leading whitespace' => ['Fruitfulness 1', '   Fruitfulness 1'],
            'collapsed whitespace' => ['Fruitfulness 1', 'Fruitfulness   1'],
            'case' => ['Fruitfulness 1', 'fruitfulness 1'],
            'mixed case' => ['Fruitfulness 1', 'FRUITFULNESS 1'],
            'curly apostrophe' => ["God's Presence 1", "God\u{2019}s Presence 1"],
            'curly quotes' => ['Name "quoted" 1', "Name \u{201C}quoted\u{201D} 1"],
        ];
    }

    public function test_different_names_normalize_differently(): void
    {
        $this->assertNotSame(
            AttendanceSummaryImporter::normalizeName('Fruitfulness 1'),
            AttendanceSummaryImporter::normalizeName('Fruitfulness 2'),
        );
    }
}
