<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <router-link to="/settings/integrations" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 mb-4">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      {{ $t('Back to Integrations') }}
    </router-link>
    <h2 class="text-lg font-semibold">{{ $t('WooCommerce') }}</h2>
    <p class="text-sm text-gray-500 mb-4">{{ $t('Optimize product image alt text with product-aware AI generation.') }}</p>
    <hr class="mb-6" />

    <template v-if="woocommerceInstalled">
      <p class="text-sm text-gray-600 mb-4">{{ $t('Configure alt text generation for WooCommerce products') }}</p>

      <div class="mb-4">
        <Checkbox true-value="1" false-value="0" v-model="settings.icais_woo_include_product_info">
          <span>{{ $t('Include product information') }}</span>
        </Checkbox>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('Use product name, categories, and SKU in alt text generation') }}
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Default Tone for Products') }}</label>
        <select
          v-model="settings.icais_woo_default_tone"
          class="icais-select w-full"
        >
          <optgroup :label="$t('WooCommerce Optimized')">
            <option value="woo_seo">{{ $t('Product SEO (Recommended)') }}</option>
            <option value="woo_descriptive">{{ $t('Product Descriptive (Accessibility)') }}</option>
            <option value="woo_lifestyle">{{ $t('Product Lifestyle (In-Use Photos)') }}</option>
            <option value="woo_custom">{{ $t('Custom Prompt') }}</option>
          </optgroup>
          <optgroup :label="$t('General')">
            <option value="professional">{{ $t('Professional') }}</option>
            <option value="casual">{{ $t('Casual') }}</option>
            <option value="technical">{{ $t('Technical') }}</option>
            <option value="seo">{{ $t('SEO-Optimized') }}</option>
          </optgroup>
        </select>
      </div>

      <div v-if="settings.icais_woo_default_tone === 'woo_custom'" class="mb-4">
        <label class="block text-sm font-medium mb-2">{{ $t('Custom WooCommerce Prompt') }}</label>
        <textarea
          v-model="settings.icais_woo_custom_prompt"
          class="icais-textarea w-full"
          rows="4"
          :placeholder="$t('e.g. Generate product alt text with the product name, visible features, and use case. Do not start with \'Image of\'. Return only the alt text.')"
        ></textarea>
        <p class="text-xs text-gray-500 mt-1">
          {{ $t('The max character length setting will be applied automatically. Product name, category, and SKU are automatically included as context.') }}
        </p>
      </div>
    </template>

    <p v-else class="text-sm text-gray-600">
      {{ $t('WooCommerce is not installed. Install WooCommerce to access product image optimization features.') }}
    </p>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'
import { Checkbox } from '../../@ui'

const { settings, woocommerceInstalled } = inject('settingsContext')
</script>
