<template>
  <div class="help p-6">
    <h1 class="text-2xl font-bold mb-6">{{ $t('Help & Support') }}</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Support Card -->
      <div class="bg-white rounded-lg shadow border p-8 flex flex-col items-center text-center">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <h2 class="text-xl font-semibold mb-2">{{ $t('24/7 Dedicated Support') }}</h2>
        <p class="text-gray-600 mb-2">
          {{ $t('Our support team is here to help you with any questions, issues, or feature requests.') }}
        </p>
        <p class="text-gray-500 text-sm mb-6">
          {{ $t('Whether you need help setting up your API key, configuring WooCommerce, or troubleshooting batch processing — we\'ve got you covered.') }}
        </p>
        <a
          href="https://support.softminal.com"
          target="_blank"
          rel="noopener noreferrer"
          class="bg-blue-600 text-white! px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 hover:text-white focus:bg-blue-600 focus:text-white focus:outline-hidden focus:shadow-none transition inline-flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          {{ $t('Contact Support') }}
        </a>
      </div>

      <!-- Review Card -->
      <div class="bg-white rounded-lg shadow border p-8 flex flex-col items-center text-center">
        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
          </svg>
        </div>
        <h2 class="text-xl font-semibold mb-2">{{ $t('Give Your Love') }}</h2>
        <p class="text-gray-600 mb-2">
          {{ $t('Your love is our motivation! A quick review helps us grow and keep improving ImageCraft for everyone.') }}
        </p>
        <p class="text-gray-500 text-sm mb-6">
          {{ $t('If ImageCraft has helped your workflow, please take a moment to share your experience on WordPress.org.') }}
        </p>
        <a
          href="https://wordpress.org/support/plugin/imagecraft-ai-alt-text-file-renamer-image-seo/reviews/#new-post"
          target="_blank"
          rel="noopener noreferrer"
          class="bg-yellow-500 text-white! px-8 py-3 rounded-lg font-semibold hover:bg-yellow-600 hover:text-white focus:bg-yellow-500 focus:text-white focus:outline-hidden focus:shadow-none transition inline-flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
          </svg>
          {{ $t('Leave a Review') }}
        </a>
      </div>
    </div>

    <!-- Documentation & Quick Links -->
    <div class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('Quick Links') }}</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a
          href="https://wordpress.org/plugins/imagecraft-ai-alt-text-file-renamer-image-seo/"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:bg-gray-50 no-underline text-gray-700 hover:text-gray-700 focus:text-gray-700 focus:outline-hidden focus:shadow-none transition"
        >
          <svg class="w-6 h-6 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke-width="2" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />
          </svg>
          <div>
            <div class="font-medium text-sm text-gray-900">{{ $t('WordPress.org Plugin Page') }}</div>
            <div class="text-xs text-gray-500">{{ $t('View plugin details and changelog') }}</div>
          </div>
        </a>
        <a
          href="https://www.softminal.com/products/imagecraft-ai-alt-text-file-renamer-image-seo"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:bg-gray-50 no-underline text-gray-700 hover:text-gray-700 focus:text-gray-700 focus:outline-hidden focus:shadow-none transition"
        >
          <svg class="w-6 h-6 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <div class="font-medium text-sm text-gray-900">{{ $t('Knowledge Base') }}</div>
            <div class="text-xs text-gray-500">{{ $t('Guides, tutorials, and FAQs') }}</div>
          </div>
        </a>
        <a
          href="https://wordpress.org/support/plugin/imagecraft-ai-alt-text-file-renamer-image-seo/"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:bg-gray-50 no-underline text-gray-700 hover:text-gray-700 focus:text-gray-700 focus:outline-hidden focus:shadow-none transition"
        >
          <svg class="w-6 h-6 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
          </svg>
          <div>
            <div class="font-medium text-sm text-gray-900">{{ $t('Community Forum') }}</div>
            <div class="text-xs text-gray-500">{{ $t('Ask questions and get help from the community') }}</div>
          </div>
        </a>
      </div>
    </div>

    <!-- Video Tutorial -->
    <div class="bg-white rounded-lg shadow border p-6 mt-6">
      <h2 class="text-lg font-semibold mb-4">{{ $t('Video Tutorial') }}</h2>
      <p class="text-gray-600 text-sm mb-4">{{ $t('Watch how to set up and use ImageCraft in just a few minutes.') }}</p>
      <div class="relative w-full" style="padding-bottom: 56.25%;">
        <iframe
          class="absolute inset-0 w-full h-full rounded-lg"
          src="https://www.youtube.com/embed/VVhnzSmrjLE"
          title="How to Use ImageCraft"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        ></iframe>
      </div>
    </div>
  </div>
</template>
