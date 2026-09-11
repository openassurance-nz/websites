# Deploying

The sites are plain static HTML. No PHP, no database, no build step on the server.

Hosting is an entry-level cPanel shared-hosting account running Apache or LiteSpeed. Both read the `.htaccess` file included in each build.

Nothing below is specific to a particular hosting company. Where a step names a cPanel screen, any cPanel host has the same one.

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

## Holding the profile domains on the umbrella site

While `opencompetency.nz` and `openprequal.nz` do not have their own sites yet, they can be pointed at the umbrella site at no extra hosting cost.

DNS alone cannot do this. DNS maps a name to an IP address and has no concept of URLs or redirects, so pointing those domains at the hosting IP without configuring them just lands visitors on the server's default page.

Use a cPanel **Alias** (called *Parked Domain* on older versions) instead of an Addon Domain:

1. cPanel → **Aliases** → add `opencompetency.nz` and `openprequal.nz`.
2. Point each domain's `A` records, apex and `www`, at the hosting IP.
3. Run **AutoSSL** so the certificate covers all three names.

An alias serves the primary site's document root, so the redirect rule already present in `dist/openassurance.nz/.htaccess` takes over: requests arriving with an alias `Host` header get a **302** to `https://openassurance.nz/#profiles`.

Aliases are usually unmetered on plans that limit addon domains, which is what makes this free. Check your plan if unsure.

The redirect is a 302, never a 301. A permanent redirect is cached hard by browsers and search engines, and these domains are going to become sites of their own — a cached 301 would keep sending visitors away from them long after they existed.

### Promoting a profile site later

1. Remove the domain from **Aliases**, add it as an **Addon Domain** with its own document root.
2. Delete that domain from `HELD_ALIASES` in `build.py` and rebuild, which removes the temporary block from the umbrella `.htaccess`.
3. Upload that site's `dist/<domain>/` contents to its new document root, and the rebuilt umbrella `.htaccess`.
4. Re-run AutoSSL.

The `holding/<domain>/` folders are an alternative for hosts without alias support: a document root containing only a redirecting `.htaccess` and a meta-refresh fallback page.

## Setting up the domains in cPanel

The primary domain of the hosting account uses `public_html/` as its document root. The other two are added as **Addon Domains**, each of which gets its own folder, typically `public_html/<domain>/`.

Two things to check on an Economy plan before relying on this:

1. **How many addon domains the plan allows.** If it permits only one website, the other two domains will need either their own hosting, or a redirect until the plan is upgraded.
2. **That an addon domain's document root is not nested inside another site's.** cPanel defaults to `public_html/<domain>/`, which means the addon site is also reachable at `primarydomain.nz/<domain>/`. That produces duplicate content at two URLs. The `.htaccess` canonical redirect handles it — requests to the wrong host are sent to the right one — but a document root outside `public_html/` is cleaner if the plan allows it.

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
