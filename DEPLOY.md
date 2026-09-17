# Deploying

The sites are plain static HTML, with one exception: openassurance.nz carries a self-hosted survey under `/survey/` and a contact form under `/contact/`, which need PHP and a database. Everything else needs no PHP, no database, and no build step on the server.

Hosting is an entry-level cPanel shared-hosting account running Apache or LiteSpeed. Both read the `.htaccess` file included in each build.

Nothing below is specific to a particular hosting company. Where a step names a cPanel screen, any cPanel host has the same one.

## Current configuration

All three sites are live, each with its own document root and its own certificate.

| Domain | Document root | Certificate | Renews |
|---|---|---|---|
| openassurance.nz | the account's primary root | DigiCert RapidSSL, expires **28 March 2027** | **No — manual** |
| opencompetency.nz | its own root | Let's Encrypt, via AutoSSL | Yes, automatically |
| openprequal.nz | its own root | Let's Encrypt, via AutoSSL | Yes, automatically |

**The openassurance.nz certificate does not renew itself.** It was issued by the registrar rather than AutoSSL, and AutoSSL will not replace a third-party certificate while it remains valid. Either let it lapse close to expiry so AutoSSL takes over, or delete it under *SSL/TLS → Manage SSL sites* and run AutoSSL, which brings it onto the same automatic footing as the other two. Doing that deliberately, well before March 2027, is safer than discovering it on the day.

The `/.well-known/` exclusion at the top of every `.htaccess` is what keeps the automatic renewals working. Removing it breaks renewal silently, months later.

## What to upload

Build first:

```
python build.py
```

Each `dist/<domain>/` folder is the complete document root for that domain. Upload its **contents** — not the folder itself — to that domain's document root:

```text
dist/openassurance.nz/    ->  document root for openassurance.nz
dist/opencompetency.nz/   ->  document root for opencompetency.nz
dist/openprequal.nz/      ->  document root for openprequal.nz
```

Each folder contains:

| File | Purpose |
|---|---|
| `index.html` | The entire site |
| `404.html` | Not-found page |
| `.htaccess` | HTTPS and canonical host redirect, security headers, compression |
| `robots.txt` | Crawler permissions |
| `sitemap.xml` | Single-page sitemap |

`.htaccess` begins with a dot, so it is hidden by default. In cPanel File Manager, enable **Settings → Show Hidden Files (dotfiles)** before uploading, or it will be silently skipped.

## Promoting a domain from alias to its own site

A domain added with cPanel's **Share document root** option is an *alias*: it serves the primary site's files and cannot be given a root of its own later. cPanel warns that the setting is permanent, and it means it. Converting one is a remove-and-re-add, not an edit.

To give `opencompetency.nz` or `openprequal.nz` a site of its own:

1. cPanel → **Domains** → remove the domain.
2. Add it again, this time **without** ticking *Share document root*, and note the document root it is given.
3. Upload that domain's `dist/<domain>/` contents to that root, hidden files included.
4. Remove the domain from `HELD_ALIASES` in `build.py`, rebuild, and upload the regenerated `dist/openassurance.nz/.htaccess`.
5. Run **AutoSSL**.

**Do steps 1 to 3 before step 4.** The umbrella `.htaccess` sends any host that is not `openassurance.nz` to the canonical site with a **301**, and that rule is what remains once the temporary 302 block is removed. Upload it while the domain is still an alias and visitors — and search engines — cache a permanent redirect away from a site that is about to exist.

Separate document roots also mean separate vhosts, so each domain can hold its own certificate. That usually removes the obstacle where AutoSSL declines to replace a still-valid third-party certificate shared across aliases.

## HTTPS

**Upload the current `.htaccess` before running AutoSSL.** Certificate validation fetches a file under `/.well-known/` on each domain, and the redirect rules would otherwise send that request to the umbrella site instead of serving the file. Issuance fails, and the error does not mention redirects. The rule excluding `/.well-known/` is first in every `.htaccess` for that reason — leave it there, including at renewal time, or certificates will stop renewing silently.

Enable SSL for all three domains before announcing them. cPanel issues free certificates through **SSL/TLS Status** → AutoSSL, where the host has it enabled.

The `.htaccess` redirects HTTP to HTTPS, so a certificate must exist first or visitors hit a browser warning.

Once HTTPS is confirmed working on a domain, you can uncomment the HSTS line in that site's `.htaccess`:

```apache
# Header always set Strict-Transport-Security "max-age=31536000"
```

**Do not enable HSTS before HTTPS works.** Browsers remember the instruction for the full max-age and will refuse to load the site over HTTP, so a premature HSTS header is difficult to undo — it persists in visitors' browsers even after you remove it from the server.

Do not edit `.htaccess` on the server. Edit `build.py`, rebuild, and upload — a server-side edit is lost on the next deploy.

## DNS

Each domain needs A records pointing at the hosting account's IP address, shown in cPanel under **Shared IP Address**.

A newly registered domain often points at the registrar's parking service instead. Parking pages commonly carry advertising and tracking, and present a certificate for the registrar's own domain rather than yours — so a domain left parked fails HTTPS and serves someone else's content. Check the A records rather than assuming they were set when hosting was attached.

Point both the apex (`openassurance.nz`) and `www` at the host. The `.htaccess` redirects `www` to the apex, but the record has to resolve first for that redirect to run.

## The survey

openassurance.nz carries a short survey at `/survey/`. It is the only part of any of the three sites that needs PHP and a database, and the two profile sites stay strictly static.

It is self-hosted on purpose. A hosted survey service would be a third-party tracker on a site whose argument is that information should not be copied into more places than it needs to be. The survey sets no cookies, stores no IP addresses, and loads nothing from anywhere else.

Requirements: PHP 7.4 or later with PDO and its MySQL driver, which every cPanel host provides, and one MySQL or MariaDB database.

### One-off setup

1. **Create the table.** In cPanel open *phpMyAdmin*, select the survey database, open the *SQL* tab, paste the contents of `survey-setup/schema.sql`, and run it.
2. **Give the database user only what it needs.** In *MySQL Databases*, the user the survey connects as needs `SELECT`, `INSERT`, and `DELETE` on that database and nothing else. It never creates, alters, or drops anything.
3. **Create the configuration file outside the web root.** In File Manager, in your home directory beside `public_html` and not inside it, create a folder named `openassurance-private`. Copy `survey-setup/survey-config.sample.php` into it as `survey-config.php` and fill in the database name, user, and password there, on the server.
4. **Password-protect the admin area.** In cPanel open *Directory Privacy*, browse to `public_html/survey/admin`, tick *Password protect this directory*, and create a user. cPanel keeps the password file in `.htpasswds` in your home directory, outside the web root, and writes a small `.htaccess` into the admin folder.

The real configuration file holds database credentials. It must never be committed: this repository is public. `.gitignore` excludes it by name as a last line of defence.

The survey looks for its configuration one level above the document root, at `openassurance-private/survey-config.php`. If openassurance.nz is ever given a document root that is not directly under the home directory, move the folder so that it still sits beside that root.

### The admin area

`/survey/admin/` shows totals for every question, each response, and a CSV download, and lets a response be deleted.

It is protected by the web server and not by code in this repository. The page checks that the server has authenticated someone and refuses to show anything if it has not, so forgetting step 4 produces a refusal and not an exposure. It deliberately ignores a user name that merely arrives in a request header, because anyone can send one.

When uploading a new build, upload over the top of `survey/admin` and do not delete the folder, or the `.htaccess` cPanel put there is lost and the area refuses everyone until Directory Privacy is set again.

### The optional contact field

The contact field is off until you turn it on. Contact details are personal information, and the Privacy Act 2020 expects whoever collects it to say who is collecting and holding it. The field therefore appears only when `collect_contact` is true and `holder_notice` in the configuration file has been written. With it off, the survey is fully anonymous and none of that arises.

Every respondent is shown a removal code on submitting. Anyone holding the code can delete that response, contact details included, at `/survey/remove.php`, so access to deletion never depends on anyone being reachable by email.

### Changing the questions

The questions live in `src/survey/inc/questions.php`, which drives the form, the validation, and the admin labels. Never name a product, a company, a scheme, or a person in a question. Change `SURVEY_VERSION` whenever a question's meaning changes, so that answers to different questions are never added together.

### Publishing results

Publish totals only. Leave out any answer chosen by fewer than five organisations, which the admin page marks with an asterisk, and never publish a comment or a contact detail.

### Checking a deployment

```text
https://openassurance.nz/survey/              the form, or "not open yet" if the configuration is missing
https://openassurance.nz/survey/admin/        a password prompt
https://openassurance.nz/survey/inc/lib.php   403 Forbidden
```

Submit one test response, confirm it appears in the admin area, and delete it with its removal code.

## The contact form

openassurance.nz also carries a contact form at `/contact/`. The two profile sites link to it and pass a topic, and they carry no form of their own, so there is one place where messages arrive.

It is built on the survey: the same database, the same configuration file, the same protections, and the same admin area. It sets no cookies, stores no IP address, runs no script, and sends nothing to any third party. Your own address appears nowhere on the site and nowhere in this repository.

### Setting it up

1. **Upload the new build.** Upload `dist/openassurance.nz/contact/` and the changed files under `dist/openassurance.nz/survey/`, over the top of what is there. Do not delete `survey/admin`, for the reason given above. The admin pages for messages live inside that folder, so they are already behind its password.
2. **Add the table.** In phpMyAdmin, run `survey-setup/schema.sql` again. It only creates what is missing, so the survey's table and its responses are untouched. Until the table exists the form tells visitors it is not open yet.
3. **Turn on the notification, if you want one.** On the server, edit `openassurance-private/survey-config.php` and add `'notify_email' => 'your address',`. The sample in `survey-setup/survey-config.sample.php` shows every setting. Never put the address in the repository.

The database user needs the same three rights as before, `SELECT`, `INSERT`, and `DELETE`, on the new table as well.

The form posts to `contact/submit.php`. Do not rename it to anything containing `send`: the host's bot filter challenges every URL with `send` in its name by returning a 409 and a small script that sets a cookie, the site's security policy blocks that script, and the visitor sees a blank page. If an earlier upload left a `contact/send.php` on the server, delete it.

### What a sender is asked, and what is kept

A message is the only thing required. The name, organisation, and email fields are optional, and they only appear once `collect_contact` is true and a notice says who holds them, which is `contact_notice` if you fill it in and otherwise the survey's `holder_notice`. Without that notice the form still takes anonymous messages.

Messages are deleted automatically once they are older than `message_retention_days`, which defaults to 365, and the form tells senders the period in months, so change the two together. Every sender is shown a removal code, which deletes the message at `/survey/remove.php`.

### The notification

The notification says that a message has arrived, gives its number and its topic, and links to the admin area. It deliberately carries no part of the message and nothing about the sender, because email leaves the host unencrypted. Read and answer messages from `/survey/admin/messages.php`, which shows the sender's address as a link where one was left.

Shared hosts often refuse or mark as spam any mail whose sender does not exist on the domain. If notices do not arrive, create a mailbox or forwarder such as `no-reply@openassurance.nz` in cPanel and set `notify_from` to it. A notice that fails to send never stops a message being saved.

### Checking a deployment

```text
https://openassurance.nz/contact/                          the form, or "not open yet" until the table exists
https://openassurance.nz/survey/admin/messages.php         a password prompt, then the messages
https://openassurance.nz/survey/admin/messages-export.php  a password prompt, then a CSV file
```

Send one test message, confirm the notice arrives and the message appears in the admin area, and delete it with its removal code.

### Testing a change

`bash survey-setup/test-survey.sh` runs the survey and the contact form end to end against SQLite. Run it after `python build.py` and before uploading anything under `survey/` or `contact/`.

## Verifying a deployment

After uploading, check each site:

- `https://<domain>/` loads and shows the correct accent colour;
- `http://<domain>/` redirects to HTTPS;
- `https://www.<domain>/` redirects to the apex;
- `https://<domain>/nonexistent` shows the 404 page, not the host's default error page;
- the browser console is empty. A Content-Security-Policy violation here would mean something external crept into the page.

The last check matters. The `.htaccess` sets a policy that forbids all external resources, which enforces on the server what `build.py` checks at build time.

## Moving off shared hosting later

Nothing here is specific to this host beyond `.htaccess`. The same `dist/` folders deploy unchanged to Cloudflare Pages, Netlify, or any static host — those platforms ignore `.htaccess` and take equivalent settings from their own configuration, so the redirect and header rules would need restating there.
