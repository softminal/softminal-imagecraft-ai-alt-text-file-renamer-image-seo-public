<template>
  <div>
    <h2 class="text-xl font-bold mb-1 mt-0">License</h2>
    <p class="text-sm text-gray-500 mb-6">Activate your ImageCraft Pro license to unlock unlimited generations.</p>

    <!-- Status card -->
    <div class="bg-white rounded-lg shadow border p-6 mb-4">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800 text-base mt-0">License Status</h3>
        <span :class="statusBadgeClass">{{ statusLabel }}</span>
      </div>

      <div v-if="status.key" class="mb-4">
        <p class="text-sm text-gray-500 mb-1.5">License Key</p>
        <code class="text-sm bg-gray-50 px-3 py-2 rounded border block tracking-widest">{{ status.key }}</code>
      </div>

      <!-- Domain usage -->
      <div v-if="status.valid" class="mt-4 pt-4 border-t">
        <p class="text-sm text-gray-500 mb-1.5">Domain Usage</p>
        <div class="flex items-center gap-3">
          <div class="flex-1 bg-gray-100 rounded-full h-2">
            <div
              class="bg-blue-500 h-2 rounded-full transition-all"
              :style="{ width: siteUsagePercent + '%' }"
            ></div>
          </div>
          <span class="text-sm font-medium text-gray-700 whitespace-nowrap">
            {{ status.unlimited ? 'Unlimited domains' : `${status.site_count ?? 0} / ${status.site_limit} domain${status.site_limit !== 1 ? 's' : ''}` }}
          </span>
        </div>
      </div>

      <div v-if="status.last_check" class="text-xs text-gray-400 mt-3">
        Last verified: {{ lastVerified }}
      </div>
    </div>

    <!-- Activate / Update form -->
    <div class="bg-white rounded-lg shadow border p-6">
      <h3 class="font-semibold text-gray-800 text-base mt-0 mb-4">
        {{ status.key ? 'Update License Key' : 'Activate License' }}
      </h3>

      <div class="flex gap-3">
        <input
          v-model="licenseKey"
          type="text"
          placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
          class="flex-1 border rounded-lg px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
          :disabled="activating"
          @keydown.enter="activate"
        />
        <button
          @click="activate"
          :disabled="activating || !licenseKey.trim()"
          class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
        >
          {{ activating ? 'Verifying...' : (status.key ? 'Update' : 'Activate') }}
        </button>
      </div>

      <div v-if="status.key" class="mt-3">
        <button
          @click="deactivate"
          :disabled="activating"
          class="text-sm text-red-500 hover:text-red-700 disabled:opacity-50"
        >
          Remove license from this domain
        </button>
      </div>

      <p v-if="errorMsg" class="mt-3 text-sm text-red-600">{{ errorMsg }}</p>

      <p class="mt-5 text-xs text-gray-400">
        Find your license key in your
        <a href="https://softminal.com/dashboard/downloads" target="_blank" class="text-blue-600 hover:underline">Softminal dashboard</a>.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { notification } from '../../plugins/notification'

const proApiUrl = window.icaisPro?.proApiUrl ?? ''
const nonce     = window.icais?.nonce ?? ''

const status     = ref({ key: '', valid: false, status: 'inactive', last_check: null })
const licenseKey = ref('')
const activating = ref(false)
const errorMsg   = ref('')

const statusBadgeClass = computed(() => {
  const map = {
    active:             'text-xs font-semibold px-2.5 py-1 rounded-full bg-green-100 text-green-700',
    inactive:           'text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-600',
    invalid:            'text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-600',
    cancelled:          'text-xs font-semibold px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700',
    expired:            'text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-600',
    site_limit_reached: 'text-xs font-semibold px-2.5 py-1 rounded-full bg-orange-100 text-orange-700',
  }
  return map[status.value.status] ?? map.inactive
})

const statusLabel = computed(() => {
  const map = {
    active:             'Active',
    inactive:           'Not activated',
    invalid:            'Invalid key',
    cancelled:          'Cancelled',
    expired:            'Expired',
    site_limit_reached: 'Domain limit reached',
  }
  return map[status.value.status] ?? 'Not activated'
})

const siteUsagePercent = computed(() => {
  if (status.value.unlimited || !status.value.site_limit) return 0
  return Math.min(100, Math.round(((status.value.site_count ?? 0) / status.value.site_limit) * 100))
})

const lastVerified = computed(() => {
  if (!status.value.last_check) return ''
  return new Date(status.value.last_check * 1000).toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
})

const apiFetch = (path, options = {}) =>
  fetch(`${proApiUrl}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...(options.headers ?? {}),
    },
  }).then(r => r.json())

const loadStatus = async () => {
  try {
    const res = await apiFetch('/license/status')
    if (res) status.value = res
  } catch {
    // Silently fail — defaults to inactive
  }
}

const activate = async () => {
  if (!licenseKey.value.trim()) return
  activating.value = true
  errorMsg.value   = ''

  try {
    const res = await apiFetch('/license/activate', {
      method: 'POST',
      body: JSON.stringify({ key: licenseKey.value.trim() }),
    })

    if (res?.valid) {
      notification.success('License activated! Reloading...')
      licenseKey.value = ''
      setTimeout(() => window.location.reload(), 1000)
    } else if (res?.network_error) {
      errorMsg.value = 'Could not reach the license server. Please try again in a moment.'
      await loadStatus()
    } else {
      const messages = {
        invalid:            'License key not found. Please check your key.',
        cancelled:          'This license has been cancelled.',
        expired:            'This license has expired.',
        site_limit_reached: `Domain limit reached (${res?.site_count ?? '?'} of ${res?.site_limit ?? '?'} used). Deactivate on another domain first.`,
      }
      errorMsg.value = messages[res?.status] ?? 'License key is invalid.'
      await loadStatus()
    }
  } catch {
    errorMsg.value = 'Could not reach the license server. Please check your connection.'
  } finally {
    activating.value = false
  }
}

const deactivate = async () => {
  // eslint-disable-next-line no-alert
  if (!confirm('Remove this license key from this domain?')) return
  activating.value = true
  errorMsg.value   = ''

  try {
    await apiFetch('/license/deactivate', { method: 'POST' })
    notification.success('License removed.')
    setTimeout(() => window.location.reload(), 800)
  } catch {
    errorMsg.value = 'Failed to remove license.'
  } finally {
    activating.value = false
  }
}

onMounted(loadStatus)
</script>
