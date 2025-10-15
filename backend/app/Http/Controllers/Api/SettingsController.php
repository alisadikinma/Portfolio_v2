<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAboutSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Get about settings.
     */
    public function getAboutSettings(): JsonResponse
    {
        try {
            $settings = Setting::byGroup('about')->get();

            $aboutData = [];
            foreach ($settings as $setting) {
                $value = $setting->value;

                // Decode JSON values
                if ($setting->type === 'json' || $setting->type === 'array') {
                    $value = json_decode($value, true);
                }

                $aboutData[$setting->key] = $value;
            }

            // Ensure default structure
            $aboutData = array_merge([
                'name' => '',
                'title' => '',
                'bio' => '',
                'profile_photo' => null,
                'skills' => [],
                'experience' => [],
                'education' => [],
                'social_links' => []
            ], $aboutData);

            return response()->json([
                'success' => true,
                'data' => $aboutData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch about settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch about settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update about settings.
     */
    public function updateAboutSettings(UpdateAboutSettingsRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            // Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = time() . '_' . $file->getClientOriginalName();

                // Create directory if it doesn't exist
                $uploadPath = public_path('uploads/about');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $file->move($uploadPath, $filename);
                $validated['profile_photo'] = '/uploads/about/' . $filename;

                // Delete old profile photo
                $oldPhoto = Setting::where('key', 'profile_photo')->where('group', 'about')->first();
                if ($oldPhoto && $oldPhoto->value && file_exists(public_path($oldPhoto->value))) {
                    unlink(public_path($oldPhoto->value));
                }
            } else {
                // Keep existing profile photo
                unset($validated['profile_photo']);
            }

            // Decode JSON strings from FormData
            foreach (['skills', 'experience', 'education', 'social_links'] as $jsonField) {
                if (isset($validated[$jsonField]) && is_string($validated[$jsonField])) {
                    $validated[$jsonField] = json_decode($validated[$jsonField], true);
                }
            }

            // Save each setting
            foreach ($validated as $key => $value) {
                $type = 'text';

                // Determine type
                if (in_array($key, ['skills', 'experience', 'education', 'social_links'])) {
                    $type = 'json';
                    $value = json_encode($value);
                } elseif ($key === 'profile_photo') {
                    $type = 'image';
                }

                Setting::updateOrCreate(
                    [
                        'key' => $key,
                        'group' => 'about'
                    ],
                    [
                        'value' => $value,
                        'type' => $type
                    ]
                );
            }

            DB::commit();

            // Fetch updated settings
            $settings = Setting::byGroup('about')->get();
            $aboutData = [];
            foreach ($settings as $setting) {
                $value = $setting->value;

                if ($setting->type === 'json') {
                    $value = json_decode($value, true);
                }

                $aboutData[$setting->key] = $value;
            }

            return response()->json([
                'success' => true,
                'message' => 'About settings updated successfully',
                'data' => $aboutData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update about settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update about settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get site settings.
     */
    public function getSiteSettings(): JsonResponse
    {
        try {
            $settings = Setting::byGroup('site')->get();

            $siteData = [];
            foreach ($settings as $setting) {
                $value = $setting->value;

                // Decode JSON values
                if ($setting->type === 'json' || $setting->type === 'array') {
                    $value = json_decode($value, true);
                }

                $siteData[$setting->key] = $value;
            }

            // Ensure default structure
            $siteData = array_merge([
                'site_name' => '',
                'site_description' => '',
                'site_logo' => null,
                'contact_email' => '',
                'contact_phone' => '',
                'social_media' => [],
                'meta_tags' => [],
                'analytics_code' => ''
            ], $siteData);

            return response()->json([
                'success' => true,
                'data' => $siteData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch site settings', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch site settings'
            ], 500);
        }
    }
}
