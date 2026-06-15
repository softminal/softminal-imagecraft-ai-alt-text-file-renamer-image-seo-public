<template>
  <div>
    <!-- Upsell for non-Pro users -->
    <div v-if="!isProInstalled" class="bg-white rounded-lg shadow border p-6">
      <h2 class="text-lg font-semibold">License</h2>
      <p class="text-sm text-gray-500 mb-4">Activate your ImageCraft Pro license to unlock unlimited generations.</p>
      <hr class="mb-6" />

      <div class="flex items-center gap-3 mb-3">
        <div class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
          <svg class="h-5 w-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.07 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.286-3.957z" />
          </svg>
        </div>
        <h3 class="text-base font-semibold text-gray-800 mt-0 mb-0">Unlock ImageCraft Pro</h3>
      </div>

      <div class="pl-[52px]">
        <p class="text-sm text-gray-500 mb-4">
          Upgrade to Pro for unlimited AI generations, priority support, and advanced features.
          Once you purchase, install the Pro add-on to activate your license here.
        </p>

        <a
          :href="proUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="get-pro-btn inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold bg-amber-500 rounded-lg shadow-sm hover:bg-amber-600 transition-colors no-underline"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.784.57-1.838-.196-1.539-1.118l1.286-3.957a1 1 0 00-.363-1.118L2.07 9.384c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.286-3.957z" />
          </svg>
          Get Pro
        </a>
      </div>
    </div>

    <!-- Pro: license management -->
    <template v-else>
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
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { notification } from '../../plugins/notification'

const isProInstalled = window.icais?.isProInstalled ?? false
const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'

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

onMounted(() => {
  if (isProInstalled) loadStatus()
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
