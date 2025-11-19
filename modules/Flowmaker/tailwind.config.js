
import type { Config } from "tailwindcss";

export default {
  darkMode: ["class"],
  content: [
    "./pages/**/*.{ts,tsx}",
    "./components/**/*.{ts,tsx}",
    "./app/**/*.{ts,tsx}",
    "./src/**/*.{ts,tsx}",
    "./Resources/**/*.{ts,tsx}",
  ],
  prefix: "",
  theme: {
    container: {
      center: true,
      padding: "2rem",
      screens: {
        "2xl": "1400px",
      },
    },
    extend: {
      colors: {
        whatsapp: {
          green: "#25D366",
          blue: "#075E54",
          lightblue: "#34B7F1",
          purple: "#E9E2F5", // Adding a purple color
        },
        ai: {
          purple: "#9b87f5",
          deepPurple: "#7E69AB",
          vivid: "#8B5CF6",
          magenta: "#D946EF",
          blue: "#0EA5E9",
          glow: "rgba(139, 92, 246, 0.5)",
        },
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
      },
      fontFamily: {
        inter: ["Inter", "sans-serif"],
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      keyframes: {
        "accordion-down": {
          from: { height: "0" },
          to: { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to: { height: "0" },
        },
        "pulse-subtle": {
          "0%, 100%": { opacity: "1" },
          "50%": { opacity: "0.9" },
        },
        "gradient-x": {
          "0%": { backgroundPosition: "0% 50%" },
          "50%": { backgroundPosition: "100% 50%" },
          "100%": { backgroundPosition: "0% 50%" },
        },
        "ai-glow": {
          "0%": { 
            boxShadow: "0 0 5px #8B5CF6, 0 0 10px #9b87f5, 0 0 15px #D946EF",
            opacity: 0.8 
          },
          "50%": { 
            boxShadow: "0 0 10px #8B5CF6, 0 0 20px #9b87f5, 0 0 30px #D946EF",
            opacity: 1 
          },
          "100%": { 
            boxShadow: "0 0 5px #8B5CF6, 0 0 10px #9b87f5, 0 0 15px #D946EF",
            opacity: 0.8 
          },
        },
        "shimmer": {
          "0%": { backgroundPosition: "200% 0" },
          "100%": { backgroundPosition: "-200% 0" },
        },
        "rainbow-border": {
          "0%": { borderColor: "#8B5CF6" },
          "25%": { borderColor: "#D946EF" },
          "50%": { borderColor: "#0EA5E9" },
          "75%": { borderColor: "#8B5CF6" },
          "100%": { borderColor: "#D946EF" },
        },
        "rainbow-glow": {
          "0%": { boxShadow: "0 0 10px rgba(139, 92, 246, 0.7)" },
          "25%": { boxShadow: "0 0 10px rgba(217, 70, 239, 0.7)" },
          "50%": { boxShadow: "0 0 10px rgba(14, 165, 233, 0.7)" },
          "75%": { boxShadow: "0 0 10px rgba(139, 92, 246, 0.7)" },
          "100%": { boxShadow: "0 0 10px rgba(217, 70, 239, 0.7)" },
        }
      },
      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
        "pulse-subtle": "pulse-subtle 4s ease-in-out infinite",
        "gradient-x": "gradient-x 10s ease infinite",
        "ai-glow": "ai-glow 3s ease-in-out infinite",
        "shimmer": "shimmer 8s infinite linear",
        "rainbow-border": "rainbow-border 6s linear infinite",
        "rainbow-glow": "rainbow-glow 6s linear infinite"
      },
    },
  },
  plugins: [
    require("tailwindcss-animate"),
    function({ addComponents }) {
      addComponents({
        '.rainbow-border': {
          'position': 'relative',
          'border': '1px solid transparent',
          'background-clip': 'padding-box, border-box',
          'background-origin': 'border-box',
          'background-image': 'linear-gradient(to right, #000, #000), linear-gradient(90deg, #8B5CF6, #D946EF, #0EA5E9, #8B5CF6)',
          'background-size': '100% 100%, 300% 100%',
          'animation': 'rainbow-border 6s linear infinite',
          '&::after': {
            'content': '""',
            'position': 'absolute',
            'inset': '-2px',
            'z-index': '-1',
            'border-radius': 'inherit',
            'background': 'linear-gradient(90deg, #8B5CF6, #D946EF, #0EA5E9, #8B5CF6)',
            'background-size': '300% 100%',
            'animation': 'rainbow-border 6s linear infinite',
            'filter': 'blur(8px)',
            'opacity': '0.5',
            'pointer-events': 'none'
          }
        }
      });
    }
  ],
} satisfies Config;
