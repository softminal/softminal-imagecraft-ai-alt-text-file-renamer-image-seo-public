import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import notification from './plugins/notification';
import '../scss/index.scss';

const app = createApp(App);

app.config.globalProperties.$t = (text)=>{
    return  text
}

app.use(router);
app.use(notification);
app.mount('#smart-alt-text-ai-app');