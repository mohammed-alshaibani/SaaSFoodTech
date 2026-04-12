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
                    DEFAULT: "#7C3AED", // Brand Purple
                    light: "#8D5CF6",
                    dark: "#6D28D9",
                },
                accent: {
                    DEFAULT: "#F59E0B", // Brand Orange
                    light: "#FBBF24",
                    dark: "#D97706",
                },
                navy: {
                    DEFAULT: "#1E293B",
                    light: "#334155",
                    dark: "#0F172A",
                },
                charcoal: {
                    DEFAULT: "#1A202C",
                },
                green: {
                    DEFAULT: "#064E3B",
                    light: "#065F46",
                    dark: "#043E2E",
                },
                slate: {
                    50: "#F8FAFC",
                    100: "#F1F5F9",
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
