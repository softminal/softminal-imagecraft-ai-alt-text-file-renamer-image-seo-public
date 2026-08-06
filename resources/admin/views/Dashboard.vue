<template>
  <div class="dashboard p-6 bg-ic-page min-h-screen">
    <!-- Greeting -->
    <div class="flex items-center gap-4 mb-6">
      <img
        v-if="userAvatar"
        :src="userAvatar"
        alt=""
        class="w-12 h-12 rounded-full bg-brand-soft object-cover shrink-0"
      />
      <div
        v-else
        class="w-12 h-12 rounded-full bg-brand-soft text-brand flex items-center justify-center text-xl font-bold shrink-0"
      >
        {{ (userName || 'I').charAt(0).toUpperCase() }}
      </div>
      <div>
        <h2 class="text-[22px]! font-bold! text-ic-text m-0! p-0! leading-tight">{{ greeting }} {{ userName }} 👋</h2>
        <p class="text-[15px]! text-ic-soft m-0! mt-1! leading-tight">{{ $t('Here is how your images are doing') }}</p>
      </div>
    </div>

    <!-- No API key configured — the single canonical prompt for AI setup. -->
    <div v-if="!hasAiKey" class="bg-ic-info-soft border border-ic-info/20 rounded-card p-4 mb-6 flex items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <Info :size="18" :stroke-width="2" class="text-ic-info shrink-0 mt-0.5" aria-hidden="true" />
        <p class="text-sm text-ic-text m-0">
          {{ $t('Add your API key to generate alt text, titles and captions. Image compression works without a key.') }}
        </p>
      </div>
      <a href="#/settings/api" class="shrink-0 text-sm font-semibold text-ic-info underline hover:opacity-80">
        {{ $t('Add API key') }}
      </a>
    </div>

    <!-- Shared daily free quota (free only) — informational, no CTA (Get Pro lives in the navbar) -->
    <div v-if="usageLimit && !isPro && usageLimit.daily_limit > 0" class="bg-ic-card rounded-card shadow-card border border-ic-border p-4 mb-6">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm text-ic-text">
          {{ $t('Daily free usage') }}:
          <span class="font-bold" :class="usageLimit.daily_remaining === 0 ? 'text-ic-danger' : 'text-ic-text'">
            {{ usageLimit.daily_used }} / {{ usageLimit.daily_limit }}
          </span>
        </span>
        <span v-if="usageLimit.reset_in" class="text-xs text-ic-faint">{{ $t('Resets in %s', usageLimit.reset_in) }}</span>
      </div>
      <div class="w-full bg-ic-muted rounded-pill h-2">
        <div class="h-2 rounded-pill transition-all" :class="usageBarColor" :style="{ width: usageBarWidth + '%' }"></div>
      </div>
      <div class="mt-2 text-xs text-ic-soft">
        {{ $t('Shared across AI generation and compression. Generating and compressing the same image counts as two.') }}
      </div>
    </div>

    <!-- Main layout: content (3/4) + sidebar (1/4) -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- ==================== LEFT: main content ==================== -->
      <div class="lg:col-span-3 flex flex-col gap-6">
        <!-- Loading skeleton mirrors the two-pillar hero so nothing shifts -->
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="n in 2" :key="n" class="bg-ic-card rounded-card shadow-card border border-ic-border p-6">
            <UiSkeleton :lines="4" height="1.25rem" />
          </div>
        </div>

        <!-- ============ HERO: the two pillars ============ -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Image SEO pillar -->
          <div class="bg-ic-card rounded-card shadow-card border border-ic-border flex flex-col overflow-hidden border-t-2 border-t-pillar-seo">
            <div class="flex items-center justify-between px-6 pt-4">
              <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-ic bg-pillar-seo-soft text-pillar-seo">
                  <ScanSearch :size="18" :stroke-width="2" aria-hidden="true" />
                </span>
                <div>
                  <h3 class="text-base font-semibold text-ic-text m-0">{{ $t('Image SEO') }}</h3>
                  <p class="text-xs text-ic-soft m-0">{{ $t('Make your images rank') }}</p>
                </div>
              </div>
              <UiBadge v-if="stats.api_status?.connected" tone="success">{{ $t('Connected') }}</UiBadge>
              <router-link v-else-if="isAdmin" to="/settings/api" class="text-xs text-ic-danger underline">
                {{ $t('Configure API') }}
              </router-link>
            </div>

            <div class="px-6 py-5 flex-1">
              <div class="flex items-center justify-around gap-3">
                <div v-for="ring in seoRings" :key="ring.label" class="text-center">
                  <div class="relative w-18 h-18 mx-auto">
                    <svg class="w-18 h-18 -rotate-90" viewBox="0 0 100 100">
                      <circle cx="50" cy="50" r="42" stroke="var(--ic-border)" stroke-width="8" fill="none" />
                      <circle
                        cx="50" cy="50" r="42"
                        :stroke="ring.color"
                        stroke-width="8"
                        fill="none"
                        stroke-linecap="round"
                        :stroke-dasharray="263.89"
                        :stroke-dashoffset="263.89 - (263.89 * ring.score / 100)"
                      />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-base font-bold text-ic-text">{{ ring.score }}%</span>
                    </div>
                  </div>
                  <div class="mt-1.5 text-[0.8125rem] font-medium text-ic-text">{{ ring.label }}</div>
                </div>
              </div>
            </div>

            <div class="px-6 py-4 bg-ic-muted border-t border-ic-border-soft">
              <UiButton @click="$router.push('/scanner')">
                {{ $t('Open Image SEO') }} →
              </UiButton>
            </div>
          </div>

          <!-- Performance pillar -->
          <div class="bg-ic-card rounded-card shadow-card border border-ic-border flex flex-col overflow-hidden border-t-2 border-t-pillar-perf">
            <div class="flex items-center justify-between px-6 pt-4">
              <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-ic bg-pillar-perf-soft text-pillar-perf">
                  <Zap :size="18" :stroke-width="2" aria-hidden="true" />
                </span>
                <div>
                  <h3 class="text-base font-semibold text-ic-text m-0">{{ $t('Performance') }}</h3>
                  <p class="text-xs text-ic-soft m-0">{{ $t('Make your site fast') }}</p>
                </div>
              </div>
              <UiBadge tone="perf">{{ $t('Local engine') }}</UiBadge>
            </div>

            <div class="px-6 py-5 flex-1">
              <div class="text-3xl font-bold text-pillar-perf leading-tight">{{ compressionStats.total_saved_human || '0 B' }}</div>
              <div class="text-xs text-ic-soft">{{ $t('space saved') }}</div>
              <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                  <div class="text-xl font-bold text-ic-text">{{ compressionStats.total_compressed || 0 }}</div>
                  <div class="text-xs text-ic-soft">{{ $t('compressed') }}</div>
                </div>
                <div>
                  <div class="text-xl font-bold" :class="uncompressedCount > 0 ? 'text-ic-warning' : 'text-ic-text'">{{ uncompressedCount }}</div>
                  <div class="text-xs text-ic-soft">{{ $t('not compressed') }}</div>
                </div>
              </div>

              <!-- Capability status: makes the pillar's features discoverable
                   without promo cards — one quiet row per feature (admins). -->
              <div v-if="isAdmin" class="mt-4 pt-3 border-t border-ic-border-soft flex flex-col gap-1.5">
                <router-link to="/performance/nextgen" class="feature-status flex items-center justify-between text-[0.8125rem]">
                  <span class="text-ic-soft">{{ $t('Next-gen formats (WebP/AVIF)') }}</span>
                  <span v-if="nextgenOn" class="font-medium text-ic-success">{{ $t('On') }}</span>
                  <span v-else class="font-medium text-brand">{{ $t('Set up') }} →</span>
                </router-link>
                <router-link to="/performance/fallbacks" class="feature-status flex items-center justify-between text-[0.8125rem]">
                  <span class="text-ic-soft">{{ $t('Image fallbacks') }}</span>
                  <span v-if="fallbackOn" class="font-medium text-ic-success">{{ $t('On') }}</span>
                  <span v-else class="font-medium text-brand">{{ $t('Set up') }} →</span>
                </router-link>
              </div>
            </div>

            <div class="px-6 py-4 bg-ic-muted border-t border-ic-border-soft">
              <UiButton @click="$router.push('/performance')">
                {{ $t('Open Performance') }} →
              </UiButton>
            </div>
          </div>
        </div>

        <!-- ============ Secondary row: overall score + library size ============ -->
        <div v-if="audit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-ic-card rounded-card shadow-card border border-ic-border p-5 flex items-center gap-5">
            <div class="relative w-20 h-20 shrink-0">
              <svg class="w-20 h-20 -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="42" stroke="var(--ic-border)" stroke-width="9" fill="none" />
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
                <span class="text-xl font-bold text-ic-text">{{ overallScore }}%</span>
              </div>
            </div>
            <div class="min-w-0">
              <div class="text-xs font-semibold uppercase tracking-wide text-ic-faint">{{ $t('Overall optimization') }}</div>
              <div class="text-lg font-semibold text-ic-text">{{ overallLabel }}</div>
              <div class="text-sm text-ic-soft">{{ $t('Blended across alt text, titles, descriptions and compression.') }}</div>
            </div>
          </div>
          <div class="bg-ic-card rounded-card shadow-card border border-ic-border p-5 flex items-center gap-4">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-ic bg-brand-soft text-brand shrink-0">
              <Image :size="20" :stroke-width="2" aria-hidden="true" />
            </span>
            <div>
              <div class="text-2xl font-bold text-ic-text leading-tight">{{ stats.total_images ?? 0 }}</div>
              <div class="text-sm text-ic-soft">{{ $t('images in your media library') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ==================== RIGHT: sidebar ==================== -->
      <div class="lg:col-span-1">
        <div class="flex flex-col gap-6 lg:sticky lg:top-6">
          <!-- Quick actions (from config/navigation.js — labels can't drift) -->
          <div class="bg-ic-card rounded-card shadow-card border border-ic-border p-5">
            <h3 class="text-base font-semibold text-ic-text mb-2 mt-0">{{ $t('Quick Actions') }}</h3>
            <ul class="m-0 p-0 list-none flex flex-col">
              <li v-for="action in actions" :key="action.path" class="m-0">
                <router-link
                  :to="action.path"
                  class="quick-action flex items-start gap-3 py-2 rounded-ic"
                >
                  <span
                    class="inline-flex items-center justify-center w-8 h-8 rounded-ic shrink-0"
                    :class="action.pillar === 'perf' ? 'bg-pillar-perf-soft text-pillar-perf' : 'bg-pillar-seo-soft text-pillar-seo'"
                  >
                    <component :is="action.icon" :size="15" :stroke-width="2" aria-hidden="true" />
                  </span>
                  <span class="min-w-0">
                    <span class="block text-sm font-medium text-ic-text">{{ $t(action.name) }}</span>
                    <span class="block text-xs text-ic-soft">{{ $t(action.description) }}</span>
                  </span>
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Needs attention — only renders when something needs fixing -->
          <div v-if="audit && needsAttention.length" class="bg-ic-card rounded-card shadow-card border border-ic-border p-5">
            <h3 class="text-base font-semibold text-ic-text mb-2 mt-0">{{ $t('Needs Attention') }}</h3>
            <ul class="m-0 p-0 list-none flex flex-col">
              <li v-for="item in needsAttention" :key="item.key" class="m-0">
                <router-link
                  :to="item.to"
                  class="attention-row flex items-center justify-between gap-2 py-2"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <span :class="['inline-flex items-center justify-center min-w-7 h-6 px-1.5 rounded-md text-xs font-bold', badgeClasses[item.tone]]">
                      {{ item.count }}
                    </span>
                    <span class="attention-label text-sm text-ic-text truncate">{{ item.label }}</span>
                  </span>
                  <span class="text-xs font-medium text-brand shrink-0">{{ $t('Fix') }} →</span>
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
import { Info, ScanSearch, Zap, Image } from 'lucide-vue-next'
import Api from '../services/Api'
import { getSettings } from '../router'
import { quickActions, visibleItems } from '../config/navigation'
import { UiBadge, UiButton, UiSkeleton } from '../@ui'
import { $t } from '../utils/i18n'

const router = useRouter()
const stats = ref({})
const usageLimit = ref(null)
const woocommerceInstalled = ref(false)
const audit = ref(null)
const loading = ref(true)
const compressionStats = ref({})
const brokenCount = ref(0)

const isAdmin = window.icais?.isAdmin ?? false
const isPro = window.icais?.isPro ?? false

// Performance pillar feature-status rows (quiet discovery — no promo cards).
const nextgenOn = ref(false)
const fallbackOn = ref(false)
// Show the "add API key" prompt only when we know there is no usable AI provider
// (undefined → assume configured, so existing setups never see a spurious nag).
const hasAiKey = window.icais?.hasAiKey ?? true

// Quick actions derive from the shared navigation model.
const actions = computed(() =>
  visibleItems(quickActions, { isAdmin, wooActive: woocommerceInstalled.value })
)

// Greeting: time-of-day + current user's name/avatar (from PHP).
const userName = window.icais?.userName || ''
const userAvatar = window.icais?.userAvatar || ''
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return $t('Good morning')
  if (h < 17) return $t('Good afternoon')
  return $t('Good evening')
})

// Needs-attention count badges.
const badgeClasses = {
  red: 'bg-ic-danger-soft text-ic-danger',
  indigo: 'bg-brand-soft text-brand',
  green: 'bg-ic-success-soft text-ic-success',
  orange: 'bg-ic-warning-soft text-ic-warning',
  yellow: 'bg-ic-warning-soft text-ic-warning',
}

const needsAttention = computed(() => {
  const a = audit.value
  if (!a) return []
  return [
    { key: 'broken', count: brokenCount.value, label: $t('Broken images'), to: '/performance/fallbacks', tone: 'red' },
    { key: 'missing', count: a.missing || 0, label: $t('Missing alt text'), to: '/scanner?filter=missing', tone: 'red' },
    { key: 'title', count: a.title_missing || 0, label: $t('Missing title'), to: '/scanner?filter=missing_title', tone: 'indigo' },
    { key: 'uncompressed', count: uncompressedCount.value, label: $t('Not compressed'), to: '/performance', tone: 'green' },
    { key: 'too_long', count: a.too_long || 0, label: $t('Alt text too long'), to: '/scanner?filter=too_long', tone: 'orange' },
    { key: 'generic', count: a.generic || 0, label: $t('Generic alt text'), to: '/scanner?filter=generic', tone: 'yellow' },
  ].filter((i) => i.count > 0)
})

// Best-effort "not yet optimized" count.
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
  if (pct >= 100) return 'bg-ic-danger'
  if (pct >= 80) return 'bg-ic-warning'
  return 'bg-brand'
})

// Ring stroke color by coverage score.
const ringColor = (s) => {
  if (s >= 80) return 'var(--ic-success)'
  if (s >= 60) return 'var(--ic-warning)'
  if (s >= 40) return '#ea580c'
  return 'var(--ic-danger)'
}

const seoRings = computed(() => [
  { label: $t('Alt text'), score: audit.value?.score ?? 0, color: ringColor(audit.value?.score ?? 0) },
  { label: $t('Title'), score: audit.value?.title_score ?? 0, color: ringColor(audit.value?.title_score ?? 0) },
  { label: $t('Description'), score: audit.value?.caption_score ?? 0, color: ringColor(audit.value?.caption_score ?? 0) },
])

// Blended "overall optimization" score across SEO coverage + compression.
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
  try {
    const result = await Api.get('/stats/audit')
    if (result.success) {
      audit.value = result.audit
    }
  } catch (error) {
    console.error('Failed to load audit:', error)
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
      if (!settingsData.wizard_completed) {
        router.push('/wizard')
        return
      }
      woocommerceInstalled.value = settingsData.woo_active || false

      // Feature-status rows in the Performance pillar card.
      const s = settingsData.settings || {}
      nextgenOn.value = s.icais_compression_convert_webp === '1' || s.icais_compression_convert_avif === '1'
      fallbackOn.value = s.icais_fallback_enabled === '1'
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
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboard();
})
</script>

<style scoped>
.quick-action {
    text-decoration: none;
}
.quick-action:hover .block.font-medium {
    color: var(--ic-brand-strong);
}
.quick-action:focus,
.quick-action:active {
    outline: none;
    box-shadow: none;
}

.feature-status {
    text-decoration: none;
}
.feature-status:hover .text-ic-soft {
    color: var(--ic-brand-strong);
}
.feature-status:focus,
.feature-status:active {
    outline: none;
    box-shadow: none;
}

.attention-row {
    text-decoration: none;
}
.attention-row:hover .attention-label {
    color: var(--ic-brand-strong);
    text-decoration: underline;
    text-underline-offset: 3px;
}
.attention-row:focus,
.attention-row:active {
    outline: none;
    box-shadow: none;
}
</style>
