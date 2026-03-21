<?php

namespace App\Filament\Resources\GroupResource\Pages;

use App\Filament\Resources\GroupResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Group Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('groupType.name')
                            ->label('Type'),
                        Infolists\Components\TextEntry::make('parent.name')
                            ->label('Parent Group')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('leader.member.full_name')
                            ->label('Leader')
                            ->default('—'),
                        Infolists\Components\TextEntry::make('total_members_count')
                            ->label('Total Members')
                            ->getStateUsing(fn ($record) => $record->total_members_count),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ])
                    ->columns(3),
            ]);
    }
}
