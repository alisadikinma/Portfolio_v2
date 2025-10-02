<?php

namespace App\Filament\Admin\Resources\Awards\Pages;

use App\Filament\Admin\Resources\Awards\AwardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAward extends EditRecord
{
    protected static string $resource = AwardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
