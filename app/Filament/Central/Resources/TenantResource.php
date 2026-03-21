<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Church Details')
                    ->schema([
                        Forms\Components\TextInput::make('church_name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state, Forms\Set $set, string $operation) {
                                if ($operation === 'create') {
                                    $set('id', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('id')
                            ->label('Tenant ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(),
                        Forms\Components\TextInput::make('contact_email')
                            ->email(),
                        Forms\Components\TextInput::make('contact_phone'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Plan & Status')
                    ->schema([
                        Forms\Components\Select::make('plan')
                            ->options([
                                'free' => 'Free',
                                'starter' => 'Starter',
                                'pro' => 'Pro',
                            ])
                            ->default('free'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends At'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Domain')
                    ->schema([
                        Forms\Components\Repeater::make('domains')
                            ->relationship()
                            ->schema([
                                Forms\Components\TextInput::make('domain')
                                    ->required()
                                    ->suffix('.poimen.co.uk')
                                    ->helperText('Enter subdomain only (e.g. "gochurch")'),
                            ])
                            ->minItems(1)
                            ->maxItems(3)
                            ->defaultItems(1),
                    ])
                    ->visibleOn('create'),

                Forms\Components\Section::make('Branding')
                    ->schema([
                        Forms\Components\TextInput::make('branding_church_name')
                            ->label('Display Name')
                            ->helperText('Shown in the admin panel header and mobile app. Defaults to church name above.'),
                        Forms\Components\TextInput::make('branding_tagline')
                            ->label('Tagline'),
                        Forms\Components\ColorPicker::make('branding_color_primary')
                            ->label('Primary Colour'),
                        Forms\Components\ColorPicker::make('branding_color_secondary')
                            ->label('Secondary Colour'),
                        Forms\Components\FileUpload::make('branding_logo')
                            ->label('Logo (Light Mode)')
                            ->image()
                            ->directory('branding')
                            ->disk('public')
                            ->helperText('PNG with transparent background recommended.'),
                        Forms\Components\FileUpload::make('branding_logo_dark')
                            ->label('Logo (Dark Mode)')
                            ->image()
                            ->directory('branding')
                            ->disk('public')
                            ->helperText('Leave empty to use the light mode logo.'),
                    ])
                    ->columns(2)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('church_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domains.domain')
                    ->label('Domain')
                    ->badge(),
                Tables\Columns\TextColumn::make('contact_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'free' => 'gray',
                        'starter' => 'info',
                        'pro' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')
                    ->options([
                        'free' => 'Free',
                        'starter' => 'Starter',
                        'pro' => 'Pro',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => $record->is_active)
                    ->action(fn (Tenant $record) => $record->suspend()),
                Tables\Actions\Action::make('activate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record) => !$record->is_active)
                    ->action(fn (Tenant $record) => $record->activate()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
