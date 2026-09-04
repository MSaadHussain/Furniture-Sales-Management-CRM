import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import Chart from 'chart.js/auto';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.css';

Alpine.plugin(collapse);

window.Alpine = Alpine;
window.Chart = Chart;
window.flatpickr = flatpickr;

// Alpine datepicker directive
Alpine.directive('datepicker', (el, { expression }, { evaluateLater, effect }) => {
    let customOptions = {};
    if (expression) {
        try {
            const evalFn = evaluateLater(expression);
            evalFn(val => { customOptions = val || {}; });
        } catch (e) {}
    }

    const initFP = () => {
        if (el._flatpickr) return;
        const options = Object.assign({
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            altInputClass: el.className,
            allowInput: true,
            disableMobile: true,
        }, customOptions);

        el._flatpickr = flatpickr(el, options);
    };

    setTimeout(initFP, 10);
});

// Auto-init for elements with data-datepicker attribute
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-datepicker]').forEach(el => {
        if (!el._flatpickr) {
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                altInputClass: el.className,
                allowInput: true,
                disableMobile: true,
            });
        }
    });
});

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

