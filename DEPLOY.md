# Deploying

The sites are plain static HTML. No PHP, no database, no build step on the server.

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
