
import {
    LayoutDashboard,
    ScanSearch,
    Zap,
    ShoppingCart,
    Settings,
    HelpCircle,
    Image,
    Type,
    Sparkles,
    Pencil,
    Plug,
    Gauge,
    ImageOff,
    FileImage,
    Shield,
    Key,
    CreditCard,
    SlidersHorizontal,
    Store,
    Search,
} from 'lucide-vue-next'

/** Top-level navigation (daily workflows). */
export const topNav = [
    {
        name: 'Dashboard',
        path: '/',
        icon: LayoutDashboard,
        description: 'Your image health at a glance',
    },
    {
        name: 'Image SEO',
        path: '/scanner',
        icon: ScanSearch,
        pillar: 'seo',
        description: 'Alt text, titles, captions & file names',
    },
    {
        name: 'Performance',
        path: '/performance',
        icon: Zap,
        pillar: 'perf',
        description: 'Compress images & speed up your site',
    },
    {
        name: 'Settings',
        path: '/settings',
        icon: Settings,
        adminOnly: true,
        description: 'Configure ImageCraft',
    },
    {
        name: 'Help',
        path: '/help',
        icon: HelpCircle,
        adminOnly: true,
        description: 'Guides and support',
    },
]

/**
 * Settings sidebar, grouped under the same two pillars (+ General).
 * `children` render as visible sub-items (nothing buried a level deeper).
 */
export const settingsSections = [
    {
        section: 'General',
        items: [
            { name: 'General', path: '/settings/general', icon: SlidersHorizontal, description: 'Basic behavior & defaults' },
            { name: 'API', path: '/settings/api', icon: Key, description: 'Connect your AI provider' },
            { name: 'Permissions', path: '/settings/permissions', icon: Shield, description: 'Which roles can use ImageCraft', adminOnly: true },
            { name: 'License', path: '/settings/license', icon: CreditCard, description: 'Pro license & account' },
        ],
    },
    {
        section: 'Image SEO',
        pillar: 'seo',
        items: [
            { name: 'Alt Text', path: '/settings/alt-text', icon: Image, description: 'Tone, length & generation rules' },
            { name: 'Image Title', path: '/settings/title', icon: Type, description: 'Title generation preferences' },
            { name: 'Generate All', path: '/settings/meta', icon: Sparkles, description: 'Alt + title + caption in one pass' },
            { name: 'File Renaming', path: '/settings/rename', icon: Pencil, description: 'SEO-friendly file names' },
            {
                name: 'Integrations',
                path: '/settings/integrations',
                icon: Plug,
                description: 'WooCommerce & SEO plugins',
                children: [
                    { name: 'WooCommerce', path: '/settings/integrations/woocommerce', icon: Store, description: 'Store image settings' },
                    { name: 'SEO Plugins', path: '/settings/integrations/seo', icon: Search, description: 'Yoast & Rank Math keyphrases' },
                ],
            },
        ],
    },
    {
        section: 'Performance',
        pillar: 'perf',
        items: [
            // Next-gen Formats + Image Fallbacks live in the Performance
            // workspace (/performance/nextgen, /performance/fallbacks) — their
            // legacy /settings/* routes still resolve for old deep links.
            { name: 'Compression', path: '/settings/compression', icon: Gauge, description: 'Quality, resizing & backups' },
        ],
    },
]

/**
 * Dashboard quick actions — derived from the same definitions so labels
 * never drift between the menu and the dashboard.
 */
export const quickActions = [
    { name: 'Generate alt text', path: '/scanner?filter=missing', icon: Image, description: 'Fill in missing alt text', pillar: 'seo' },
    { name: 'Compress images', path: '/performance', icon: Zap, description: 'Shrink files, keep quality', pillar: 'perf' },
    { name: 'Rename files', path: '/scanner', icon: Pencil, description: 'SEO-friendly file names', pillar: 'seo' },
    { name: 'Image fallbacks', path: '/performance/fallbacks', icon: ImageOff, description: 'Cover broken images', pillar: 'perf', adminOnly: true },
    { name: 'WooCommerce', path: '/scanner/woocommerce', icon: ShoppingCart, description: 'Optimize product images', pillar: 'seo', adminOnly: true, requiresWoo: true },
]

/** Filter any item list by the current user/site context. */
export const visibleItems = (items, { isAdmin = false, wooActive = false } = {}) =>
    items.filter((item) => {
        if (item.adminOnly && !isAdmin) return false
        if (item.requiresWoo && !wooActive) return false
        return true
    })

/**
 * The pillar a path belongs to ('seo' | 'perf' | null). Drives the
 * contextual SectionBar and pillar-tinted accents.
 */
export const pillarForPath = (path) => {
    if (path.startsWith('/scanner') || path.startsWith('/woocommerce')) return 'seo'
    if (path.startsWith('/performance') || path.startsWith('/compression')) return 'perf'
    return null
}

/**
 * Sibling screens within each pillar — rendered by SectionBar.vue as a
 * contextual sub-bar so a pillar's tools stay one click away while you
 * work inside it. The bar only renders when 2+ items are visible.
 */
export const pillarScreens = {
    seo: [
        { name: 'Media Library', path: '/scanner', icon: ScanSearch, description: 'Scan & fix alt text, titles, file names' },
        { name: 'WooCommerce', path: '/scanner/woocommerce', icon: ShoppingCart, description: 'Product & gallery images', adminOnly: true, requiresWoo: true },
    ],
    perf: [
        { name: 'Compress', path: '/performance', icon: Gauge, description: 'Shrink files, keep quality' },
        { name: 'Next-gen Formats', path: '/performance/nextgen', icon: FileImage, description: 'WebP & AVIF conversion', adminOnly: true },
        { name: 'Image Fallbacks', path: '/performance/fallbacks', icon: ImageOff, description: 'Never show a broken image', adminOnly: true },
    ],
}
