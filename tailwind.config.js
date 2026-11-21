/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/**/*.{js,jsx,ts,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        border: 'rgba(0, 0, 0, 0.08)',
        borderHover: 'rgba(0, 0, 0, 0.25)',
        surface: {
          DEFAULT: '#ffffff',
          muted: '#f9fafb',
          secondary: '#f3f4f6',
        },
      },
      fontFamily: {
        sans: [
          'Google Sans',
          'Google Sans Text',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'sans-serif',
        ],
      },
      boxShadow: {
        DEFAULT: 'none',
        none: 'none',
        '2xs': 'none',
        xs: 'none',
        sm: 'none',
        md: 'none',
        lg: 'none',
        xl: 'none',
        '2xl': 'none',
      },
    },
  },
  plugins: [
    require('tailwindcss-animate'),
  ],
};
