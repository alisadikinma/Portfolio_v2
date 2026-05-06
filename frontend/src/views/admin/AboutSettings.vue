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

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="telegramFormData.telegram_notify_segment_failed === 'true'"
                type="checkbox"
                :disabled="!telegramEnabledBool"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => telegramFormData.telegram_notify_segment_failed = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Alert on segment retry exhausted (3 attempts, image stuck)
              </span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="telegramFormData.telegram_notify_cover_critical === 'true'"
                type="checkbox"
                :disabled="!telegramEnabledBool"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => telegramFormData.telegram_notify_cover_critical = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Alert on cover-critical failure (idea blocked — cover image terminal)
              </span>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="telegramFormData.telegram_notify_translate_failed === 'true'"
                type="checkbox"
                :disabled="!telegramEnabledBool"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => telegramFormData.telegram_notify_translate_failed = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Alert on auto-translate exhausted (cron fell back to monolingual publish)
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

      <!-- Email — SMTP Settings Card (Newsletter system, May 2026) -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          Email — SMTP Settings
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
          Outgoing email config used by the weekly newsletter digest, contact-form notifications, and admin test sends.
          For Hostinger mailbox <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">aiagent@alisadikinma.com</code>:
          host <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">smtp.hostinger.com</code>, port 465, SSL.
          <strong>Password is encrypted at rest</strong> (Laravel Crypt) and never returned in API responses.
        </p>

        <!-- Active driver diagnostic — surfaced when admin saves SMTP but
             real delivery still doesn't work (driver still 'log' etc) -->
        <div
          v-if="mailEffective"
          class="mb-6 p-3 rounded-md text-sm flex items-start gap-2"
          :class="mailEffective.driver === 'smtp'
            ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800/50'
            : 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50'"
        >
          <span class="font-mono text-xs uppercase tracking-wider px-1.5 py-0.5 rounded font-semibold"
                :class="mailEffective.driver === 'smtp'
                  ? 'bg-green-200 dark:bg-green-800/50 text-green-900 dark:text-green-200'
                  : 'bg-amber-200 dark:bg-amber-800/50 text-amber-900 dark:text-amber-200'">
            {{ mailEffective.driver }}
          </span>
          <span class="flex-1">
            <strong>Active driver: {{ mailEffective.driver }}</strong>
            <template v-if="mailEffective.driver === 'smtp'">
              — sends go to <code class="text-xs">{{ mailEffective.host }}:{{ mailEffective.port }}</code> as <code class="text-xs">{{ mailEffective.from_address }}</code>.
              <span v-if="!mailEffective.password_set" class="block mt-1 text-amber-700 dark:text-amber-400">⚠️ Password not set yet — saves below before test send.</span>
            </template>
            <template v-else-if="mailEffective.driver === 'log'">
              — emails go to <code class="text-xs">storage/logs/laravel.log</code>, NOT real inboxes. Save SMTP form below to switch to real delivery.
            </template>
            <template v-else>
              — driver "<code>{{ mailEffective.driver }}</code>" doesn't deliver. Configure SMTP form below.
            </template>
          </span>
        </div>

        <form @submit.prevent="handleMailSubmit" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="mail_host" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                SMTP Host
              </label>
              <input
                id="mail_host"
                v-model="mailFormData.mail_host"
                type="text"
                placeholder="smtp.hostinger.com"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <div>
              <label for="mail_port" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Port
              </label>
              <input
                id="mail_port"
                v-model="mailFormData.mail_port"
                type="text"
                inputmode="numeric"
                pattern="\d*"
                placeholder="465"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
              <p class="text-xs text-neutral-500 mt-1">465 = SSL · 587 = TLS · 25 = none</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="mail_username" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Username (mailbox address)
              </label>
              <input
                id="mail_username"
                v-model="mailFormData.mail_username"
                type="text"
                placeholder="aiagent@alisadikinma.com"
                autocomplete="username"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <div>
              <label for="mail_encryption" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Encryption
              </label>
              <select
                id="mail_encryption"
                v-model="mailFormData.mail_encryption"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option value="ssl">SSL (port 465)</option>
                <option value="tls">TLS (port 587)</option>
                <option value="none">None (insecure, port 25)</option>
              </select>
            </div>
          </div>

          <div>
            <label for="mail_password" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
              Password
              <span v-if="mailPasswordConfigured" class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                ✓ Configured
              </span>
            </label>
            <input
              id="mail_password"
              v-model="mailFormData.mail_password"
              type="password"
              autocomplete="new-password"
              :placeholder="mailPasswordConfigured ? 'Leave blank to keep current password' : 'Paste mailbox password here'"
              class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
            <p class="text-xs text-neutral-500 mt-1">
              Get from Hostinger panel → Email → {{ mailFormData.mail_username || 'mailbox' }} → Manage. Encrypted before storage; never visible after save.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="mail_from_address" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                From Address
              </label>
              <input
                id="mail_from_address"
                v-model="mailFormData.mail_from_address"
                type="email"
                placeholder="aiagent@alisadikinma.com"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
              <p class="text-xs text-neutral-500 mt-1">Must match the mailbox above for SPF/DKIM to validate.</p>
            </div>
            <div>
              <label for="mail_from_name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                From Name
              </label>
              <input
                id="mail_from_name"
                v-model="mailFormData.mail_from_name"
                type="text"
                placeholder="Ali Sadikin"
                maxlength="120"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
            </div>
          </div>

          <div class="pt-3 border-t border-neutral-200 dark:border-neutral-700 space-y-4">
            <BaseButton
              type="submit"
              :disabled="mailSubmitting"
              button-type="primary"
            >
              {{ mailSubmitting ? 'Saving...' : 'Save SMTP Settings' }}
            </BaseButton>

            <div class="flex flex-wrap items-end gap-3 pt-3 border-t border-neutral-200 dark:border-neutral-700">
              <div class="flex-1 min-w-[260px]">
                <label for="mail_test_recipient" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                  Send test to
                </label>
                <input
                  id="mail_test_recipient"
                  v-model="mailTestRecipient"
                  type="email"
                  :placeholder="authStore.user?.email ? `Default: ${authStore.user.email}` : 'recipient@example.com'"
                  class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
                <p class="text-xs text-neutral-500 mt-1">
                  Type any email you have access to (e.g. your Gmail). Defaults to your admin user email if blank.
                </p>
              </div>

              <BaseButton
                type="button"
                :disabled="mailTesting || !mailPasswordConfigured"
                :loading="mailTesting"
                button-type="secondary"
                @click="handleMailTest"
              >
                📤 Send test email
              </BaseButton>
            </div>

            <p v-if="mailTestResult" :class="mailTestResult.success ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-sm leading-relaxed">
              {{ mailTestResult.message }}
            </p>
          </div>
        </form>
      </BaseCard>

      <!-- CV Master Export Card (schema_version 2.0.0, May 2026) -->
      <!-- Drives /api/cv/export response — feeds the jobhunter platform.    -->
      <!-- Four sections: 3 summary_variants + 3 JSON blobs (work_experience, -->
      <!-- skills_matrix, education). Each section validates inline on save. -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          CV Master Export — for jobhunter Agent
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
          Drives the JSON returned by
          <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">GET /api/cv/export</code>
          (schema_version <strong>2.0.0</strong>) — consumed by the jobhunter platform's
          <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">cv-tailor</code> /
          <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">job-score</code> skills.
          Operator-edited values survive deploys (idempotent seeder uses
          <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">firstOrCreate</code>).
          See the
          <router-link to="/admin/automation/docs" class="text-blue-600 dark:text-blue-400 underline">API Docs page</router-link>
          for the full response shape.
        </p>

        <form @submit.prevent="handleCvSubmit" class="space-y-8">
          <!-- summary_variants — 3 labeled textareas -->
          <div>
            <div class="mb-3 flex items-baseline justify-between">
              <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                Summary Variants
              </h3>
              <span class="text-xs text-neutral-500 dark:text-neutral-400">basics.summary_variants</span>
            </div>
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-4">
              Three opening paragraphs — jobhunter scores each scraped role against all three and picks the strongest match.
              Keep each ~3-5 sentences, lead with the strongest concrete signal (deployments, names, $ amounts).
            </p>
            <div class="space-y-4">
              <div v-for="variant in cvVariantOrder" :key="variant">
                <label :for="`cv-summary-${variant}`" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                  <span class="inline-flex items-center gap-2">
                    <span class="inline-block w-2 h-2 rounded-full" :class="cvVariantDot(variant)"></span>
                    {{ cvVariantLabel(variant) }}
                  </span>
                </label>
                <textarea
                  :id="`cv-summary-${variant}`"
                  v-model="cvVariants[variant]"
                  rows="4"
                  class="w-full rounded-md border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                  :placeholder="cvVariantPlaceholder(variant)"
                />
              </div>
            </div>
            <p v-if="cvErrors.summary_variants" class="mt-2 text-xs text-red-600 dark:text-red-400">
              {{ cvErrors.summary_variants }}
            </p>
          </div>

          <!-- work_experience — JSON textarea -->
          <div>
            <div class="mb-3 flex items-baseline justify-between">
              <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                Work Experience
              </h3>
              <span class="text-xs text-neutral-500 dark:text-neutral-400">work[]</span>
            </div>
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-2">
              Employment history (separate from <code class="text-[11px] bg-neutral-100 dark:bg-neutral-800 px-1 rounded">projects[]</code>).
              JSON array of <code class="text-[11px]">{company, position, start_date, end_date, summary, highlights[], tech_stack[]}</code> entries.
              Without this, ATS scorers compute years-of-experience by summing all 56 projects → inflated to ~50 years.
            </p>
            <textarea
              v-model="cvWorkJson"
              rows="14"
              spellcheck="false"
              class="w-full rounded-md border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900 text-xs font-mono text-neutral-900 dark:text-neutral-100 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder='[{"company":"INDUSIA.ai","position":"Founder","start_date":"2024-01","end_date":null,"summary":"...","highlights":[],"tech_stack":["Python"]}]'
            />
            <p v-if="cvErrors.work_experience" class="mt-2 text-xs text-red-600 dark:text-red-400">
              {{ cvErrors.work_experience }}
            </p>
          </div>

          <!-- skills_matrix — JSON textarea -->
          <div>
            <div class="mb-3 flex items-baseline justify-between">
              <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                Skills Matrix
              </h3>
              <span class="text-xs text-neutral-500 dark:text-neutral-400">skills{}</span>
            </div>
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-2">
              Categorized object — JSON object mapping
              <code class="text-[11px]">category &rarr; string[]</code>.
              Suggested keys: <code class="text-[11px]">languages</code>, <code class="text-[11px]">frameworks</code>,
              <code class="text-[11px]">ai_tools</code>, <code class="text-[11px]">cloud</code>,
              <code class="text-[11px]">databases</code>, <code class="text-[11px]">infrastructure</code>,
              <code class="text-[11px]">domain</code>. Categories are freeform but should stay stable across responses
              (don't rename <code class="text-[11px]">ai_tools</code> &rarr; <code class="text-[11px]">ai</code> later — breaks consumer caching).
            </p>
            <textarea
              v-model="cvSkillsJson"
              rows="12"
              spellcheck="false"
              class="w-full rounded-md border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900 text-xs font-mono text-neutral-900 dark:text-neutral-100 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder='{"languages":["Python","TypeScript"],"frameworks":["FastAPI","Next.js 15"],"ai_tools":["Claude Sonnet 4.6","VEO 3"],"cloud":["AWS"],"domain":["Computer Vision"]}'
            />
            <p v-if="cvErrors.skills_matrix" class="mt-2 text-xs text-red-600 dark:text-red-400">
              {{ cvErrors.skills_matrix }}
            </p>
          </div>

          <!-- education — JSON textarea -->
          <div>
            <div class="mb-3 flex items-baseline justify-between">
              <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                Education
              </h3>
              <span class="text-xs text-neutral-500 dark:text-neutral-400">education[]</span>
            </div>
            <p class="text-xs text-neutral-600 dark:text-neutral-400 mb-2">
              JSON array of <code class="text-[11px]">{institution, area, study_type, start_date, end_date, score, courses[]}</code> entries.
              Empty list <code class="text-[11px]">[]</code> is valid (consumer renders nothing — no error).
            </p>
            <textarea
              v-model="cvEducationJson"
              rows="8"
              spellcheck="false"
              class="w-full rounded-md border border-neutral-300 dark:border-neutral-600 bg-neutral-50 dark:bg-neutral-900 text-xs font-mono text-neutral-900 dark:text-neutral-100 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              placeholder="[]"
            />
            <p v-if="cvErrors.education" class="mt-2 text-xs text-red-600 dark:text-red-400">
              {{ cvErrors.education }}
            </p>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <BaseButton type="submit" :disabled="cvSubmitting" variant="primary">
              <span v-if="cvSubmitting">Saving…</span>
              <span v-else>Save CV Settings</span>
            </BaseButton>
            <a
              :href="apiBaseOrigin + '/api/cv/export'"
              target="_blank"
              rel="noopener"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline"
              title="Open the live JSON response in a new tab (requires a cv:read token)"
            >
              View live /api/cv/export →
            </a>
          </div>
        </form>
      </BaseCard>

      <!-- LinkedIn Integration Card -->
      <BaseCard>
        <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
          LinkedIn Integration — Direct OAuth
        </h2>
        <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
          Connect a LinkedIn account so blog posts can auto-convert + auto-publish as algorithm-optimized LinkedIn posts.
          Register a LinkedIn Developer App at
          <a href="https://linkedin.com/developers" target="_blank" class="text-amber-600 dark:text-amber-400 hover:underline">linkedin.com/developers</a>
          with products <strong>"Share on LinkedIn"</strong> + <strong>"Sign In with LinkedIn using OpenID Connect"</strong> (scope: <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">openid profile email w_member_social</code>),
          then paste <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">LINKEDIN_OAUTH_CLIENT_ID</code> + <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">LINKEDIN_OAUTH_CLIENT_SECRET</code> into VPS <code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">.env</code>.
        </p>

        <div v-if="linkedinLoading" class="py-6 text-center text-neutral-500">
          Loading LinkedIn status…
        </div>

        <div v-else class="space-y-5">
          <!-- OAuth flash messages -->
          <div v-if="linkedinOauthFlash" class="rounded-lg px-4 py-3 text-sm" :class="{
            'bg-emerald-500/10 border border-emerald-500/40 text-emerald-700 dark:text-emerald-400': linkedinOauthFlash.type === 'success',
            'bg-red-500/10 border border-red-500/40 text-red-700 dark:text-red-400': linkedinOauthFlash.type === 'error',
          }">
            {{ linkedinOauthFlash.message }}
          </div>

          <!-- State 1: OAuth app NOT configured -->
          <div
            v-if="!linkedinOauthConfigured"
            class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4"
          >
            <p class="font-semibold text-amber-700 dark:text-amber-400 mb-2">⚠️ OAuth app not configured</p>
            <p class="text-sm text-neutral-700 dark:text-neutral-300 mb-2">
              Set these env vars on the VPS and restart the queue workers:
            </p>
            <pre class="text-xs bg-neutral-900 text-neutral-100 rounded p-3 overflow-x-auto">LINKEDIN_OAUTH_CLIENT_ID=...
LINKEDIN_OAUTH_CLIENT_SECRET=...
LINKEDIN_OAUTH_REDIRECT_URI=https://alisadikinma.com/api/admin/linkedin/oauth/callback</pre>
          </div>

          <!-- State 2: configured + connected -->
          <div v-else-if="linkedinAccounts.length > 0" class="space-y-3">
            <div
              v-for="account in linkedinAccounts"
              :key="account.id"
              class="rounded-lg border border-neutral-200 dark:border-neutral-700 p-4 flex items-start justify-between gap-3"
            >
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span v-if="!account.is_access_token_expired" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                  <span v-else class="w-2 h-2 rounded-full bg-red-500"></span>
                  <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ account.display_name }}</span>
                  <span v-if="account.is_access_token_expired" class="text-xs text-red-500 font-medium">Token expired</span>
                  <span v-else-if="account.needs_refresh" class="text-xs text-amber-500 font-medium">Needs refresh</span>
                </div>
                <p class="text-xs text-neutral-500 font-mono break-all mt-1">{{ account.person_urn }}</p>
                <p class="text-xs text-neutral-500 mt-1">
                  Token valid until: {{ formatDate(account.access_token_expires_at) }}
                </p>
                <p v-if="linkedinFormData.linkedin_last_test_connection_result" class="text-xs text-neutral-500 mt-1">
                  Last test: {{ formatDate(linkedinFormData.linkedin_last_test_connection_at) }} — {{ linkedinFormData.linkedin_last_test_connection_result }}
                </p>
              </div>
              <div class="flex flex-col gap-2 shrink-0">
                <BaseButton
                  type="button"
                  button-type="secondary"
                  :disabled="linkedinTesting"
                  @click="handleLinkedInTest(account.id)"
                >
                  {{ linkedinTesting ? '…' : 'Test' }}
                </BaseButton>
                <BaseButton
                  type="button"
                  button-type="secondary"
                  :disabled="linkedinSubmitting"
                  @click="handleLinkedInDisconnect(account.id)"
                >
                  Disconnect
                </BaseButton>
              </div>
            </div>

            <div v-if="linkedinTestResult" class="text-sm" :class="linkedinTestResult.success ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
              {{ linkedinTestResult.message }}
            </div>

            <BaseButton
              type="button"
              button-type="secondary"
              :disabled="linkedinSubmitting"
              @click="handleLinkedInConnect"
            >
              + Connect another account
            </BaseButton>
          </div>

          <!-- State 3: configured but not connected -->
          <div v-else class="rounded-lg border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-sm text-neutral-700 dark:text-neutral-300 mb-3">
              OAuth app configured — no LinkedIn accounts connected yet.
            </p>
            <BaseButton
              type="button"
              button-type="primary"
              :disabled="linkedinSubmitting"
              @click="handleLinkedInConnect"
            >
              Connect LinkedIn Account
            </BaseButton>
          </div>

          <!-- Publishing controls -->
          <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700 space-y-4">
            <h3 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">
              Publishing Controls
            </h3>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="linkedinFormData.linkedin_auto_publish === 'true'"
                type="checkbox"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => linkedinFormData.linkedin_auto_publish = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                Enable auto-publish (master kill-switch)
              </span>
            </label>
            <p class="text-xs text-neutral-500 -mt-2 pl-7">
              When OFF, drafts that pass validation stop at <code class="text-[10px] bg-neutral-100 dark:bg-neutral-800 px-1 rounded">awaiting_publish</code> and never fire the cancel-window timer.
            </p>

            <div>
              <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Depth Score threshold
                <span class="font-mono text-amber-500 ml-2">{{ linkedinFormData.linkedin_depth_score_threshold }} / 100</span>
              </label>
              <input
                v-model="linkedinFormData.linkedin_depth_score_threshold"
                type="range"
                min="60"
                max="95"
                class="w-full accent-amber-500"
              >
              <p class="text-xs text-neutral-500 mt-1">
                Drafts scoring below this go to <code class="text-[10px] bg-neutral-100 dark:bg-neutral-800 px-1 rounded">manual_review</code>. Recommended 80 (quality gate), 70 (relaxed during ramp-up).
              </p>
            </div>

            <div>
              <label for="linkedin_cancel_window_minutes" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-1">
                Cancel window (minutes)
              </label>
              <input
                id="linkedin_cancel_window_minutes"
                v-model="linkedinFormData.linkedin_cancel_window_minutes"
                type="number"
                min="1"
                max="1440"
                class="w-full px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:outline-none focus:border-amber-500"
              >
              <p class="text-xs text-neutral-500 mt-1">
                Time between "awaiting_publish" and actual publish. Allows last-minute cancel via Telegram.
              </p>
            </div>
          </div>

          <!-- First comment automation -->
          <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700 space-y-3">
            <h3 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300 uppercase tracking-wider">
              First Comment Automation
            </h3>

            <label class="flex items-center gap-3 cursor-pointer">
              <input
                :checked="linkedinFormData.linkedin_first_comment_enabled === 'true'"
                type="checkbox"
                class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                @change="e => linkedinFormData.linkedin_first_comment_enabled = e.target.checked ? 'true' : 'false'"
              >
              <span class="text-sm text-neutral-700 dark:text-neutral-300">
                Auto-post blog link as first comment (avoids 60% reach penalty on body links)
              </span>
            </label>

            <div class="pl-7">
              <label for="linkedin_first_comment_delay_seconds" class="block text-xs text-neutral-600 dark:text-neutral-400 mb-1">
                Delay (seconds)
              </label>
              <input
                id="linkedin_first_comment_delay_seconds"
                v-model="linkedinFormData.linkedin_first_comment_delay_seconds"
                type="number"
                min="0"
                max="3600"
                class="w-32 px-3 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-sm focus:outline-none focus:border-amber-500"
              >
            </div>
          </div>

          <!-- Save button -->
          <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700">
            <BaseButton
              type="button"
              button-type="primary"
              :disabled="linkedinSubmitting"
              @click="handleLinkedInSubmit"
            >
              {{ linkedinSubmitting ? 'Saving...' : 'Save LinkedIn Settings' }}
            </BaseButton>
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
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQueryClient } from '@tanstack/vue-query'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import api from '@/services/api'
import {
  useLinkedInAccounts,
  useLinkedInSettings,
  startLinkedInConnect,
  useDisconnectLinkedInAccount,
  useTestLinkedInConnection,
} from '@/composables/useLinkedInDrafts'

const route = useRoute()
const router = useRouter()

const queryClient = useQueryClient()
const settingsStore = useSettingsStore()
const uiStore = useUiStore()
const authStore = useAuthStore()

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
  telegram_notify_segment_failed: 'true',
  telegram_notify_cover_critical: 'true',
  telegram_notify_translate_failed: 'true',
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

// ============================================================================
// Mail SMTP Settings (Newsletter system, May 2026)
// ============================================================================

const mailSubmitting = ref(false)
const mailTesting = ref(false)
const mailTestResult = ref(null)
const mailTestRecipient = ref('')

const mailFormData = ref({
  mail_mailer: 'smtp',
  mail_host: 'smtp.hostinger.com',
  mail_port: '465',
  mail_username: 'aiagent@alisadikinma.com',
  mail_password: '',
  mail_encryption: 'ssl',
  mail_from_address: 'aiagent@alisadikinma.com',
  mail_from_name: 'Ali Sadikin',
})

const mailPasswordConfigured = computed(() => settingsStore.mailSettings?.mail_password_configured === true)
const mailEffective = computed(() => settingsStore.mailSettings?.effective || null)

async function loadMailSettings() {
  try {
    await settingsStore.fetchMailSettings()
    const s = settingsStore.mailSettings
    mailFormData.value = {
      mail_mailer: s.mail_mailer || 'smtp',
      mail_host: s.mail_host || 'smtp.hostinger.com',
      mail_port: s.mail_port || '465',
      mail_username: s.mail_username || 'aiagent@alisadikinma.com',
      mail_password: '', // never prefill — even masked value
      mail_encryption: s.mail_encryption || 'ssl',
      mail_from_address: s.mail_from_address || 'aiagent@alisadikinma.com',
      mail_from_name: s.mail_from_name || 'Ali Sadikin',
    }
  } catch (err) {
    // Defaults remain if fetch fails (e.g. fresh DB before seeder runs)
  }
}

async function handleMailSubmit() {
  mailSubmitting.value = true
  mailTestResult.value = null
  try {
    const payload = { ...mailFormData.value }
    // Don't send empty password — server treats empty as "preserve existing"
    if (!payload.mail_password) delete payload.mail_password

    await settingsStore.updateMailSettings(payload)
    uiStore.showSuccess('SMTP settings saved. Use "Send test email" to verify.', 'Saved')
    mailFormData.value.mail_password = '' // clear the field — masked indicator now shown
  } catch (err) {
    uiStore.showError(err.response?.data?.message || err.message || 'Failed to save SMTP settings', 'Save Failed')
  } finally {
    mailSubmitting.value = false
  }
}

async function handleMailTest() {
  mailTesting.value = true
  mailTestResult.value = null
  try {
    const recipient = mailTestRecipient.value.trim() || undefined
    const result = await settingsStore.sendMailTestMessage(recipient)
    mailTestResult.value = result.success
      ? { success: true, message: '✓ ' + (result.message || 'Test sent — check your inbox + spam') }
      : { success: false, message: '✗ ' + (result.error || 'SMTP test failed') }
    // Reload mail settings — picks up updated `effective` block so the
    // active-driver badge reflects current state (operator may have saved
    // password just before clicking test).
    await loadMailSettings().catch(() => {})
    setTimeout(() => { mailTestResult.value = null }, 15000)
  } finally {
    mailTesting.value = false
  }
}

// ============================================================================
// CV Master Export settings (schema_version 2.0.0, May 2026)
// ============================================================================
//
// Drives /api/cv/export response. Four UI sections:
//   1. summary_variants — 3 labeled textareas (vibe_coding / ai_automation /
//      ai_video). Bind directly to an object so save sends the structured
//      shape the backend expects.
//   2. work_experience — JSON textarea (array). Pre-filled with the seeded
//      example so operator can use it as a template.
//   3. skills_matrix — JSON textarea (object). Categorized skills.
//   4. education — JSON textarea (array). Empty list is valid.
//
// JSON textareas hold strings; backend accepts both raw JSON strings AND
// already-decoded values, parses + validates server-side. Per-field 422
// errors surface inline beneath each textarea.

const cvVariantOrder = ['vibe_coding', 'ai_automation', 'ai_video']
const cvVariants = ref({ vibe_coding: '', ai_automation: '', ai_video: '' })
const cvWorkJson = ref('[]')
const cvSkillsJson = ref('{}')
const cvEducationJson = ref('[]')
const cvSubmitting = ref(false)
const cvErrors = ref({})

// Origin of the actual API host (NOT the Vite dev server) — used for the
// "View live /api/cv/export" deep-link. Vite dev runs on :5173 but the
// API itself lives on XAMPP at localhost/Portfolio_v2/backend/public.
const apiBaseOrigin = (() => {
  if (typeof window === 'undefined') return ''
  const origin = window.location.origin
  if (origin.includes('localhost:5173')) return 'http://localhost/Portfolio_v2/backend/public'
  return origin
})()

function cvVariantLabel(slug) {
  return {
    vibe_coding: 'Vibe Coding',
    ai_automation: 'AI Automation',
    ai_video: 'AI Video',
  }[slug] || slug
}

function cvVariantDot(slug) {
  return {
    vibe_coding: 'bg-purple-500',
    ai_automation: 'bg-emerald-500',
    ai_video: 'bg-amber-500',
  }[slug] || 'bg-neutral-400'
}

function cvVariantPlaceholder(slug) {
  return {
    vibe_coding: 'Full-stack vibe coder shipping production AI apps from prompt → deploy in days. INDUSIA.ai stack…',
    ai_automation: 'AI Visual Inspection live on production lines at Evident Scientific and Novanta. Replacing $24K Keyence rigs…',
    ai_video: 'AI video generation pipeline operator — VEO 3, Sora, Runway, Kling. 7-beat narrative-arc engine…',
  }[slug] || ''
}

async function loadCvSettings() {
  try {
    const data = await settingsStore.fetchCvSettings()
    cvVariants.value = {
      vibe_coding: data?.summary_variants?.vibe_coding || '',
      ai_automation: data?.summary_variants?.ai_automation || '',
      ai_video: data?.summary_variants?.ai_video || '',
    }
    cvWorkJson.value = JSON.stringify(data?.work_experience ?? [], null, 2)
    cvSkillsJson.value = JSON.stringify(data?.skills_matrix ?? {}, null, 2)
    cvEducationJson.value = JSON.stringify(data?.education ?? [], null, 2)
  } catch (err) {
    // Silent — defaults remain (e.g. fresh DB before seeder ran).
  }
}

async function handleCvSubmit() {
  cvSubmitting.value = true
  cvErrors.value = {}
  try {
    await settingsStore.updateCvSettings({
      summary_variants: cvVariants.value,
      work_experience: cvWorkJson.value,
      skills_matrix: cvSkillsJson.value,
      education: cvEducationJson.value,
    })
    uiStore.showSuccess('CV Master Export settings saved.', 'Saved')
    // Re-pretty-print the JSON textareas from the freshly persisted state.
    await loadCvSettings()
  } catch (err) {
    const data = err.response?.data
    cvErrors.value = data?.errors || {}
    uiStore.showError(
      data?.message || err.message || 'Failed to save CV settings',
      'Save Failed'
    )
  } finally {
    cvSubmitting.value = false
  }
}

// ============================================================================
// LinkedIn Integration — direct OAuth + publishing controls
// ============================================================================

const linkedinSubmitting = ref(false)
const linkedinTesting = ref(false)
const linkedinTestResult = ref(null)
const linkedinOauthFlash = ref(null)

const linkedinFormData = ref({
  linkedin_auto_publish: 'false',
  linkedin_depth_score_threshold: '80',
  linkedin_cancel_window_minutes: '15',
  linkedin_first_comment_enabled: 'true',
  linkedin_first_comment_delay_seconds: '30',
  linkedin_last_test_connection_at: null,
  linkedin_last_test_connection_result: null,
})

const {
  oauthConfigured: linkedinOauthConfigured,
  accounts: linkedinAccounts,
  isLoading: linkedinAccountsLoading,
  refetch: refetchLinkedinAccounts,
} = useLinkedInAccounts()

const {
  settings: linkedinSettings,
  isLoading: linkedinSettingsLoading,
  save: saveLinkedinSettings,
} = useLinkedInSettings()

const disconnectMutation = useDisconnectLinkedInAccount()
const testConnectionMutation = useTestLinkedInConnection()

const linkedinLoading = computed(() =>
  linkedinAccountsLoading.value || linkedinSettingsLoading.value
)

// Sync settings ref into local form data when query resolves
watch(linkedinSettings, (s) => {
  if (!s) return
  linkedinFormData.value = {
    linkedin_auto_publish: s.linkedin_auto_publish ?? 'false',
    linkedin_depth_score_threshold: s.linkedin_depth_score_threshold ?? '80',
    linkedin_cancel_window_minutes: s.linkedin_cancel_window_minutes ?? '15',
    linkedin_first_comment_enabled: s.linkedin_first_comment_enabled ?? 'true',
    linkedin_first_comment_delay_seconds: s.linkedin_first_comment_delay_seconds ?? '30',
    linkedin_last_test_connection_at: s.linkedin_last_test_connection_at ?? null,
    linkedin_last_test_connection_result: s.linkedin_last_test_connection_result ?? null,
  }
}, { immediate: true })

async function handleLinkedInSubmit() {
  linkedinSubmitting.value = true
  try {
    await saveLinkedinSettings({
      linkedin_auto_publish: linkedinFormData.value.linkedin_auto_publish,
      linkedin_depth_score_threshold: parseInt(linkedinFormData.value.linkedin_depth_score_threshold, 10),
      linkedin_cancel_window_minutes: parseInt(linkedinFormData.value.linkedin_cancel_window_minutes, 10),
      linkedin_first_comment_enabled: linkedinFormData.value.linkedin_first_comment_enabled,
      linkedin_first_comment_delay_seconds: parseInt(linkedinFormData.value.linkedin_first_comment_delay_seconds, 10),
    })
    uiStore.showSuccess('LinkedIn settings saved', 'Saved')
  } catch (err) {
    uiStore.showError(err.response?.data?.message || err.message || 'Failed to save LinkedIn settings', 'Save Failed')
  } finally {
    linkedinSubmitting.value = false
  }
}

async function handleLinkedInConnect() {
  linkedinSubmitting.value = true
  try {
    await startLinkedInConnect() // redirects browser to LinkedIn
  } catch (err) {
    uiStore.showError(err.message || 'Failed to start LinkedIn OAuth', 'Connect Failed')
    linkedinSubmitting.value = false
  }
}

async function handleLinkedInDisconnect(accountId) {
  if (!confirm('Disconnect this LinkedIn account? Scheduled posts will fail to publish.')) return
  linkedinSubmitting.value = true
  try {
    await disconnectMutation.mutateAsync(accountId)
    await refetchLinkedinAccounts()
    uiStore.showSuccess('LinkedIn account disconnected', 'Disconnected')
  } catch (err) {
    uiStore.showError(err.response?.data?.error?.message || 'Failed to disconnect', 'Disconnect Failed')
  } finally {
    linkedinSubmitting.value = false
  }
}

async function handleLinkedInTest(accountId) {
  linkedinTesting.value = true
  linkedinTestResult.value = null
  try {
    const result = await testConnectionMutation.mutateAsync(accountId)
    const payload = result?.data || {}
    linkedinTestResult.value = payload.success
      ? { success: true, message: '✓ ' + (payload.message || 'Connection OK') }
      : { success: false, message: '✗ ' + (payload.message || 'Connection failed') }
    setTimeout(() => { linkedinTestResult.value = null }, 8000)
  } catch (err) {
    linkedinTestResult.value = { success: false, message: '✗ ' + (err.message || 'Test failed') }
  } finally {
    linkedinTesting.value = false
  }
}

// Read OAuth callback flash from query params (?linkedin_oauth=success|error)
function consumeOauthFlash() {
  const { linkedin_oauth: flashType, message, account } = route.query
  if (!flashType) return
  if (flashType === 'success') {
    linkedinOauthFlash.value = {
      type: 'success',
      message: '✓ LinkedIn connected' + (account ? ` as ${account}` : ''),
    }
  } else if (flashType === 'error') {
    linkedinOauthFlash.value = {
      type: 'error',
      message: '✗ LinkedIn OAuth failed: ' + (message || 'unknown error'),
    }
  }
  // Strip the query params so a refresh doesn't re-show the flash
  router.replace({ query: {} })
  // Auto-dismiss after 10s
  setTimeout(() => { linkedinOauthFlash.value = null }, 10000)
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
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
      loadMailSettings().catch((err) => {
        // Non-fatal: mail seeder may not have run on older installs
        console.warn('[AboutSettings] mail fetch failed — using defaults', err)
      }),
      loadCvSettings().catch((err) => {
        // Non-fatal: cv seeder may not have run on older installs
        console.warn('[AboutSettings] cv fetch failed — using defaults', err)
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
      telegram_notify_segment_failed: tg.telegram_notify_segment_failed || 'true',
      telegram_notify_cover_critical: tg.telegram_notify_cover_critical || 'true',
      telegram_notify_translate_failed: tg.telegram_notify_translate_failed || 'true',
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
  consumeOauthFlash()
})
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
