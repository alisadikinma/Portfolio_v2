<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAboutSettingsRequest;
use App\Http\Requests\UpdateSiteSettingsRequest;
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
                // Hero & About Page Enhancement
                'hero_tagline' => '',
                'availability_note' => '',
                'mission' => '',
                'approach' => '',
                'trust_strip' => [],
                'what_i_do' => [],
                // Other fields
                'languages' => [],
                'skills' => [],
                'experience' => [],
                'education' => [],
                'social_links' => [],
                'certifications' => [],
                'statistics' => [
                    'years_experience' => '16+',
                    'followers' => '1K',
                    'projects_delivered' => '50+',
                    'cost_savings' => '$2M+',
                    'success_rate' => '95%'
                ]
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
            Log::info('🔵 updateAboutSettings - Request method:', [
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'has_files' => $request->hasFile('profile_photo'),
                'all_keys' => array_keys($request->all())
            ]);
            
            $validated = $request->validated();
            
            // DEBUG: Log certifications BEFORE processing
            Log::info('🎓 CERTIFICATIONS BEFORE PROCESSING:', [
                'exists' => isset($validated['certifications']),
                'is_array' => isset($validated['certifications']) && is_array($validated['certifications']),
                'count' => isset($validated['certifications']) ? count($validated['certifications']) : 0,
                'data' => $validated['certifications'] ?? null
            ]);
            
            Log::info('📝 About settings update started', [
                'validated_keys' => array_keys($validated),
                'has_photo' => $request->hasFile('profile_photo')
            ]);

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
            foreach (['languages', 'skills', 'experience', 'education', 'social_links', 'statistics', 'certifications', 'trust_strip', 'what_i_do'] as $jsonField) {
                if (isset($validated[$jsonField]) && is_string($validated[$jsonField])) {
                    $validated[$jsonField] = json_decode($validated[$jsonField], true);
                }
            }

            // Handle certification logo uploads
            if (isset($validated['certifications']) && is_array($validated['certifications'])) {
                Log::info('🎓 Processing certification logos...');
                
                $uploadPath = public_path('uploads/certifications');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                    Log::info('📁 Created certifications upload directory');
                }

                foreach ($validated['certifications'] as $index => $cert) {
                    $fileKey = "certification_logo_{$index}";
                    
                    Log::info("🔍 Checking cert #{$index}:", [
                        'fileKey' => $fileKey,
                        'hasFile' => $request->hasFile($fileKey),
                        'cert_name' => $cert['name'] ?? 'N/A',
                        'existing_logo' => $cert['logo'] ?? null
                    ]);
                    
                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);
                        $filename = time() . "_{$index}_" . $file->getClientOriginalName();
                        $file->move($uploadPath, $filename);
                        $validated['certifications'][$index]['logo'] = '/uploads/certifications/' . $filename;
                        
                        Log::info("✅ Logo uploaded for cert #{$index}:", [
                            'filename' => $filename,
                            'path' => $validated['certifications'][$index]['logo']
                        ]);

                        // Delete old logo if exists
                        if (!empty($cert['logo']) && file_exists(public_path($cert['logo']))) {
                            unlink(public_path($cert['logo']));
                            Log::info("🗑️ Deleted old logo: {$cert['logo']}");
                        }
                    } else {
                        Log::info("ℹ️ No new logo for cert #{$index}, keeping existing");
                    }
                }
                
                Log::info('🎓 CERTIFICATIONS AFTER PROCESSING:', [
                    'data' => $validated['certifications']
                ]);
            } else {
                Log::warning('⚠️ Certifications not found or not an array in validated data');
            }

            // Save each setting
            foreach ($validated as $key => $value) {
                $type = 'text';

                // Determine type
                if (in_array($key, ['languages', 'skills', 'experience', 'education', 'social_links', 'statistics', 'certifications', 'trust_strip', 'what_i_do'])) {
                    $type = 'json';
                    $value = json_encode($value);
                } elseif ($key === 'profile_photo') {
                    $type = 'image';
                } elseif (in_array($key, ['mission', 'approach', 'bio'])) {
                    $type = 'textarea';
                }

                Log::info('💾 Saving setting', [
                    'key' => $key,
                    'type' => $type,
                    'value_length' => strlen($value),
                    'value_preview' => $key === 'certifications' ? $value : (strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value)
                ]);

                $setting = Setting::updateOrCreate(
                    [
                        'key' => $key,
                        'group' => 'about'
                    ],
                    [
                        'value' => $value,
                        'type' => $type
                    ]
                );
                
                Log::info('✅ Setting saved', [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'updated' => $setting->wasRecentlyCreated ? 'created' : 'updated'
                ]);
            }

            DB::commit();
            
            Log::info('✅ About settings update completed successfully');

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
            Log::info('🔍 getSiteSettings - Fetching settings from database');
            
            $settings = Setting::byGroup('site')->get();
            
            Log::info('📥 Found settings:', [
                'count' => $settings->count(),
                'keys' => $settings->pluck('key')->toArray()
            ]);

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
                'location' => '',
                'social_media' => [],
                'meta_tags' => [],
                'analytics_code' => ''
            ], $siteData);
            
            Log::info('✅ Site settings fetched successfully', [
                'keys' => array_keys($siteData)
            ]);

            return response()->json([
                'success' => true,
                'data' => $siteData
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to fetch site settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch site settings'
            ], 500);
        }
    }

    /**
     * Update site settings.
     */
    public function updateSiteSettings(UpdateSiteSettingsRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            Log::info('🔵 updateSiteSettings - Request method:', [
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'has_files' => $request->hasFile('site_logo'),
                'all_keys' => array_keys($request->all())
            ]);
            
            $validated = $request->validated();
            
            Log::info('📝 Site settings update started', [
                'validated_keys' => array_keys($validated),
                'has_logo' => $request->hasFile('site_logo')
            ]);

            // Handle site logo upload
            if ($request->hasFile('site_logo')) {
                $file = $request->file('site_logo');
                $filename = time() . '_' . $file->getClientOriginalName();

                // Create directory if it doesn't exist
                $uploadPath = public_path('uploads/site');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                $file->move($uploadPath, $filename);
                $validated['site_logo'] = '/uploads/site/' . $filename;

                // Delete old site logo
                $oldLogo = Setting::where('key', 'site_logo')->where('group', 'site')->first();
                if ($oldLogo && $oldLogo->value && file_exists(public_path($oldLogo->value))) {
                    unlink(public_path($oldLogo->value));
                }
            } else {
                // Keep existing site logo
                unset($validated['site_logo']);
            }

            // Decode JSON strings from FormData
            foreach (['social_media', 'meta_tags'] as $jsonField) {
                if (isset($validated[$jsonField]) && is_string($validated[$jsonField])) {
                    $validated[$jsonField] = json_decode($validated[$jsonField], true);
                }
            }

            // Save each setting
            foreach ($validated as $key => $value) {
                $type = 'text';

                // Determine type
                if (in_array($key, ['social_media', 'meta_tags'])) {
                    $type = 'json';
                    $value = json_encode($value);
                } elseif ($key === 'site_logo') {
                    $type = 'image';
                } elseif ($key === 'analytics_code') {
                    $type = 'textarea';
                }

                Log::info('💾 Saving setting', [
                    'key' => $key,
                    'type' => $type,
                    'value_length' => strlen($value)
                ]);

                $setting = Setting::updateOrCreate(
                    [
                        'key' => $key,
                        'group' => 'site'
                    ],
                    [
                        'value' => $value,
                        'type' => $type
                    ]
                );
                
                Log::info('✅ Setting saved', [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'updated' => $setting->wasRecentlyCreated ? 'created' : 'updated'
                ]);
            }

            DB::commit();
            
            Log::info('✅ Site settings update completed successfully');

            // Fetch updated settings
            $settings = Setting::byGroup('site')->get();
            $siteData = [];
            foreach ($settings as $setting) {
                $value = $setting->value;

                if ($setting->type === 'json') {
                    $value = json_decode($value, true);
                }

                $siteData[$setting->key] = $value;
            }

            return response()->json([
                'success' => true,
                'message' => 'Site settings updated successfully',
                'data' => $siteData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update site settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update site settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get creator brand settings (watermark + filename prefix config).
     * Returns all 5 keys with seeded defaults applied when a row is missing.
     */
    public function getCreatorBrandSettings(): JsonResponse
    {
        try {
            $settings = Setting::byGroup('creator_brand')->get();

            $data = [];
            foreach ($settings as $setting) {
                $data[$setting->key] = $setting->value;
            }

            $data = array_merge([
                'creator_brand_logo' => null,
                'creator_brand_tagline' => 'alisadikinma.com',
                'creator_brand_slug' => 'alisadikinma',
                'watermark_opacity' => '0.30',
                'watermark_enabled' => 'false',
            ], $data);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch creator brand settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch creator brand settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update creator brand settings. Accepts FormData (for logo upload) or JSON.
     * Inline validation — no FormRequest class to keep the surface area tight.
     */
    public function updateCreatorBrandSettings(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'creator_brand_logo' => ['nullable', 'file', 'image', 'max:5120'],
            'creator_brand_tagline' => ['nullable', 'string', 'max:60'],
            'creator_brand_slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],
            'watermark_opacity' => ['nullable', 'numeric', 'min:0.05', 'max:0.95'],
            'watermark_enabled' => ['nullable', 'in:true,false,1,0'],
        ]);

        DB::beginTransaction();

        try {
            // Logo upload (mirrors profile_photo pattern)
            if ($request->hasFile('creator_brand_logo')) {
                $file = $request->file('creator_brand_logo');
                $filename = time() . '_' . $file->getClientOriginalName();

                $uploadPath = public_path('uploads/branding');
                if (!file_exists($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                $file->move($uploadPath, $filename);

                $oldLogo = Setting::where('group', 'creator_brand')->where('key', 'creator_brand_logo')->first();
                if ($oldLogo && $oldLogo->value && file_exists(public_path($oldLogo->value))) {
                    unlink(public_path($oldLogo->value));
                }

                $validated['creator_brand_logo'] = '/uploads/branding/' . $filename;
            } else {
                unset($validated['creator_brand_logo']);
            }

            // Normalize scalar types before persistence
            if (isset($validated['watermark_enabled'])) {
                $validated['watermark_enabled'] = in_array($validated['watermark_enabled'], [true, 'true', 1, '1'], true) ? 'true' : 'false';
            }
            if (isset($validated['watermark_opacity'])) {
                $validated['watermark_opacity'] = (string) $validated['watermark_opacity'];
            }

            foreach ($validated as $key => $value) {
                $type = $key === 'creator_brand_logo' ? 'image' : 'text';
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'creator_brand'],
                    ['value' => $value, 'type' => $type]
                );
            }

            DB::commit();

            // Return fresh state
            $fresh = Setting::byGroup('creator_brand')->get();
            $data = [];
            foreach ($fresh as $s) {
                $data[$s->key] = $s->value;
            }

            return response()->json([
                'success' => true,
                'message' => 'Creator brand settings updated successfully',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update creator brand settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update creator brand settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    // --------------------------------------------------------------------
    // Telegram Notification Settings (Phase E of named-entity cover plan)
    // --------------------------------------------------------------------

    /**
     * Get telegram notification settings. Bot token is MASKED in the response
     * (only last 4 chars visible) so it's safe to surface in the admin UI —
     * admin can re-paste on change but can't accidentally exfiltrate the full
     * token to another browser tab or screenshot.
     */
    public function getTelegramSettings(): \Illuminate\Http\JsonResponse
    {
        try {
            $settings = Setting::byGroup('telegram')->get();

            $data = [];
            foreach ($settings as $setting) {
                $data[$setting->key] = $setting->value;
            }

            // Apply defaults for any missing rows (seeder should cover all 6
            // but be defensive for fresh DBs that haven't run the seeder yet).
            $data = array_merge([
                'telegram_bot_token' => null,
                'telegram_chat_id' => null,
                'telegram_enabled' => 'false',
                'telegram_notify_manifest_needed' => 'true',
                'telegram_notify_generation_failed' => 'true',
                'telegram_notify_publish_success' => 'false',
            ], $data);

            $data['telegram_bot_token'] = $this->maskBotToken($data['telegram_bot_token']);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch telegram settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch telegram settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update telegram notification settings. All fields optional — omit a key
     * to leave it unchanged. Bot token is only updated when a non-empty value
     * is provided (prevents accidentally clearing it via empty form submit).
     */
    public function updateTelegramSettings(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50'],
            'telegram_enabled' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_manifest_needed' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_generation_failed' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_publish_success' => ['nullable', 'in:true,false,1,0'],
        ]);

        DB::beginTransaction();

        try {
            // Bot token: preserve existing when payload omits key or provides empty.
            // Handles both JSON (unset key) and FormData (empty string) cases.
            $tokenValue = $request->input('telegram_bot_token');
            if (!array_key_exists('telegram_bot_token', $validated) || $tokenValue === null || $tokenValue === '') {
                unset($validated['telegram_bot_token']);
            }

            // Normalize booleans to canonical 'true'/'false' strings
            foreach (['telegram_enabled', 'telegram_notify_manifest_needed', 'telegram_notify_generation_failed', 'telegram_notify_publish_success'] as $boolKey) {
                if (isset($validated[$boolKey])) {
                    $validated[$boolKey] = in_array($validated[$boolKey], [true, 'true', 1, '1'], true) ? 'true' : 'false';
                }
            }

            foreach ($validated as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'telegram'],
                    ['value' => $value, 'type' => 'text']
                );
            }

            DB::commit();

            $fresh = Setting::byGroup('telegram')->get();
            $data = [];
            foreach ($fresh as $s) {
                $data[$s->key] = $s->value;
            }
            $data['telegram_bot_token'] = $this->maskBotToken($data['telegram_bot_token'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Telegram settings updated successfully',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update telegram settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update telegram settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Send a test Telegram message using current settings. Used by the
     * "Send test message" button in the AboutSettings Telegram card to verify
     * the bot token + chat_id pairing works before relying on notifications.
     */
    public function testTelegramNotification(\App\Services\TelegramNotificationService $service): \Illuminate\Http\JsonResponse
    {
        $result = $service->sendTestMessage();

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Telegram send failed',
                'telegram_response' => $result['telegram_response'] ?? null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Test message sent successfully',
            'telegram_response' => $result['telegram_response'] ?? null,
        ]);
    }

    /**
     * Mask bot token for safe display. Returns null → null so callers know
     * when token hasn't been set. Masks middle portion: "123456789:ABC...wxyz".
     */
    private function maskBotToken(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        if (strlen($token) <= 8) {
            return '****';
        }

        return substr($token, 0, 4) . '****' . substr($token, -4);
    }
}
