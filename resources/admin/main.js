import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import notification from './plugins/notification';
import { $t } from './utils/i18n';
import '../scss/index.scss';

const app = createApp(App);

app.config.globalProperties.appVars = window.icais || {};
app.config.globalProperties.$t = $t;

app.use(router);
app.use(notification);
app.mount('#icais-app');
