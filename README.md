# Modul IS Coding Standard

_Based on Nette Coding Standard_

This is a set of [sniffs](https://github.com/PHPCSStandards/PHP_CodeSniffer) and [fixers](https://github.com/FriendsOfPHP/PHP-CS-Fixer) that **checks and fixes** code of Nette Framework against [Coding Standard in Documentation](https://nette.org/en/coding-standard) with some extra Modul IS fixers and tweaks.

## How to use

#### Check PHP files

Check coding standard for PHP 8.4 in directory `src`:

```bash
php ecs check src --preset php84
```

And fix it:

```bash
php ecs check src --preset php84 --fix
```

If no PHP version is specified, it will try to detect it automatically from the `composer.json` file.

#### Inline styles

The checker also scans `.latte` and `.twig` templates for inline `style="..."` HTML attributes and reports them as an error – a `style` attribute cannot be allowed by a CSP nonce, a nonce only works on the `<style>` element. The `<style nonce="...">` element is left untouched.

No generic classes are generated. With `--fix` a style is replaced only when every declaration has an exact Bootstrap 5 or core utility class:

```latte
<div class="box" style="width: 100%; margin-top: 1rem">
<a style="cursor: pointer" n:class="$active ? active">
```

```latte
<div class="box w-100 mt-3">
<a n:class="$active ? active, 'cursor-pointer'">
```

| Declaration | Class |
|---|---|
| `width`/`height`: `25%`, `50%`, `75%`, `100%`, `auto`, `100vw`/`100vh` | `w-*`, `h-*`, `vw-100`, `vh-100` |
| `max-width`/`max-height: 100%`, `min-width: 100vw`/`min-height: 100vh` | `mw-100`, `mh-100`, `min-vw-100`, `min-vh-100` |
| `margin*`, `padding*`, `gap`, `row-gap`, `column-gap`: `0`, `.25rem`, `.5rem`, `1rem`, `1.5rem`, `3rem` (or the same in `px`), `auto` for margin | `m-*`, `mt-*`, `ms-*`, `mx-*`, `p-*`, `gap-*`, … |
| `text-align`, `white-space`, `text-transform`, `text-decoration`, `word-wrap: break-word` | `text-start`, `text-center`, `text-end`, `text-nowrap`, `text-uppercase`, `text-decoration-none`, `text-break`, … |
| `font-weight`, `font-style`, `line-height` | `fw-*`, `fst-*`, `lh-*` |
| `vertical-align`, `float`, `position`, `top`/`bottom`/`left`/`right`: `0`, `50%`, `100%` | `align-*`, `float-start`/`float-end`, `position-*`, `top-*`, `start-*`, … |
| `overflow`, `visibility`, `opacity`, `pointer-events`, `user-select`, `object-fit` | `overflow-*`, `visible`/`invisible`, `opacity-*`, `pe-*`, `user-select-*`, `object-fit-*` |
| `flex-direction`, `flex-wrap`, `justify-content`, `align-items` | `flex-row`, `flex-column`, `flex-wrap`, `justify-content-*`, `align-items-*` |
| `cursor: pointer` | `cursor-pointer` (core) |

`display` is never replaced automatically – `d-*` classes are `!important`, so jQuery `.show()`/`.hide()` could not override them anymore. The class is merged into an existing `class` or `n:class` attribute; a style wrapped in a condition inside the tag gets its class in the same branch:

```latte
<a href="{$link}" {if $isDisabled}class="pe-none" style="text-align: center"{/if}>
```

```latte
<a href="{$link}" {if $isDisabled}class="pe-none text-center"{/if}>
```

Everything else is listed for a manual fix with a hint – a value without an exact spacer (`margin-top: 20px`), `display: none` (use the `hidden` attribute and toggle it in JS by `.prop('hidden', …)`), a dynamic value (`style="width: {$width}%"`), a style whose element already has a competing utility class (`class="mt-2" style="margin-top: 0"`) or anything else – use a utility class or a named class of the element in the project stylesheet.

E-mail and PDF/print templates are skipped by default: they are not rendered by a browser, so no CSP applies to them and a class from an external stylesheet would never reach them – inline styles are the correct solution there. The default globs match a directory named `Mail` or `Print` at any depth (`FooModule/Mail/`, `FooModule/templates/Print/`), so a whole `MailModule`/`PrintModule` – which also holds regular browser rendered templates – is not skipped. The match is case insensitive.

| Option | Default | Meaning |
|---|---|---|
| `--inline-exclude <globs>` | `*/Mail/*,*/Print/*` | Comma separated globs of templates that are left untouched |

#### Check SQL settings

```bash
php sqltest [server] [username] [password]
```
