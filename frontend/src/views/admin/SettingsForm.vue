<template>
  <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-display font-bold text-neutral-900 dark:text-neutral-100 mb-2">
        Settings
      </h1>
      <p class="text-neutral-600 dark:text-neutral-400">
        Site information, integrations, and operational tooling — grouped by tab to keep the page short.
      </p>
    </div>

    <!-- Loading State (initial site fetch) -->
    <div v-if="loading && !settingsStore.siteSettings.site_name" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-900 dark:border-neutral-100"></div>
      <p class="mt-4 text-neutral-600 dark:text-neutral-400">Loading settings...</p>
    </div>

    <div v-else>
      <!-- Tab navigation -->
      <nav
        class="mb-6 flex gap-1 overflow-x-auto border-b border-neutral-200 dark:border-neutral-700"
        role="tablist"
        aria-label="Settings sections"
      >
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab.id"
          :aria-controls="`tab-panel-${tab.id}`"
          :id="`tab-${tab.id}`"
          :class="[
            'px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition-colors',
            activeTab === tab.id
              ? 'border-amber-500 text-amber-600 dark:text-amber-400'
              : 'border-transparent text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200',
          ]"
          @click="setTab(tab.id)"
        >
          {{ tab.label }}
        </button>
      </nav>

      <!-- ================================================================ -->
      <!-- TAB: Site (existing General + Contact + SEO + Analytics)         -->
      <!-- ================================================================ -->
      <section
        v-show="activeTab === 'site'"
        role="tabpanel"
        id="tab-panel-site"
        aria-labelledby="tab-site"
      >
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <!-- General Settings Card -->
          <BaseCard>
            <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
              General Settings
            </h2>

            <div class="space-y-6">
              <!-- Site Name -->
              <div>
                <label for="site_name" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Site Name
                </label>
                <input
                  id="site_name"
                  v-model="formData.site_name"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="Your Portfolio"
                />
              </div>

              <!-- Site Description -->
              <div>
                <label for="site_description" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Site Description
                </label>
                <textarea
                  id="site_description"
                  v-model="formData.site_description"
                  rows="3"
                  maxlength="500"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="A brief description of your site (max 500 characters)"
                ></textarea>
                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                  {{ formData.site_description.length }}/500 characters
                </p>
              </div>

              <!-- Site Logo -->
              <div>
                <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Site Logo
                </label>

                <div class="flex items-center gap-4">
                  <div v-if="currentLogoUrl" class="relative w-32 h-32 rounded-lg overflow-hidden bg-neutral-100 dark:bg-neutral-800 border border-neutral-200 dark:border-neutral-700">
                    <img :src="currentLogoUrl" alt="Site Logo" class="w-full h-full object-contain p-2" />
                  </div>

                  <div class="flex-1">
                    <input
                      ref="logoInput"
                      type="file"
                      accept="image/*"
                      class="hidden"
                      @change="handleLogoChange"
                    />
                    <BaseButton
                      type="button"
                      button-type="secondary"
                      @click="$refs.logoInput.click()"
                    >
                      {{ currentLogoUrl ? 'Change Logo' : 'Upload Logo' }}
                    </BaseButton>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-2">
                      Maximum file size: 5MB. Recommended: PNG with transparent background.
                    </p>
                  </div>

                  <BaseButton
                    v-if="currentLogoUrl"
                    type="button"
                    button-type="danger"
                    @click="removeLogo"
                  >
                    Remove
                  </BaseButton>
                </div>
              </div>
            </div>
          </BaseCard>

          <!-- Contact Information Card -->
          <BaseCard>
            <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
              Contact Information
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="contact_email" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Contact Email
                </label>
                <input
                  id="contact_email"
                  v-model="formData.contact_email"
                  type="email"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="contact@example.com"
                />
              </div>

              <div>
                <label for="contact_phone" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Contact Phone
                </label>
                <input
                  id="contact_phone"
                  v-model="formData.contact_phone"
                  type="tel"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="+1 234 567 8900"
                />
              </div>

              <div class="md:col-span-2">
                <label for="location" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  Location
                </label>
                <input
                  id="location"
                  v-model="formData.location"
                  type="text"
                  class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="City, Country"
                />
              </div>
            </div>
          </BaseCard>

          <!-- SEO & Meta Tags Card -->
          <BaseCard>
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100">
                SEO & Meta Tags
              </h2>
              <BaseButton
                type="button"
                button-type="secondary"
                @click="addMetaTag"
              >
                + Add Tag
              </BaseButton>
            </div>

            <div class="space-y-4">
              <div
                v-for="(tag, index) in formData.meta_tags"
                :key="index"
                class="grid grid-cols-1 md:grid-cols-12 gap-4 p-4 bg-neutral-50 dark:bg-neutral-900 rounded-lg"
              >
                <div class="md:col-span-4">
                  <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                    Name
                  </label>
                  <input
                    v-model="tag.name"
                    type="text"
                    class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="keywords"
                  />
                </div>

                <div class="md:col-span-7">
                  <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                    Content
                  </label>
                  <input
                    v-model="tag.content"
                    type="text"
                    class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="portfolio, web developer, designer"
                  />
                </div>

                <div class="md:col-span-1 flex items-end">
                  <BaseButton
                    type="button"
                    button-type="danger"
                    class="w-full"
                    @click="removeMetaTag(index)"
                  >
                    ×
                  </BaseButton>
                </div>
              </div>

              <p v-if="formData.meta_tags.length === 0" class="text-center text-neutral-500 dark:text-neutral-400 py-4">
                No meta tags added yet. Click "+ Add Tag" to add one.
              </p>
            </div>
          </BaseCard>

          <!-- Analytics Card -->
          <BaseCard>
            <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-6">
              Analytics & Tracking
            </h2>

            <div>
              <label for="analytics_code" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                Analytics Code
              </label>
              <textarea
                id="analytics_code"
                v-model="formData.analytics_code"
                rows="6"
                class="w-full px-4 py-2 border border-neutral-300 dark:border-neutral-600 rounded-lg bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                placeholder="<!-- Google Analytics, GTM, or other tracking codes -->"
              ></textarea>
              <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Paste your Google Analytics, Google Tag Manager, or other tracking codes here. They will be added to the site's &lt;head&gt; section.
              </p>
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
              <span v-else>Save Settings</span>
            </BaseButton>
          </div>
        </form>

        <!-- Error Display -->
        <div v-if="error" class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
          <p class="text-red-800 dark:text-red-200">{{ error }}</p>
        </div>
      </section>

      <!-- ================================================================ -->
      <!-- TAB: LinkedIn Integration                                        -->
      <!-- ================================================================ -->
      <section
        v-show="activeTab === 'linkedin'"
        role="tabpanel"
        id="tab-panel-linkedin"
        aria-labelledby="tab-linkedin"
      >
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

              <label class="flex items-center gap-3 cursor-pointer">
                <input
                  :checked="linkedinFormData.linkedin_auto_approve_enabled === 'true'"
                  type="checkbox"
                  class="w-4 h-4 text-amber-600 border-neutral-300 rounded focus:ring-amber-500"
                  @change="e => linkedinFormData.linkedin_auto_approve_enabled = e.target.checked ? 'true' : 'false'"
                >
                <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                  Enable auto-schedule for manual_review drafts
                </span>
              </label>
              <p class="text-xs text-neutral-500 -mt-2 pl-7">
                Daily 04:30 WIB cron <code class="text-[10px] bg-neutral-100 dark:bg-neutral-800 px-1 rounded">linkedin:auto-schedule</code> promotes <code class="text-[10px] bg-neutral-100 dark:bg-neutral-800 px-1 rounded">manual_review</code> drafts to the next ideal posting slot (<code class="text-[10px] bg-neutral-100 dark:bg-neutral-800 px-1 rounded">posting_time_rules.score &ge; 85</code>, 14-day lookahead). Pairs with auto-publish — flip both ON for hands-off pipeline.
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
      </section>

      <!-- ================================================================ -->
      <!-- TAB: Publer Cross-Post Integration (May 8, 2026)                 -->
      <!-- ================================================================ -->
      <section
        v-show="activeTab === 'publer'"
        role="tabpanel"
        id="tab-panel-publer"
        aria-labelledby="tab-publer"
      >
        <BaseCard>
          <h2 class="text-xl font-display font-semibold text-neutral-900 dark:text-neutral-100 mb-2">
            Publer Integration — Cross-post to Facebook, Instagram, TikTok
          </h2>
          <p class="text-sm text-neutral-600 dark:text-neutral-400 mb-6">
            Backend POSTs draft captions + LinkedIn carousel slides to Publer's REST API
            (<code class="text-xs bg-neutral-100 dark:bg-neutral-800 px-1 rounded">app.publer.com/api/v1</code>),
            which auto-publishes at the scheduled time. Operator approves per-platform in our admin queue first.
            <strong>API key is encrypted at rest</strong> (Laravel Crypt) and never returned in API responses —
            only the <code class="text-xs">***SET***</code> placeholder + a boolean flag.
          </p>

          <!-- Master enable toggle -->
          <div class="mb-6 p-4 rounded-md border"
               :class="publerEnabled
                 ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50'
                 : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50'">
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                v-model="publerEnabled"
                class="w-5 h-5 rounded border-neutral-300 text-primary-600 focus:ring-2 focus:ring-primary-500"
              />
              <span class="flex-1">
                <strong class="block text-sm" :class="publerEnabled ? 'text-emerald-900 dark:text-emerald-200' : 'text-amber-900 dark:text-amber-200'">
                  {{ publerEnabled ? '✓ Publer integration ENABLED' : '⚠ Publer integration DISABLED' }}
                </strong>
                <span class="text-xs" :class="publerEnabled ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'">
                  {{ publerEnabled
                    ? 'Approved drafts auto-publish via Publer at their scheduled_at time.'
                    : 'Master kill switch — drafts queue normally but never POST to Publer until enabled.' }}
                </span>
              </span>
            </label>
          </div>

          <form @submit.prevent="handlePublerSubmit" class="space-y-4">
            <!-- API key -->
            <div>
              <label for="publer_api_key" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                API Key
                <span v-if="publerKeyConfigured" class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                  ✓ Configured
                </span>
              </label>
              <input
                id="publer_api_key"
                v-model="publerFormData.publer_api_key"
                type="password"
                autocomplete="new-password"
                :placeholder="publerKeyConfigured ? 'Leave blank to keep current key' : 'Paste Publer API key here'"
                class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 font-mono text-sm"
              />
              <p class="text-xs text-neutral-500 mt-1">
                Get from
                <a href="https://app.publer.com/#/account/api" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline">app.publer.com → Account → API</a>.
                Stored encrypted via Laravel <code class="text-xs">Crypt::encryptString</code>; never visible after save.
                <strong class="text-amber-700 dark:text-amber-400">Rotate the key in Publer dashboard if it has ever been exposed.</strong>
              </p>
            </div>

            <!-- Save + Test buttons -->
            <div class="pt-3 border-t border-neutral-200 dark:border-neutral-700 flex flex-wrap gap-3">
              <BaseButton
                type="submit"
                :disabled="publerSubmitting"
                button-type="primary"
              >
                {{ publerSubmitting ? 'Saving...' : 'Save Publer Settings' }}
              </BaseButton>
              <BaseButton
                type="button"
                :disabled="publerTesting || !publerKeyConfigured"
                :loading="publerTesting"
                button-type="secondary"
                @click="handlePublerTest"
              >
                🔌 Test Connection
              </BaseButton>
              <BaseButton
                type="button"
                :disabled="publerSyncing || !publerKeyConfigured"
                :loading="publerSyncing"
                button-type="secondary"
                @click="handlePublerSyncAccounts"
              >
                🔄 Refresh Accounts
              </BaseButton>
            </div>

            <p v-if="publerTestResult" :class="publerTestResult.success ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-sm leading-relaxed">
              {{ publerTestResult.message }}
            </p>
            <p v-if="publerSyncResult" :class="publerSyncResult.success ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" class="text-sm leading-relaxed">
              {{ publerSyncResult.message }}
            </p>

            <!-- Account dropdowns (3 platforms) -->
            <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700 space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">Default Posting Accounts</h3>
                <span v-if="publerLastSyncedRelative" class="text-xs text-neutral-500">
                  Last synced: {{ publerLastSyncedRelative }}
                </span>
              </div>
              <p class="text-xs text-neutral-500">
                Dropdowns auto-populate when the API key is configured. Click <em>Refresh Accounts</em>
                if you connect new accounts in Publer. Each cross-post draft uses these as defaults.
              </p>

              <!-- Facebook -->
              <div>
                <label for="publer_facebook_account_id" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  📘 Facebook Page
                </label>
                <select
                  id="publer_facebook_account_id"
                  v-model="publerFormData.publer_facebook_account_id"
                  class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                  <option value="">(none selected)</option>
                  <option v-if="publerFormData.publer_facebook_account_id && !publerAccounts.facebook?.length"
                          :value="publerFormData.publer_facebook_account_id">
                    {{ publerFormData.publer_facebook_account_id }} (saved — refresh to load names)
                  </option>
                  <option v-for="acc in publerAccounts.facebook" :key="acc.id" :value="acc.id">
                    {{ acc.name }} — {{ acc.id }}
                  </option>
                </select>
              </div>

              <!-- Instagram -->
              <div>
                <label for="publer_instagram_account_id" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  📸 Instagram
                </label>
                <select
                  id="publer_instagram_account_id"
                  v-model="publerFormData.publer_instagram_account_id"
                  class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                  <option value="">(none selected)</option>
                  <option v-if="publerFormData.publer_instagram_account_id && !publerAccounts.instagram?.length"
                          :value="publerFormData.publer_instagram_account_id">
                    {{ publerFormData.publer_instagram_account_id }} (saved — refresh to load names)
                  </option>
                  <option v-for="acc in publerAccounts.instagram" :key="acc.id" :value="acc.id">
                    {{ acc.name }} — {{ acc.id }}
                  </option>
                </select>
              </div>

              <!-- TikTok -->
              <div>
                <label for="publer_tiktok_account_id" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                  🎵 TikTok
                </label>
                <select
                  id="publer_tiktok_account_id"
                  v-model="publerFormData.publer_tiktok_account_id"
                  class="w-full border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                  <option value="">(none selected)</option>
                  <option v-if="publerFormData.publer_tiktok_account_id && !publerAccounts.tiktok?.length"
                          :value="publerFormData.publer_tiktok_account_id">
                    {{ publerFormData.publer_tiktok_account_id }} (saved — refresh to load names)
                  </option>
                  <option v-for="acc in publerAccounts.tiktok" :key="acc.id" :value="acc.id">
                    {{ acc.name }} — {{ acc.id }}
                  </option>
                </select>
              </div>

              <!-- Save button (mirrors the one at top of form for discoverability) -->
              <div class="pt-3 border-t border-neutral-200 dark:border-neutral-700">
                <BaseButton
                  type="submit"
                  :disabled="publerSubmitting"
                  button-type="primary"
                >
                  {{ publerSubmitting ? 'Saving...' : 'Save Selected Accounts' }}
                </BaseButton>
              </div>
            </div>
          </form>
        </BaseCard>
      </section>

      <!-- ================================================================ -->
      <!-- TAB: Email — SMTP Settings                                       -->
      <!-- ================================================================ -->
      <section
        v-show="activeTab === 'email'"
        role="tabpanel"
        id="tab-panel-email"
        aria-labelledby="tab-email"
      >
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

          <!-- Active driver diagnostic -->
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
      </section>

      <!-- ================================================================ -->
      <!-- TAB: Telegram Notifications                                      -->
      <!-- ================================================================ -->
      <section
        v-show="activeTab === 'telegram'"
        role="tabpanel"
        id="tab-panel-telegram"
        aria-labelledby="tab-telegram"
      >
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
      </section>

      <!-- ================================================================ -->
      <!-- TAB: CV Master Export                                            -->
      <!-- ================================================================ -->
      <section
        v-show="activeTab === 'cv'"
        role="tabpanel"
        id="tab-panel-cv"
        aria-labelledby="tab-cv"
      >
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
            <!-- summary_variants -->
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

            <!-- work_experience -->
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

            <!-- skills_matrix -->
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

            <!-- education -->
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
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useSettings } from '@/composables/useSettings'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import {
  useLinkedInAccounts,
  useLinkedInSettings,
  startLinkedInConnect,
  useDisconnectLinkedInAccount,
  useTestLinkedInConnection,
} from '@/composables/useLinkedInDrafts'

const route = useRoute()
const router = useRouter()

const settingsStore = useSettingsStore()
const uiStore = useUiStore()
const authStore = useAuthStore()
const { clearCache } = useSettings()

// ---------------------------------------------------------------------------
// Tab navigation — synced to ?tab= so deep-linking works.
// ---------------------------------------------------------------------------
const tabs = [
  { id: 'site',     label: 'Site' },
  { id: 'linkedin', label: 'LinkedIn' },
  { id: 'publer',   label: 'Publer' },
  { id: 'email',    label: 'Email (SMTP)' },
  { id: 'telegram', label: 'Telegram' },
  { id: 'cv',       label: 'CV Export' },
]
const tabIds = tabs.map(t => t.id)

const activeTab = ref(tabIds.includes(route.query.tab) ? route.query.tab : 'site')

function setTab(id) {
  if (!tabIds.includes(id) || activeTab.value === id) return
  activeTab.value = id
  router.replace({ query: { ...route.query, tab: id } })
}

watch(() => route.query.tab, (next) => {
  if (next && tabIds.includes(next) && next !== activeTab.value) {
    activeTab.value = next
  }
})

// ===========================================================================
// SITE — General + Contact + SEO + Analytics (existing behavior)
// ===========================================================================
const logoInput = ref(null)
const isSubmitting = ref(false)
const loading = ref(false)
const error = ref(null)
const isLoadingSettings = ref(false)

const formData = ref({
  site_name: '',
  site_description: '',
  site_logo: null,
  contact_email: '',
  contact_phone: '',
  location: '',
  meta_tags: [],
  analytics_code: ''
})

const logoFile = ref(null)
const logoRemoved = ref(false)

const currentLogoUrl = computed(() => {
  if (logoRemoved.value) return null
  if (logoFile.value) {
    return URL.createObjectURL(logoFile.value)
  }
  const logo = formData.value.site_logo
  if (!logo) return null
  if (logo.startsWith('http')) return logo
  const baseUrl = (import.meta.env.VITE_API_URL || '').replace('/api', '')
  return `${baseUrl}${logo}`
})

function addMetaTag() {
  formData.value.meta_tags.push({ name: '', content: '' })
}

function removeMetaTag(index) {
  formData.value.meta_tags.splice(index, 1)
}

function handleLogoChange(event) {
  const file = event.target.files[0]
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      uiStore.showError('File size must not exceed 5MB', 'Upload Error')
      return
    }
    logoFile.value = file
    logoRemoved.value = false
  }
}

function removeLogo() {
  logoFile.value = null
  logoRemoved.value = true
  if (logoInput.value) {
    logoInput.value.value = ''
  }
}

async function handleSubmit() {
  isSubmitting.value = true
  error.value = null

  try {
    const hasInvalidMetaTags = formData.value.meta_tags.some(
      tag => !tag.name || !tag.content
    )

    if (hasInvalidMetaTags) {
      throw new Error('Please fill in all required meta tag fields (name, content)')
    }

    const data = new FormData()
    if (formData.value.site_name) data.append('site_name', formData.value.site_name)
    if (formData.value.site_description) data.append('site_description', formData.value.site_description)
    if (formData.value.contact_email) data.append('contact_email', formData.value.contact_email)
    if (formData.value.contact_phone) data.append('contact_phone', formData.value.contact_phone)
    if (formData.value.location) data.append('location', formData.value.location)
    if (formData.value.analytics_code) data.append('analytics_code', formData.value.analytics_code)
    if (logoFile.value) data.append('site_logo', logoFile.value)
    if (formData.value.meta_tags.length > 0) {
      data.append('meta_tags', JSON.stringify(formData.value.meta_tags))
    }

    await settingsStore.updateSiteSettings(data)
    clearCache()
    uiStore.showSuccess('Site settings updated successfully', 'Settings Saved')

    await settingsStore.fetchSiteSettings()
    formData.value.site_logo = settingsStore.siteSettings.site_logo || null

    logoFile.value = null
    logoRemoved.value = false
    if (logoInput.value) logoInput.value.value = ''
  } catch (err) {
    error.value = err.message || err.response?.data?.message || 'Failed to update settings. Please try again.'
    uiStore.showError(error.value, 'Update Failed', 0)
  } finally {
    isSubmitting.value = false
  }
}

function resetForm() {
  loadSiteSettings()
  logoFile.value = null
  logoRemoved.value = false
  if (logoInput.value) logoInput.value.value = ''
  error.value = null
}

async function loadSiteSettings() {
  if (isLoadingSettings.value) return
  isLoadingSettings.value = true
  loading.value = true
  error.value = null

  try {
    await settingsStore.fetchSiteSettings()
    formData.value = {
      site_name: settingsStore.siteSettings.site_name || '',
      site_description: settingsStore.siteSettings.site_description || '',
      site_logo: settingsStore.siteSettings.site_logo || null,
      contact_email: settingsStore.siteSettings.contact_email || '',
      contact_phone: settingsStore.siteSettings.contact_phone || '',
      location: settingsStore.siteSettings.location || '',
      meta_tags: Array.isArray(settingsStore.siteSettings.meta_tags)
        ? JSON.parse(JSON.stringify(settingsStore.siteSettings.meta_tags))
        : [],
      analytics_code: settingsStore.siteSettings.analytics_code || ''
    }
  } catch (err) {
    error.value = 'Failed to load settings. Please refresh the page.'
    uiStore.showError(error.value, 'Load Failed')
  } finally {
    loading.value = false
    isLoadingSettings.value = false
  }
}

// ===========================================================================
// TELEGRAM — notification settings
// ===========================================================================
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

async function loadTelegramSettings() {
  try {
    await settingsStore.fetchTelegramSettings()
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
  } catch (err) {
    console.warn('[Settings] telegram fetch failed — using defaults', err)
  }
}

async function handleTelegramSubmit() {
  telegramSubmitting.value = true
  telegramTestResult.value = null
  try {
    const payload = { ...telegramFormData.value }
    if (!payload.telegram_bot_token) delete payload.telegram_bot_token

    await settingsStore.updateTelegramSettings(payload)
    uiStore.showSuccess('Telegram settings saved', 'Saved')
    telegramFormData.value.telegram_bot_token = ''
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

// ===========================================================================
// MAIL (SMTP) — Newsletter system, May 2026
// ===========================================================================
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
      mail_password: '',
      mail_encryption: s.mail_encryption || 'ssl',
      mail_from_address: s.mail_from_address || 'aiagent@alisadikinma.com',
      mail_from_name: s.mail_from_name || 'Ali Sadikin',
    }
  } catch (err) {
    console.warn('[Settings] mail fetch failed — using defaults', err)
  }
}

async function handleMailSubmit() {
  mailSubmitting.value = true
  mailTestResult.value = null
  try {
    const payload = { ...mailFormData.value }
    if (!payload.mail_password) delete payload.mail_password

    await settingsStore.updateMailSettings(payload)
    uiStore.showSuccess('SMTP settings saved. Use "Send test email" to verify.', 'Saved')
    mailFormData.value.mail_password = ''
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
    await loadMailSettings().catch(() => {})
    setTimeout(() => { mailTestResult.value = null }, 15000)
  } finally {
    mailTesting.value = false
  }
}

// ===========================================================================
// PUBLER — Cross-post integration (May 8, 2026)
//
// Operator manages: encrypted api_key + master enable toggle + 3 default
// account IDs (FB Page + IG + TikTok). Account IDs auto-discovered via
// Publer GET /accounts after key entry — operator picks from dropdowns.
// ===========================================================================
const publerSubmitting = ref(false)
const publerTesting = ref(false)
const publerSyncing = ref(false)
const publerTestResult = ref(null) // { success, message } | null
const publerSyncResult = ref(null) // { success, message } | null
const publerAccounts = ref({ facebook: [], instagram: [], tiktok: [], other: [] })

const publerFormData = ref({
  publer_api_key: '',
  publer_enabled: 'false',
  publer_facebook_account_id: '',
  publer_instagram_account_id: '',
  publer_tiktok_account_id: '',
})

const publerKeyConfigured = computed(() => settingsStore.publerSettings?.publer_api_key_configured === true)
const publerEnabled = computed({
  get: () => publerFormData.value.publer_enabled === 'true' || publerFormData.value.publer_enabled === true,
  set: (v) => { publerFormData.value.publer_enabled = v ? 'true' : 'false' },
})
const publerLastSyncedAt = computed(() => settingsStore.publerSettings?.publer_last_account_sync_at)
const publerLastSyncedRelative = computed(() => {
  const ts = publerLastSyncedAt.value
  if (!ts) return null
  const ms = Date.now() - new Date(ts).getTime()
  const min = Math.floor(ms / 60000)
  if (min < 1) return 'just now'
  if (min < 60) return `${min}m ago`
  const hr = Math.floor(min / 60)
  if (hr < 24) return `${hr}h ago`
  const d = Math.floor(hr / 24)
  return `${d}d ago`
})

async function loadPublerSettings() {
  try {
    await settingsStore.fetchPublerSettings()
    const s = settingsStore.publerSettings
    publerFormData.value = {
      publer_api_key: '', // never bind the masked sentinel — let placeholder show
      publer_enabled: s.publer_enabled || 'false',
      publer_facebook_account_id: s.publer_facebook_account_id || '',
      publer_instagram_account_id: s.publer_instagram_account_id || '',
      publer_tiktok_account_id: s.publer_tiktok_account_id || '',
    }
    // Auto-populate dropdowns when api_key is configured and we haven't
    // synced yet this session — saves the operator a manual click. Silent
    // (no banner / spinner) so it doesn't visually compete with manual sync.
    if (s.publer_api_key_configured === true && !publerAccountsLoaded.value) {
      autoLoadPublerAccounts()
    }
  } catch (err) {
    console.warn('[Settings] publer fetch failed — using defaults', err)
  }
}

// Tracks whether the dropdowns have been populated at least once this
// session — prevents duplicate auto-syncs across re-mounts of this view.
const publerAccountsLoaded = ref(false)

async function autoLoadPublerAccounts() {
  try {
    const result = await settingsStore.syncPublerAccounts()
    if (result.success) {
      publerAccounts.value = result.accounts || { facebook: [], instagram: [], tiktok: [], other: [] }
      publerAccountsLoaded.value = true
    }
  } catch (err) {
    // Silent — operator can still click "Refresh Accounts" to see the error
    console.warn('[Settings] publer auto-sync skipped', err)
  }
}

async function handlePublerSubmit() {
  publerSubmitting.value = true
  publerTestResult.value = null
  try {
    const payload = { ...publerFormData.value }
    // Empty api_key = preserve existing (operator chose not to change)
    if (!payload.publer_api_key) delete payload.publer_api_key

    await settingsStore.updatePublerSettings(payload)
    uiStore.showSuccess('Publer settings saved.', 'Saved')
    publerFormData.value.publer_api_key = ''
  } catch (err) {
    uiStore.showError(err.response?.data?.message || err.message || 'Failed to save Publer settings', 'Save Failed')
  } finally {
    publerSubmitting.value = false
  }
}

async function handlePublerTest() {
  publerTesting.value = true
  publerTestResult.value = null
  try {
    const result = await settingsStore.testPublerConnection()
    publerTestResult.value = result.success
      ? { success: true, message: '✓ Publer connection OK' + (result.data?.email ? ` (${result.data.email})` : '') }
      : { success: false, message: '✗ ' + (result.error || result.message || 'Publer test failed') }
    setTimeout(() => { publerTestResult.value = null }, 15000)
  } finally {
    publerTesting.value = false
  }
}

async function handlePublerSyncAccounts() {
  publerSyncing.value = true
  publerSyncResult.value = null
  try {
    const result = await settingsStore.syncPublerAccounts()
    if (result.success) {
      publerAccounts.value = result.accounts || { facebook: [], instagram: [], tiktok: [], other: [] }
      publerAccountsLoaded.value = true
      const total = (publerAccounts.value.facebook?.length || 0)
        + (publerAccounts.value.instagram?.length || 0)
        + (publerAccounts.value.tiktok?.length || 0)
      const otherCount = publerAccounts.value.other?.length || 0
      const debug = result.debug || {}
      const diagBits = []
      if (typeof debug.workspace_count === 'number') diagBits.push(`${debug.workspace_count} workspace${debug.workspace_count === 1 ? '' : 's'}`)
      if (typeof debug.total_accounts === 'number') diagBits.push(`${debug.total_accounts} raw`)
      if (otherCount > 0) diagBits.push(`${otherCount} unmatched`)
      if (Array.isArray(debug.raw_types) && debug.raw_types.length) diagBits.push(`types: [${debug.raw_types.join(', ')}]`)
      const diagSuffix = diagBits.length ? ` (${diagBits.join(' · ')})` : ''
      publerSyncResult.value = {
        success: true,
        message: `✓ Synced ${total} accounts from Publer${diagSuffix}`,
      }
      // Refresh settings so publer_last_account_sync_at updates
      await loadPublerSettings().catch(() => {})
    } else {
      publerSyncResult.value = { success: false, message: '✗ ' + (result.error || 'Failed to sync accounts') }
    }
    setTimeout(() => { publerSyncResult.value = null }, 30000)
  } finally {
    publerSyncing.value = false
  }
}

// ===========================================================================
// CV MASTER EXPORT — schema_version 2.0.0
// ===========================================================================
const cvVariantOrder = ['vibe_coding', 'ai_automation', 'ai_video']
const cvVariants = ref({ vibe_coding: '', ai_automation: '', ai_video: '' })
const cvWorkJson = ref('[]')
const cvSkillsJson = ref('{}')
const cvEducationJson = ref('[]')
const cvSubmitting = ref(false)
const cvErrors = ref({})

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
    console.warn('[Settings] cv fetch failed — using defaults', err)
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

// ===========================================================================
// LINKEDIN — direct OAuth + publishing controls
// ===========================================================================
const linkedinSubmitting = ref(false)
const linkedinTesting = ref(false)
const linkedinTestResult = ref(null)
const linkedinOauthFlash = ref(null)

const linkedinFormData = ref({
  linkedin_auto_publish: 'false',
  linkedin_auto_approve_enabled: 'false',
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

watch(linkedinSettings, (s) => {
  if (!s) return
  linkedinFormData.value = {
    linkedin_auto_publish: s.linkedin_auto_publish ?? 'false',
    linkedin_auto_approve_enabled: s.linkedin_auto_approve_enabled ?? 'false',
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
      linkedin_auto_approve_enabled: linkedinFormData.value.linkedin_auto_approve_enabled,
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
    await startLinkedInConnect()
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

function consumeOauthFlash() {
  const { linkedin_oauth: flashType, message, account } = route.query
  if (!flashType) return
  if (flashType === 'success') {
    linkedinOauthFlash.value = {
      type: 'success',
      message: '✓ LinkedIn connected' + (account ? ` as ${account}` : ''),
    }
    // Auto-jump to LinkedIn tab when arriving from OAuth callback
    activeTab.value = 'linkedin'
  } else if (flashType === 'error') {
    linkedinOauthFlash.value = {
      type: 'error',
      message: '✗ LinkedIn OAuth failed: ' + (message || 'unknown error'),
    }
    activeTab.value = 'linkedin'
  }
  router.replace({ query: { ...route.query, linkedin_oauth: undefined, message: undefined, account: undefined } })
  setTimeout(() => { linkedinOauthFlash.value = null }, 10000)
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

// ===========================================================================
// Mount — load every section's data in parallel so tab switches feel instant.
// ===========================================================================
onMounted(() => {
  Promise.allSettled([
    loadSiteSettings(),
    loadTelegramSettings(),
    loadMailSettings(),
    loadPublerSettings(),
    loadCvSettings(),
  ])
  consumeOauthFlash()
})
</script>

<style scoped>
/* Minimal custom styles - rely on Tailwind */
</style>
