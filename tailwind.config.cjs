/** @type {import('tailwindcss').Config} */
module.exports = {
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
                sans: ['Instrument Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                'dark': {
                    700: '#374151',
                    800: '#1f2937',
                    900: '#111827',
                },
                'accent': '#3b82f6',
            },
            screens: {
                // Touch device detection (no hover capability)
                'touch': { 'raw': '(hover: none)' },
                // Non-touch device (has hover)
                'pointer': { 'raw': '(hover: hover)' },
            },
        },
    },
    plugins: [
        require('@tailwindcss/container-queries'),
    ],
};
