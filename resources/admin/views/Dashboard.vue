<template>
  <div class="dashboard p-6">
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

    <!-- Two flagship feature panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- ============ AI Alt Text ============ -->
      <div class="bg-white rounded-lg shadow border flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 pt-6">
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 text-blue-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16M4 6v12a2 2 0 002 2h12a2 2 0 002-2V6" />
              </svg>
            </span>
            <h3 class="text-lg font-semibold">{{ $t('AI Alt Text') }}</h3>
          </div>
          <!-- API status (folded into the panel header) -->
          <div class="text-xs">
            <span v-if="stats.api_status?.connected" class="inline-flex items-center gap-1 text-green-600">
              <span>✓</span>{{ $t('Connected to %s', stats.api_status.provider) }}
            </span>
            <router-link v-else to="/settings/api" class="inline-flex items-center gap-1 text-red-600 underline">
              <span>✗</span>{{ $t('Configure API') }}
            </router-link>
          </div>
        </div>

        <div class="px-6 py-5 flex-1">
          <!-- Score ring + headline metric -->
          <div class="flex items-center gap-5">
            <div class="relative w-24 h-24 shrink-0">
              <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="42" stroke="#e5e7eb" stroke-width="8" fill="none" />
                <circle
                  cx="50" cy="50" r="42"
                  :stroke="scoreColor"
                  stroke-width="8"
                  fill="none"
                  stroke-linecap="round"
                  :stroke-dasharray="263.89"
                  :stroke-dashoffset="263.89 - (263.89 * (audit?.score || 0) / 100)"
                />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-xl font-bold">{{ audit?.score || 0 }}%</span>
              </div>
            </div>
            <div>
              <div class="text-sm font-semibold" :class="scoreLabelColor">{{ scoreLabel }} {{ $t('SEO score') }}</div>
              <div class="mt-1 text-2xl font-bold text-orange-600">
                {{ stats.missing_alt || 0 }}
                <span class="text-base font-normal text-gray-500">{{ $t('missing') }}</span>
              </div>
              <div class="text-xs text-gray-500">
                {{ $t('%1$s of %2$s images optimized', audit?.good || 0, audit?.total_images || stats.total_images || 0) }}
              </div>
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
        <div class="flex items-center justify-between px-6 pt-6">
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
          <!-- Headline metric: total saved -->
          <div class="flex items-center gap-5">
            <div class="shrink-0 w-24 text-center">
              <div class="text-3xl font-bold text-green-600 leading-tight">{{ compressionStats.total_saved_human || '0 B' }}</div>
              <div class="text-xs text-gray-500">{{ $t('saved') }}</div>
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-800">
                {{ compressionStats.total_compressed || 0 }}
                <span class="text-base font-normal text-gray-500">{{ $t('compressed') }}</span>
              </div>
              <div class="text-xs text-gray-500">
                {{ $t('%s images not yet optimized', uncompressedCount) }}
              </div>
            </div>
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

    <!-- Alt-text health: actionable problem states, deep-linking into Scanner filters -->
    <div v-if="audit && audit.total_images > 0" class="mb-6">
      <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-2">{{ $t('Alt text health') }}</div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <router-link
          to="/scanner?filter=missing"
          class="flex items-center justify-between bg-white border border-red-200 rounded-lg px-4 py-3 hover:shadow-md transition cursor-pointer"
        >
          <span class="text-sm text-red-600">{{ $t('Missing Alt Text') }}</span>
          <span class="text-xl font-bold text-red-700">{{ audit.missing }}</span>
        </router-link>
        <router-link
          to="/scanner?filter=generic"
          class="flex items-center justify-between bg-white border border-yellow-200 rounded-lg px-4 py-3 hover:shadow-md transition cursor-pointer"
        >
          <span class="text-sm text-yellow-600">{{ $t('Generic / Placeholder') }}</span>
          <span class="text-xl font-bold text-yellow-700">{{ audit.generic }}</span>
        </router-link>
        <router-link
          to="/scanner?filter=too_long"
          class="flex items-center justify-between bg-white border border-orange-200 rounded-lg px-4 py-3 hover:shadow-md transition cursor-pointer"
        >
          <span class="text-sm text-orange-600">{{ $t('Too Long') }} (&gt;{{ audit.max_length }})</span>
          <span class="text-xl font-bold text-orange-700">{{ audit.too_long }}</span>
        </router-link>
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

const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'

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

const scoreColor = computed(() => {
  const s = audit.value?.score ?? 0
  if (s >= 80) return '#16a34a'
  if (s >= 60) return '#ca8a04'
  if (s >= 40) return '#ea580c'
  return '#dc2626'
})

const scoreLabel = computed(() => {
  const s = audit.value?.score ?? 0
  if (s >= 80) return $t('Excellent')
  if (s >= 60) return $t('Good')
  if (s >= 40) return $t('Needs Work')
  return $t('Poor')
})

const scoreLabelColor = computed(() => {
  const s = audit.value?.score ?? 0
  if (s >= 80) return 'text-green-600'
  if (s >= 60) return 'text-yellow-600'
  if (s >= 40) return 'text-orange-600'
  return 'text-red-600'
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
    const settingsData = await getSettings()
    // Setup is complete when the wizard is done AND a provider is available —
    // a BYOK key (api_setting.has_key) OR a site-wide source (config key / the
    // WordPress Native AI Connector), surfaced as site_has_key. Mirrors the
    // router guard in router.js so native-connector users aren't bounced.
    if (!settingsData.wizard_completed || !(settingsData.api_setting?.has_key || settingsData.site_has_key)) {
      router.push('/wizard')
      return
    }

    // Read WooCommerce status from settings (no extra API call)
    woocommerceInstalled.value = settingsData.woo_active || false

    // Parallelize dashboard stats, audit, and compression stats
    const [statsData] = await Promise.all([
      Api.get('/stats/dashboard'),
      runAudit(),
      loadCompression(),
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
.get-pro-btn:active {
    color: #ffffff;
}
</style>
