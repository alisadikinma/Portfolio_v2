<?php

namespace App\Filament\Admin\Resources\Projects\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('content')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->image(),
                Textarea::make('images')
                    ->columnSpanFull(),
                TextInput::make('category')
                    ->required(),
                Textarea::make('technologies')
                    ->columnSpanFull(),
                TextInput::make('client'),
                TextInput::make('url')
                    ->url(),
                DatePicker::make('completed_at'),
                Toggle::make('featured')
                    ->required(),
                Toggle::make('published')
                    ->required(),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
