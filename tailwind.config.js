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
                    primary: 'var(--color-primary)',
                    secondary: 'var(--color-primary-hover)',
                },
            },
            backgroundImage: {
                'gradient-crm': 'linear-gradient(var(--color-primary), var(--color-primary))',
                'gradient-crm-vertical': 'linear-gradient(var(--color-primary), var(--color-primary))',
            },
        },
    },
    plugins: [],
    
};
