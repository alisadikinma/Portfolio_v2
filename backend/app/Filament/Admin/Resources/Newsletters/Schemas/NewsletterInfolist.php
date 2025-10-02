<?php

namespace App\Filament\Admin\Resources\Newsletters\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class NewsletterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('email')
                    ->label('Email address'),
                IconEntry::make('is_subscribed')
                    ->boolean(),
                TextEntry::make('subscribed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('unsubscribed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
