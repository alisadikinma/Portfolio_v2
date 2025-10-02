<?php

namespace App\Filament\Admin\Resources\Contacts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('subject'),
                Textarea::make('message')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_read')
                    ->required(),
                DateTimePicker::make('read_at'),
            ]);
    }
}
