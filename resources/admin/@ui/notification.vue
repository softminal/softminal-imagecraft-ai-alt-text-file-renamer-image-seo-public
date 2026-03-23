<template>
  <div class="notification-container fixed top-8 right-4 z-50 space-y-2">
    <transition-group name="notification" tag="div">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="[
          'notification-item p-4 rounded-lg shadow-lg min-w-[300px] max-w-[400px]',
          getNotificationClass(notification.type)
        ]"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div v-if="notification.title" class="font-semibold mb-1">
              {{ notification.title }}
            </div>
            <div class="text-sm">
              {{ notification.message }}
            </div>
          </div>
          <button
            @click="removeNotification(notification.id)"
            class="ml-4 text-gray-600 hover:text-gray-800"
          >
            ×
          </button>
        </div>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const notifications = ref([])
let notificationId = 0

const getNotificationClass = (type) => {
  const classes = {
    success: 'bg-green-50 border border-green-200 text-green-800',
    error: 'bg-red-50 border border-red-200 text-red-800',
    warning: 'bg-yellow-50 border border-yellow-200 text-yellow-800',
    info: 'bg-blue-50 border border-blue-200 text-blue-800'
  }
  return classes[type] || classes.info
}

const addNotification = (options) => {
  const id = ++notificationId
  const notification = {
    id,
    type: options.type || 'info',
    title: options.title,
    message: options.message || options,
    duration: options.duration || 3000
  }
  
  notifications.value.push(notification)
  
  if (notification.duration > 0) {
    setTimeout(() => {
      removeNotification(id)
    }, notification.duration)
  }
  
  return id
}

const removeNotification = (id) => {
  const index = notifications.value.findIndex(n => n.id === id)
  if (index > -1) {
    notifications.value.splice(index, 1)
  }
}

const success = (message, options = {}) => {
  return addNotification({ ...options, type: 'success', message })
}

const error = (message, options = {}) => {
  return addNotification({ ...options, type: 'error', message })
}

const warning = (message, options = {}) => {
  return addNotification({ ...options, type: 'warning', message })
}

const info = (message, options = {}) => {
  return addNotification({ ...options, type: 'info', message })
}

// Expose methods for the notification plugin
defineExpose({
  addNotification,
  success,
  error,
  warning,
  info
})
</script>

<style scoped>
.notification-enter-active,
.notification-leave-active {
  transition: all 0.3s ease;
}

.notification-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.notification-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
