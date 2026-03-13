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

        <div v-else-if="audit && audit.total_images === 0" class="text-center py-8 text-gray-500">
          <div class="text-4xl mb-3">🖼️</div>
          <div class="text-lg font-medium text-gray-600">{{ $t('No images found') }}</div>
          <div class="text-sm mt-1">{{ $t('Upload images to your media library to see the SEO audit.') }}</div>
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
              <div class="text-sm text-gray-500">{{ $t('%1$s of %2$s images are fully optimized', audit.good, audit.total_images) }}</div>
            </div>
          </div>

          <!-- Breakdown -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
              <div class="text-2xl font-bold text-green-700">{{ audit.good }}</div>
              <div class="text-xs text-green-600">{{ $t('Good') }}</div>
            </div>
            <router-link
              to="/scanner?filter=missing"
              class="bg-red-50 border border-red-200 rounded-lg p-3 text-center hover:shadow-md transition cursor-pointer block"
            >
              <div class="text-2xl font-bold text-red-700">{{ audit.missing }}</div>
              <div class="text-xs text-red-600">{{ $t('Missing Alt Text') }}</div>
            </router-link>
            <router-link
              to="/scanner?filter=generic"
              class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center hover:shadow-md transition cursor-pointer block"
            >
              <div class="text-2xl font-bold text-yellow-700">{{ audit.generic }}</div>
              <div class="text-xs text-yellow-600">{{ $t('Generic / Placeholder') }}</div>
            </router-link>
            <router-link
              to="/scanner?filter=too_long"
              class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-center hover:shadow-md transition cursor-pointer block"
            >
              <div class="text-2xl font-bold text-orange-700">{{ audit.too_long }}</div>
              <div class="text-xs text-orange-600">{{ $t('Too Long') }} (&gt;{{ audit.max_length }})</div>
            </router-link>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Usage & Cost Chart -->
        <div class="bg-white p-6 rounded-lg shadow border">
          <h3 class="text-lg font-semibold mb-4">{{ $t('Usage & Cost (Last 30 Days)') }}</h3>
          <div v-if="loading" class="h-64 flex items-center justify-center text-gray-500">
            {{ $t('Loading...') }}
          </div>
          <div v-else-if="!hasUsageData" class="h-64 flex items-center justify-center text-gray-400">
            {{ $t('No usage data yet. Generate some alt texts to see trends here.') }}
          </div>
          <div v-else ref="usageChartRef" class="h-64"></div>
        </div>

        <!-- Generation Results Chart -->
        <div class="bg-white p-6 rounded-lg shadow border">
          <h3 class="text-lg font-semibold mb-4">{{ $t('Generation Results') }}</h3>
          <div v-if="loading" class="h-64 flex items-center justify-center text-gray-500">
            {{ $t('Loading...') }}
          </div>
          <div v-else-if="!hasResultsData" class="h-64 flex items-center justify-center text-gray-400">
            {{ $t('No generation data yet. Results breakdown will appear here.') }}
          </div>
          <div v-else ref="resultsChartRef" class="h-64"></div>
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
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'
import { getSettings } from '../router'
import { $t } from '../utils/i18n'
import { useECharts } from '../composables/useECharts'

const router = useRouter()
const stats = ref({})
const recentActivity = ref([])
const loading = ref(true)
const woocommerceInstalled = ref(false)
const audit = ref(null)
const auditLoading = ref(false)

const chartUsage = ref([])
const chartResults = ref([])

const hasUsageData = computed(() => chartUsage.value.some(d => d.processed > 0 || d.cost > 0))
const hasResultsData = computed(() => chartResults.value.some(d => d.value > 0))

const { chartRef: usageChartRef, updateOptions: updateUsageOptions } = useECharts()
const { chartRef: resultsChartRef, updateOptions: updateResultsOptions } = useECharts()

const updateUsageChart = () => {
  const dates = chartUsage.value.map(d => {
    const dt = new Date(d.date)
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  })
  const processed = chartUsage.value.map(d => d.processed)
  const costs = chartUsage.value.map(d => d.cost)

  updateUsageOptions({
    tooltip: {
      trigger: 'axis',
      axisPointer: { type: 'cross' },
    },
    legend: {
      data: [$t('Images Processed'), $t('Cost ($)')],
      bottom: 0,
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '15%',
      top: '8%',
      containLabel: true,
    },
    xAxis: {
      type: 'category',
      data: dates,
      axisLabel: {
        rotate: 45,
        fontSize: 10,
      },
    },
    yAxis: [
      {
        type: 'value',
        name: $t('Images'),
        position: 'left',
        minInterval: 1,
      },
      {
        type: 'value',
        name: $t('Cost ($)'),
        position: 'right',
        axisLabel: { formatter: '${value}' },
      },
    ],
    series: [
      {
        name: $t('Images Processed'),
        type: 'bar',
        data: processed,
        itemStyle: { color: '#3b82f6' },
      },
      {
        name: $t('Cost ($)'),
        type: 'line',
        yAxisIndex: 1,
        data: costs,
        smooth: true,
        itemStyle: { color: '#f59e0b' },
      },
    ],
  })
}

const updateResultsChart = () => {
  updateResultsOptions({
    tooltip: {
      trigger: 'item',
      formatter: '{b}: {c} ({d}%)',
    },
    legend: {
      bottom: 0,
    },
    series: [
      {
        type: 'pie',
        radius: ['45%', '70%'],
        center: ['50%', '45%'],
        avoidLabelOverlap: true,
        itemStyle: {
          borderRadius: 6,
          borderColor: '#fff',
          borderWidth: 2,
        },
        label: {
          show: true,
          formatter: '{b}: {c}',
        },
        data: chartResults.value.map(item => ({
          ...item,
          itemStyle: {
            color: item.name === 'Accepted' ? '#16a34a'
              : item.name === 'Rejected' ? '#dc2626'
              : '#f59e0b',
          },
        })),
      },
    ],
  })
}

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
    const settingsData = await getSettings()
    if (!settingsData.wizard_completed || !settingsData.api_setting?.has_key) {
      router.push('/wizard')
      return
    }

    // Read WooCommerce status from settings (no extra API call)
    woocommerceInstalled.value = settingsData.woo_active || false

    // Parallelize dashboard stats and audit
    const [statsData] = await Promise.all([
      Api.get('/stats/dashboard'),
      runAudit()
    ])

    if (statsData.success) {
      stats.value = statsData.stats
      recentActivity.value = statsData.recent_activity || []
      chartUsage.value = statsData.chart_usage || []
      chartResults.value = statsData.chart_results || []
    }
  } catch (error) {
    console.error('Failed to load dashboard:', error)
  } finally {
    loading.value = false
    await nextTick()
    if (hasUsageData.value) {
      updateUsageChart()
    }
    if (hasResultsData.value) {
      updateResultsChart()
    }
  }
}

onMounted(() => {
  loadDashboard();
})
</script>
