# Modul IS Coding Standard

_Based on Nette Coding Standard_

## How to use

#### Check PHP files

Check coding standard:

```
php ecs check [target directory]
```

And fix it:

```
php ecs check [target directory] --fix
```

Your PHP version is detected automatically but if you want to use fixers for a different version,
you can use the `config` parameter

```
php ecs check [target directory] --config coding-standard-php56.yml --fix
```

#### Check SQL settings

```
php sqltest [server] [username] [password]
```