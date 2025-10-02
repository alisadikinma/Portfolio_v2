<?php

namespace App\Filament\Admin\Resources\Newsletters\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class NewsletterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Toggle::make('is_subscribed')
                    ->required(),
                DateTimePicker::make('subscribed_at'),
                DateTimePicker::make('unsubscribed_at'),
            ]);
    }
}
