/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./app/**/*.{js,ts,jsx,tsx,mdx}",
        "./pages/**/*.{js,ts,jsx,tsx,mdx}",
        "./components/**/*.{js,ts,jsx,tsx,mdx}",
        "./context/**/*.{js,ts,jsx,tsx,mdx}",
        "./lib/**/*.{js,ts,jsx,tsx,mdx}",
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: "#064E3B",
                    light: "#065F46",
                    dark: "#043E2E",
                },
                accent: {
                    DEFAULT: "#F59E0B",
                    light: "#FBBF24",
                    dark: "#D97706",
                },
                slate: {
                    50: "#F8FAFC",
                }
            },
            fontFamily: {
                sans: ["var(--font-geist-sans)", "ui-sans-serif", "system-ui"],
                arabic: ["var(--font-ibm-plex-arabic)", "serif"],
            },
            animation: {
                'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
            },
            keyframes: {
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
        },
    },
    plugins: [
        require('tailwindcss-rtl'),
    ],
};
