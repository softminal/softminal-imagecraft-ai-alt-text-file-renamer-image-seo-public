<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('Integrations') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Enable or disable integrations with other plugins.') }}</p>
    <hr class="mb-6" />

    <!-- Filter buttons -->
    <div class="flex flex-wrap gap-2 mb-6">
      <button
        v-for="filter in filters"
        :key="filter.value"
        @click="activeFilter = filter.value"
        class="px-3 py-1.5 text-xs font-medium rounded-full border transition"
        :class="activeFilter === filter.value
          ? 'bg-blue-600 text-white border-blue-600'
          : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
      >
        {{ $t(filter.label) }}
      </button>
    </div>

    <div class="space-y-4">
      <div
        v-for="item in filteredIntegrations"
        :key="item.key"
        class="flex items-center justify-between p-4 rounded-lg border"
        :class="item.installed ? 'bg-white' : 'bg-gray-50 opacity-60'"
      >
        <div class="flex items-center gap-3">
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div class="w-8 h-8 shrink-0" v-html="logos[item.key]"></div>
          <div>
            <div class="font-medium text-sm text-gray-900">{{ item.name }}</div>
            <div class="text-xs text-gray-500 mt-0.5">{{ item.description }}</div>
          </div>
        </div>

        <div v-if="item.installed" class="flex items-center gap-3">
          <span
            v-if="isComingSoon(item.key)"
            class="text-[10px] font-semibold uppercase tracking-wider text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full"
          >
            {{ $t('Coming Soon') }}
          </span>

          <template v-else>
            <router-link
              v-if="hasSettingsPage(item.key) && isEnabled(item.key)"
              :to="getSettingsRoute(item.key)"
              class="text-gray-400 hover:text-blue-600 transition"
              :title="$t('Settings')"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </router-link>

            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                class="sr-only peer"
                :checked="isEnabled(item.key)"
                @change="toggleIntegration(item.key, $event.target.checked)"
              />
              <div class="w-9 h-5 bg-gray-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </template>
        </div>

        <span v-else class="text-xs text-gray-400 font-medium whitespace-nowrap">
          {{ $t('Plugin is not active') }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import Api from '../../services/Api'
import { notification } from '../../plugins/notification'
import { $t } from '../../utils/i18n'

const { settings, integrations } = inject('settingsContext')

const toggleIntegration = async (key, checked) => {
  const settingKey = 'icais_integration_' + key
  const value = checked ? '1' : '0'
  settings.value[settingKey] = value

  try {
    await Api.post('/settings', { settings: { [settingKey]: value } })
  } catch {
    settings.value[settingKey] = checked ? '0' : '1'
    notification.error($t('Failed to save setting'))
  }
}

const activeFilter = ref('all')

const filters = [
  { label: 'All', value: 'all' },
  { label: 'Block', value: 'block' },
  { label: 'SEO', value: 'seo' },
  { label: 'Ecommerce', value: 'ecommerce' },
  { label: 'Multilingual', value: 'multilingual' },
]

const filteredIntegrations = computed(() => {
  if (activeFilter.value === 'all') return integrations.value
  return integrations.value.filter(item => item.category === activeFilter.value)
})

const settingsRoutes = {
  woocommerce: 'woocommerce',
  yoast: 'seo',
  rankmath: 'seo',
  aioseo: 'seo',
  seopress: 'seo',
}

const hasSettingsPage = (key) => key in settingsRoutes
const getSettingsRoute = (key) => '/settings/integrations/' + settingsRoutes[key]

const isEnabled = (key) => {
  const val = settings.value['icais_integration_' + key]
  return val === '1' || val === 1
}

const comingSoonKeys = ['wpml', 'polylang']
const isComingSoon = (key) => comingSoonKeys.includes(key)

const logos = {
  kadence: '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#0058E6"/><path d="M8 8l8 8-8 8" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 8l8 8-8 8" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/></svg>',
  spectra: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#6104ff"/><path d="M16 6l2.5 6.5L25 15l-6.5 2.5L16 24l-2.5-6.5L7 15l6.5-2.5L16 6z" fill="#fff"/></svg>',
  yoast: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#a4286a"/><path d="M16 6C10.48 6 6 10.48 6 16s4.48 10 10 10 10-4.48 10-10S21.52 6 16 6zm-1.5 14.5L10 16l1.41-1.41L14.5 17.67l6.09-6.09L22 13l-7.5 7.5z" fill="#fff"/></svg>',
  rankmath: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#e93dee"/><text x="16" y="22" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="16" fill="#fff">RM</text></svg>',
  aioseo: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#005AE0"/><path d="M16 7l7 4.5v9L16 25l-7-4.5v-9L16 7z" stroke="#fff" stroke-width="2" fill="none"/><circle cx="16" cy="16" r="3" fill="#fff"/></svg>',
  seopress: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#08759b"/><text x="16" y="22" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="12" fill="#fff">SEO</text></svg>',
  woocommerce: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#7f54b3"/><path d="M6 10c0-1.1.9-2 2-2h16c1.1 0 2 .9 2 2v8c0 1.1-.9 2-2 2h-5l-3 4-3-4H8c-1.1 0-2-.9-2-2v-8z" fill="#fff"/><circle cx="12" cy="14" r="1.5" fill="#7f54b3"/><circle cx="16" cy="14" r="1.5" fill="#7f54b3"/><circle cx="20" cy="14" r="1.5" fill="#7f54b3"/></svg>',
  wpml: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#2d3b69"/><circle cx="16" cy="16" r="8" stroke="#fff" stroke-width="2" fill="none"/><path d="M8 16h16M16 8c-2.2 2.7-3.5 5.2-3.5 8s1.3 5.3 3.5 8c2.2-2.7 3.5-5.2 3.5-8s-1.3-5.3-3.5-8z" stroke="#fff" stroke-width="1.5" fill="none"/></svg>',
  polylang: '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="6" fill="#24831d"/><path d="M8 12h6v10H8z" fill="#fff" opacity=".9"/><path d="M18 8h6v14h-6z" fill="#fff" opacity=".6"/><path d="M13 15h6v7h-6z" fill="#fff" opacity=".75"/></svg>',
}
</script>
