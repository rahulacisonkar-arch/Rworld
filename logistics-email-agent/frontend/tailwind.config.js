/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        dark: {
          bg: "#0f172a",
          card: "rgba(30, 41, 59, 0.7)",
          border: "rgba(255, 255, 255, 0.08)"
        }
      }
    },
  },
  plugins: [],
}
