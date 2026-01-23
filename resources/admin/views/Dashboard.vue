<template>
  <div class="dashboard p-6">
    <!-- Dashboard Content -->
    <div>
      <!-- Overview Stats -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">Total Images</div>
          <div class="text-3xl font-bold">{{ stats.total_images || 0 }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">Missing Alt Text</div>
          <div class="text-3xl font-bold text-orange-600">{{ stats.missing_alt || 0 }}</div>
          <div class="text-sm text-gray-500">{{ stats.missing_percentage || 0 }}%</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">This Month</div>
          <div class="text-3xl font-bold text-blue-600">{{ stats.this_month_processed || 0 }}</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border">
          <div class="text-sm text-gray-600 mb-1">Success Rate</div>
          <div class="text-3xl font-bold text-green-600">{{ stats.success_rate || 0 }}%</div>
        </div>
      </div>
      
      <!-- API Status -->
      <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <h3 class="text-lg font-semibold mb-4">API Status</h3>
        <div v-if="stats.api_status?.connected" class="flex items-center gap-2">
          <span class="text-green-600">✓</span>
          <span>Connected to {{ stats.api_status.provider }}</span>
        </div>
        <div v-else class="flex items-center gap-2">
          <span class="text-red-600">✗</span>
          <span>Not connected</span>
          <router-link to="/settings" class="text-blue-600 underline ml-2">Configure API</router-link>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
          <button 
            @click="$router.push('/scanner')"
            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition"
          >
            Scan Media Library
          </button>
          <button 
            @click="$router.push('/settings')"
            class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 transition"
          >
            Settings
          </button>
        </div>
      </div>
      
      <!-- Recent Activity -->
      <div class="bg-white p-6 rounded-lg shadow border">
        <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
        <div v-if="loading" class="text-center py-8 text-gray-500">
          Loading...
        </div>
        <div v-else-if="recentActivity.length === 0" class="text-center py-8 text-gray-500">
          No recent activity
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
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Api from '../services/Api'

const router = useRouter()
const stats = ref({})
const recentActivity = ref([])
const loading = ref(true)

const loadDashboard = async () => {
  try {
    // Check if wizard is completed, redirect if not
    const settings = await Api.get('/settings')
    if (!settings.wizard_completed || !settings.api_setting?.has_key) {
      router.push('/wizard')
      return
    }
    
    // Load stats
    const statsData = await Api.get('/stats/dashboard')
    if (statsData.success) {
      stats.value = statsData.stats
    }
    
    // Load recent activity (we'll need to add this endpoint or use history)
    // For now, we'll skip this
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