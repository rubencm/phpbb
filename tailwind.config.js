/** @type {import('tailwindcss').Config} */
export default {
	darkMode: 'selector',
	content: [
		'./phpBB/styles/clarity/**/*.{html,js,twig}',
	],
	theme: {
		container: {
			center: true,
			padding: '1rem',
			screens: {
				'2xl': '72rem',
			},
		},
		extend: {
			colors: {
				border: 'hsl(var(--border, 218 20% 88%) / <alpha-value>)',
				input: 'hsl(var(--input, 218 20% 88%) / <alpha-value>)',
				ring: 'hsl(var(--ring, 221 84% 54%) / <alpha-value>)',
				background: 'hsl(var(--background, 240 13% 97%) / <alpha-value>)',
				'background-light': 'hsl(var(--background, 240 13% 97%) / <alpha-value>)',
				'background-dark': 'hsl(var(--background-dark, 219 38% 10%) / <alpha-value>)',
				foreground: 'hsl(var(--foreground, 222 47% 11%) / <alpha-value>)',
				primary: {
					DEFAULT: 'hsl(var(--primary, 221 84% 54%) / <alpha-value>)',
					foreground: 'hsl(var(--primary-foreground, 0 0% 100%) / <alpha-value>)',
				},
				secondary: {
					DEFAULT: 'hsl(var(--secondary, 210 40% 96%) / <alpha-value>)',
					foreground: 'hsl(var(--secondary-foreground, 222 47% 11%) / <alpha-value>)',
				},
				muted: {
					DEFAULT: 'hsl(var(--muted, 210 40% 96%) / <alpha-value>)',
					foreground: 'hsl(var(--muted-foreground, 215 16% 47%) / <alpha-value>)',
				},
				accent: {
					DEFAULT: 'hsl(var(--accent, 188 86% 43%) / <alpha-value>)',
					foreground: 'hsl(var(--accent-foreground, 0 0% 100%) / <alpha-value>)',
				},
				popover: {
					DEFAULT: 'hsl(var(--popover, 0 0% 100%) / <alpha-value>)',
					foreground: 'hsl(var(--popover-foreground, 222 47% 11%) / <alpha-value>)',
				},
				card: {
					DEFAULT: 'hsl(var(--card, 0 0% 100%) / <alpha-value>)',
					foreground: 'hsl(var(--card-foreground, 222 47% 11%) / <alpha-value>)',
				},
				destructive: {
					DEFAULT: 'hsl(var(--destructive, 0 84% 60%) / <alpha-value>)',
					foreground: 'hsl(var(--destructive-foreground, 0 0% 100%) / <alpha-value>)',
				},
			},
			fontFamily: {
				display: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
				sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
			},
			boxShadow: {
				glass: '0 8px 32px rgba(31, 38, 135, 0.07)',
				float: '0 20px 40px -10px rgba(15, 23, 42, 0.08)',
			},
			borderRadius: {
				lg: 'var(--radius)',
				md: 'calc(var(--radius) - 2px)',
				sm: 'calc(var(--radius) - 4px)',
			},
		},
	},
	plugins: [],
};
