<template>
  <div class="dashboard p-6">
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

    <!-- Needs attention: actionable to-do tiles, deep-linked to the fix screens -->
    <div v-if="audit && needsAttention.length" class="mb-6">
      <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">{{ $t('Needs attention') }}</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <router-link
          v-for="item in needsAttention"
          :key="item.key"
          :to="item.to"
          :class="['flex items-center justify-between bg-white border rounded-lg px-4 py-3 hover:shadow-md transition', toneClasses[item.tone]]"
        >
          <div>
            <div class="text-2xl font-bold leading-tight">{{ item.count }}</div>
            <div class="text-sm text-gray-600">{{ item.label }}</div>
          </div>
          <span class="text-sm font-medium">{{ $t('Fix') }} →</span>
        </router-link>
      </div>
    </div>

    <!-- Two flagship feature panels: Image SEO (wide) + Compression (compact widget) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
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
    <div v-if="audit" class="bg-white rounded-lg shadow border p-5 mb-6 flex items-center gap-5">
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

    <!-- Recent activity: latest AI generations -->
    <div v-if="recentActivity.length" class="mb-6">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $t('Recent activity') }}</h3>
        <router-link to="/scanner" class="text-xs text-blue-600 hover:underline">{{ $t('View all') }} →</router-link>
      </div>
      <div class="bg-white rounded-lg shadow border divide-y">
        <div v-for="item in recentActivity" :key="item.id" class="flex items-center gap-3 px-4 py-3">
          <img
            v-if="item.thumbnail"
            :src="item.thumbnail"
            :alt="item.filename"
            class="w-10 h-10 rounded object-cover bg-gray-100 shrink-0"
          />
          <div v-else class="w-10 h-10 rounded bg-gray-100 shrink-0"></div>
          <div class="min-w-0 flex-1">
            <div class="text-sm text-gray-800 truncate">{{ item.generated_alt || $t('(no alt text)') }}</div>
            <div class="text-xs text-gray-400 truncate">{{ item.filename }}</div>
          </div>
          <span
            :class="['shrink-0 text-xs font-medium px-2 py-0.5 rounded-full capitalize', statusBadge(item.status)]"
          >{{ item.status }}</span>
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
const recentActivity = ref([])

// Actionable to-do tiles — only surface items that actually need fixing.
const toneClasses = {
  red: 'border-red-200 text-red-600',
  indigo: 'border-indigo-200 text-indigo-600',
  green: 'border-green-200 text-green-600',
  orange: 'border-orange-200 text-orange-600',
  yellow: 'border-yellow-200 text-yellow-600',
}

const needsAttention = computed(() => {
  const a = audit.value
  if (!a) return []
  return [
    { key: 'missing', count: a.missing || 0, label: $t('Missing alt text'), to: '/scanner?filter=missing', tone: 'red' },
    { key: 'title', count: a.title_missing || 0, label: $t('Missing title'), to: '/scanner?filter=missing_title', tone: 'indigo' },
    { key: 'uncompressed', count: uncompressedCount.value, label: $t('Not compressed'), to: '/compression', tone: 'green' },
    { key: 'too_long', count: a.too_long || 0, label: $t('Alt text too long'), to: '/scanner?filter=too_long', tone: 'orange' },
    { key: 'generic', count: a.generic || 0, label: $t('Generic alt text'), to: '/scanner?filter=generic', tone: 'yellow' },
  ].filter((i) => i.count > 0)
})

const statusBadge = (status) => {
  if (status === 'accepted') return 'bg-green-100 text-green-700'
  if (status === 'rejected') return 'bg-red-100 text-red-700'
  return 'bg-yellow-100 text-yellow-700'
}

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
  return Math.round(parts.reduce((sum, n) => sum + n, 0) / parts.length)
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
    }

    // Parallelize dashboard stats, audit, and compression stats
    const [statsData] = await Promise.all([
      Api.get('/stats/dashboard'),
      runAudit(),
      loadCompression(),
    ])

    if (statsData.success) {
      stats.value = statsData.stats
      usageLimit.value = statsData.usage_limit || null
      recentActivity.value = statsData.recent_activity || []
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
.get-pro-btn:active {
    color: #ffffff;
}
</style>
