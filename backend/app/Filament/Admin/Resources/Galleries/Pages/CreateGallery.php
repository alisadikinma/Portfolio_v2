<?php

namespace App\Filament\Admin\Resources\Galleries\Pages;

use App\Filament\Admin\Resources\Galleries\GalleryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;
}
