<?php

namespace App\Filament\Admin\Resources\Posts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('category_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('excerpt')
                    ->columnSpanFull(),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('featured_image')
                    ->image(),
                Textarea::make('tags')
                    ->columnSpanFull(),
                Toggle::make('is_premium')
                    ->required(),
                Toggle::make('published')
                    ->required(),
                DateTimePicker::make('published_at'),
                TextInput::make('views')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reading_time')
                    ->numeric(),
            ]);
    }
}
