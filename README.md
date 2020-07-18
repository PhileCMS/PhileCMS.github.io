### Setup ###

```
git clone https://github.com/PhileCMS/PhileCMS.github.io.git;
cd PhileCMS.github.io/;
git submodule init;
composer install;
```

Run the page with e.g.

```
php -S localhost:8080
```

### Build ###

```
vendor/bin/phulp
```

### Release ###

Commit changes and push to origin.
