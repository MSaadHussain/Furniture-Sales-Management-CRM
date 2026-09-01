import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import Chart from 'chart.js/auto';

Alpine.plugin(collapse);

// Chart.js is used by the dashboard and the reports; expose it for the inline
// Blade scripts that build each chart.
window.Alpine = Alpine;
window.Chart = Chart;

// ---- Global theme (dark/light) store ----
Alpine.store('theme', {
    dark: localStorage.getItem('ta-theme') === 'dark',
    toggle() {
        this.dark = !this.dark;
        localStorage.setItem('ta-theme', this.dark ? 'dark' : 'light');
        document.documentElement.classList.toggle('dark', this.dark);
    },
});

Alpine.start();
