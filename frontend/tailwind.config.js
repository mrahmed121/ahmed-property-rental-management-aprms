/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        // APRMS executive design system — charcoal + Ahmed gold signature + copper
        charcoal: {
          950: '#12161d',
          900: '#1a2029',
          800: '#232c38',
          700: '#2f3a49',
        },
        gold: {
          DEFAULT: '#d4af37',
          light: '#e8c96a',
          dark: '#a8862a',
        },
        copper: {
          DEFAULT: '#b87333',
          light: '#d19a5f',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'Segoe UI', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
