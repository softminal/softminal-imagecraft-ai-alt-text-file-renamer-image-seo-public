import { ref, onMounted, onUnmounted } from 'vue'
import echarts from '../utils/echarts'

export function useECharts() {
  const chartRef = ref(null)
  let chartInstance = null

  const handleResize = () => {
    chartInstance?.resize()
  }

  onMounted(() => {
    if (chartRef.value) {
      chartInstance = echarts.init(chartRef.value)
      window.addEventListener('resize', handleResize)
    }
  })

  onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    chartInstance?.dispose()
    chartInstance = null
  })

  const updateOptions = (options) => {
    if (!chartInstance && chartRef.value) {
      chartInstance = echarts.init(chartRef.value)
      window.addEventListener('resize', handleResize)
    }
    chartInstance?.setOption(options)
  }

  return { chartRef, updateOptions }
}
