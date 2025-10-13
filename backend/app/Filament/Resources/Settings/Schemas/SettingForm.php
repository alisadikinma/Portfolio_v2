<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Unique identifier for this setting'),
                Textarea::make('value')
                    ->default(null)
                    ->columnSpanFull()
                    ->rows(5),
                Select::make('group')
                    ->required()
                    ->options([
                        'general' => 'General',
                        'profile' => 'Profile',
                        'about' => 'About',
                        'hero' => 'Hero',
                    ])
                    ->default('general'),
                Select::make('type')
                    ->required()
                    ->options([
                        'text' => 'Text',
                        'textarea' => 'Textarea',
                        'image' => 'Image',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ])
                    ->default('text'),
            ]);
    }
}
