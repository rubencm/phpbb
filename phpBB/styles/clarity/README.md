# Clarity Style

Clarity is a modern phpBB style built with Twig templates and a compiled
Tailwind CSS pipeline. It is a standalone style and does not inherit from
prosilver.

The goal of the style is to keep phpBB's template variables, events, and
runtime behavior intact while moving the visual system to a Tailwind-first
architecture.

## Architecture

Clarity has three main layers:

```text
template/
  Twig templates and phpBB template events.

theme/main.css
  Tailwind source file. Edit this file for global design, shared components,
  color tokens, responsive behavior, and phpBB-wide styling.

theme/stylesheet.css
  Generated CSS consumed by phpBB. Do not edit this by hand.
```

There is also a minified generated file:

```text
theme/stylesheet.min.css
```

phpBB still loads `theme/stylesheet.css` through `T_STYLESHEET_LINK`, but that
file is generated from Tailwind. The source of truth is `theme/main.css`.

The build tooling is in the repository root:

```text
tailwind.config.js
postcss.config.js
scripts/build-css.js
scripts/watch-css.js
scripts/minify-css.js
package.json
```

## Build Commands

Run commands from the repository root.

Install dependencies:

```bash
npm install
```

Build the normal stylesheet:

```bash
npm run build:css
```

Watch for changes while developing:

```bash
npm run watch:css
```

Build and minify:

```bash
npm run build
```

The generated files are:

```text
phpBB/styles/clarity/theme/stylesheet.css
phpBB/styles/clarity/theme/stylesheet.min.css
```

## What To Edit

Edit this for most visual changes:

```text
phpBB/styles/clarity/theme/main.css
```

Edit templates when the HTML structure needs to change:

```text
phpBB/styles/clarity/template/*.html
```

Edit Tailwind configuration when adding design tokens, theme colors, custom
shadows, content paths, font families, or other Tailwind-level settings:

```text
tailwind.config.js
```

Do not hand-edit:

```text
phpBB/styles/clarity/theme/stylesheet.css
phpBB/styles/clarity/theme/stylesheet.min.css
```

Those files are generated and will be overwritten by the build.

## Tailwind Basics

Tailwind uses small utility classes directly in templates:

```html
<div class="rounded-2xl bg-white/70 p-4 shadow-float">
```

This means:

```text
rounded-2xl     large border radius
bg-white/70     white background at 70% opacity
p-4             padding
shadow-float    custom shadow from tailwind.config.js
```

Responsive classes start with a breakpoint:

```html
<div class="grid gap-4 md:grid-cols-3">
```

This means:

```text
grid            grid layout on all screens
gap-4           spacing between grid items
md:grid-cols-3  use 3 columns on medium screens and larger
```

State classes use prefixes:

```html
<a class="text-muted-foreground hover:text-primary">
```

This means the link is muted by default and primary colored on hover.

## Design Tokens

Colors are defined as CSS variables in `theme/main.css`:

```css
@layer base {
	:root {
		--background: 240 13% 97%;
		--foreground: 222 47% 11%;
		--primary: 221 84% 54%;
		--primary-foreground: 0 0% 100%;
	}
}
```

The values are HSL without the `hsl()` wrapper. For example:

```css
--primary: 221 84% 54%;
```

Tailwind maps these variables in `tailwind.config.js`:

```js
primary: {
	DEFAULT: 'hsl(var(--primary, 221 84% 54%) / <alpha-value>)',
	foreground: 'hsl(var(--primary-foreground, 0 0% 100%) / <alpha-value>)',
},
```

That lets templates use simple classes:

```html
<button class="bg-primary text-primary-foreground">
```

## Changing Colors

For most color changes, edit `theme/main.css`.

Example: change the primary color:

```css
:root {
	--primary: 188 86% 43%;
}
```

Then rebuild:

```bash
npm run build:css
```

Common tokens:

```text
--background              page background
--foreground              default text
--card                    panels and cards
--card-foreground         text inside cards
--primary                 main brand/action color
--primary-foreground      text on primary backgrounds
--secondary               subtle backgrounds
--muted                   quiet surfaces
--muted-foreground        secondary text
--accent                  secondary highlight color
--destructive             errors and destructive actions
--border                  borders
--input                   form borders
--ring                    focus rings
```

Dark mode tokens live under:

```css
.dark {
	--background: 219 38% 10%;
	--foreground: 210 40% 98%;
}
```

## Dark Mode

Clarity uses Tailwind's manual selector strategy:

```js
darkMode: 'selector'
```

Dark mode is enabled when the `dark` class is present on the `<html>` element:

```html
<html class="dark">
```

The headers include a small script before the stylesheet loads. It checks
`localStorage.theme` first, then falls back to the operating system preference.
This avoids a flash of the wrong theme while the page is loading.

The navbar contains a toggle button with `data-theme-toggle`. Clicking it
switches between light and dark mode and stores the choice:

```js
localStorage.theme = 'dark';
localStorage.theme = 'light';
```

For one-off dark-mode styles in templates, use Tailwind's `dark:` prefix:

```html
<div class="bg-white text-slate-900 dark:bg-card dark:text-foreground">
```

For global dark-mode styling, prefer editing the `.dark` tokens in
`theme/main.css`.

## Shared Component Styling

Reusable phpBB-wide styles live in `theme/main.css` under `@layer components`.

Example:

```css
@layer components {
	.button,
	input.button1,
	input.button2 {
		@apply inline-flex min-h-10 items-center justify-center rounded-full px-4 py-2 text-sm font-black;
	}
}
```

Use `@apply` when a class or selector appears across many templates.

Good candidates for `@apply`:

```text
.button
.inputbox
.panel
.post
.pagination
.dropdown
.row-item
```

Prefer template utility classes when the style is specific to one template.

## Template Structure

Clarity uses base templates instead of repeating full header/footer includes:

```text
base.html
base_simple.html
base_ucp.html
base_mcp.html
base_ajax.html
```

Typical page templates extend a base:

```twig
{% extends 'base.html' %}

{% block content %}
	...
{% endblock %}
```

Control panel pages use:

```twig
{% extends 'base_ucp.html' %}

{% block ucp_content %}
	...
{% endblock %}
```

Moderator control panel pages use:

```twig
{% extends 'base_mcp.html' %}

{% block mcp_content %}
	...
{% endblock %}
```

## phpBB Variables And Events

Do not remove phpBB template variables, conditions, loops, includes, or events
unless the PHP side was changed too.

Examples that must be preserved:

```twig
{{ U_VIEW_FORUM }}
{{ FORUM_NAME }}
{% if S_USER_LOGGED_IN %}
{% for topicrow in loops.topicrow %}
{% EVENT viewforum_body_topic_row_before %}
```

Events are extension hooks. Removing them can break extensions.

Includes may be dynamic:

```twig
{% include CAPTCHA_TEMPLATE %}
{% include PROVIDER_TEMPLATE_FILE %}
```

Keep those intact.

## Extending The Style

For a new page or major layout:

1. Pick the right base template.
2. Add semantic markup in the page template.
3. Use Tailwind utility classes for page-specific layout.
4. Move repeated patterns into `@layer components` in `theme/main.css`.
5. Run `npm run build:css`.
6. Test the page in phpBB.

For a new shared control, prefer a small Twig component:

```text
template/components/button.html.twig
template/components/input.html.twig
```

This keeps long utility class strings from being copied everywhere.

## Compatibility Notes

Clarity no longer uses the old prosilver split CSS architecture.

Removed legacy model:

```text
base.css
common.css
colours.css
content.css
forms.css
responsive.css
...
```

Current model:

```text
theme/main.css       source
theme/stylesheet.css generated output
```

Some phpBB class names are still used in templates because phpBB JavaScript,
forms, and extension events need stable hooks. Keeping a class like `post`,
`panel`, or `dropdown` does not mean the style is based on prosilver. Those are
now styled by Tailwind in `theme/main.css`.

## Adding New Tailwind Classes

Tailwind only generates classes it can see in files listed in
`tailwind.config.js`:

```js
content: [
	'./phpBB/styles/clarity/**/*.{html,js,twig}',
],
```

If a class is created dynamically, Tailwind may not generate it.

Avoid this:

```twig
class="bg-{{ color }}-500"
```

Prefer explicit classes:

```twig
{% if type == 'error' %}
	class="bg-destructive text-destructive-foreground"
{% else %}
	class="bg-primary text-primary-foreground"
{% endif %}
```

## Common Tasks

Change the main brand color:

```text
Edit --primary in theme/main.css, then run npm run build:css.
```

Change page width:

```text
Edit max-width/container classes in templates or container settings in tailwind.config.js.
```

Change all buttons:

```text
Edit .button rules in @layer components in theme/main.css.
```

Change forum row cards:

```text
Edit .topiclist .row and .row-item in theme/main.css.
```

Change one specific page:

```text
Edit that page's template utility classes.
```

## Verification Checklist

After changes:

```bash
npm run build:css
```

If preparing production assets:

```bash
npm run build
```

Then check:

```text
- No Tailwind CDN script was added back.
- theme/stylesheet.css was regenerated.
- Template variables and events were preserved.
- Mobile layouts still show important forum stats.
- UCP/MCP pages still render their side navigation.
```

## Troubleshooting

If a new utility class has no effect:

```text
1. Make sure the class is written literally in a scanned file.
2. Check tailwind.config.js content paths.
3. Run npm run build:css again.
4. Clear phpBB/template/browser cache if needed.
```

If colors do not change:

```text
1. Edit theme/main.css, not stylesheet.css.
2. Rebuild with npm run build:css.
3. Confirm the generated stylesheet contains the new token.
```

If generated CSS is overwritten:

```text
That is expected. stylesheet.css is generated. Move manual edits to main.css.
```
