<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MemberFields extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Member Fields';
    protected static ?string $title = 'Additional Member Fields';
    protected static string $view = 'filament.pages.member-fields';

    public ?array $fields = [];

    public function mount(): void
    {
        $this->fields = Setting::get('member_additional_fields', []) ?? [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('fields')
                    ->label('Additional Fields')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Field Key')
                            ->required()
                            ->alphaDash()
                            ->helperText('Unique identifier (e.g. "born_again")'),
                        Forms\Components\TextInput::make('label')
                            ->label('Display Label')
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->label('Field Type')
                            ->options([
                                'text' => 'Text Input',
                                'textarea' => 'Text Area',
                                'toggle' => 'Yes/No Toggle',
                                'select' => 'Dropdown Select',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TagsInput::make('options')
                            ->label('Options')
                            ->helperText('Add options for the dropdown')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'select'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->defaultItems(0),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('member_additional_fields', $data['fields'] ?? [], 'json');

        Notification::make()
            ->title('Member fields updated')
            ->success()
            ->send();
    }
}
