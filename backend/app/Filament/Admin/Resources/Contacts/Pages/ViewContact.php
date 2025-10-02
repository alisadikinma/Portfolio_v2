<?php

namespace App\Filament\Admin\Resources\Contacts\Pages;

use App\Filament\Admin\Resources\Contacts\ContactResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
