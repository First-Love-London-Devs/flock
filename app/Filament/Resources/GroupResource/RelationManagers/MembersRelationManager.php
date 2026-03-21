<?php

namespace App\Filament\Resources\GroupResource\RelationManagers;

use App\Models\Member;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';
    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $group = $this->getOwnerRecord();

                return Member::query()
                    ->whereHas('groups', fn ($q) => $q->whereIn('groups.id', $group->allGroupIds()));
            })
            ->columns([
                Tables\Columns\ImageColumn::make('picture')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => $record->avatar_url),
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone_number'),
                Tables\Columns\TextColumn::make('member_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ]);
    }
}
