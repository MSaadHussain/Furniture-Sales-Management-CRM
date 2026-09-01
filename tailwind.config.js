import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                // TailAdmin uses Outfit for the dashboard chrome.
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // ---- TailAdmin CRM palette ----
                brand: {
                    DEFAULT: '#465FFF',
                    50:  '#ECF0FF',
                    100: '#DDE3FF',
                    400: '#7592FF',
                    500: '#465FFF',
                    600: '#3641F5',
                    700: '#2A31D8',
                    900: '#1A1E63',
                },
                sidebar: '#111827',
                ink:     '#101828',
                muted:   '#667085',
                surface: '#F9FAFB',
                line:    '#EAECF0',
                success: '#12B76A',
                warning: '#F79009',
                danger:  '#F04438',
                info:    '#2E90FA',
                purple:  '#7A5AF8',
                // Dark-mode surfaces matching the TailAdmin demo.
                boxdark:    '#1C2434',
                boxdark2:   '#24303F',
                strokedark: '#2E3A47',
            },
            boxShadow: {
                card:    '0 1px 3px 0 rgba(16,24,40,0.08), 0 1px 2px -1px rgba(16,24,40,0.06)',
                'card-lg':'0 4px 24px -2px rgba(16,24,40,0.08)',
                dropdown:'0 12px 34px 0 rgba(16,24,40,0.12)',
            },
            borderRadius: {
                xl: '0.75rem',
                '2xl': '1rem',
            },
        },
    },
    plugins: [forms],
};
