<?php

use App\Models\RoleDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RoleDefinition::firstOrCreate(
            ['slug' => 'understanding-campaign'],
            [
                'name' => 'Understanding Campaign',
                'permission_level' => 40,
                'applies_to_group_type_id' => null,
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        RoleDefinition::where('slug', 'understanding-campaign')->delete();
    }
};
