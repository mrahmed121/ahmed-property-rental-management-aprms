/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        // APRMS executive identity — charcoal base, Ahmed gold, copper
        charcoal: {
          950: '#0b0e13',
          900: '#12161d',
          800: '#1a2029',
          700: '#232b37',
          600: '#2f3947',
        },
        gold: {
          DEFAULT: '#d4af37',
          light: '#e8c96a',
          dark: '#a8862a',
        },
        copper: {
          DEFAULT: '#b87333',
          light: '#d19a5f',
          dark: '#8f5a26',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'Segoe UI', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
