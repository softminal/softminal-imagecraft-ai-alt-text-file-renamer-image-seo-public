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
    <div v-else class="bg-white rounded-lg shadow border p-6">
      <h2 class="text-lg font-semibold">License</h2>
      <p class="text-sm text-gray-500 mb-4">Activate your ImageCraft Pro license to unlock unlimited generations.</p>
      <hr class="mb-6" />

      <!-- Active license: detail card -->
      <div v-if="status.valid">
        <div>
          <div class="flex items-center justify-between py-4 border-b border-dashed border-gray-200">
            <span class="text-sm text-gray-500">Expires</span>
            <span class="inline-flex items-center rounded-full bg-green-100 text-green-700 text-xs font-semibold px-3 py-1">{{ expiresLabel }}</span>
          </div>

          <div class="flex items-center justify-between py-4 border-b border-dashed border-gray-200">
            <span class="text-sm text-gray-500">License Key</span>
            <span class="font-mono text-sm tracking-widest text-gray-800">{{ keyDisplay }}</span>
          </div>

          <div v-if="status.licensee_name || status.licensee_email" class="flex items-start justify-between py-4 border-b border-dashed border-gray-200">
            <span class="text-sm text-gray-500">Licensed To</span>
            <span class="text-right">
              <span v-if="status.licensee_name" class="block text-sm font-semibold text-gray-800">{{ status.licensee_name }}</span>
              <span v-if="status.licensee_email" class="block text-xs font-mono text-gray-400">{{ status.licensee_email }}</span>
            </span>
          </div>

          <div v-if="!status.unlimited && status.site_limit" class="flex items-center justify-between py-4">
            <span class="text-sm text-gray-500">Domains</span>
            <span class="text-sm text-gray-700">{{ status.site_count ?? 0 }} / {{ status.site_limit }}</span>
          </div>
          <div v-else-if="status.unlimited" class="flex items-center justify-between py-4">
            <span class="text-sm text-gray-500">Domains</span>
            <span class="text-sm text-gray-700">Unlimited</span>
          </div>
        </div>

        <div class="flex items-center justify-between flex-wrap gap-3 mt-4 pt-4 border-t">
          <div class="flex items-center gap-3 text-sm flex-wrap">
            <span class="text-gray-400">Last checked: {{ lastCheckedLabel }}</span>
            <button type="button" @click="refresh" :disabled="activating" class="text-blue-600 hover:underline disabled:opacity-50">Refresh</button>
            <span class="text-gray-300">|</span>
            <a :href="accountUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Your Account</a>
            <span class="text-gray-300">|</span>
            <a :href="supportUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Get Support</a>
          </div>
          <button
            @click="deactivate"
            :disabled="activating"
            class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 disabled:opacity-50"
          >
            Deactivate
          </button>
        </div>

        <p v-if="errorMsg" class="mt-3 text-sm text-red-600">{{ errorMsg }}</p>
      </div>

      <!-- No / invalid license: activation form -->
      <div v-else>
        <div v-if="status.status && status.status !== 'inactive'" class="mb-4">
          <span :class="statusBadgeClass">{{ statusLabel }}</span>
        </div>

        <h3 class="font-semibold text-gray-800 text-base mt-0 mb-4">Activate License</h3>

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
            {{ activating ? 'Verifying...' : 'Activate' }}
          </button>
        </div>

        <p v-if="errorMsg" class="mt-3 text-sm text-red-600">{{ errorMsg }}</p>

        <p class="mt-5 text-xs text-gray-400">
          Find your license key in your
          <a href="https://softminal.com/dashboard/downloads" target="_blank" class="text-blue-600 hover:underline">Softminal dashboard</a>.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { notification } from '../../plugins/notification'

const isProInstalled = window.icais?.isProInstalled ?? false
const proUrl = 'https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo#pricing'
const accountUrl = 'https://softminal.com/dashboard'
const supportUrl = 'https://softminal.com/support'

const proApiUrl = window.icaisPro?.proApiUrl ?? ''
const nonce     = window.icais?.nonce ?? ''

const status     = ref({ key: '', valid: false, status: 'inactive', last_check: null, licensee_name: '', licensee_email: '', expires: null })
const licenseKey = ref('')
const activating = ref(false)
const errorMsg   = ref('')

// Ticks so the "last checked" label stays fresh without a reload.
const now   = ref(Math.floor(Date.now() / 1000))
let   clock = null

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

// Masked key with the server's asterisks rendered as dots, e.g. 8f60••••••8cf2.
const keyDisplay = computed(() => (status.value.key || '—').replace(/\*/g, '•'))

// No expiry in the license model → perpetual. null/empty renders as "Lifetime".
const expiresLabel = computed(() => {
  if (!status.value.expires) return 'Lifetime'
  const d = new Date(status.value.expires)
  return isNaN(d.getTime()) ? String(status.value.expires) : d.toLocaleDateString()
})

const lastCheckedLabel = computed(() => {
  if (!status.value.last_check) return 'just now'
  const secs = Math.max(0, now.value - status.value.last_check)
  if (secs < 5) return 'just now'
  if (secs < 60) return `${secs} second${secs === 1 ? '' : 's'} ago`
  const mins = Math.floor(secs / 60)
  if (mins < 60) return `${mins} minute${mins === 1 ? '' : 's'} ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs} hour${hrs === 1 ? '' : 's'} ago`
  const days = Math.floor(hrs / 24)
  return `${days} day${days === 1 ? '' : 's'} ago`
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
    now.value = Math.floor(Date.now() / 1000)
  } catch {
    // Silently fail — defaults to inactive
  }
}

const refresh = async () => {
  activating.value = true
  errorMsg.value   = ''
  try {
    await loadStatus()
  } finally {
    activating.value = false
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
  clock = setInterval(() => { now.value = Math.floor(Date.now() / 1000) }, 15000)
})

onUnmounted(() => {
  if (clock) { clearInterval(clock); clock = null }
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
