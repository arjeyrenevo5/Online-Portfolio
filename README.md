# Arjey Renevo Portfolio

This starter portfolio keeps its files separate: `index.html` contains the page, `styles.css` contains its design, `script.js` powers the feedback interface, and `feedback.php` provides the MySQL CRUD API.

## Run with XAMPP

1. Start Apache and MySQL in XAMPP.
2. Import `setup.sql` using phpMyAdmin (`http://localhost/phpmyadmin`).
3. Put this folder under XAMPP's `htdocs` directory.
4. Open `http://localhost/arjey-renevo/`.

For the PHP built-in server, run `php -S localhost:8000` from this folder and open `http://localhost:8000/`.

The default database connection is for a local XAMPP setup: MySQL at `127.0.0.1`, database `portfolio_feedback`, user `root`, empty password. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables for other credentials. Use a dedicated database user and password for a public server. GitHub Pages does not run PHP.

Ratings can be created, changed, and removed. Comments can be created, viewed, edited, and deleted by the visitor session that created them. The Projects section links to the Net Learn networking education platform in the `net-learn/` subfolder. Import `net-learn/setup.sql` into MySQL/MariaDB and configure its database credentials before opening the subsite.


