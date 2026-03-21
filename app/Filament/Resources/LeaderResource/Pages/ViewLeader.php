<?php

namespace App\Filament\Resources\LeaderResource\Pages;

use App\Filament\Resources\LeaderResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewLeader extends ViewRecord
{
    protected static string $resource = LeaderResource::class;

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
                Infolists\Components\Section::make('Leader Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('member.full_name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('username'),
                        Infolists\Components\TextEntry::make('member.phone_number')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('member.email')
                            ->label('Email'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Leader Since')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Roles')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('leaderRoles')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('roleDefinition.name')
                                    ->label('Role')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('group.name')
                                    ->label('Group')
                                    ->default('—'),
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),
                                Infolists\Components\TextEntry::make('assigned_at')
                                    ->label('Assigned')
                                    ->dateTime(),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
