<template>
  <div class="min-h-screen bg-bg-deep text-fg-primary">

    <!-- Hero -->
    <section class="relative pt-28 pb-20 overflow-hidden">
      <div class="container-custom relative">
        <div class="max-w-3xl mx-auto text-center">
          <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Contact</span>
          <h1 class="section-heading text-4xl md:text-5xl lg:text-6xl mt-4 mb-6">
            Let's <span class="text-gradient">Work Together</span>
          </h1>
          <p class="text-lg text-fg-muted font-light max-w-xl mx-auto">
            Have a project, idea, or collaboration in mind? Reach out — I respond within 24 hours.
          </p>
        </div>
      </div>
    </section>

    <!-- Main Content -->
    <section class="pb-28">
      <div class="container-custom">
        <div class="max-w-6xl mx-auto grid lg:grid-cols-5 gap-10">

          <!-- Contact Form — 3 cols -->
          <div class="lg:col-span-3">

            <!-- Success State -->
            <div v-if="submitSuccess" class="bezel-shell">
              <div class="bezel-core p-8 text-center">
                <div class="w-14 h-14 rounded-full bg-success-500/10 flex items-center justify-center mx-auto mb-6">
                  <svg class="w-7 h-7 text-success-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <h2 class="section-heading text-2xl mb-3 text-fg-primary">Message Sent!</h2>
                <p class="text-fg-muted font-light mb-6">
                  Thank you for reaching out. I'll contact you via WhatsApp within 24 hours.
                </p>
                <button @click="resetForm" class="btn-glass text-sm">
                  Send Another Message
                </button>
              </div>
            </div>

            <!-- Contact Form -->
            <div v-else class="bezel-shell">
              <div class="bezel-core p-8">
                <h2 class="section-heading text-2xl mb-2 text-fg-primary">Send a Message</h2>
                <p class="text-fg-muted text-sm font-light mb-8">All fields are required unless noted.</p>

                <form @submit.prevent="handleSubmit" class="space-y-5" novalidate>

                  <!-- Name -->
                  <div>
                    <label for="name" class="block mono-label mb-2">
                      Name <span class="text-destructive">*</span>
                    </label>
                    <div class="bezel-shell-sm">
                      <input
                        id="name"
                        v-model="form.name"
                        @input="clearError('name')"
                        type="text"
                        autocomplete="name"
                        placeholder="Your full name"
                        class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none text-sm rounded-[calc(1.25rem-4px)]"
                        :class="{ 'ring-1 ring-destructive/40': formErrors.name }"
                      />
                    </div>
                    <p v-if="formErrors.name" class="mt-1.5 text-xs text-destructive">{{ formErrors.name }}</p>
                  </div>

                  <!-- Email -->
                  <div>
                    <label for="email" class="block mono-label mb-2">
                      Email <span class="text-destructive">*</span>
                    </label>
                    <div class="bezel-shell-sm">
                      <input
                        id="email"
                        v-model="form.email"
                        @input="clearError('email')"
                        type="email"
                        autocomplete="email"
                        placeholder="your.email@example.com"
                        class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none text-sm rounded-[calc(1.25rem-4px)]"
                        :class="{ 'ring-1 ring-destructive/40': formErrors.email }"
                      />
                    </div>
                    <p v-if="formErrors.email" class="mt-1.5 text-xs text-destructive">{{ formErrors.email }}</p>
                  </div>

                  <!-- WhatsApp -->
                  <div>
                    <label for="whatsapp" class="block mono-label mb-2">
                      WhatsApp Number <span class="text-destructive">*</span>
                    </label>
                    <div class="bezel-shell-sm">
                      <div class="relative">
                        <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                          <svg class="h-4 w-4 text-success-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                          </svg>
                        </div>
                        <input
                          id="whatsapp"
                          v-model="form.whatsapp_number"
                          @input="clearError('whatsapp_number')"
                          type="tel"
                          autocomplete="tel"
                          placeholder="+62812345678 or 08123456789"
                          class="w-full pl-11 pr-4 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none text-sm rounded-[calc(1.25rem-4px)]"
                          :class="{ 'ring-1 ring-destructive/40': formErrors.whatsapp_number }"
                        />
                      </div>
                    </div>
                    <p v-if="formErrors.whatsapp_number" class="mt-1.5 text-xs text-destructive">{{ formErrors.whatsapp_number }}</p>
                    <p v-else class="mt-1.5 text-xs text-fg-dim">For faster follow-up via WhatsApp</p>
                  </div>

                  <!-- Subject -->
                  <div>
                    <label for="subject" class="block mono-label mb-2">
                      Subject <span class="text-destructive">*</span>
                    </label>
                    <div class="bezel-shell-sm">
                      <input
                        id="subject"
                        v-model="form.subject"
                        @input="clearError('subject')"
                        type="text"
                        placeholder="What's this about?"
                        class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none text-sm rounded-[calc(1.25rem-4px)]"
                        :class="{ 'ring-1 ring-destructive/40': formErrors.subject }"
                      />
                    </div>
                    <p v-if="formErrors.subject" class="mt-1.5 text-xs text-destructive">{{ formErrors.subject }}</p>
                  </div>

                  <!-- Message -->
                  <div>
                    <label for="message" class="block mono-label mb-2">
                      Message <span class="text-destructive">*</span>
                    </label>
                    <div class="bezel-shell-sm">
                      <textarea
                        id="message"
                        v-model="form.message"
                        @input="clearError('message')"
                        rows="6"
                        placeholder="Tell me about your project or idea..."
                        class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none resize-none text-sm rounded-[calc(1.25rem-4px)]"
                        :class="{ 'ring-1 ring-destructive/40': formErrors.message }"
                      ></textarea>
                    </div>
                    <p v-if="formErrors.message" class="mt-1.5 text-xs text-destructive">{{ formErrors.message }}</p>
                    <p v-else class="mt-1.5 text-xs text-fg-dim text-right">{{ form.message.length }} / 5000</p>
                  </div>

                  <!-- Honeypot -->
                  <input v-model="form.website" type="text" name="website" autocomplete="off" tabindex="-1" class="absolute -left-[9999px] w-0 h-0 opacity-0" aria-hidden="true" />

                  <!-- Submit -->
                  <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full btn-gold justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg v-if="isSubmitting" class="w-4.5 h-4.5 animate-spin" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <svg v-else class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    {{ isSubmitting ? 'Sending...' : 'Send Message' }}
                    <span v-if="!isSubmitting" class="btn-icon-island">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"/></svg>
                    </span>
                  </button>

                  <p v-if="submitError" class="text-center text-sm text-destructive">{{ submitError }}</p>
                </form>
              </div>
            </div>
          </div>

          <!-- Right Sidebar — 2 cols -->
          <div class="lg:col-span-2 space-y-5">

            <!-- WhatsApp CTA -->
            <div v-if="siteSettings?.contact_phone" class="bezel-shell-sm">
              <div class="bezel-core-sm p-6">
                <span class="eyebrow-tag text-accent-gold mb-3 inline-flex">Quick Connect</span>
                <h3 class="section-heading text-lg text-fg-primary mt-3 mb-2">Chat on WhatsApp</h3>
                <p class="text-fg-muted text-sm font-light mb-5">Fastest way to reach me. Available Mon-Fri, 9am-6pm WIB.</p>
                <a
                  :href="whatsappLink"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn-gold w-full justify-center"
                >
                  <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                  {{ siteSettings.contact_phone }}
                </a>
              </div>
            </div>

            <!-- Contact Info -->
            <div class="bezel-shell-sm">
              <div class="bezel-core-sm p-6 space-y-5">
                <span class="eyebrow-tag text-accent-cyan inline-flex">Contact Info</span>

                <div v-if="siteSettings?.contact_email" class="flex items-center gap-4 mt-3">
                  <span class="w-9 h-9 rounded-full bg-accent-cyan/8 border border-border-hairline flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-accent-cyan" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                  </span>
                  <div class="min-w-0">
                    <p class="mono-label text-[10px] mb-0.5">Email</p>
                    <a :href="`mailto:${siteSettings.contact_email}`" class="text-fg-primary hover:text-accent-cyan transition-all duration-700 ease-spring text-sm truncate block">
                      {{ siteSettings.contact_email }}
                    </a>
                  </div>
                </div>

                <div class="flex items-center gap-4">
                  <span class="w-9 h-9 rounded-full bg-accent-gold/8 border border-border-hairline flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-accent-gold" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </span>
                  <div>
                    <p class="mono-label text-[10px] mb-0.5">Response Time</p>
                    <p class="text-fg-primary text-sm">Within 24 hours</p>
                  </div>
                </div>

                <div v-if="siteSettings?.address" class="flex items-center gap-4">
                  <span class="w-9 h-9 rounded-full bg-accent-indigo/8 border border-border-hairline flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-accent-indigo" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                  </span>
                  <div>
                    <p class="mono-label text-[10px] mb-0.5">Location</p>
                    <p class="text-fg-primary text-sm">{{ siteSettings.address }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Social Links -->
            <div v-if="socialLinks.length > 0" class="bezel-shell-sm">
              <div class="bezel-core-sm p-6">
                <span class="eyebrow-tag text-accent-gold mb-4 inline-flex">Find Me On</span>
                <div class="flex flex-wrap gap-2.5 mt-3">
                  <a
                    v-for="(link, index) in socialLinks"
                    :key="index"
                    :href="link.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :title="link.platform"
                    class="w-10 h-10 rounded-full bg-white/4 border border-border-hairline flex items-center justify-center text-fg-muted hover:text-accent-gold hover:border-accent-gold/30 transition-all duration-700 ease-spring"
                  >
                    <i v-if="link.icon" :class="link.icon + ' text-sm'"></i>
                    <svg v-else-if="link.platform.toLowerCase() === 'github'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" /></svg>
                    <svg v-else-if="link.platform.toLowerCase() === 'linkedin'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    <svg v-else-if="link.platform.toLowerCase() === 'twitter' || link.platform.toLowerCase() === 'x'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    <svg v-else-if="link.platform.toLowerCase() === 'instagram'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 00-1.242-7.244l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" /></svg>
                  </a>
                </div>
              </div>
            </div>

            <!-- Newsletter -->
            <div class="bezel-shell-sm">
              <div class="bezel-core-sm p-6">
                <span class="eyebrow-tag text-accent-indigo mb-3 inline-flex">Newsletter</span>
                <h3 class="section-heading text-lg text-fg-primary mt-3 mb-2">Stay Updated</h3>
                <p class="text-fg-muted text-sm font-light mb-5">Get notified about new projects, articles, and AI insights.</p>
                <div class="flex gap-2">
                  <div class="flex-1 min-w-0 bezel-shell-sm">
                    <input
                      v-model="newsletterEmail"
                      type="email"
                      placeholder="your@email.com"
                      class="w-full px-4 py-3 bg-transparent text-fg-primary placeholder:text-fg-dim focus:outline-none text-sm rounded-[calc(1.25rem-4px)]"
                      @keydown.enter.prevent="handleNewsletter"
                    />
                  </div>
                  <button @click="handleNewsletter" class="btn-glass px-4 py-3 text-sm shrink-0">
                    Subscribe
                  </button>
                </div>
                <p v-if="newsletterMessage" class="mt-3 text-xs text-fg-muted">
                  Newsletter coming soon — stay tuned!
                </p>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useContact } from '@/composables/useContact'
import api from '@/services/api'

const { submitContact, isSubmitting } = useContact()

const form = ref({
  name: '',
  email: '',
  whatsapp_number: '',
  subject: '',
  message: '',
  website: '',
  form_timestamp: null
})

const formErrors = ref({})
const submitSuccess = ref(false)
const submitError = ref(null)

const siteSettings = ref(null)
const aboutSettings = ref(null)

const newsletterEmail = ref('')
const newsletterMessage = ref(null)

const socialLinks = computed(() => {
  const links = aboutSettings.value?.social_links
  if (Array.isArray(links) && links.length > 0) return links
  return []
})

const whatsappLink = computed(() => {
  if (!siteSettings.value?.contact_phone) return '#'
  const phone = siteSettings.value.contact_phone.replace(/[^0-9]/g, '')
  const msg = encodeURIComponent("Hi! I found your portfolio and would like to discuss a project.")
  return `https://wa.me/${phone}?text=${msg}`
})

const validateForm = () => {
  const errors = {}
  if (!form.value.name || form.value.name.trim().length < 2) errors.name = 'Name must be at least 2 characters'
  const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/
  if (!form.value.email) errors.email = 'Email is required'
  else if (!emailRegex.test(form.value.email)) errors.email = 'Please enter a valid email address'
  else if (form.value.email.length > 254) errors.email = 'Email address is too long'
  if (!form.value.whatsapp_number) {
    errors.whatsapp_number = 'WhatsApp number is required'
  } else {
    const cleaned = form.value.whatsapp_number.replace(/\D/g, '')
    if (cleaned.length < 8) errors.whatsapp_number = 'Phone number too short (min 8 digits)'
    else if (cleaned.length > 15) errors.whatsapp_number = 'Phone number too long (max 15 digits)'
    else if (cleaned.startsWith('0') && (cleaned.length < 10 || cleaned.length > 13)) errors.whatsapp_number = 'Invalid Indonesian format (e.g. 08123456789)'
    else if (cleaned.startsWith('62') && (cleaned.length < 11 || cleaned.length > 14)) errors.whatsapp_number = 'Invalid Indonesian format (e.g. +628123456789)'
  }
  if (!form.value.subject || form.value.subject.trim().length < 3) errors.subject = 'Subject must be at least 3 characters'
  else if (form.value.subject.trim().length > 255) errors.subject = 'Subject too long (max 255 characters)'
  if (!form.value.message || form.value.message.trim().length < 10) errors.message = 'Message must be at least 10 characters'
  else if (form.value.message.trim().length > 5000) errors.message = 'Message too long (max 5000 characters)'
  return errors
}

const clearError = (field) => {
  if (formErrors.value[field]) delete formErrors.value[field]
  submitError.value = null
}

const handleSubmit = async () => {
  submitError.value = null
  formErrors.value = validateForm()
  if (Object.keys(formErrors.value).length > 0) return
  const payload = {
    name: form.value.name.trim(),
    email: form.value.email.trim().toLowerCase(),
    whatsapp_number: form.value.whatsapp_number.trim(),
    subject: form.value.subject.trim(),
    message: form.value.message.trim(),
    website: form.value.website,
    form_timestamp: form.value.form_timestamp
  }
  const result = await submitContact(payload)
  if (result.success) submitSuccess.value = true
  else if (result.errors) formErrors.value = result.errors
  else submitError.value = result.error || 'Failed to send message. Please try again.'
}

const resetForm = () => {
  form.value = { name: '', email: '', whatsapp_number: '', subject: '', message: '', website: '', form_timestamp: Math.floor(Date.now() / 1000) }
  formErrors.value = {}
  submitError.value = null
  submitSuccess.value = false
}

const handleNewsletter = () => { newsletterMessage.value = true }

const fetchSettings = async () => {
  try {
    const [siteRes, aboutRes] = await Promise.all([
      api.get('/settings/site'),
      api.get('/settings/about')
    ])
    if (siteRes.data.success) siteSettings.value = siteRes.data.data
    if (aboutRes.data.success) aboutSettings.value = aboutRes.data.data
  } catch (err) {
    console.error('Failed to fetch settings:', err)
  }
}

onMounted(() => {
  fetchSettings()
  form.value.form_timestamp = Math.floor(Date.now() / 1000)
})
</script>
