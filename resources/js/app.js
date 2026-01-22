import { createApp } from 'vue'
import App from './App.vue'
import Swal from 'sweetalert2'

const app = createApp(App)

app.config.globalProperties.$swal = Swal

app.mount('#app')
