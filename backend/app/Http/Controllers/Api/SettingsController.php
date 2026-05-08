<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAboutSettingsRequest;
use App\Http\Requests\UpdateSiteSettingsRequest;
use App\Models\Gallery;
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

            // Hydrate experience[i].galleries[] from gallery_ids[]. Adds a sibling
            // `galleries` array to each experience entry shaped for inline thumb
            // strip rendering on About.vue. Original `gallery_ids` is preserved
            // so the admin picker still round-trips.
            if (!empty($aboutData['experience']) && is_array($aboutData['experience'])) {
                $aboutData['experience'] = $this->hydrateExperienceGalleries($aboutData['experience']);
            }

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

            // Apply defaults for any missing rows (seeder should cover all 12
            // but be defensive for fresh DBs that haven't run the seeder yet).
            $data = array_merge([
                'telegram_bot_token' => null,
                'telegram_chat_id' => null,
                'telegram_enabled' => 'false',
                'telegram_notify_manifest_needed' => 'true',
                'telegram_notify_generation_failed' => 'true',
                'telegram_notify_publish_success' => 'false',
                'telegram_notify_segment_failed' => 'true',
                'telegram_notify_cover_critical' => 'true',
                'telegram_notify_translate_failed' => 'true',
                // LinkedIn notification toggles (per Decision #9 — live in
                // telegram group so TelegramNotificationService reads one source)
                'telegram_notify_linkedin_preview' => 'true',
                'telegram_notify_linkedin_depth_failed' => 'true',
                'telegram_notify_linkedin_published' => 'true',
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
            'telegram_notify_segment_failed' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_cover_critical' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_translate_failed' => ['nullable', 'in:true,false,1,0'],
            // LinkedIn notification toggles (Decision #9 — same group)
            'telegram_notify_linkedin_preview' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_linkedin_depth_failed' => ['nullable', 'in:true,false,1,0'],
            'telegram_notify_linkedin_published' => ['nullable', 'in:true,false,1,0'],
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
            foreach ([
                'telegram_enabled',
                'telegram_notify_manifest_needed',
                'telegram_notify_generation_failed',
                'telegram_notify_publish_success',
                'telegram_notify_segment_failed',
                'telegram_notify_cover_critical',
                'telegram_notify_translate_failed',
                'telegram_notify_linkedin_preview',
                'telegram_notify_linkedin_depth_failed',
                'telegram_notify_linkedin_published',
            ] as $boolKey) {
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
     * Hydrate experience[i].galleries[] from gallery_ids[].
     *
     * Each experience entry stored in settings.about.experience may carry a
     * `gallery_ids` array (set via the AboutSettings admin picker). Public
     * About.vue needs a thumbnail strip per role, so we fetch the linked
     * Gallery rows + a small preview slice of items in a single batched
     * query, then attach a `galleries` sibling array to each experience.
     *
     * Edge cases:
     *   - missing/null gallery_ids        → galleries: []
     *   - dangling ID (deleted gallery)   → silently filtered
     *   - gallery with 0 items            → items_count=0, preview_items=[]
     *   - missing thumbnail/file_path     → null
     */
    private function hydrateExperienceGalleries(array $experience): array
    {
        $allIds = collect($experience)
            ->pluck('gallery_ids')
            ->flatten()
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($allIds->isEmpty()) {
            return collect($experience)->map(function ($exp) {
                $exp['galleries'] = [];
                return $exp;
            })->all();
        }

        // Eager-load up to 4 items per gallery for the preview strip + a
        // separate aggregate count so the strip header reflects the REAL
        // total (the limited preview collection cannot be used for counting).
        $galleryMap = Gallery::withCount('items')
            ->with(['items' => fn ($q) => $q->limit(4)])
            ->whereIn('id', $allIds)
            ->get()
            ->keyBy('id');

        return collect($experience)->map(function ($exp) use ($galleryMap) {
            $ids = $exp['gallery_ids'] ?? [];
            if (!is_array($ids)) {
                $ids = [];
            }

            $exp['galleries'] = collect($ids)
                ->map(fn ($id) => $galleryMap->get((int) $id))
                ->filter()
                ->map(function ($gallery) {
                    return [
                        'id' => $gallery->id,
                        'title' => $gallery->title,
                        'thumbnail' => $this->resolveAssetUrl($gallery->thumbnail),
                        'items_count' => (int) ($gallery->items_count ?? 0),
                        'preview_items' => $gallery->items->take(4)->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'title' => $item->title,
                                'thumbnail' => $this->resolveAssetUrl($item->file_path),
                            ];
                        })->values()->all(),
                    ];
                })
                ->values()
                ->all();

            return $exp;
        })->all();
    }

    /**
     * Resolve a stored asset path to a public URL. Handles three storage
     * conventions used across the codebase:
     *   - already-absolute https URL      → return as-is
     *   - /uploads/* (public_path)         → prepend APP_URL
     *   - /storage/* or bare path          → use Storage::url()
     */
    private function resolveAssetUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/uploads/') || str_starts_with($path, '/storage/')) {
            return rtrim(config('app.url'), '/') . $path;
        }

        // Storage::url() handles bare 'galleries/foo.jpg' → '/storage/galleries/foo.jpg'
        return Storage::url($path);
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

    /**
     * GET /api/admin/settings/linkedin
     *
     * Returns the `linkedin` settings group (operator-facing publishing flags)
     * + OAuth configuration status. Used by the LinkedIn Integration card on
     * /admin/settings/about.
     */
    public function getLinkedInSettings(): \Illuminate\Http\JsonResponse
    {
        try {
            $settings = Setting::byGroup('linkedin')->get();

            $data = [];
            foreach ($settings as $setting) {
                $data[$setting->key] = $setting->value;
            }

            $data = array_merge([
                'linkedin_auto_publish' => 'false',
                'linkedin_auto_approve_enabled' => 'false',
                'linkedin_depth_score_threshold' => '80',
                'linkedin_cancel_window_minutes' => '15',
                'linkedin_first_comment_enabled' => 'true',
                'linkedin_first_comment_delay_seconds' => '30',
                'linkedin_last_test_connection_at' => null,
                'linkedin_last_test_connection_result' => null,
            ], $data);

            // Append runtime flags the UI needs to render the OAuth section
            $data['oauth_configured'] = !empty(config('linkedin.oauth.client_id'))
                && !empty(config('linkedin.oauth.client_secret'));

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch linkedin settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch linkedin settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * PUT /api/admin/settings/linkedin
     *
     * Update the `linkedin` settings group. All fields optional — omit a key
     * to leave it unchanged. Booleans normalized to 'true'/'false' strings.
     */
    public function updateLinkedInSettings(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'linkedin_auto_publish' => ['nullable', 'in:true,false,1,0'],
            'linkedin_auto_approve_enabled' => ['nullable', 'in:true,false,1,0'],
            'linkedin_depth_score_threshold' => ['nullable', 'integer', 'between:0,100'],
            'linkedin_cancel_window_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'linkedin_first_comment_enabled' => ['nullable', 'in:true,false,1,0'],
            'linkedin_first_comment_delay_seconds' => ['nullable', 'integer', 'between:0,3600'],
        ]);

        DB::beginTransaction();

        try {
            // Normalize booleans to canonical 'true'/'false' strings
            foreach (['linkedin_auto_publish', 'linkedin_auto_approve_enabled', 'linkedin_first_comment_enabled'] as $boolKey) {
                if (isset($validated[$boolKey])) {
                    $validated[$boolKey] = in_array($validated[$boolKey], [true, 'true', 1, '1'], true) ? 'true' : 'false';
                }
            }

            foreach ($validated as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'linkedin'],
                    ['value' => (string) $value, 'type' => 'text']
                );
            }

            DB::commit();

            return $this->getLinkedInSettings();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update linkedin settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update linkedin settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    // --------------------------------------------------------------------
    // Mail SMTP Settings (Newsletter system, May 2026)
    // --------------------------------------------------------------------

    /**
     * Get SMTP settings for the admin UI. The mail_password field is NEVER
     * surfaced raw — returns the literal string "***SET***" if configured,
     * empty string otherwise. Admin UI shows it as read-only with a "change"
     * affordance; saving an empty value preserves the existing password.
     */
    public function getMailSettings(): JsonResponse
    {
        try {
            $rows = Setting::byGroup('mail')->get();

            $data = [];
            foreach ($rows as $setting) {
                $data[$setting->key] = $setting->value;
            }

            $data = array_merge([
                'mail_mailer' => 'smtp',
                'mail_host' => 'smtp.hostinger.com',
                'mail_port' => '465',
                'mail_username' => 'aiagent@alisadikinma.com',
                'mail_password' => null,
                'mail_encryption' => 'ssl',
                'mail_from_address' => 'aiagent@alisadikinma.com',
                'mail_from_name' => 'Ali Sadikin',
            ], $data);

            // Mask password — UI never sees the real value
            $data['mail_password'] = !empty($data['mail_password']) ? '***SET***' : '';
            $data['mail_password_configured'] = $data['mail_password'] === '***SET***';

            // Effective config Laravel will actually use right now (after
            // MailConfigOverrideProvider boot + any in-request overrides).
            // Lets the admin UI surface "Active driver: log" when SMTP isn't
            // wired up yet — explains why test sends don't actually deliver.
            $data['effective'] = $this->buildMailDiagnostics();

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch mail settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mail settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Update SMTP settings. Empty mail_password is treated as "no change" —
     * non-empty values get encrypted via Crypt::encryptString before storage.
     */
    public function updateMailSettings(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mail_mailer' => ['nullable', 'string', 'in:smtp,log,sendmail,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'string', 'regex:/^\d{1,5}$/'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:1024'],
            'mail_encryption' => ['nullable', 'string', 'in:ssl,tls,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        DB::beginTransaction();
        try {
            // Empty password = preserve existing (admin chose not to change it)
            $passwordValue = $request->input('mail_password');
            if ($passwordValue === null || $passwordValue === '' || $passwordValue === '***SET***') {
                unset($validated['mail_password']);
            } else {
                $validated['mail_password'] = \Illuminate\Support\Facades\Crypt::encryptString($passwordValue);
            }

            // Normalize "none" → empty for encryption (matches Symfony Mailer convention)
            if (isset($validated['mail_encryption']) && $validated['mail_encryption'] === 'none') {
                $validated['mail_encryption'] = '';
            }

            foreach ($validated as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'mail'],
                    ['value' => $value, 'type' => 'text']
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mail settings updated. Restart the queue worker for changes to fully apply.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update mail settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mail settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Send a one-shot test email to verify SMTP creds work end-to-end.
     * Defaults to auth user's email; admin can override via request body.
     */
    public function testMailConnection(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient' => ['nullable', 'email'],
        ]);

        $recipient = $validated['recipient'] ?? $request->user()?->email;
        if (!$recipient) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NO_RECIPIENT', 'message' => 'No recipient email available.'],
            ], 422);
        }

        // Re-apply config from DB before sending — covers the case where the
        // admin just saved new creds and the worker hasn't restarted yet.
        $this->reapplyMailConfigFromDb();

        $diagnostics = $this->buildMailDiagnostics();

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "This is a test from your Portfolio admin panel.\n\nIf you're reading this, SMTP is configured correctly. ✅\n\nSent at: " . now()->toIso8601String() . "\nDriver: " . $diagnostics['driver'] . "\nHost: " . ($diagnostics['host'] ?? 'n/a'),
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('SMTP Test — Portfolio Admin');
                }
            );

            $driverNote = match ($diagnostics['driver']) {
                'log' => " ⚠️ DELIVERED TO LOG FILE only (driver='log'). Save SMTP password above + retry.",
                'array' => " ⚠️ Driver='array' — message stayed in memory, no real send.",
                'smtp' => " via SMTP {$diagnostics['host']}:{$diagnostics['port']}. If not in inbox, check spam folder.",
                default => " via driver '{$diagnostics['driver']}'.",
            };

            return response()->json([
                'success' => true,
                'message' => "Test email sent to {$recipient}{$driverNote}",
                'diagnostics' => $diagnostics,
            ]);
        } catch (\Throwable $e) {
            Log::error('SMTP test send failed', ['error' => $e->getMessage(), 'recipient' => $recipient]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SMTP_FAILED',
                    'message' => $e->getMessage(),
                ],
                'diagnostics' => $diagnostics,
            ], 500);
        }
    }

    private function buildMailDiagnostics(): array
    {
        $driver = (string) config('mail.default');
        return [
            'driver' => $driver,
            'host' => $driver === 'smtp' ? config('mail.mailers.smtp.host') : null,
            'port' => $driver === 'smtp' ? config('mail.mailers.smtp.port') : null,
            'username' => $driver === 'smtp' ? config('mail.mailers.smtp.username') : null,
            'encryption' => $driver === 'smtp' ? (config('mail.mailers.smtp.encryption') ?: 'none') : null,
            'password_set' => $driver === 'smtp' ? !empty(config('mail.mailers.smtp.password')) : null,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    private function reapplyMailConfigFromDb(): void
    {
        $rows = Setting::byGroup('mail')->pluck('value', 'key')->toArray();

        if (!empty($rows['mail_mailer'])) {
            \Illuminate\Support\Facades\Config::set('mail.default', $rows['mail_mailer']);
        }
        if (!empty($rows['mail_host'])) {
            \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.host', $rows['mail_host']);
        }
        if (!empty($rows['mail_port'])) {
            \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.port', (int) $rows['mail_port']);
        }
        if (!empty($rows['mail_username'])) {
            \Illuminate\Support\Facades\Config::set('mail.mailers.smtp.username', $rows['mail_username']);
        }
        if (isset($rows['mail_encryption'])) {
            \Illuminate\Support\Facades\Config::set(
                'mail.mailers.smtp.encryption',
                $rows['mail_encryption'] === 'none' ? '' : $rows['mail_encryption']
            );
        }
        if (!empty($rows['mail_password'])) {
            try {
                \Illuminate\Support\Facades\Config::set(
                    'mail.mailers.smtp.password',
                    \Illuminate\Support\Facades\Crypt::decryptString($rows['mail_password'])
                );
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // leave .env fallback in place
            }
        }
        if (!empty($rows['mail_from_address'])) {
            \Illuminate\Support\Facades\Config::set('mail.from.address', $rows['mail_from_address']);
        }
        if (!empty($rows['mail_from_name'])) {
            \Illuminate\Support\Facades\Config::set('mail.from.name', $rows['mail_from_name']);
        }
    }

    // =====================================================================
    // CV Master Export settings (group=cv) — schema_version 2.0.0
    // =====================================================================
    //
    // Four JSON keys consumed by /api/cv/export and the jobhunter platform:
    //   summary_variants  : { vibe_coding, ai_automation, ai_video } strings
    //   work_experience   : [ {company, position, start_date, end_date, ...} ]
    //   skills_matrix     : { languages, frameworks, ai_tools, ... } -> string[]
    //   education         : [ {institution, area, study_type, start, end} ]
    //
    // Each row's `value` column stores the JSON-encoded blob and `type=json`.
    // Admin UI on AboutSettings.vue posts the raw JSON back; we re-encode
    // server-side so persisted strings stay canonical.

    public function getCvSettings(): JsonResponse
    {
        try {
            $rows = Setting::byGroup('cv')->get();

            $data = [
                'summary_variants' => null,
                'work_experience'  => [],
                'skills_matrix'    => [],
                'education'        => [],
            ];

            foreach ($rows as $row) {
                $decoded = is_string($row->value) ? json_decode($row->value, true) : $row->value;
                $data[$row->key] = $decoded;
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch CV settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch CV settings',
                'error'   => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function updateCvSettings(\Illuminate\Http\Request $request): JsonResponse
    {
        // Each field can be either an already-decoded array (axios JSON) OR a
        // raw JSON string (admin UI textarea). Normalize both shapes.
        $validated = $request->validate([
            'summary_variants' => ['nullable'],
            'work_experience'  => ['nullable'],
            'skills_matrix'    => ['nullable'],
            'education'        => ['nullable'],
        ]);

        $errors = [];
        $payload = [];

        foreach (['summary_variants', 'work_experience', 'skills_matrix', 'education'] as $key) {
            if (!array_key_exists($key, $validated)) continue;
            $value = $validated[$key];

            // Skip explicit nulls — operator clearing the field via UI sends
            // empty string, not null. Treat null as "no change" so partial
            // updates from the admin UI don't accidentally clear other keys.
            if ($value === null) continue;

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    // Empty string = explicit clear → store empty array/object.
                    $payload[$key] = $key === 'summary_variants' ? new \stdClass() : [];
                    continue;
                }
                $decoded = json_decode($trimmed, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[$key] = 'Invalid JSON: ' . json_last_error_msg();
                    continue;
                }
                $payload[$key] = $decoded;
            } else {
                // Already-decoded array (e.g. axios sent JSON body).
                $payload[$key] = $value;
            }
        }

        // Shape-level validation — each field has a known top-level shape.
        if (isset($payload['summary_variants']) && !is_array($payload['summary_variants']) && !($payload['summary_variants'] instanceof \stdClass)) {
            $errors['summary_variants'] = 'Must be an object with vibe_coding / ai_automation / ai_video keys';
        }
        if (isset($payload['work_experience']) && !is_array($payload['work_experience'])) {
            $errors['work_experience'] = 'Must be an array of work entries';
        }
        if (isset($payload['skills_matrix']) && !is_array($payload['skills_matrix']) && !($payload['skills_matrix'] instanceof \stdClass)) {
            $errors['skills_matrix'] = 'Must be an object mapping category → string[]';
        }
        if (isset($payload['education']) && !is_array($payload['education'])) {
            $errors['education'] = 'Must be an array of education entries';
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($payload as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'cv'],
                    [
                        'value' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'type'  => 'json',
                    ]
                );
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'CV settings updated',
                'data'    => $this->getCvSettings()->getData(true)['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update CV settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update CV settings',
                'error'   => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * GET /api/admin/settings/publer — read Publer integration config.
     * api_key value is NEVER returned; surfaces only `publer_api_key_configured`
     * boolean flag + ***SET*** placeholder so the UI knows whether a key
     * exists without exposing the actual encrypted blob.
     */
    public function getPublerSettings(): JsonResponse
    {
        try {
            $rows = Setting::byGroup('publer')->get();

            $data = [];
            foreach ($rows as $setting) {
                $data[$setting->key] = $setting->value;
            }

            // Defaults if seeder hasn't run yet
            $data = array_merge([
                'publer_api_key' => null,
                'publer_enabled' => 'false',
                'publer_facebook_account_id' => null,
                'publer_instagram_account_id' => null,
                'publer_tiktok_account_id' => null,
                'publer_last_account_sync_at' => null,
            ], $data);

            // Mask api_key — UI never sees the real encrypted blob nor the plaintext.
            $hasKey = !empty($data['publer_api_key']);
            $data['publer_api_key'] = $hasKey ? '***SET***' : '';
            $data['publer_api_key_configured'] = $hasKey;

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch publer settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch publer settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * PUT /api/admin/settings/publer — update Publer integration config.
     * Empty publer_api_key is treated as "no change" (preserves existing
     * encrypted value) so admin can edit other fields without re-entering.
     * Non-empty value gets encrypted via Crypt::encryptString before storage.
     */
    public function updatePublerSettings(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publer_api_key' => ['nullable', 'string', 'max:512'],
            'publer_enabled' => ['nullable', 'in:true,false,1,0'],
            'publer_facebook_account_id' => ['nullable', 'string', 'max:100'],
            'publer_instagram_account_id' => ['nullable', 'string', 'max:100'],
            'publer_tiktok_account_id' => ['nullable', 'string', 'max:100'],
        ]);

        DB::beginTransaction();
        try {
            // Empty / sentinel api_key = preserve existing
            $keyValue = $request->input('publer_api_key');
            if ($keyValue === null || $keyValue === '' || $keyValue === '***SET***') {
                unset($validated['publer_api_key']);
            } else {
                $validated['publer_api_key'] = \Illuminate\Support\Facades\Crypt::encryptString($keyValue);
            }

            // Normalize boolean-ish enabled flag to consistent string
            if (isset($validated['publer_enabled'])) {
                $validated['publer_enabled'] = in_array(
                    (string) $validated['publer_enabled'],
                    ['true', '1'],
                    true
                ) ? 'true' : 'false';
            }

            foreach ($validated as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key, 'group' => 'publer'],
                    ['value' => $value, 'type' => 'text']
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Publer settings updated.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update publer settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update publer settings',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * POST /api/admin/settings/publer/test — synchronous Test Connection
     * button. Calls PublerClient::me() to verify api_key works end-to-end.
     * Returns user profile on success or operator-actionable error message.
     */
    public function testPublerConnection(\App\Services\PublerClient $client): JsonResponse
    {
        try {
            $user = $client->me();
            return response()->json([
                'success' => true,
                'message' => 'Publer connection OK.',
                'data' => [
                    'user_id' => $user['id'] ?? null,
                    'email' => $user['email'] ?? null,
                    'name' => $user['name'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            // Don't 500 on auth failure — operator's error, not server's.
            // Return 200 with success=false so frontend can render the
            // error message inline without a console.error noise.
            return response()->json([
                'success' => false,
                'message' => 'Publer connection failed.',
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * POST /api/admin/settings/publer/sync-accounts — fetch the operator's
     * connected social accounts from Publer and return as dropdown options
     * grouped by platform. Persists `publer_last_account_sync_at` timestamp
     * so admin UI can show "Last synced: Xh ago".
     *
     * Returns shape:
     *   {
     *     success: true,
     *     data: {
     *       facebook: [{ id, name, picture? }, ...],
     *       instagram: [...],
     *       tiktok: [...],
     *       other: [...]   // fallback bucket for unsupported types
     *     },
     *     synced_at: ISO 8601 timestamp
     *   }
     */
    public function syncPublerAccounts(\App\Services\PublerClient $client): JsonResponse
    {
        try {
            $aggregate = $client->listAllAccountsAcrossWorkspaces();
            $accounts = $aggregate['accounts'];
            $workspaces = $aggregate['workspaces'];
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch Publer accounts.',
                'error' => $e->getMessage(),
            ], 200);
        }

        // Group by platform `type` (or `provider` / `network`) field. Publer's
        // type values vary by account subtype — `facebook_page`, `instagram_business`,
        // `tiktok_business` are common. Substring match against the canonical
        // platform name handles all variants.
        $grouped = [
            'facebook' => [],
            'instagram' => [],
            'tiktok' => [],
            'other' => [],
        ];
        $rawTypes = [];

        foreach ($accounts as $account) {
            $rawType = strtolower((string) (
                $account['type']
                    ?? $account['provider']
                    ?? $account['network']
                    ?? $account['platform']
                    ?? 'other'
            ));
            $rawTypes[] = $rawType;

            $bucket = 'other';
            if (str_contains($rawType, 'facebook') || $rawType === 'fb') {
                $bucket = 'facebook';
            } elseif (str_contains($rawType, 'instagram') || $rawType === 'ig') {
                $bucket = 'instagram';
            } elseif (str_contains($rawType, 'tiktok') || $rawType === 'tt') {
                $bucket = 'tiktok';
            }

            $grouped[$bucket][] = [
                'id' => $account['id'] ?? null,
                'name' => $account['name'] ?? $account['display_name'] ?? '(unnamed)',
                'picture' => $account['picture'] ?? $account['picture_url'] ?? null,
                'raw_type' => $rawType,
            ];
        }

        $now = now()->toIso8601String();
        Setting::updateOrCreate(
            ['key' => 'publer_last_account_sync_at', 'group' => 'publer'],
            ['value' => $now, 'type' => 'text']
        );

        return response()->json([
            'success' => true,
            'message' => 'Publer accounts synced.',
            'data' => $grouped,
            'synced_at' => $now,
            'debug_raw_types' => array_values(array_unique($rawTypes)),
            'debug_workspace_count' => count($workspaces),
            'debug_total_accounts' => count($accounts),
        ]);
    }
}
