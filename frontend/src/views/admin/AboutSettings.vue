<template>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100 mb-2">
        About Settings
      </h1>
      <p class="text-neutral-600 dark:text-neutral-400">
        Manage your personal information, skills, experience, and education
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !settingsStore.aboutSettings.name" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-900 dark:border-neutral-100"></div>
      <p class="mt-4 text-neutral-600 dark:text-neutral-400">Loading settings...</p>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Basic Information Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
          Basic Information
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Name
            </label>
            <input
              id="name"
              v-model="formData.name"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Your full name"
            />
          </div>

          <!-- Title -->
          <div>
            <label for="title" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Title
            </label>
            <input
              id="title"
              v-model="formData.title"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="e.g., Full Stack Developer"
            />
          </div>
        </div>

        <!-- Profile Photo -->
        <div class="mt-6">
          <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Profile Photo
          </label>

          <div class="flex items-center gap-4">
            <!-- Current Photo Preview -->
            <div class="relative w-24 h-24 rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
              <img 
                v-if="currentPhotoUrl" 
                :src="currentPhotoUrl" 
                alt="Profile" 
                class="w-full h-full object-cover"
                @error="handleImageError"
              />
              <!-- Fallback Icon -->
              <svg 
                v-else
                class="w-12 h-12 text-neutral-400 dark:text-neutral-500" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </div>

            <!-- Upload Input -->
            <div class="flex-1">
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handlePhotoChange"
              />
              <BaseButton
                type="button"
                button-type="secondary"
                @click="$refs.fileInput.click()"
              >
                {{ currentPhotoUrl ? 'Change Photo' : 'Upload Photo' }}
              </BaseButton>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-2">
                Maximum file size: 5MB
              </p>
            </div>

            <!-- Remove Photo Button -->
            <BaseButton
              v-if="currentPhotoUrl"
              type="button"
              button-type="danger"
              @click="removePhoto"
            >
              Remove
            </BaseButton>
          </div>
        </div>
      </BaseCard>

      <!-- Creator Brand Card (Image Watermark + Filename Prefix) -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          Creator Brand — Image Watermark
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
          Applied automatically to every AI-generated blog image. Logo + tagline render at the configured opacity; filename prefix is used for storage and downloads.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Brand Logo -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Brand Logo <span class="text-xs text-neutral-500">(PNG with transparent background recommended)</span>
            </label>
            <div class="flex items-center gap-4">
              <div class="relative w-24 h-24 rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center border border-neutral-200 dark:border-neutral-700">
                <img
                  v-if="brandLogoPreviewUrl"
                  :src="brandLogoPreviewUrl"
                  alt="Brand Logo"
                  class="w-full h-full object-contain"
                  @error="brandFormData.creator_brand_logo = null"
                />
                <svg v-else class="w-10 h-10 text-neutral-400 dark:text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="flex-1">
                <input
                  ref="brandLogoInput"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleBrandLogoChange"
                />
                <BaseButton
                  type="button"
                  button-type="secondary"
                  @click="$refs.brandLogoInput.click()"
                >
                  {{ brandLogoPreviewUrl ? 'Change Logo' : 'Upload Logo' }}
                </BaseButton>
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-2">
                  Max 5MB. Square or horizontal PNG preferred.
                </p>
              </div>
              <BaseButton
                v-if="brandLogoPreviewUrl"
                type="button"
                button-type="danger"
                @click="removeBrandLogo"
              >
                Remove
              </BaseButton>
            </div>
          </div>

          <!-- Brand Tagline -->
          <div>
            <label for="creator_brand_tagline" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Tagline <span class="text-xs text-neutral-500">(rendered below logo)</span>
            </label>
            <input
              id="creator_brand_tagline"
              v-model="brandFormData.creator_brand_tagline"
              type="text"
              maxlength="60"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="alisadikinma.com"
            />
          </div>

          <!-- Brand Slug -->
          <div>
            <label for="creator_brand_slug" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Filename Prefix <span class="text-xs text-neutral-500">(lowercase, kebab-case)</span>
            </label>
            <input
              id="creator_brand_slug"
              v-model="brandFormData.creator_brand_slug"
              type="text"
              maxlength="60"
              :class="[
                'w-full px-4 py-2 border rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:border-transparent',
                brandSlugError ? 'border-red-500 focus:ring-red-500' : 'border-neutral-300 dark:border-neutral-600 focus:ring-blue-500'
              ]"
              placeholder="alisadikinma"
              @blur="validateBrandSlug"
            />
            <p v-if="brandSlugError" class="text-xs text-red-500 mt-1">{{ brandSlugError }}</p>
            <p v-else class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
              Filenames: <code>{{ brandFormData.creator_brand_slug || 'alisadikinma' }}-{{ '{seo-keyword}' }}-cover.png</code>
            </p>
          </div>

          <!-- Opacity Slider -->
          <div>
            <label for="watermark_opacity" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Watermark Opacity
              <span class="text-xs font-mono text-blue-600 dark:text-blue-400 ml-2">{{ watermarkOpacityPct }}%</span>
            </label>
            <input
              id="watermark_opacity"
              v-model.number="watermarkOpacityPct"
              type="range"
              min="5"
              max="95"
              step="5"
              class="w-full h-2 bg-neutral-200 dark:bg-neutral-700 rounded-lg appearance-none cursor-pointer accent-blue-600"
            />
            <div class="flex justify-between text-[10px] text-neutral-500 mt-1">
              <span>5% (subtle)</span>
              <span>50%</span>
              <span>95% (bold)</span>
            </div>
          </div>

          <!-- Enable Toggle -->
          <div class="flex items-center justify-between p-4 rounded-lg bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-700">
            <div>
              <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Enable Watermark</div>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Applied to every generated image</p>
            </div>
            <button
              type="button"
              role="switch"
              :aria-checked="watermarkEnabledBool"
              @click="watermarkEnabledBool = !watermarkEnabledBool"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                watermarkEnabledBool ? 'bg-blue-600' : 'bg-neutral-300 dark:bg-neutral-600'
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                  watermarkEnabledBool ? 'translate-x-6' : 'translate-x-1'
                ]"
              />
            </button>
          </div>
        </div>

        <!-- Save Button (dedicated for this card) -->
        <div class="mt-6 flex justify-end">
          <BaseButton
            type="button"
            button-type="primary"
            :disabled="brandSubmitting || !!brandSlugError"
            @click="handleBrandSubmit"
          >
            {{ brandSubmitting ? 'Saving...' : 'Save Creator Brand' }}
          </BaseButton>
        </div>
      </BaseCard>

      <!-- Telegram Notifications Card (Phase I) -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          Telegram Notifications — Manual Upload Alerts
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
          Get pinged on your phone when the Content Engine needs you:
          manual reference upload required, image generation failed, or a successful publish.
          <br>
          Setup: message
          <a href="https://t.me/BotFather" target="_blank" class="text-amber-600 dark:text-amber-400 hover:underline">@BotFather</a>
          on Telegram to create a bot, paste the token below. To find your chat_id,
          message your new bot once then visit
          <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code>
          and copy the <code>chat.id</code> number.
        </p>

        <div class="space-y-4">
          <div>
            <label for="telegram_bot_token" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Bot Token
              <span class="text-xs text-neutral-500">— from @BotFather (leave blank to keep existing)</span>
            </label>
            <input
              id="telegram_bot_token"
              v-model="telegramFormData.telegram_bot_token"
              type="password"
              placeholder="123456789:ABC-DEF..."
              autocomplete="off"
              class="w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-amber-500"
            >
            <p v-if="telegramTokenMasked" class="text-xs text-neutral-500 mt-1">
              Current: <code>{{ telegramTokenMasked }}</code>
            </p>
          </div>

          <div>
            <label for="telegram_chat_id" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Chat ID
            </label>
            <input
              id="telegram_chat_id"
              v-model="telegramFormData.telegram_chat_id"
              type="text"
              placeholder="987654321"
              class="w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-amber-500"
            >
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-neutral-200 dark:border-neutral-700">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                v-model="telegramEnabledBool"
                type="checkbox"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
              >
              <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                Enable Telegram notifications (master toggle)
              </span>
            </label>
          </div>

          <div class="pl-7 space-y-2" :class="{ 'opacity-50': !telegramEnabledBool }">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="telegramFormData.telegram_notify_manifest_needed === 'true'"
                type="checkbox"
                :disabled="!telegramEnabledBool"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => telegramFormData.telegram_notify_manifest_needed = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Alert on manual-upload needed (public figure / landmark reference missing)
              </span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="telegramFormData.telegram_notify_generation_failed === 'true'"
                type="checkbox"
                :disabled="!telegramEnabledBool"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => telegramFormData.telegram_notify_generation_failed = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Alert on image generation failure
              </span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="telegramFormData.telegram_notify_publish_success === 'true'"
                type="checkbox"
                :disabled="!telegramEnabledBool"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => telegramFormData.telegram_notify_publish_success = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Alert on successful publish (celebratory)
              </span>
            </label>
          </div>

          <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-neutral-200 dark:border-neutral-700">
            <BaseButton
              type="button"
              button-type="primary"
              :disabled="telegramSubmitting"
              @click="handleTelegramSubmit"
            >
              {{ telegramSubmitting ? 'Saving...' : 'Save Telegram Settings' }}
            </BaseButton>

            <BaseButton
              type="button"
              button-type="secondary"
              :disabled="telegramTesting || !telegramEnabledBool"
              @click="handleTelegramTest"
            >
              {{ telegramTesting ? 'Sending...' : '📨 Send test message' }}
            </BaseButton>

            <span v-if="telegramTestResult" :class="telegramTestResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-sm">
              {{ telegramTestResult.message }}
            </span>
          </div>
        </div>
      </BaseCard>

      <!-- Hero & About Enhancement Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
          Hero & About Page Enhancement
        </h2>

        <!-- Availability Note -->
        <div class="mb-6">
          <label for="availability_note" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Availability Note <span class="text-xs text-neutral-500">(Current status)</span>
          </label>
          <input
            id="availability_note"
            v-model="formData.availability_note"
            type="text"
            maxlength="255"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Available for consulting and freelance projects"
          />
        </div>

        <!-- Hero Tagline -->
        <div class="mb-6">
          <label for="hero_tagline" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Hero Tagline <span class="text-xs text-neutral-500">(Main positioning statement)</span>
          </label>
          <input
            id="hero_tagline"
            v-model="formData.hero_tagline"
            type="text"
            maxlength="255"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="AI Automation Architect & Senior Tech Consultant"
          />
        </div>

        <!-- Bio -->
        <div class="mb-6">
          <label for="bio" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Bio
          </label>
          <textarea
            id="bio"
            v-model="formData.bio"
            rows="4"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Tell us about yourself..."
          ></textarea>
        </div>

        <!-- Mission Statement -->
        <div class="mb-6">
          <label for="mission" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Mission Statement
          </label>
          <textarea
            id="mission"
            v-model="formData.mission"
            rows="3"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Empowering businesses through intelligent automation..."
          ></textarea>
        </div>

        <!-- Approach -->
        <div class="mb-6">
          <label for="approach" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
            Approach / Methodology
          </label>
          <textarea
            id="approach"
            v-model="formData.approach"
            rows="4"
            class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="I believe in combining technical excellence with business impact..."
          ></textarea>
        </div>

        <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-4">
          <strong>Note:</strong> Trust Strip, What I Do, and Collaboration Modes are complex arrays.
          For now, they are seeded with default values.
          Advanced editor coming soon.
        </p>
      </BaseCard>

      <!-- Languages Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Languages
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addLanguage"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Language
          </BaseButton>
        </div>

        <div v-if="formData.languages.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No languages added yet. Click "Add Language" to get started.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="(language, index) in formData.languages"
            :key="index"
            class="flex items-center gap-3"
          >
            <input
              v-model="formData.languages[index]"
              type="text"
              class="flex-1 px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Enter language (e.g., Indonesia, English, Mandarin)"
            />
            <button
              type="button"
              @click="removeLanguage(index)"
              class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
              aria-label="Remove language"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </BaseCard>

      <!-- Skills Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Skills
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addSkill"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Skill
          </BaseButton>
        </div>

        <div v-if="formData.skills.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No skills added yet. Click "Add Skill" to get started.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="(skill, index) in formData.skills"
            :key="index"
            class="flex items-center gap-3"
          >
            <input
              v-model="formData.skills[index]"
              type="text"
              class="flex-1 px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="Enter skill name"
            />
            <button
              type="button"
              @click="removeSkill(index)"
              class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
              aria-label="Remove skill"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </BaseCard>

      <!-- Experience Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Work Experience
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addExperience"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Experience
          </BaseButton>
        </div>

        <div v-if="formData.experience.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No experience added yet. Click "Add Experience" to get started.
        </div>

        <div v-else class="space-y-6">
          <div
            v-for="(exp, index) in formData.experience"
            :key="index"
            class="p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="flex items-start justify-between mb-4">
              <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                Experience #{{ index + 1 }}
              </h3>
              <button
                type="button"
                @click="removeExperience(index)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                aria-label="Remove experience"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Title -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Job Title *
                </label>
                <input
                  v-model="exp.title"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Senior Developer"
                  required
                />
              </div>

              <!-- Company -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Company *
                </label>
                <input
                  v-model="exp.company"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Company name"
                  required
                />
              </div>

              <!-- Company Logo -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Company Logo URL
                </label>
                <input
                  v-model="exp.company_logo"
                  type="url"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="https://example.com/logo.png"
                />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                  Optional: Enter company logo URL
                </p>
              </div>

              <!-- Company Website URL -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Company Website
                </label>
                <input
                  v-model="exp.company_url"
                  type="url"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="https://company.com"
                />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                  Optional: Company website will be linked from company name
                </p>
              </div>

              <!-- Location -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Location
                </label>
                <input
                  v-model="exp.location"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="City, Country"
                />
              </div>

              <!-- Start Date -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Start Date *
                </label>
                <input
                  v-model="exp.start_date"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Jan 2020"
                  required
                />
              </div>

              <!-- End Date -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  End Date
                </label>
                <input
                  v-model="exp.end_date"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Dec 2023 or Present"
                  :disabled="exp.current"
                />
              </div>

              <!-- Current Position Checkbox -->
              <div class="flex items-center">
                <input
                  :id="`current-${index}`"
                  v-model="exp.current"
                  type="checkbox"
                  class="w-4 h-4 text-blue-600 border-neutral-300 rounded focus:ring-blue-500"
                  @change="exp.end_date = exp.current ? '' : exp.end_date"
                />
                <label :for="`current-${index}`" class="ml-2 text-sm text-neutral-700 dark:text-neutral-300">
                  I currently work here
                </label>
              </div>
            </div>

            <!-- Description -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Description
              </label>
              <textarea
                v-model="exp.description"
                rows="3"
                class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Describe your role and achievements..."
              ></textarea>
            </div>

            <!-- Gallery Selection -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Work Gallery Photos
              </label>
              <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
                Select galleries to showcase photos from this work experience
              </p>
              
              <div v-if="loadingGalleries" class="text-sm text-neutral-500 dark:text-neutral-400">
                Loading galleries...
              </div>
              
              <div v-else-if="availableGalleries.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-2 border border-neutral-200 dark:border-neutral-700 rounded-lg bg-neutral-50 dark:bg-neutral-900">
                <label
                  v-for="gallery in availableGalleries"
                  :key="gallery.id"
                  class="flex items-start gap-2 p-2 hover:bg-neutral-100 dark:hover:bg-neutral-800 rounded cursor-pointer"
                >
                  <input
                    type="checkbox"
                    :value="gallery.id"
                    v-model="exp.gallery_ids"
                    class="mt-0.5 w-4 h-4 text-blue-600 border-neutral-300 rounded focus:ring-blue-500"
                  />
                  <span class="text-sm text-neutral-700 dark:text-neutral-300">{{ gallery.title }}</span>
                </label>
              </div>
              
              <div v-else class="text-sm text-neutral-500 dark:text-neutral-400">
                No galleries available. Create galleries first to link them here.
              </div>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Education Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Education
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addEducation"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Education
          </BaseButton>
        </div>

        <div v-if="formData.education.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No education added yet. Click "Add Education" to get started.
        </div>

        <div v-else class="space-y-6">
          <div
            v-for="(edu, index) in formData.education"
            :key="index"
            class="p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="flex items-start justify-between mb-4">
              <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                Education #{{ index + 1 }}
              </h3>
              <button
                type="button"
                @click="removeEducation(index)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                aria-label="Remove education"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Degree -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Degree *
                </label>
                <input
                  v-model="edu.degree"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Bachelor of Science"
                  required
                />
              </div>

              <!-- Institution -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Institution *
                </label>
                <input
                  v-model="edu.institution"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="University name"
                  required
                />
              </div>

              <!-- Location -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Location
                </label>
                <input
                  v-model="edu.location"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="City, Country"
                />
              </div>

              <!-- Start Year -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Start Year *
                </label>
                <input
                  v-model="edu.start_year"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., 2016"
                  required
                />
              </div>

              <!-- End Year -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  End Year
                </label>
                <input
                  v-model="edu.end_year"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., 2020 or Present"
                />
              </div>
            </div>

            <!-- Description -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Description
              </label>
              <textarea
                v-model="edu.description"
                rows="3"
                class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Describe your studies, achievements, etc..."
              ></textarea>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Social Links Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Social Links
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addSocialLink"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Link
          </BaseButton>
        </div>

        <div v-if="formData.social_links.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No social links added yet. Click "Add Link" to get started.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="(link, index) in formData.social_links"
            :key="index"
            class="p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="flex items-start justify-between mb-4">
              <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                Link #{{ index + 1 }}
              </h3>
              <button
                type="button"
                @click="removeSocialLink(index)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                aria-label="Remove link"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Platform -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Platform *
                </label>
                <input
                  v-model="link.platform"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., GitHub, LinkedIn"
                  required
                />
              </div>

              <!-- URL -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  URL *
                </label>
                <input
                  v-model="link.url"
                  type="url"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="https://..."
                  required
                />
              </div>

              <!-- Icon -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Icon Class
                </label>
                <input
                  v-model="link.icon"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., fab fa-github"
                />
              </div>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Certifications Card -->
      <BaseCard>
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
            Certifications
          </h2>
          <BaseButton
            type="button"
            button-type="secondary"
            size="sm"
            @click="addCertification"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Certification
          </BaseButton>
        </div>

        <div v-if="formData.certifications.length === 0" class="text-center py-8 text-neutral-500 dark:text-neutral-400">
          No certifications added yet. Click "Add Certification" to get started.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="(cert, index) in formData.certifications"
            :key="index"
            class="p-4 border border-neutral-200 dark:border-neutral-700 rounded-lg"
          >
            <div class="flex items-start justify-between mb-4">
              <h3 class="font-medium text-neutral-900 dark:text-neutral-100">
                Certification #{{ index + 1 }}
              </h3>
              <button
                type="button"
                @click="removeCertification(index)"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                aria-label="Remove certification"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Certification Name *
                </label>
                <input
                  v-model="cert.name"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="e.g., Google Cloud Certified, Oracle Certified"
                  required
                />
              </div>

              <!-- URL -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Certificate URL *
                </label>
                <input
                  v-model="cert.url"
                  type="url"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="https://..."
                  required
                />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                  Link to certificate or credential page
                </p>
              </div>
            </div>

            <!-- Logo Upload -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Certification Logo
              </label>
              <div class="flex items-center gap-4">
                <!-- Current Logo Preview -->
                <div class="relative w-16 h-16 rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center">
                  <img 
                    v-if="getCertLogoPreview(index)" 
                    :src="getCertLogoPreview(index)" 
                    :alt="cert.name" 
                    class="w-full h-full object-contain"
                  />
                  <svg 
                    v-else
                    class="w-8 h-8 text-neutral-400 dark:text-neutral-500" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>

                <!-- Upload Input -->
                <div class="flex-1">
                  <input
                    :ref="el => certLogoInputs[index] = el"
                    type="file"
                    accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                    class="hidden"
                    @change="handleCertLogoChange(index, $event)"
                  />
                  <BaseButton
                    type="button"
                    button-type="secondary"
                    size="sm"
                    @click="certLogoInputs[index]?.click()"
                  >
                    {{ getCertLogoPreview(index) ? 'Change Logo' : 'Upload Logo' }}
                  </BaseButton>
                  <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-2">
                    PNG, JPG, SVG (max 2MB)
                  </p>
                </div>

                <!-- Remove Logo Button -->
                <BaseButton
                  v-if="getCertLogoPreview(index)"
                  type="button"
                  button-type="danger"
                  size="sm"
                  @click="removeCertLogo(index)"
                >
                  Remove
                </BaseButton>
              </div>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Statistics Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
          Homepage Statistics
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
          These numbers will be displayed on your homepage hero section.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Years Experience -->
          <div>
            <label for="stat-years" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Years Experience
            </label>
            <input
              id="stat-years"
              v-model="formData.statistics.years_experience"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="16+"
            />
          </div>

          <!-- Followers -->
          <div>
            <label for="stat-followers" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Followers
            </label>
            <input
              id="stat-followers"
              v-model="formData.statistics.followers"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="1K"
            />
          </div>

          <!-- Projects Delivered -->
          <div>
            <label for="stat-projects" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Projects Delivered
            </label>
            <input
              id="stat-projects"
              v-model="formData.statistics.projects_delivered"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="50+"
            />
          </div>

          <!-- Cost Savings -->
          <div>
            <label for="stat-cost" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Cost Savings
            </label>
            <input
              id="stat-cost"
              v-model="formData.statistics.cost_savings"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="$2M+"
            />
          </div>

          <!-- Success Rate -->
          <div>
            <label for="stat-success" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Success Rate
            </label>
            <input
              id="stat-success"
              v-model="formData.statistics.success_rate"
              type="text"
              class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              placeholder="95%"
            />
          </div>
        </div>
      </BaseCard>

      <!-- Form Actions -->
      <div class="flex items-center justify-end gap-4">
        <BaseButton
          type="button"
          button-type="secondary"
          @click="resetForm"
          :disabled="isSubmitting"
        >
          Reset
        </BaseButton>
        <BaseButton
          type="submit"
          :disabled="isSubmitting"
        >
          <span v-if="isSubmitting" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
          </span>
          <span v-else>Save Changes</span>
        </BaseButton>
      </div>
    </form>

    <!-- Error Display -->
    <div v-if="error" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
      <p class="text-red-800 dark:text-red-200">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import api from '@/services/api'

const queryClient = useQueryClient()
const settingsStore = useSettingsStore()
const uiStore = useUiStore()

const fileInput = ref(null)
const isSubmitting = ref(false)
const loading = ref(false)
const error = ref(null)
const isLoadingSettings = ref(false)
const availableGalleries = ref([])
const loadingGalleries = ref(false)

// Form data
const formData = ref({
  name: '',
  title: '',
  bio: '',
  profile_photo: null,
  languages: [],
  skills: [],
  experience: [],
  education: [],
  social_links: [],
  certifications: [],
  statistics: {
    years_experience: '16+',
    followers: '1K',
    projects_delivered: '50+',
    cost_savings: '$2M+',
    success_rate: '95%'
  },
  // Hero & About Enhancement fields (added Nov 3, 2025)
  hero_tagline: '',
  availability_note: '',
  trust_strip: {
    years_experience: '17+',
    projects_delivered: '56+',
    clients_served: '25+',
    success_rate: '95%'
  },
  mission: '',
  what_i_do: [],
  approach: '',
  collaboration_modes: []
})

const photoFile = ref(null)
const photoRemoved = ref(false)
const certLogoInputs = ref([])
const certLogoFiles = ref({}) // { index: File }
const certLogosRemoved = ref([]) // [index]

// ── Creator Brand (Watermark + Filename Prefix) ──
const brandLogoInput = ref(null)
const brandLogoFile = ref(null)
const brandLogoRemoved = ref(false)
const brandSubmitting = ref(false)
const brandSlugError = ref('')
const brandFormData = ref({
  creator_brand_logo: null,
  creator_brand_tagline: 'alisadikinma.com',
  creator_brand_slug: 'alisadikinma',
  watermark_opacity: '0.30',
  watermark_enabled: 'false',
})

// Phase I: Telegram notification settings
const telegramSubmitting = ref(false)
const telegramTesting = ref(false)
const telegramTestResult = ref(null)
const telegramFormData = ref({
  telegram_bot_token: '',
  telegram_chat_id: '',
  telegram_enabled: 'false',
  telegram_notify_manifest_needed: 'true',
  telegram_notify_generation_failed: 'true',
  telegram_notify_publish_success: 'false',
})

const telegramTokenMasked = computed(() => {
  const t = settingsStore.telegramSettings?.telegram_bot_token
  return t && t !== telegramFormData.value.telegram_bot_token ? t : null
})

const telegramEnabledBool = computed({
  get: () => telegramFormData.value.telegram_enabled === 'true',
  set: (v) => { telegramFormData.value.telegram_enabled = v ? 'true' : 'false' },
})

async function handleTelegramSubmit() {
  telegramSubmitting.value = true
  telegramTestResult.value = null
  try {
    const payload = { ...telegramFormData.value }
    // Don't send the masked placeholder — only send a real token when the user types one
    if (!payload.telegram_bot_token) delete payload.telegram_bot_token

    await settingsStore.updateTelegramSettings(payload)
    uiStore.showSuccess('Telegram settings saved', 'Saved')
    telegramFormData.value.telegram_bot_token = '' // clear form — masked token now shown below
  } catch (err) {
    uiStore.showError(err.response?.data?.message || err.message || 'Failed to save telegram settings', 'Save Failed')
  } finally {
    telegramSubmitting.value = false
  }
}

async function handleTelegramTest() {
  telegramTesting.value = true
  telegramTestResult.value = null
  try {
    const result = await settingsStore.sendTelegramTestMessage()
    telegramTestResult.value = result.success
      ? { success: true, message: '✓ Test message sent successfully — check your Telegram' }
      : { success: false, message: '✗ ' + (result.error || 'Test failed') }
    setTimeout(() => { telegramTestResult.value = null }, 8000)
  } finally {
    telegramTesting.value = false
  }
}

// Opacity slider binds as integer percentage; convert to/from stored string
const watermarkOpacityPct = computed({
  get: () => Math.round(parseFloat(brandFormData.value.watermark_opacity || '0.30') * 100),
  set: (pct) => {
    const clamped = Math.max(5, Math.min(95, Number(pct) || 30))
    brandFormData.value.watermark_opacity = (clamped / 100).toFixed(2)
  },
})

const watermarkEnabledBool = computed({
  get: () => brandFormData.value.watermark_enabled === 'true' || brandFormData.value.watermark_enabled === true,
  set: (val) => { brandFormData.value.watermark_enabled = val ? 'true' : 'false' },
})

const brandLogoPreviewUrl = computed(() => {
  if (brandLogoRemoved.value) return null
  if (brandLogoFile.value) return URL.createObjectURL(brandLogoFile.value)
  const logo = brandFormData.value.creator_brand_logo
  if (!logo) return null
  if (logo.startsWith('http://') || logo.startsWith('https://')) return logo
  const apiBase = import.meta.env.VITE_API_URL || import.meta.env.VITE_API_BASE_URL || ''
  return apiBase.replace(/\/api\/?$/, '') + logo
})

function handleBrandLogoChange(event) {
  const file = event.target.files[0]
  if (!file) return
  if (file.size > 5 * 1024 * 1024) {
    uiStore.showError('Logo file must not exceed 5MB', 'Upload Error')
    return
  }
  brandLogoFile.value = file
  brandLogoRemoved.value = false
}

function removeBrandLogo() {
  brandLogoFile.value = null
  brandLogoRemoved.value = true
  brandFormData.value.creator_brand_logo = null
  if (brandLogoInput.value) brandLogoInput.value.value = ''
}

function validateBrandSlug() {
  const slug = (brandFormData.value.creator_brand_slug || '').trim()
  if (!slug) {
    brandSlugError.value = 'Slug is required — used as filename prefix'
    return false
  }
  if (!/^[a-z0-9-]+$/.test(slug)) {
    brandSlugError.value = 'Lowercase letters, numbers, hyphens only (no spaces)'
    return false
  }
  brandSlugError.value = ''
  return true
}

async function handleBrandSubmit() {
  if (!validateBrandSlug()) return

  brandSubmitting.value = true
  try {
    const data = new FormData()
    data.append('_method', 'PUT')
    if (brandLogoFile.value) data.append('creator_brand_logo', brandLogoFile.value)
    if (brandFormData.value.creator_brand_tagline) data.append('creator_brand_tagline', brandFormData.value.creator_brand_tagline)
    if (brandFormData.value.creator_brand_slug) data.append('creator_brand_slug', brandFormData.value.creator_brand_slug)
    data.append('watermark_opacity', brandFormData.value.watermark_opacity)
    data.append('watermark_enabled', brandFormData.value.watermark_enabled)

    await settingsStore.updateCreatorBrandSettings(data)
    uiStore.showSuccess('Creator brand settings updated successfully', 'Saved')

    // Refresh store state back into formData
    const fresh = settingsStore.creatorBrandSettings
    brandFormData.value = {
      creator_brand_logo: fresh.creator_brand_logo,
      creator_brand_tagline: fresh.creator_brand_tagline || 'alisadikinma.com',
      creator_brand_slug: fresh.creator_brand_slug || 'alisadikinma',
      watermark_opacity: fresh.watermark_opacity || '0.30',
      watermark_enabled: fresh.watermark_enabled || 'false',
    }
    brandLogoFile.value = null
    brandLogoRemoved.value = false
    if (brandLogoInput.value) brandLogoInput.value.value = ''
  } catch (err) {
    uiStore.showError(err.response?.data?.message || err.message || 'Failed to save creator brand', 'Save Failed')
  } finally {
    brandSubmitting.value = false
  }
}

// Current photo URL
const currentPhotoUrl = computed(() => {
  if (photoRemoved.value) return null
  if (photoFile.value) {
    return URL.createObjectURL(photoFile.value)
  }
  if (formData.value.profile_photo) {
    const photo = formData.value.profile_photo
    if (photo.startsWith('http://') || photo.startsWith('https://')) {
      return photo
    }
    if (photo.startsWith('/uploads/')) {
      return import.meta.env.VITE_API_URL.replace('/api', '') + photo
    }
    if (!photo.startsWith('/')) {
      return import.meta.env.VITE_API_URL.replace('/api', '') + '/uploads/' + photo
    }
    return import.meta.env.VITE_API_URL.replace('/api', '') + photo
  }
  return null
})

// Languages methods
function addLanguage() {
  formData.value.languages.push('')
}

function removeLanguage(index) {
  formData.value.languages.splice(index, 1)
}

// Skills methods
function addSkill() {
  formData.value.skills.push('')
}

function removeSkill(index) {
  formData.value.skills.splice(index, 1)
}

// Experience methods
function addExperience() {
  formData.value.experience.push({
    title: '',
    company: '',
    company_logo: null,
    company_url: '',
    location: '',
    start_date: '',
    end_date: '',
    description: '',
    current: false,
    gallery_ids: []
  })
}

function removeExperience(index) {
  formData.value.experience.splice(index, 1)
}

// Education methods
function addEducation() {
  formData.value.education.push({
    degree: '',
    institution: '',
    location: '',
    start_year: '',
    end_year: '',
    description: ''
  })
}

function removeEducation(index) {
  formData.value.education.splice(index, 1)
}

// Social links methods
function addSocialLink() {
  formData.value.social_links.push({
    platform: '',
    url: '',
    icon: ''
  })
}

function removeSocialLink(index) {
  formData.value.social_links.splice(index, 1)
}

// Certifications methods
function addCertification() {
  formData.value.certifications.push({
    name: '',
    url: '',
    logo: null
  })
}

function removeCertification(index) {
  formData.value.certifications.splice(index, 1)
  // Clean up logo files
  delete certLogoFiles.value[index]
  certLogosRemoved.value = certLogosRemoved.value.filter(i => i !== index)
}

// Certification logo handling
function handleCertLogoChange(index, event) {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 2 * 1024 * 1024) {
      uiStore.showError('Logo file size must not exceed 2MB', 'Upload Error')
      return
    }
    certLogoFiles.value[index] = file
    certLogosRemoved.value = certLogosRemoved.value.filter(i => i !== index)
  }
}

function removeCertLogo(index) {
  certLogoFiles.value[index] = null
  certLogosRemoved.value.push(index)
  if (certLogoInputs.value[index]) {
    certLogoInputs.value[index].value = ''
  }
}

function getCertLogoPreview(index) {
  console.log(`[AboutSettings] getCertLogoPreview(${index}):`, {
    hasFile: !!certLogoFiles.value[index],
    removed: certLogosRemoved.value.includes(index),
    dbLogo: formData.value.certifications[index]?.logo
  })
  
  if (certLogosRemoved.value.includes(index)) return null
  
  if (certLogoFiles.value[index]) {
    const url = URL.createObjectURL(certLogoFiles.value[index])
    console.log(`[AboutSettings] Preview from file:`, url)
    return url
  }
  
  if (formData.value.certifications[index]?.logo) {
    const logo = formData.value.certifications[index].logo
    console.log(`[AboutSettings] DB logo path:`, logo)
    
    if (logo.startsWith('http://') || logo.startsWith('https://')) {
      return logo
    }
    
    // FIXED: Path already includes /uploads/, don't double it
    const fullUrl = import.meta.env.VITE_API_URL.replace('/api', '') + logo
    console.log(`[AboutSettings] Preview from DB:`, fullUrl)
    return fullUrl
  }
  
  console.warn(`[AboutSettings] No logo for cert #${index}`)
  return null
}

// Photo handling
function handlePhotoChange(event) {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      uiStore.showError('File size must not exceed 5MB', 'Upload Error')
      return
    }
    photoFile.value = file
    photoRemoved.value = false
  }
}

function removePhoto() {
  photoFile.value = null
  photoRemoved.value = true
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function handleImageError(event) {
  console.warn('Failed to load profile image:', {
    src: event.target.src,
    rawValue: formData.value.profile_photo
  })
  photoRemoved.value = true
}

// Form submission
async function handleSubmit() {
  isSubmitting.value = true
  error.value = null

  try {
    // Validate required fields
    const hasInvalidExperience = formData.value.experience.some(
      exp => !exp.title || !exp.company || !exp.start_date
    )
    const hasInvalidEducation = formData.value.education.some(
      edu => !edu.degree || !edu.institution || !edu.start_year
    )
    const hasInvalidSocialLinks = formData.value.social_links.some(
      link => !link.platform || !link.url
    )

    if (hasInvalidExperience) {
      throw new Error('Please fill in all required experience fields (title, company, start date)')
    }
    if (hasInvalidEducation) {
      throw new Error('Please fill in all required education fields (degree, institution, start year)')
    }
    if (hasInvalidSocialLinks) {
      throw new Error('Please fill in all required social link fields (platform, URL)')
    }

    // Filter out empty arrays
    const cleanedLanguages = formData.value.languages.filter(lang => lang.trim() !== '')
    const cleanedSkills = formData.value.skills.filter(skill => skill.trim() !== '')
    const cleanedCertifications = formData.value.certifications.filter(cert => cert.name.trim() !== '' && cert.url.trim() !== '')

    // Prepare FormData
    const data = new FormData()
    
    // Laravel method spoofing for FormData
    data.append('_method', 'PUT')

    // Basic fields
    if (formData.value.name) data.append('name', formData.value.name)
    if (formData.value.title) data.append('title', formData.value.title)
    if (formData.value.bio) data.append('bio', formData.value.bio)

    // Profile photo
    if (photoFile.value) {
      data.append('profile_photo', photoFile.value)
    }

    // Certification logos
    Object.keys(certLogoFiles.value).forEach(index => {
      if (certLogoFiles.value[index]) {
        data.append(`certification_logo_${index}`, certLogoFiles.value[index])
      }
    })

    // Arrays as JSON strings
    if (cleanedLanguages.length > 0) {
      data.append('languages', JSON.stringify(cleanedLanguages))
    }
    if (cleanedSkills.length > 0) {
      data.append('skills', JSON.stringify(cleanedSkills))
    }
    if (formData.value.experience.length > 0) {
      data.append('experience', JSON.stringify(formData.value.experience))
    }
    if (formData.value.education.length > 0) {
      data.append('education', JSON.stringify(formData.value.education))
    }
    if (formData.value.social_links.length > 0) {
      data.append('social_links', JSON.stringify(formData.value.social_links))
    }
    if (cleanedCertifications.length > 0) {
      data.append('certifications', JSON.stringify(cleanedCertifications))
    }
    if (formData.value.statistics) {
      data.append('statistics', JSON.stringify(formData.value.statistics))
    }

    // Hero & About Enhancement fields (added Nov 3, 2025)
    if (formData.value.hero_tagline) {
      data.append('hero_tagline', formData.value.hero_tagline)
    }
    if (formData.value.availability_note) {
      data.append('availability_note', formData.value.availability_note)
    }
    if (formData.value.trust_strip) {
      data.append('trust_strip', JSON.stringify(formData.value.trust_strip))
    }
    if (formData.value.mission) {
      data.append('mission', formData.value.mission)
    }
    if (formData.value.what_i_do && formData.value.what_i_do.length > 0) {
      data.append('what_i_do', JSON.stringify(formData.value.what_i_do))
    }
    if (formData.value.approach) {
      data.append('approach', formData.value.approach)
    }
    if (formData.value.collaboration_modes && formData.value.collaboration_modes.length > 0) {
      data.append('collaboration_modes', JSON.stringify(formData.value.collaboration_modes))
    }

    await settingsStore.updateAboutSettings(data)
    
    // FORCE REFETCH ALL INSTANCES (Nov 4, 2025)
    // Step 1: Remove from cache completely
    queryClient.removeQueries({ queryKey: ['about-settings'] })
    console.log('✅ [AboutSettings] Cache cleared')
    
    // Step 2: Force refetch ALL active queries (including homepage if open)
    await queryClient.refetchQueries({ 
      queryKey: ['about-settings'],
      type: 'all'
    })
    console.log('✅ [AboutSettings] All instances refetched')
    
    console.log('🎉 [AboutSettings] Homepage will show changes immediately!')
    
    uiStore.showSuccess(
      'About settings updated successfully. Refresh homepage to see changes!', 
      'Settings Saved'
    )

    // Reload settings from backend to get fresh data
    await loadSettings()

    // Reset photo upload state
    photoFile.value = null
    photoRemoved.value = false
    certLogoFiles.value = {}
    certLogosRemoved.value = []
    if (fileInput.value) {
      fileInput.value.value = ''
    }
  } catch (err) {
    console.error('❌ Failed to update about settings:', err)
    error.value = err.message || err.response?.data?.message || 'Failed to update settings. Please try again.'
    uiStore.showError(error.value, 'Update Failed', 0)
  } finally {
    isSubmitting.value = false
  }
}

// Reset form
function resetForm() {
  loadSettings()
  photoFile.value = null
  photoRemoved.value = false
  if (fileInput.value) {
    fileInput.value.value = ''
  }
  error.value = null
}

// Load settings
async function loadSettings() {
  if (isLoadingSettings.value) {
    return
  }
  
  isLoadingSettings.value = true
  loading.value = true
  error.value = null

  try {
    await Promise.all([
      settingsStore.fetchAboutSettings(),
      settingsStore.fetchCreatorBrandSettings().catch((err) => {
        // Non-fatal: creator_brand seeder may not have run on older installs
        console.warn('[AboutSettings] creator_brand fetch failed — using defaults', err)
      }),
      settingsStore.fetchTelegramSettings().catch((err) => {
        // Non-fatal: telegram seeder may not have run on older installs
        console.warn('[AboutSettings] telegram fetch failed — using defaults', err)
      }),
    ])

    // Populate creator brand form data
    const cb = settingsStore.creatorBrandSettings || {}
    brandFormData.value = {
      creator_brand_logo: cb.creator_brand_logo || null,
      creator_brand_tagline: cb.creator_brand_tagline || 'alisadikinma.com',
      creator_brand_slug: cb.creator_brand_slug || 'alisadikinma',
      watermark_opacity: cb.watermark_opacity || '0.30',
      watermark_enabled: cb.watermark_enabled || 'false',
    }

    // Populate telegram form data (Phase I)
    const tg = settingsStore.telegramSettings || {}
    telegramFormData.value = {
      telegram_bot_token: '', // never prefill — masked token shown via telegramTokenMasked
      telegram_chat_id: tg.telegram_chat_id || '',
      telegram_enabled: tg.telegram_enabled || 'false',
      telegram_notify_manifest_needed: tg.telegram_notify_manifest_needed || 'true',
      telegram_notify_generation_failed: tg.telegram_notify_generation_failed || 'true',
      telegram_notify_publish_success: tg.telegram_notify_publish_success || 'false',
    }

    // Populate form data
    const experiences = JSON.parse(JSON.stringify(settingsStore.aboutSettings.experience || []))
    experiences.forEach(exp => {
      if (!exp.gallery_ids) exp.gallery_ids = []
      if (!exp.company_url) exp.company_url = ''
    })

    formData.value = {
      name: settingsStore.aboutSettings.name || '',
      title: settingsStore.aboutSettings.title || '',
      bio: settingsStore.aboutSettings.bio || '',
      profile_photo: settingsStore.aboutSettings.profile_photo || null,
      languages: [...(settingsStore.aboutSettings.languages || [])],
      skills: [...(settingsStore.aboutSettings.skills || [])],
      experience: experiences,
      education: JSON.parse(JSON.stringify(settingsStore.aboutSettings.education || [])),
      social_links: JSON.parse(JSON.stringify(settingsStore.aboutSettings.social_links || [])),
      certifications: JSON.parse(JSON.stringify(settingsStore.aboutSettings.certifications || [])),
      statistics: {
        years_experience: settingsStore.aboutSettings.statistics?.years_experience || '16+',
        followers: settingsStore.aboutSettings.statistics?.followers || '1K',
        projects_delivered: settingsStore.aboutSettings.statistics?.projects_delivered || '50+',
        cost_savings: settingsStore.aboutSettings.statistics?.cost_savings || '$2M+',
        success_rate: settingsStore.aboutSettings.statistics?.success_rate || '95%'
      },
      // Hero & About Enhancement fields (added Nov 3, 2025)
      hero_tagline: settingsStore.aboutSettings.hero_tagline || '',
      availability_note: settingsStore.aboutSettings.availability_note || '',
      trust_strip: settingsStore.aboutSettings.trust_strip || {
        years_experience: '17+',
        projects_delivered: '56+',
        clients_served: '25+',
        success_rate: '95%'
      },
      mission: settingsStore.aboutSettings.mission || '',
      what_i_do: settingsStore.aboutSettings.what_i_do || [],
      approach: settingsStore.aboutSettings.approach || '',
      collaboration_modes: settingsStore.aboutSettings.collaboration_modes || []
    }
  } catch (err) {
    console.error('❌ Failed to load settings:', err)
    error.value = 'Failed to load settings. Please refresh the page.'
    uiStore.showError(error.value, 'Load Failed')
  } finally {
    loading.value = false
    isLoadingSettings.value = false
  }
}

// Fetch galleries for selection
async function fetchGalleries() {
  loadingGalleries.value = true
  try {
    const response = await api.get('/galleries', {
      params: {
        per_page: 100,
        is_active: 1
      }
    })
    if (response.data.success && response.data.data) {
      availableGalleries.value = response.data.data
    }
  } catch (err) {
    console.error('Failed to fetch galleries:', err)
  } finally {
    loadingGalleries.value = false
  }
}

// Load on mount
onMounted(() => {
  loadSettings()
  fetchGalleries()
})
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
