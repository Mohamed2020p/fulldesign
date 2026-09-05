/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './pages/**/*.php',
    './includes/**/*.php',
    './pages/admin/**/*.php'
  ],
  theme: {
    extend: {
      colors: {
        parchment: {
          DEFAULT: '#f4ecdc',
          dark: '#eadfc8',
          light: '#fbf6ec'
        },
        ink: {
          DEFAULT: '#1c2b45',
          dark: '#142034',
          soft: '#5a6a80'
        },
        gold: '#c2a14d',
        crimson: '#8f2c2c',
        forest: '#2f5d43',
        rule: '#d9cbaf'
      },
      fontFamily: {
        sans: ['Poppins', 'system-ui', 'sans-serif'],
        serif: ['Poppins', 'Georgia', 'serif']
      },
      boxShadow: {
        panel: '0 1px 0 0 #d9cbaf, 0 2px 8px -6px rgba(28, 43, 69, 0.35)'
      }
    }
  },
  plugins: []
};
