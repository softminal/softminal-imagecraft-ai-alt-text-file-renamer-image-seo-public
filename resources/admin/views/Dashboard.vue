<template>
  <div class="dashboard p-6">
    <!-- Dashboard Content -->
    <div>
      <!-- Overview Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">{{ $t('Total Images') }}</div>
          <div class="text-3xl font-bold">{{ stats.total_images || 0 }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">{{ $t('Missing Alt Text') }}</div>
          <div class="text-3xl font-bold text-orange-600">{{ stats.missing_alt || 0 }}</div>
          <div class="text-sm text-gray-500">{{ stats.missing_percentage || 0 }}%</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">{{ $t('This Month') }}</div>
          <div class="text-3xl font-bold text-blue-600">{{ stats.this_month_processed || 0 }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">{{ $t('Success Rate') }}</div>
          <div class="text-3xl font-bold text-green-600">{{ stats.success_rate || 0 }}%</div>
        </div>
      </div>

      <!-- SEO Audit Report -->
      <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">{{ $t('Image SEO Audit') }}</h3>
          <button
            @click="runAudit"
            :disabled="auditLoading"
            class="text-sm bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-200 transition disabled:opacity-50"
          >
            {{ auditLoading ? $t('Scanning...') : $t('Refresh') }}
          </button>
        </div>

        <div v-if="auditLoading && !audit" class="text-center py-8 text-gray-500">
          {{ $t('Scanning media library...') }}
        </div>

        <div v-else-if="audit">
          <!-- Score -->
          <div class="flex items-center gap-6 mb-6">
            <div class="relative w-24 h-24">
              <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="42" stroke="#e5e7eb" stroke-width="8" fill="none" />
                <circle
                  cx="50" cy="50" r="42"
                  :stroke="scoreColor"
                  stroke-width="8"
                  fill="none"
                  stroke-linecap="round"
                  :stroke-dasharray="263.89"
                  :stroke-dashoffset="263.89 - (263.89 * audit.score / 100)"
                />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-2xl font-bold">{{ audit.score }}%</span>
              </div>
            </div>
            <div>
              <div class="text-lg font-semibold" :class="scoreLabelColor">{{ scoreLabel }}</div>
              <div class="text-sm text-gray-500">{{ $t('%s of %s images are fully optimized', audit.good, audit.total_images) }}</div>
            </div>
          </div>

          <!-- Breakdown -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-green-700">{{ audit.good }}</div>
              <div class="text-xs text-green-600">{{ $t('Good') }}</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-red-700">{{ audit.missing }}</div>
              <div class="text-xs text-red-600">{{ $t('Missing Alt Text') }}</div>
            </div>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-yellow-700">{{ audit.generic }}</div>
              <div class="text-xs text-yellow-600">{{ $t('Generic / Placeholder') }}</div>
            </div>
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-orange-700">{{ audit.too_long }}</div>
              <div class="text-xs text-orange-600">{{ $t('Too Long') }} (&gt;{{ audit.max_length }})</div>
            </div>
          </div>

          <!-- Generic Examples -->
          <div v-if="audit.generic_examples.length" class="mt-4">
            <div class="text-sm font-medium text-gray-700 mb-2">{{ $t('Generic alt text found:') }}</div>
            <div class="space-y-2">
              <div
                v-for="item in audit.generic_examples"
                :key="item.id"
                class="flex items-center gap-3 p-2 bg-yellow-50 border border-yellow-100 rounded-lg text-sm"
              >
                <img
                  v-if="item.thumbnail"
                  :src="item.thumbnail"
                  class="w-10 h-10 object-cover rounded"
                  :alt="item.filename"
                />
                <div class="flex-1 min-w-0">
                  <div class="text-gray-600 truncate">{{ item.filename }}</div>
                  <div class="text-yellow-700 font-mono truncate">"{{ item.alt_text }}"</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Too Long Examples -->
          <div v-if="audit.too_long_examples.length" class="mt-4">
            <div class="text-sm font-medium text-gray-700 mb-2">{{ $t('Alt text exceeding %s characters:', audit.max_length) }}</div>
            <div class="space-y-2">
              <div
                v-for="item in audit.too_long_examples"
                :key="item.id"
                class="flex items-center gap-3 p-2 bg-orange-50 border border-orange-100 rounded-lg text-sm"
              >
                <img
                  v-if="item.thumbnail"
                  :src="item.thumbnail"
                  class="w-10 h-10 object-cover rounded"
                  :alt="item.filename"
                />
                <div class="flex-1 min-w-0">
                  <div class="text-gray-600 truncate">{{ item.filename }} <span class="text-orange-600">({{ $t('%s chars', item.length) }})</span></div>
                  <div class="text-orange-700 font-mono truncate">"{{ item.alt_text }}"</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- API Status -->
      <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <h3 class="text-lg font-semibold mb-4">{{ $t('API Status') }}</h3>
        <div v-if="stats.api_status?.connected" class="flex items-center gap-2">
          <span class="text-green-600">✓</span>
          <span>{{ $t('Connected to %s', stats.api_status.provider) }}</span>
        </div>
        <div v-else class="flex items-center gap-2">
          <span class="text-red-600">✗</span>
          <span>{{ $t('Not connected') }}</span>
          <router-link to="/settings" class="text-blue-600 underline ml-2">{{ $t('Configure API') }}</router-link>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <h3 class="text-lg font-semibold mb-4">{{ $t('Quick Actions') }}</h3>
        <div class="flex flex-wrap gap-3">
          <button
            @click="$router.push('/scanner')"
            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition"
          >
            {{ $t('Scan Media Library') }}
          </button>
          <button
            v-if="woocommerceInstalled"
            @click="$router.push('/woocommerce')"
            class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition"
          >
            {{ $t('WooCommerce Products') }}
          </button>
          <button
            @click="$router.push('/settings')"
            class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition"
          >
            {{ $t('Settings') }}
          </button>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="bg-white p-6 rounded-lg shadow border">
        <h3 class="text-lg font-semibold mb-4">{{ $t('Recent Activity') }}</h3>
        <div v-if="loading" class="text-center py-8 text-gray-500">
          {{ $t('Loading...') }}
        </div>
        <div v-else-if="recentActivity.length === 0" class="text-center py-8 text-gray-500">
          {{ $t('No recent activity') }}
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="item in recentActivity"
            :key="item.id"
            class="flex items-center gap-4 p-3 border rounded-lg"
          >
            <img
              v-if="item.thumbnail"
              :src="item.thumbnail"
              class="w-16 h-16 object-cover rounded"
              :alt="item.filename"
            />
            <div class="flex-1">
              <div class="font-medium">{{ item.filename }}</div>
              <div class="text-sm text-gray-600">{{ item.generated_alt }}</div>
            </div>
            <div class="text-sm">
              <span :class="{
                'text-green-600': item.status === 'accepted',
                'text-orange-600': item.status === 'pending',
                'text-red-600': item.status === 'rejected'
              }">
                {{ item.status }}
              </span>
            </div>
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
import { $t } from '../utils/i18n'

const router = useRouter()
const stats = ref({})
const recentActivity = ref([])
const loading = ref(true)
const woocommerceInstalled = ref(false)
const audit = ref(null)
const auditLoading = ref(false)

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

const loadDashboard = async () => {
  try {
    const settings = await Api.get('/settings')
    if (!settings.wizard_completed || !settings.api_setting?.has_key) {
      router.push('/wizard')
      return
    }

    const statsData = await Api.get('/stats/dashboard')
    if (statsData.success) {
      stats.value = statsData.stats
    }

    runAudit()

    try {
      const wooStats = await Api.get('/woocommerce/stats')
      woocommerceInstalled.value = wooStats.is_active || false
    } catch (e) {
      woocommerceInstalled.value = false
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>
