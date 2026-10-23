# Running your own FrogFind on Debian

This guide sets up a local copy of FrogFind on Debian 13 (trixie). It also works on
Debian 12 (bookworm) and on Ubuntu with the same package names.

FrogFind is five PHP files with one Composer dependency. There is no database and no
build step. The server needs:

- PHP 8.0 or newer, with the `dom`, `xml`, `mbstring` and `gd` extensions
- Outbound internet access (it fetches search results from DuckDuckGo, and fetches
  pages and images on behalf of the vintage browser)
- A web server that serves it **at the root of a site over plain HTTP**

## 1. Install the packages

```sh
sudo apt update
sudo apt install git composer php-cli php-xml php-mbstring php-gd php-intl
```

| Package       | Why                                                               |
|---------------|-------------------------------------------------------------------|
| `php-xml`     | `dom` and `xml` extensions, required by the Readability library   |
| `php-mbstring`| Required by the Readability library                               |
| `php-gd`      | Shrinks images for old machines (`image_compressed.php`)          |
| `php-intl`    | Optional; better hostname handling in the URL library             |
| `composer`    | Installs the PHP dependency into `vendor/`                        |

Check the PHP version (must be 8.0+):

```sh
php -v
```

## 2. Get the code

Install it in `/var/www/frogfind`, owned by your normal user so you can update it
without `sudo`:

```sh
sudo mkdir -p /var/www/frogfind
sudo chown "$USER": /var/www/frogfind
git clone https://github.com/dleecurt/FrogFind.git /var/www/frogfind
cd /var/www/frogfind
composer install --no-dev
```

`composer install` creates the `vendor/` directory. FrogFind will not run without it.
Run Composer as your normal user, not as root.

## 3. Try it with PHP's built-in server

Before setting up Apache, check that everything works:

```sh
cd /var/www/frogfind
php -S 0.0.0.0:8080 -t .
```

Open `http://<server-ip>:8080/` in a browser and run a search. Press Ctrl+C to stop.

The built-in server is fine for testing or occasional use but handles one request at
a time. For a permanent setup, use Apache.

## 4. Serve it permanently with Apache

```sh
sudo apt install apache2 libapache2-mod-php
```

Installing `libapache2-mod-php` enables PHP in Apache automatically.

Replace the contents of `/etc/apache2/sites-available/000-default.conf` with:

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/frogfind
    DirectoryIndex index.php

    <Directory /var/www/frogfind>
        Require all granted
        AllowOverride None
    </Directory>

    # Composer files and dependencies don't need to be reachable from the web
    <LocationMatch "^/(vendor|\.git)(/|$)">
        Require all denied
    </LocationMatch>
    <FilesMatch "^composer\.(json|lock)$">
        Require all denied
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/frogfind-error.log
    CustomLog ${APACHE_LOG_DIR}/frogfind-access.log combined
</VirtualHost>
```

Then check the config and reload:

```sh
sudo apache2ctl configtest
sudo systemctl reload apache2
```

FrogFind is now at `http://<server-ip>/`.

### Why these settings matter for vintage computers

- **Serve it at the site root.** Every link in FrogFind is absolute (`/read.php`,
  `action="/"`), so it breaks if it's installed in a subdirectory like
  `http://server/frogfind/`.
- **Plain HTTP only.** Don't enable HTTPS or add an HTTP-to-HTTPS redirect. Old
  browsers can't negotiate modern TLS and will just fail to connect.
- **Make it the default site.** Very old HTTP/1.0 browsers don't send a `Host:`
  header, so FrogFind has to answer on the bare IP address. Using `000-default.conf`
  takes care of that.

## 5. Check the PHP settings

FrogFind fetches everything with PHP's built-in URL functions, which need
`allow_url_fopen` turned on. It's on by default in Debian; confirm with:

```sh
php -i | grep allow_url_fopen
```

Apache has its own copy of `php.ini` at `/etc/php/<version>/apache2/php.ini`. If you
changed the setting there, set it back to `On`.

## 6. Keep it on your local network

`read.php` and `image_compressed.php` fetch **any** URL they are given, including
addresses inside your own network. Exposed to the internet, FrogFind is an open proxy
that strangers can use to reach your LAN.

Keep port 80 closed at your router. If the server has a firewall, allow only your LAN.
For example, with `ufw` and a `192.168.1.0/24` network:

```sh
sudo apt install ufw
sudo ufw allow from 192.168.1.0/24 to any port 80 proto tcp
sudo ufw allow OpenSSH
sudo ufw enable
```

## 7. Updating

```sh
cd /var/www/frogfind
git pull
composer install --no-dev
```

No Apache restart is needed.

## Troubleshooting

**Blank page or HTTP 500.** Check the error log:

```sh
sudo tail -n 50 /var/log/apache2/frogfind-error.log
```

A message about `vendor/autoload.php` means `composer install` wasn't run in
`/var/www/frogfind`. A message like `Call to undefined function imagecreatefromjpeg`
means `php-gd` is missing; install it and run `sudo systemctl reload apache2`.

**"Failed to get results" or "No results".** The server couldn't get usable results
from DuckDuckGo. Check that the server can reach it:

```sh
curl -sI https://html.duckduckgo.com/html?q=test
```

If that works but FrogFind still shows no results, DuckDuckGo is either rate-limiting
your IP address or has changed its results page, which FrogFind parses directly.

**Pages show "server did not return expected details".** Some sites refuse the `HEAD`
request FrogFind uses to check the content type. FrogFind still tries to load the page
after showing this message.

**Images don't appear.** Only JPEG and PNG images are supported, and only when the
image URL ends in `.jpg`, `.jpeg` or `.png`.

**The vintage machine can't connect at all.** Confirm you are using `http://`, the
server's IP address rather than its hostname (old machines often have no working DNS),
and that the firewall allows the vintage machine's address.
