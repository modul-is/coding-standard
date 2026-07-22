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

The checker also scans `.latte` and `.twig` templates for inline `style="..."` HTML attributes and reports them as an error. The `<style nonce="...">` element is allowed and left untouched. When running with `--fix`, the inline `style` attributes are automatically removed.

#### Check SQL settings

```bash
php sqltest [server] [username] [password]
```
