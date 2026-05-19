import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                crm: {
                    primary: '#0B0736',
                    secondary: '#733E87',
                },
            },
            backgroundImage: {
                'gradient-crm': 'linear-gradient(135deg, #0B0736 0%, #733E87 100%)',
                'gradient-crm-vertical': 'linear-gradient(180deg, #0B0736 0%, #733E87 100%)',
            },
        },
    },
    plugins: [],
    
};
