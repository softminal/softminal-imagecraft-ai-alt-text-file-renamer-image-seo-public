import { createApp } from 'vue'
import NotificationComponent from '../@ui/notification.vue'

// Create a singleton notification instance
let notificationInstance = null;

// Initialize the notification system
const initNotification = () => {
  if (notificationInstance) return;
  
  // Create a div to mount our notification component
  const notificationDiv = document.createElement('div')
  notificationDiv.id = 'notification-container'
  document.body.appendChild(notificationDiv)
  
  // Create a new Vue app instance with the notification component
  const notificationApp = createApp(NotificationComponent)
  const mountedApp = notificationApp.mount('#notification-container')
  
  // Save reference to the component's exposed methods
  notificationInstance = mountedApp;
};

// Create the notification function
const notification = (options) => {
  // Make sure the notification system is initialized
  initNotification();
  
  // Handle string input
  if (typeof options === 'string') {
    return notificationInstance.info(options)
  }
  
  // Handle simplified object format with success flag
  if (options.success !== undefined) {
    const type = options.success ? 'success' : 'error'
    const message = options.msg || (options.success ? 'Operation successful' : 'Operation failed')
    const title = options.title || (options.success ? 'Success' : 'Error')
    const duration = options.duration || 3000
    
    return notificationInstance.addNotification({
      type,
      message,
      title,
      duration
    })
  }
  
  // Handle standard format
  const { type = 'info', message, ...rest } = options
  
  switch (type) {
    case 'success':
      return notificationInstance.success(message, rest)
    case 'error':
      return notificationInstance.error(message, rest)
    case 'warning':
      return notificationInstance.warning(message, rest)
    case 'info':
    default:
      return notificationInstance.info(message, rest)
  }
}

// Add convenience methods
notification.success = (message, options = {}) => {
  initNotification();
  return notificationInstance.success(message, options)
}

notification.error = (message, options = {}) => {
  initNotification();
  return notificationInstance.error(message, options)
}

notification.warning = (message, options = {}) => {
  initNotification();
  return notificationInstance.warning(message, options)
}

notification.info = (message, options = {}) => {
  initNotification();
  return notificationInstance.info(message, options)
}

// Vue plugin for installing globally
const plugin = {
  install(app) {
    initNotification();
    app.config.globalProperties.$notification = notification
  }
}

// Export the notification function directly
export { notification }

// Default export for use as a plugin
export default plugin