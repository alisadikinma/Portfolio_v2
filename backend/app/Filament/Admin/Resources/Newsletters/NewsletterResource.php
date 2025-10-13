<?php

namespace App\Filament\Admin\Resources\Newsletters;

use App\Filament\Admin\Resources\Newsletters\Pages\CreateNewsletter;
use App\Filament\Admin\Resources\Newsletters\Pages\EditNewsletter;
use App\Filament\Admin\Resources\Newsletters\Pages\ListNewsletters;
use App\Filament\Admin\Resources\Newsletters\Pages\ViewNewsletter;
use App\Filament\Admin\Resources\Newsletters\Schemas\NewsletterForm;
use App\Filament\Admin\Resources\Newsletters\Schemas\NewsletterInfolist;
use App\Filament\Admin\Resources\Newsletters\Tables\NewslettersTable;
use App\Models\Newsletter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    
    protected static ?string $navigationLabel = 'Newsletters';
    
    protected static ?string $pluralModelLabel = 'Newsletters';
    
    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return NewsletterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewslettersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletters::route('/'),
            'create' => CreateNewsletter::route('/create'),
            'view' => ViewNewsletter::route('/{record}'),
            'edit' => EditNewsletter::route('/{record}/edit'),
        ];
    }
}
