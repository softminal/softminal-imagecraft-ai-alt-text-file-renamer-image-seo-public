/**
 * WordPress Media Library integration
 */
const wpMedia = {
  /**
   * Open the WordPress Media Library
   * @param {Object} options - Configuration options
   * @param {string} options.title - Title for the media modal
   * @param {string} options.buttonText - Text for the select button
   * @param {boolean} options.multiple - Allow multiple selections
   * @param {string} options.type - Media type filter
   * @param {Function} options.onSelect - Callback when media is selected
   */
  open(options = {}) {
    // Set default options
    const config = {
      title: options.title || 'Select Media',
      button: { text: options.buttonText || 'Select' },
      multiple: options.multiple !== undefined ? options.multiple : true,
      library: { type: options.type || 'image' },
    };

    // Check if WordPress media is available
    if (typeof window.wp === 'undefined' || typeof window.wp.media === 'undefined') {
      console.error('WordPress Media Library API is not available');
      
      // If we're in the WordPress admin context, try to load the media library
      if (typeof window.ajaxurl !== 'undefined') {
        alert('Please wait while the WordPress Media Library loads...');
        // You might need to implement a more sophisticated loading mechanism
        return;
      } else {
        alert('WordPress Media Library is not available. Are you running in the WordPress admin?');
        return;
      }
    }

    // Create media frame
    const mediaFrame = window.wp.media(config);

    // Set callback for selection
    if (options.onSelect) {
      mediaFrame.on('select', function() {
        const selection = mediaFrame.state().get('selection');
        options.onSelect(selection);
      });
    }

    // Open the media modal
    mediaFrame.open();
  }
};

export default wpMedia;