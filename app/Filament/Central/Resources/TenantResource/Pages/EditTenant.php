<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Models\Setting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->run(function () use (&$data) {
            $data['branding_church_name'] = Setting::get('church_name', '');
            $data['branding_tagline'] = Setting::get('church_tagline', '');
            $data['branding_color_primary'] = Setting::get('color_primary', '#4f46e5');
            $data['branding_color_secondary'] = Setting::get('color_secondary', '#7c3aed');
            $data['branding_logo'] = Setting::get('church_logo', '');
            $data['branding_logo_dark'] = Setting::get('church_logo_dark', '');
        });

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->record->run(function () use ($data) {
            if (isset($data['branding_church_name'])) {
                Setting::set('church_name', $data['branding_church_name']);
            }
            if (isset($data['branding_tagline'])) {
                Setting::set('church_tagline', $data['branding_tagline']);
            }
            if (isset($data['branding_color_primary'])) {
                Setting::set('color_primary', $data['branding_color_primary']);
            }
            if (isset($data['branding_color_secondary'])) {
                Setting::set('color_secondary', $data['branding_color_secondary']);
            }

            $logo = $data['branding_logo'] ?? '';
            $logoDark = $data['branding_logo_dark'] ?? '';
            Setting::set('church_logo', $logo ? '/storage/' . $logo : '');
            Setting::set('church_logo_dark', $logoDark ? '/storage/' . $logoDark : '');
        });

        // Remove branding fields so they don't try to save on the tenants table
        unset(
            $data['branding_church_name'],
            $data['branding_tagline'],
            $data['branding_color_primary'],
            $data['branding_color_secondary'],
            $data['branding_logo'],
            $data['branding_logo_dark'],
        );

        return $data;
    }
}
