import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import '../scss/index.scss';

const app = createApp(App);

app.config.globalProperties.$t = (text)=>{
    return  text
}

app.use(router).mount('#wp-ai-alt-text-app');