<?php

namespace Database\Seeders;

use App\Models\GroupType;
use Illuminate\Database\Seeder;

class DefaultGroupTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Zone', 'slug' => 'zone', 'level' => 0, 'tracks_attendance' => false, 'icon' => 'heroicon-o-globe-alt', 'color' => '#6366f1'],
            ['name' => 'District', 'slug' => 'district', 'level' => 1, 'tracks_attendance' => false, 'icon' => 'heroicon-o-building-office', 'color' => '#8b5cf6'],
            ['name' => 'Cell Group', 'slug' => 'cell-group', 'level' => 2, 'tracks_attendance' => true, 'icon' => 'heroicon-o-user-group', 'color' => '#10b981'],
        ];

        foreach ($types as $type) {
            GroupType::firstOrCreate(['slug' => $type['slug']], $type);
        }
    }
}
