# Sherin's Boutique

The boutique is served from `index.php`. It requires PHP with the PDO MySQL extension enabled and MySQL/MariaDB running.

## Run locally with XAMPP

Open PowerShell and start PHP's development server:

```powershell
$env:SHERIN_ADMIN_PASSWORD = 'replace-this-with-a-long-unique-password'
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t 'C:\xampp\htdocs\test'
```

Open `http://127.0.0.1:8000/index.php` in a browser. Keep the terminal open while using the site. The admin password is read from the PHP server environment and is not stored in the source code.

Use `http://127.0.0.1:8000/index.php?admin=1` to sign in and view customer enquiries. Set `SHERIN_ADMIN_PASSWORD` for the PHP server before opening the admin page.

The MySQL connection uses host `localhost`, database `sherins_boutique`, username `root`, and a blank password. Start MySQL in the XAMPP Control Panel, then import [`database.sql`](database.sql) in phpMyAdmin (or run it with a MySQL client) to create the database and table. The application also creates the table automatically after the database exists. Customer details are stored only after the customer agrees in the chat.

The database schema is available in [`database.sql`](database.sql).

The previous SQLite file, `C:\xampp\htdocs\sherins-boutique.sqlite`, is left untouched; its records are not copied automatically into MySQL.

The chat is a guided assistant and does not use an external AI service or send customer details to an AI provider.
