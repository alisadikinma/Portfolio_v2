<?php

namespace App\Filament\Admin\Resources\Awards\Pages;

use App\Filament\Admin\Resources\Awards\AwardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAward extends CreateRecord
{
    protected static string $resource = AwardResource::class;
}
