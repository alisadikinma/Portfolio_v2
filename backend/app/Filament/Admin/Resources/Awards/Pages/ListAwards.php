<?php

namespace App\Filament\Admin\Resources\Awards\Pages;

use App\Filament\Admin\Resources\Awards\AwardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAwards extends ListRecords
{
    protected static string $resource = AwardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
