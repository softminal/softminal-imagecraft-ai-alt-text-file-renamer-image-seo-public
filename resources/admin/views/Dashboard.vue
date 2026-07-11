<template>
  <div class="dashboard p-6">
    <!-- Greeting header -->
    <div class="flex items-center gap-4 mb-6">
      <img
        v-if="userAvatar"
        :src="userAvatar"
        alt=""
        class="w-14 h-14 rounded-full bg-purple-100 object-cover shrink-0"
      />
      <div
        v-else
        class="w-14 h-14 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-2xl font-bold shrink-0"
      >
        {{ (userName || 'I').charAt(0).toUpperCase() }}
      </div>
      <div>
        <h2 class="text-[26px]! font-bold! text-gray-900 m-0! p-0! leading-tight">{{ greeting }} {{ userName }} 👋</h2>
        <p class="text-[17px]! text-gray-500 m-0! mt-1! leading-tight">{{ $t('Welcome to ImageCraft') }}</p>
      </div>
    </div>

    <!-- No API key configured: AI features need a key; compression does not. -->
    <div v-if="!hasAiKey" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-blue-800">
          {{ $t('Add your API key to generate alt text, titles and captions. Image compression works without a key.') }}
        </p>
      </div>
      <a href="#/settings/api" class="shrink-0 text-sm font-semibold text-blue-700 underline hover:text-blue-900">
        {{ $t('Add API key') }}
      </a>
    </div>

    <!-- Shared daily free quota — one pool across every action (free only) -->
    <div v-if="usageLimit && !appVars.isPro && usageLimit.daily_limit > 0" class="bg-white rounded-lg shadow border p-4 mb-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm text-gray-700">
          {{ $t('Daily free usage') }}:
          <span class="font-bold" :class="usageLimit.daily_remaining === 0 ? 'text-red-600' : 'text-gray-900'">
            {{ usageLimit.daily_used }} / {{ usageLimit.daily_limit }}
          </span>
        </span>
        <div class="flex items-center gap-3">
          <span v-if="usageLimit.reset_in" class="text-xs text-gray-400">{{ $t('Resets in %s', usageLimit.reset_in) }}</span>
          <a
            :href="proUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="get-pro-btn inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-amber-500 rounded-md shadow-sm hover:bg-amber-600 transition-colors no-underline"
          >
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.07 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.286-3.957z" />
            </svg>
            {{ $t('Get Pro') }}
          </a>
        </div>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div class="h-2 rounded-full transition-all" :class="usageBarColor" :style="{ width: usageBarWidth + '%' }"></div>
      </div>
      <div class="mt-2 text-xs text-gray-500">
        {{ $t('Shared across AI generation and compression. Generating and compressing the same image counts as two.') }}
      </div>
    </div>

    <!-- Main layout: content (3/4) + full-height sidebar (1/4) -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- ==================== LEFT: main content ==================== -->
       
      <div class="lg:col-span-3 flex flex-col gap-6">
        <div
          v-if="showFallbackPromo"
          class="bg-white border border-blue-200 rounded-lg p-4 flex items-start justify-between gap-4"
        >
      <div class="flex items-start gap-3 min-w-0">
        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600 shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </span>
        <div class="min-w-0">
          <div class="text-sm font-semibold text-gray-800">
            <span class="inline-block text-[10px] font-bold uppercase tracking-wide text-blue-600 bg-blue-50 rounded px-1.5 py-0.5 mr-1.5 align-middle">{{ $t('New') }}</span>
            {{ $t('Image Fallbacks — never show a broken image') }}
          </div>
          <p class="text-sm text-gray-500 mt-0.5">
            {{ $t('Pick one fallback image: it replaces broken images for visitors and acts as the featured image for posts without one. Non-destructive, off by default.') }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <router-link
          to="/settings/fallback"
          class="fallback-promo-btn bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition no-underline"
        >
          {{ $t('Set up') }} →
        </router-link>
        <button
          @click="dismissFallbackPromo"
          :aria-label="$t('Dismiss')"
          class="text-gray-400 hover:text-gray-600 text-lg leading-none"
        >
          ✕
        </button>
      </div>
    </div>
        <!-- Stat cards -->
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
          <div v-for="stat in statCards" :key="stat.key" class="bg-white rounded-lg shadow border p-5">
            <span :class="['inline-flex items-center justify-center w-11 h-11 rounded-xl mb-3', stat.iconClass]">
              <!-- eslint-disable-next-line vue/no-v-html -->
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" v-html="stat.icon"></svg>
            </span>
            <div class="text-sm text-gray-500">{{ stat.label }}</div>
            <div class="text-2xl font-bold text-gray-900 mt-0.5">{{ stat.value }}</div>
          </div>
        </div>

        <!-- Two flagship feature panels: Image SEO (wide) + Compression (compact widget) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- ============ Image SEO (spans 2 columns) ============ -->
      <div class="lg:col-span-2 bg-white rounded-lg shadow border flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 pt-3">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6" />
              </svg>
            </span>
            <h3 class="text-lg font-semibold">{{ $t('Image SEO') }}</h3>
          </div>
          <!-- API status (folded into the panel header) -->
          <div class="text-xs">
            <span v-if="stats.api_status?.connected" class="inline-flex items-center gap-1 text-green-600">
              <span>✓</span>{{ $t('Connected to %s', stats.api_status.provider) }}
            </span>
            <router-link v-else-if="isAdmin" to="/settings/api" class="inline-flex items-center gap-1 text-red-600 underline">
              <span>✗</span>{{ $t('Configure API') }}
            </router-link>
            <span v-else class="inline-flex items-center gap-1 text-gray-500">
              <span>✗</span>{{ $t('Not connected') }}
            </span>
          </div>
        </div>

        <div class="px-6 py-5 flex-1">
          <!-- Coverage rings: Alt text + Title + Description -->
          <div class="flex items-center justify-around gap-4">
            <!-- Alt text -->
            <div class="text-center">
              <div class="relative w-20 h-20 mx-auto">
                <svg class="w-20 h-20 -rotate-90" viewBox="0 0 100 100">
                  <circle cx="50" cy="50" r="42" stroke="#e5e7eb" stroke-width="8" fill="none" />
                  <circle
                    cx="50" cy="50" r="42"
                    :stroke="altColor"
                    stroke-width="8"
                    fill="none"
                    stroke-linecap="round"
                    :stroke-dasharray="263.89"
                    :stroke-dashoffset="263.89 - (263.89 * (audit?.score || 0) / 100)"
                  />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                  <span class="text-lg font-bold">{{ audit?.score || 0 }}%</span>
                </div>
              </div>
              <div class="mt-2 text-sm font-semibold text-gray-700">{{ $t('Alt text') }}</div>
              <div class="text-xs text-gray-400">{{ $t('covered') }}</div>
            </div>
            <!-- Title -->
            <div class="text-center">
              <div class="relative w-20 h-20 mx-auto">
                <svg class="w-20 h-20 -rotate-90" viewBox="0 0 100 100">
                  <circle cx="50" cy="50" r="42" stroke="#e5e7eb" stroke-width="8" fill="none" />
                  <circle
                    cx="50" cy="50" r="42"
                    :stroke="titleColor"
                    stroke-width="8"
                    fill="none"
                    stroke-linecap="round"
                    :stroke-dasharray="263.89"
                    :stroke-dashoffset="263.89 - (263.89 * (audit?.title_score || 0) / 100)"
                  />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                  <span class="text-lg font-bold">{{ audit?.title_score || 0 }}%</span>
                </div>
              </div>
              <div class="mt-2 text-sm font-semibold text-gray-700">{{ $t('Title') }}</div>
              <div class="text-xs text-gray-400">{{ $t('covered') }}</div>
            </div>
            <!-- Description (Caption) -->
            <div class="text-center">
              <div class="relative w-20 h-20 mx-auto">
                <svg class="w-20 h-20 -rotate-90" viewBox="0 0 100 100">
                  <circle cx="50" cy="50" r="42" stroke="#e5e7eb" stroke-width="8" fill="none" />
                  <circle
                    cx="50" cy="50" r="42"
                    :stroke="captionColor"
                    stroke-width="8"
                    fill="none"
                    stroke-linecap="round"
                    :stroke-dasharray="263.89"
                    :stroke-dashoffset="263.89 - (263.89 * (audit?.caption_score || 0) / 100)"
                  />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                  <span class="text-lg font-bold">{{ audit?.caption_score || 0 }}%</span>
                </div>
              </div>
              <div class="mt-2 text-sm font-semibold text-gray-700">{{ $t('Description') }}</div>
              <div class="text-xs text-gray-400">{{ $t('covered') }}</div>
            </div>
          </div>
        </div>

        <!-- Footer CTAs -->
        <div class="px-6 py-4 bg-gray-50 border-t flex flex-wrap items-center gap-3">
          <button
            @click="$router.push('/scanner')"
            class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition"
          >
            {{ $t('Scan Media Library') }} →
          </button>
          <button
            v-if="woocommerceInstalled"
            @click="$router.push('/woocommerce')"
            class="bg-white border text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100 transition"
          >
            {{ $t('WooCommerce') }}
          </button>
        </div>
      </div>

      <!-- ============ Image Compression ============ -->
      <div class="bg-white rounded-lg shadow border flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 pt-3">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-green-50 text-green-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12l4-4m-4 4l4 4m10-4l-4-4m4 4l-4 4" />
              </svg>
            </span>
            <h3 class="text-lg font-semibold">{{ $t('Image Compression') }}</h3>
          </div>
          <span class="text-xs text-green-600 inline-flex items-center gap-1">
            <span>✓</span>{{ $t('Local engine') }}
          </span>
        </div>

        <div class="px-6 py-5 flex-1">
          <!-- Compact widget: total saved + compressed count stacked -->
          <div class="text-3xl font-bold text-green-600 leading-tight">{{ compressionStats.total_saved_human || '0 B' }}</div>
          <div class="text-xs text-gray-500">{{ $t('saved') }}</div>
          <div class="mt-4 text-xl font-bold text-gray-800">
            {{ compressionStats.total_compressed || 0 }}
            <span class="text-sm font-normal text-gray-500">{{ $t('compressed') }}</span>
          </div>
        </div>

        <!-- Footer CTAs -->
        <div class="px-6 py-4 bg-gray-50 border-t flex flex-wrap items-center gap-3">
          <button
            @click="$router.push('/compression')"
            class="bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition"
          >
            {{ $t('Compress Images') }} →
          </button>
        </div>
      </div>
    </div>

        <!-- Overall optimization: single blended KPI across SEO + compression -->
        <div v-if="audit" class="bg-white rounded-lg shadow border p-5 flex items-center gap-5">
      <div class="relative w-24 h-24 shrink-0">
        <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
          <circle cx="50" cy="50" r="42" stroke="#e5e7eb" stroke-width="9" fill="none" />
          <circle
            cx="50" cy="50" r="42"
            :stroke="overallColor"
            stroke-width="9"
            fill="none"
            stroke-linecap="round"
            :stroke-dasharray="263.89"
            :stroke-dashoffset="263.89 - (263.89 * overallScore / 100)"
          />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
          <span class="text-2xl font-bold">{{ overallScore }}%</span>
        </div>
      </div>
      <div class="min-w-0">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $t('Overall optimization') }}</div>
        <div class="text-lg font-semibold text-gray-800">{{ overallLabel }}</div>
        <div class="text-sm text-gray-500">{{ $t('Blended across alt text, titles, descriptions and compression.') }}</div>
      </div>
        </div>
      </div>

      <!-- ==================== RIGHT: full-height sidebar ==================== -->
      <div class="lg:col-span-1">
        <div class="flex flex-col gap-6 lg:sticky lg:top-6">
          <!-- Quick Actions widget -->
          <div class="bg-white rounded-lg shadow border p-5">
            <h3 class="text-base font-semibold text-gray-900 mb-2 mt-0">{{ $t('Quick Actions') }}</h3>
            <ul class="m-0 p-0 list-none flex flex-col">
              <li v-for="action in quickActions" :key="action.key" class="m-0">
                <router-link
                  :to="action.to"
                  class="quick-action inline-flex items-center gap-3 py-2 text-[15px] font-medium text-gray-800"
                >
                  <!-- eslint-disable-next-line vue/no-v-html -->
                  <svg class="w-5 h-5 shrink-0 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" v-html="action.icon"></svg>
                  <span>{{ action.label }}</span>
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Needs Attention widget: compact list, deep-linked to the fix screens -->
          <div v-if="audit && needsAttention.length" class="bg-white rounded-lg shadow border p-5">
            <h3 class="text-base font-semibold text-gray-900 mb-2 mt-0">{{ $t('Needs Attention') }}</h3>
            <ul class="m-0 p-0 list-none flex flex-col">
              <li v-for="item in needsAttention" :key="item.key" class="m-0">
                <router-link
                  :to="item.to"
                  class="attention-row flex items-center justify-between gap-2 py-2"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <span :class="['inline-flex items-center justify-center min-w-[28px] h-6 px-1.5 rounded-md text-xs font-bold', badgeClasses[item.tone]]">
                      {{ item.count }}
                    </span>
                    <span class="text-sm text-gray-700 truncate">{{ item.label }}</span>
                  </span>
                  <span class="text-xs font-medium text-blue-600 shrink-0">{{ $t('Fix') }} →</span>
                </router-link>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'
import { getSettings } from '../router'
import { $t } from '../utils/i18n'

const router = useRouter()
const stats = ref({})
const usageLimit = ref(null)
const woocommerceInstalled = ref(false)
const audit = ref(null)
const auditLoading = ref(false)
const compressionStats = ref({})
const brokenCount = ref(0)

// Image Fallbacks discovery card: shown to admins while the module is off and
// not yet dismissed (dismissal remembered per browser via localStorage).
const FALLBACK_PROMO_KEY = 'icais_fallback_promo_dismissed'
const fallbackEnabled = ref(true) // assume on until settings load (never flash the promo)
const fallbackPromoDismissed = ref(true)
try {
  fallbackPromoDismissed.value = window.localStorage?.getItem(FALLBACK_PROMO_KEY) === '1'
} catch {
  // localStorage unavailable (privacy mode) — keep the promo hidden
}
const showFallbackPromo = computed(() => isAdmin && !fallbackEnabled.value && !fallbackPromoDismissed.value)

// Quick actions: one-click jump to every core task. Admin-only entries are
// filtered out for permitted non-admin roles (they can't reach /settings or
// /woocommerce — the router guard blocks those).
const quickActions = computed(() => {
  const actions = [
    {
      key: 'compress',
      label: $t('Compress images'),
      to: '/compression',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12l4-4m-4 4l4 4m10-4l-4-4m4 4l-4 4"/>',
    },
    {
      key: 'alt',
      label: $t('Generate alt text'),
      to: '/scanner?filter=missing',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
    },
  ]
  if (isAdmin) {
    actions.push({
      key: 'fallback',
      label: fallbackEnabled.value ? $t('Image fallbacks') : $t('Set up fallbacks'),
      to: '/settings/fallback',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m1 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z"/>',
    })
    if (woocommerceInstalled.value) {
      actions.push({
        key: 'woo',
        label: $t('WooCommerce images'),
        to: '/woocommerce',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
      })
    }
  }
  actions.push({
    key: 'rename',
    label: $t('Rename files'),
    to: '/scanner',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
  })
  return actions
})
const dismissFallbackPromo = () => {
  fallbackPromoDismissed.value = true
  try {
    window.localStorage?.setItem(FALLBACK_PROMO_KEY, '1')
  } catch {
    // Non-fatal — the card just reappears next visit
  }
}

// Needs-attention count badges (sidebar list).
const badgeClasses = {
  red: 'bg-red-50 text-red-600',
  indigo: 'bg-indigo-50 text-indigo-600',
  green: 'bg-green-50 text-green-600',
  orange: 'bg-orange-50 text-orange-600',
  yellow: 'bg-yellow-50 text-yellow-600',
}

const needsAttention = computed(() => {
  const a = audit.value
  if (!a) return []
  return [
    { key: 'broken', count: brokenCount.value, label: $t('Broken images'), to: '/settings/fallback', tone: 'red' },
    { key: 'missing', count: a.missing || 0, label: $t('Missing alt text'), to: '/scanner?filter=missing', tone: 'red' },
    { key: 'title', count: a.title_missing || 0, label: $t('Missing title'), to: '/scanner?filter=missing_title', tone: 'indigo' },
    { key: 'uncompressed', count: uncompressedCount.value, label: $t('Not compressed'), to: '/compression', tone: 'green' },
    { key: 'too_long', count: a.too_long || 0, label: $t('Alt text too long'), to: '/scanner?filter=too_long', tone: 'orange' },
    { key: 'generic', count: a.generic || 0, label: $t('Generic alt text'), to: '/scanner?filter=generic', tone: 'yellow' },
  ].filter((i) => i.count > 0)
})

// Stat cards row — the at-a-glance numbers (FluentMembers-style layout).
const statCards = computed(() => [
  {
    key: 'total',
    label: $t('Total Images'),
    value: stats.value.total_images ?? 0,
    iconClass: 'bg-blue-50 text-blue-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
  },
  {
    key: 'alt',
    label: $t('Alt Text Coverage'),
    value: (audit.value?.score ?? 0) + '%',
    iconClass: 'bg-purple-50 text-purple-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
  },
  {
    key: 'compressed',
    label: $t('Images Compressed'),
    value: compressionStats.value.total_compressed ?? 0,
    iconClass: 'bg-green-50 text-green-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12l4-4m-4 4l4 4m10-4l-4-4m4 4l-4 4"/>',
  },
  {
    key: 'saved',
    label: $t('Space Saved'),
    value: compressionStats.value.total_saved_human || '0 B',
    iconClass: 'bg-teal-50 text-teal-600',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3m-16 5c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3"/>',
  },
])

// Greeting header: time-of-day greeting + current user's name/avatar (from PHP).
const userName = window.icais?.userName || ''
const userAvatar = window.icais?.userAvatar || ''
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return $t('Good morning')
  if (h < 17) return $t('Good afternoon')
  return $t('Good evening')
})

const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'
// Non-admins (permitted roles) can't reach the admin-only /settings endpoint, so
// they skip the wizard/settings check and admin-only CTAs.
const isAdmin = window.icais?.isAdmin ?? false
// Show the "add API key" prompt only when we know there is no usable AI provider
// (undefined → assume configured, so existing setups never see a spurious nag).
const hasAiKey = window.icais?.hasAiKey ?? true

// Best-effort "not yet optimized" count: total images minus those already compressed.
const uncompressedCount = computed(() => {
  const total = stats.value.total_images || 0
  const done = compressionStats.value.total_compressed || 0
  return Math.max(0, total - done)
})

const usageBarWidth = computed(() => {
  if (!usageLimit.value || !usageLimit.value.daily_limit) return 0
  return Math.min(100, (usageLimit.value.daily_used / usageLimit.value.daily_limit) * 100)
})

const usageBarColor = computed(() => {
  const pct = usageBarWidth.value
  if (pct >= 100) return 'bg-red-500'
  if (pct >= 80) return 'bg-orange-500'
  return 'bg-blue-500'
})

// Ring stroke color by coverage score (shared by the Alt text + Title rings).
const ringColor = (s) => {
  if (s >= 80) return '#16a34a'
  if (s >= 60) return '#ca8a04'
  if (s >= 40) return '#ea580c'
  return '#dc2626'
}
const altColor = computed(() => ringColor(audit.value?.score ?? 0))
const titleColor = computed(() => ringColor(audit.value?.title_score ?? 0))
const captionColor = computed(() => ringColor(audit.value?.caption_score ?? 0))

// Compression coverage (% of images compressed) and a single blended
// "overall optimization" score across alt text, titles, descriptions and
// compression — a motivational headline KPI (new data, not a duplicate).
const compressionScore = computed(() => {
  const total = stats.value.total_images || 0
  if (total <= 0) return 0
  const done = compressionStats.value.total_compressed || 0
  return Math.min(100, Math.round((done / total) * 100))
})
const overallScore = computed(() => {
  const a = audit.value
  if (!a) return 0
  const parts = [a.score || 0, a.title_score || 0, a.caption_score || 0, compressionScore.value]
  const base = Math.round(parts.reduce((sum, n) => sum + n, 0) / parts.length)
  // Broken images are a visible SEO/UX defect — apply a light penalty (max 10).
  const penalty = brokenCount.value > 0 ? Math.min(10, brokenCount.value) : 0
  return Math.max(0, base - penalty)
})
const overallColor = computed(() => ringColor(overallScore.value))
const overallLabel = computed(() => {
  const s = overallScore.value
  if (s >= 80) return $t('Excellent')
  if (s >= 60) return $t('Good')
  if (s >= 40) return $t('Needs Work')
  return $t('Poor')
})

const runAudit = async () => {
  auditLoading.value = true
  try {
    const result = await Api.get('/stats/audit')
    if (result.success) {
      audit.value = result.audit
    }
  } catch (error) {
    console.error('Failed to load audit:', error)
  } finally {
    auditLoading.value = false
  }
}

const loadCompression = async () => {
  try {
    const result = await Api.get('/compressor/stats')
    if (result.success) {
      compressionStats.value = result.stats || {}
    }
  } catch (error) {
    console.error('Failed to load compression stats:', error)
  }
}

const loadBrokenScan = async () => {
  try {
    // The scan runs under a server-side time budget; keep calling while it
    // reports partial progress (bounded so a stuck server can't loop forever).
    for (let i = 0; i < 20; i++) {
      const result = await Api.get('/fallback/scan-broken')
      if (!result.success) return
      brokenCount.value = result.broken_count || 0
      if (!result.partial) return
    }
  } catch (error) {
    console.error('Failed to scan for broken images:', error)
  }
}

const loadDashboard = async () => {
  try {
    // Admins verify setup via the admin-only /settings endpoint; non-admins skip
    // it (they can't configure setup and would 403 / bounce to the wizard).
    if (isAdmin) {
      const settingsData = await getSettings()
      // Setup is complete once the wizard is done (or skipped). An API key is no
      // longer required — compression-only users can use the plugin without one,
      // and AI features prompt for a key at point of use. Mirrors router.js.
      if (!settingsData.wizard_completed) {
        router.push('/wizard')
        return
      }

      // Read WooCommerce status from settings (no extra API call)
      woocommerceInstalled.value = settingsData.woo_active || false

      // Image Fallbacks promo: only surface while the module is off.
      fallbackEnabled.value = settingsData.settings?.icais_fallback_enabled === '1'
    }

    // Parallelize dashboard stats, audit, and compression stats
    const [statsData] = await Promise.all([
      Api.get('/stats/dashboard'),
      runAudit(),
      loadCompression(),
      loadBrokenScan(),
    ])

    if (statsData.success) {
      stats.value = statsData.stats
      usageLimit.value = statsData.usage_limit || null
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  }
}

onMounted(() => {
  loadDashboard();
})
</script>

<style scoped>
.get-pro-btn,
.get-pro-btn:hover,
.get-pro-btn:focus,
.get-pro-btn:active,
.fallback-promo-btn,
.fallback-promo-btn:hover,
.fallback-promo-btn:focus,
.fallback-promo-btn:active {
    color: #ffffff;
}

/* Quick actions: link-style rows — dark text, underline on hover. */
.quick-action {
    color: #1f2937;
    text-decoration: none;
}
.quick-action:hover,
.quick-action:focus {
    color: #1d4ed8;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.quick-action:focus,
.quick-action:active {
    outline: none;
    box-shadow: none;
}

/* Needs-attention rows are links — keep them quiet until hover. */
.attention-row {
    text-decoration: none;
}
.attention-row:hover span.text-gray-700 {
    color: #1d4ed8;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.attention-row:focus,
.attention-row:active {
    outline: none;
    box-shadow: none;
}
</style>
