<?php

namespace App\Filament\Admin\Resources\Newsletters\Pages;

use App\Filament\Admin\Resources\Newsletters\NewsletterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;
}
