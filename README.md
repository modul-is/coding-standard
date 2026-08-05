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

The checker also scans `.latte` and `.twig` templates for inline `style="..."` HTML attributes and reports them as an error. A `style` attribute cannot be allowed by a CSP nonce – a nonce only works on the `<style>` element – so with `--fix` the declarations are moved into a generated stylesheet and the element gets a hash based CSS class instead:

```latte
<div class="box" style="margin-top:10px; display:none">
```

```latte
<div class="box is-cc136c">
```

```css
/* www/assets/css/core/project.css */
.is-cc136c {
	margin-top: 10px;
	display: none;
}
```

The rules are appended to the project stylesheet (`www/assets/css/core/project.css`), which is versioned and already bundled – its current content is never rewritten, only new rules are added below a `/* Styles extracted from inline style attributes by modul-is/cs. */` marker. Identical declarations always share one class and the class is merged into an existing `class` or `n:class` attribute.

Styles containing a Latte or Twig expression (e.g. `style="width: {$width}%"`) cannot be turned into a static rule – those are listed for a manual fix and are never removed. The `<style nonce="...">` element is left untouched.

A style attribute wrapped in a condition inside the tag is fixed too – the class is placed into the same branch, so it stays conditional:

```latte
<a href="{$link}" {if $isDisabled}class="pe-none" style="opacity: 0.6"{/if}>
```

```latte
<a href="{$link}" {if $isDisabled}class="pe-none is-f411fb"{/if}>
```

When the branch has no `class` or `n:class` to merge into, one is opened in it. That is only possible when the element has no other class attribute – otherwise the element would end up with two of them and the style is reported for a manual fix instead.

E-mail and PDF/print templates are skipped by default: they are not rendered by a browser, so no CSP applies to them and an external stylesheet would never reach them – inline styles are the correct solution there. The default globs match a directory named `Mail` or `Print` at any depth (`FooModule/Mail/`, `FooModule/templates/Print/`), so a whole `MailModule`/`PrintModule` – which also holds regular browser rendered templates – is not skipped. The match is case insensitive.

| Option | Default | Meaning |
|---|---|---|
| `--inline-css <path>` | `www/assets/css/core/project.css` | Stylesheet the extracted styles are appended to (relative to the project root) |
| `--inline-class-prefix <prefix>` | `is-` | Prefix of the generated CSS classes |
| `--inline-exclude <globs>` | `*/Mail/*,*/Print/*` | Comma separated globs of templates that are left untouched |

#### Check SQL settings

```bash
php sqltest [server] [username] [password]
```
