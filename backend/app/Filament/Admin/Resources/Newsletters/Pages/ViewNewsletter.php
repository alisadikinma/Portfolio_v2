<?php

namespace App\Filament\Admin\Resources\Newsletters\Pages;

use App\Filament\Admin\Resources\Newsletters\NewsletterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsletter extends ViewRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
