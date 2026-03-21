<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'church_name', 'value' => 'My Church', 'type' => 'string', 'description' => 'Church display name'],
            ['key' => 'church_tagline', 'value' => '', 'type' => 'string', 'description' => 'Church tagline'],
            ['key' => 'color_primary', 'value' => '#4f46e5', 'type' => 'string', 'description' => 'Primary brand color'],
            ['key' => 'color_secondary', 'value' => '#7c3aed', 'type' => 'string', 'description' => 'Secondary brand color'],
            ['key' => 'font', 'value' => 'Inter', 'type' => 'string', 'description' => 'UI font family'],
            ['key' => 'dark_mode', 'value' => 'true', 'type' => 'boolean', 'description' => 'Enable dark mode toggle'],
            ['key' => 'attendance_day', 'value' => '0', 'type' => 'integer', 'description' => 'Default attendance day (0=Sunday)'],
            ['key' => 'timezone', 'value' => 'Europe/London', 'type' => 'string', 'description' => 'Church timezone'],
            ['key' => 'modules.children', 'value' => 'true', 'type' => 'boolean', 'description' => 'Enable children ministry module'],
            ['key' => 'modules.training', 'value' => 'true', 'type' => 'boolean', 'description' => 'Enable training/courses module'],
            ['key' => 'modules.equipment', 'value' => 'false', 'type' => 'boolean', 'description' => 'Enable equipment booking module'],
            ['key' => 'modules.follow_up', 'value' => 'true', 'type' => 'boolean', 'description' => 'Enable first-timer follow-up module'],
            ['key' => 'modules.ai_assistant', 'value' => 'false', 'type' => 'boolean', 'description' => 'Enable AI assistant module'],
            ['key' => 'member_additional_fields', 'value' => json_encode([
                ['key' => 'gender', 'label' => 'Gender', 'type' => 'select', 'options' => ['Male', 'Female', 'Other']],
                ['key' => 'benmp_partner', 'label' => 'BENMP Partner', 'type' => 'toggle'],
                ['key' => 'born_again', 'label' => 'Are they Born Again?', 'type' => 'toggle'],
                ['key' => 'schools_completed', 'label' => 'Schools Completed (Strong Christian & Lay Schools)', 'type' => 'text'],
            ]), 'type' => 'json', 'description' => 'Configurable additional fields for member profiles'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
