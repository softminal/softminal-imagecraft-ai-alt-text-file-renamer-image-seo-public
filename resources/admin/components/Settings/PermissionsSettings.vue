<template>
  <div class="bg-white rounded-lg shadow border p-6">
    <h2 class="text-lg font-semibold">{{ $t('Permissions') }}</h2>
    <p class="text-sm text-gray-500 mb-4">
      {{ $t('Choose which user roles are allowed to use ImageCraft. This applies to all features — alt text generation, image titles, file renaming, and image compression. Administrators always have access.') }}
    </p>
    <hr class="mb-6" />

    <div class="p-4 bg-gray-50 rounded-lg border">
      <label class="inline-flex items-center gap-1.5 mb-4 opacity-70 cursor-not-allowed">
        <input type="checkbox" checked disabled class="rounded" />
        <span class="text-sm font-medium text-gray-700">{{ $t('Administrator') }}</span>
        <span class="text-xs text-gray-400">({{ $t('always allowed') }})</span>
      </label>

      <div class="flex flex-wrap gap-x-5 gap-y-3">
        <label
          v-for="(label, slug) in availableRoles"
          :key="slug"
          class="inline-flex items-center gap-1.5 cursor-pointer"
        >
          <input
            type="checkbox"
            :value="slug"
            v-model="allowedRoles"
            class="rounded"
          />
          <span class="text-sm text-gray-700">{{ label }}</span>
        </label>
      </div>

      <p v-if="Object.keys(availableRoles).length === 0" class="text-xs text-gray-400">
        {{ $t('No other roles available') }}
      </p>
    </div>

    <div
      v-if="allowedRoles.length === 0"
      class="mt-3 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"
    >
      <p class="text-xs text-amber-800">
        {{ $t('No roles selected — every user who can upload media may use ImageCraft (current behavior). Select one or more roles to restrict access.') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { inject } from 'vue'
import { $t } from '../../utils/i18n'

const { availableRoles, allowedRoles } = inject('settingsContext')
</script>
