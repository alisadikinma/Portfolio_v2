/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Ethereal Glass Design System — OLED Deep
        'bg-deep': '#050505',
        'bg-elevated': '#0A0A0D',
        'bg-surface': 'rgba(255, 255, 255, 0.04)',
        'fg-primary': '#EDEDEF',
        'fg-muted': '#8A8F98',
        'fg-dim': '#5A5F6B',
        'accent-gold': '#D4A843',
        'accent-cyan': '#06B6D4',
        'accent-indigo': '#5E6AD2',
        'border-hairline': 'rgba(255, 255, 255, 0.06)',
        'border-hover': 'rgba(255, 255, 255, 0.12)',
        // Semantic
        success: {
          50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
          400: '#4ade80', 500: '#10B981', 600: '#059669', 700: '#047857',
        },
        error: {
          50: '#fef2f2', 100: '#fee2e2', 200: '#fecaca', 300: '#fca5a5',
          400: '#f87171', 500: '#EF4444', 600: '#dc2626', 700: '#b91c1c',
        },
        warning: {
          50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d',
          400: '#fbbf24', 500: '#F59E0B', 600: '#d97706', 700: '#b45309',
        },
        info: {
          50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
          400: '#60a5fa', 500: '#3B82F6', 600: '#2563eb', 700: '#1d4ed8',
        },
        // Legacy support for admin panel
        primary: {
          50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
          400: '#818cf8', 500: '#5E6AD2', 600: '#4F5BC0', 700: '#4338ca',
          800: '#3730a3', 900: '#312e81', 950: '#1e1b4b',
        },
        secondary: {
          50: '#fefce8', 100: '#fef9c3', 200: '#fef08a', 300: '#fde047',
          400: '#E5BF4F', 500: '#D4A843', 600: '#B8922F', 700: '#92761F',
          800: '#6B5615', 900: '#4A3C0F', 950: '#2D240A',
        },
        accent: {
          50: '#ecfeff', 100: '#cffafe', 200: '#a5f3fc', 300: '#67e8f9',
          400: '#22d3ee', 500: '#06B6D4', 600: '#0891b2', 700: '#0e7490',
          800: '#155e75', 900: '#164e63', 950: '#083344',
        },
        gray: {
          50: '#EDEDEF', 100: '#D4D4D8', 200: '#A1A1AA', 300: '#8A8F98',
          400: '#71717A', 500: '#5A5F6B', 600: '#3F3F46', 700: '#27272A',
          800: '#18181B', 900: '#0A0A0D', 950: '#050505',
        },
      },
      fontFamily: {
        sans: ['Geist', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
        display: ['Space Grotesk', 'Geist', 'sans-serif'],
        serif: ['Playfair Display', 'Georgia', 'serif'],
        mono: ['Geist Mono', 'JetBrains Mono', 'monospace'],
      },
      fontSize: {
        'xs': ['0.75rem', { lineHeight: '1rem' }],
        'sm': ['0.875rem', { lineHeight: '1.25rem' }],
        'base': ['1rem', { lineHeight: '1.5rem' }],
        'lg': ['1.125rem', { lineHeight: '1.75rem' }],
        'xl': ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1.15' }],
        '6xl': ['3.75rem', { lineHeight: '1.1' }],
        '7xl': ['4.5rem', { lineHeight: '1.05' }],
        '8xl': ['6rem', { lineHeight: '1' }],
        '9xl': ['8rem', { lineHeight: '0.9' }],
      },
      spacing: {
        '128': '32rem',
        '144': '36rem',
      },
      borderRadius: {
        '4xl': '2rem',
        '5xl': '2.5rem',
      },
      maxWidth: {
        '8xl': '88rem',
        '9xl': '96rem',
      },
      container: {
        center: true,
        padding: {
          DEFAULT: '1rem',
          sm: '2rem',
          lg: '4rem',
          xl: '5rem',
          '2xl': '6rem',
        },
      },
      animation: {
        'fade-in': 'fadeIn 0.6s cubic-bezier(0.32, 0.72, 0, 1)',
        'fade-in-up': 'fadeInUp 0.7s cubic-bezier(0.32, 0.72, 0, 1)',
        'fade-in-down': 'fadeInDown 0.7s cubic-bezier(0.32, 0.72, 0, 1)',
        'slide-in-left': 'slideInLeft 0.6s cubic-bezier(0.32, 0.72, 0, 1)',
        'slide-in-right': 'slideInRight 0.6s cubic-bezier(0.32, 0.72, 0, 1)',
        'scale-in': 'scaleIn 0.5s cubic-bezier(0.32, 0.72, 0, 1)',
        'aurora-float': 'auroraFloat 20s cubic-bezier(0.45, 0, 0.55, 1) infinite',
        'aurora-float-slow': 'auroraFloat 30s cubic-bezier(0.45, 0, 0.55, 1) infinite',
        'gradient-rotate': 'gradientRotate 4s linear infinite',
        'glow-pulse': 'glowPulse 3s cubic-bezier(0.45, 0, 0.55, 1) infinite',
        'float-y': 'floatY 4s cubic-bezier(0.45, 0, 0.55, 1) infinite',
        'bounce-slow': 'bounce 3s infinite',
        'spin-slow': 'spin 3s linear infinite',
        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        fadeInUp: {
          '0%': { opacity: '0', transform: 'translateY(32px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        fadeInDown: {
          '0%': { opacity: '0', transform: 'translateY(-32px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        slideInLeft: {
          '0%': { opacity: '0', transform: 'translateX(-60px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        slideInRight: {
          '0%': { opacity: '0', transform: 'translateX(60px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        scaleIn: {
          '0%': { opacity: '0', transform: 'scale(0.92)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        auroraFloat: {
          '0%, 100%': { transform: 'translate(0, 0) scale(1)' },
          '25%': { transform: 'translate(60px, -40px) scale(1.05)' },
          '50%': { transform: 'translate(-30px, 50px) scale(0.95)' },
          '75%': { transform: 'translate(40px, 20px) scale(1.02)' },
        },
        floatY: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-8px)' },
        },
        gradientRotate: {
          '0%': { '--angle': '0deg' },
          '100%': { '--angle': '360deg' },
        },
        glowPulse: {
          '0%, 100%': { opacity: '0.6' },
          '50%': { opacity: '1' },
        },
      },
      boxShadow: {
        'inner-lg': 'inset 0 2px 4px 0 rgb(0 0 0 / 0.1)',
        'inner-highlight': 'inset 0 1px 1px rgba(255, 255, 255, 0.08)',
        'glow-gold': '0 0 50px rgba(212, 168, 67, 0.2)',
        'glow-gold-lg': '0 0 80px rgba(212, 168, 67, 0.3)',
        'glow-cyan': '0 0 50px rgba(6, 182, 212, 0.15)',
        'glow-indigo': '0 0 50px rgba(94, 106, 210, 0.15)',
        'glass': '0 8px 32px rgba(0, 0, 0, 0.4)',
        'glass-lg': '0 12px 48px rgba(0, 0, 0, 0.5)',
      },
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
        'gradient-gold-cyan': 'linear-gradient(135deg, #D4A843, #06B6D4)',
        'gradient-gold-indigo-cyan': 'linear-gradient(135deg, #D4A843, #5E6AD2, #06B6D4)',
      },
      backdropBlur: {
        xs: '2px',
        '3xl': '40px',
      },
      transitionTimingFunction: {
        'spring': 'cubic-bezier(0.32, 0.72, 0, 1)',
        'smooth': 'cubic-bezier(0.45, 0, 0.55, 1)',
      },
    },
  },
  plugins: [],
}
