# UserSpice 5.x API cheatsheet (this install)

## Tables (unprefixed)
- `users` — `id, fname, lname, username, email, permissions (legacy), active, ...`
- `permissions` — `id, name, descrip`. Seeded: 1=User, 2=Administrator. We added 3=Caregiver.
- `user_permission_matches` — `user_id, permission_id`

## Globals available after `require_once 'users/init.php'`
- `$db` — DB instance, used as `$db->query(sql, [params])->results() | ->first() | ->count()` and `$db->lastId()`.
- `$user` — current User object. `$user->isLoggedIn()`, `$user->data()->id`, `$user->data()->username`, etc.
- `$abs_us_root`, `$us_url_root` — filesystem and URL paths to the UserSpice root.
- `$settings` — site settings object.

## Auth/permission helpers
- `hasPerm([1,2,...])` — does current user (or `$id` 2nd arg) have ANY of these permission IDs.
- `securePage($uri)` — gate a page by its row in the `pages` table.
- `Redirect::to($url)` — header redirect.

## Standard page boilerplate
```php
require_once 'users/init.php';                            // or '../users/init.php'
// ... any auth checks ...
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>
<main>...</main>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
```

## Email
```php
email($to, $subject, $body_html, $opts = [], $attachment = null);
// $opts: email, name, cc, bcc, replyTo
```
SMTP config in `email` table; `from_email`, `from_name`, `smtp_server`, etc.

## DB usage examples
```php
$row  = $db->query('SELECT * FROM cg_shifts WHERE id = ?', [$id])->first();
$rows = $db->query('SELECT * FROM cg_caregivers WHERE active = 1 ORDER BY name')->results();
$db->query('INSERT INTO cg_shifts (...) VALUES (?,?,?)', [...]);
$id   = $db->lastId();
```
