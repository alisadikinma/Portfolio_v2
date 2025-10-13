<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AwardGallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if awards and galleries exist before seeding
        $awards = DB::table('awards')->select('id')->get();
        $galleries = DB::table('galleries')->select('id')->get();

        if ($awards->isEmpty() || $galleries->isEmpty()) {
            $this->command->warn('⚠️  Awards or Galleries table is empty. Skipping AwardGallerySeeder.');
            return;
        }

        // Sample award-gallery relationships
        $awardGalleryData = [];

        // Link first award to first 3 galleries (if they exist)
        if ($awards->count() >= 1 && $galleries->count() >= 3) {
            $awardId = $awards[0]->id;
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[0]->id,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[1]->id,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[2]->id,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Link second award to 2 galleries (if they exist)
        if ($awards->count() >= 2 && $galleries->count() >= 5) {
            $awardId = $awards[1]->id;
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[3]->id,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[4]->id,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Link third award to 3 galleries (if they exist)
        if ($awards->count() >= 3 && $galleries->count() >= 3) {
            $awardId = $awards[2]->id;
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[0]->id,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $awardGalleryData[] = [
                'award_id' => $awardId,
                'gallery_id' => $galleries[1]->id,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($galleries->count() >= 6) {
                $awardGalleryData[] = [
                    'award_id' => $awardId,
                    'gallery_id' => $galleries[5]->id,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($awardGalleryData)) {
            DB::table('award_gallery')->insert($awardGalleryData);
            $this->command->info('✅ Successfully seeded ' . count($awardGalleryData) . ' award-gallery relationships.');
        } else {
            $this->command->warn('⚠️  No award-gallery relationships created. Check if awards and galleries exist.');
        }
    }
}
