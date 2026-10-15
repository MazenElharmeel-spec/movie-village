# Movies Village

Movies Village is a small PHP/MySQL catalog. Movies are database records, not individual HTML pages: each gets an automatic numeric ID and a page at `movie.php?id=ID`.

## Run locally

### Docker Desktop

With Docker Desktop running, use `docker compose up --build -d` in this folder, then open `http://localhost:8080`. The included compose setup seeds a local MariaDB database and stores uploaded movie media in a Docker volume. Stop it with `docker compose down` when finished; `docker compose down -v` also deletes the local database and uploaded files.

### PHP and MySQL

1. Install a PHP 8.1+ web server with PDO MySQL, Fileinfo, mbstring, and MySQL/MariaDB (XAMPP is an easy local setup).
2. Copy this folder into the server's document root. Start Apache and MySQL.
3. Import `schema.sql` using phpMyAdmin or the MySQL command line. It creates the `movies_village` database, tables, and the sample catalog.
4. If needed, configure the database using environment variables `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. Defaults are `127.0.0.1`, `3306`, `movies_village`, `root`, and an empty password for a fresh local XAMPP install. Set `APP_BASE_URL` if the site lives in a subfolder, such as `/movies-village`.
5. Make the `uploads` folder writable by the web server. Open `/admin/setup.php` once, create the first administrator, then sign in and choose **Manage → Add movie**.

Use an HTTPS PHP host and a dedicated database user for a public deployment, with PHP error display disabled. PHP's `upload_max_filesize` and `post_max_size` settings also limit movie uploads; raise them on the server when needed. Large libraries are better served from object storage or a video platform, using the external video URL field rather than a huge web-server upload.

## Add a movie

In **Manage → Add movie**, enter the title and optional metadata, choose a poster, and optionally choose a backdrop and video file or paste a video URL. Add cast members by name and upload an optional portrait for each one. Saving publishes the record and opens its generated detail page. The ID is assigned by the database; no page or code edits are needed. Uncheck **Published** to save a draft. Use **Edit** to update it later.

Supported posters, backdrops, and cast portraits: JPG, PNG, WebP (up to 8 MB each). Supported local video uploads: MP4, WebM, MOV (up to 2 GB, subject to PHP/server upload limits). Only add media you have permission to distribute.

The local Jumanji demo video is kept out of GitHub. After setup, an administrator can add a video they have permission to distribute from **Manage → Edit movie**.

## Site features

- Search by title, genre, synopsis, or cast; trending and featured collections are database driven.
- Members can register, sign in, and save/remove favourites.
- Administrators can create, edit, publish, unpublish, and delete movie records, including cast names and portrait photos.
- Uploaded filenames are randomized and served from a non-executable uploads folder. User passwords are hashed; database queries are prepared and forms use CSRF tokens.
