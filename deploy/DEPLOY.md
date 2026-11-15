# Deploying to cPanel

This kit installs and updates the app on shared cPanel hosting, with the
code on GitHub and pulled by the server.

## Once, on a new account

1. **PHP.** cPanel → Select PHP Version (or MultiPHP Manager): PHP 8.2 or
   newer, with intl, mbstring, mysqli, curl, dom, openssl and fileinfo ticked.
2. **Database.** cPanel → MySQL Databases: create a database and a user, give
   the user all privileges on it.
3. **Code.** cPanel → Git Version Control → Create: clone the repository into
   a folder *outside* `public_html`, e.g. `~/app`.
4. **Web root.** Point the domain (or subdomain) at the `public/` folder of the
   clone: cPanel → Domains → Manage → Document Root `app/public`.
5. **Settings.** In Terminal (or File Manager):

   ```bash
   cd ~/app
   cp .env.production.example .env && chmod 600 .env
   nano .env                       # fill in baseURL, database and email
   php spark key:generate          # once; keep a copy of the key offline
   ```

6. **First deploy.** `bash deploy/cpanel-deploy.sh`
7. **Owner account.**

   ```bash
   php spark auth:user:create --email you@example.com --generate --role superadmin --verified
   ```

8. **Multi-tenant only.** Add a wildcard DNS record `*.app.example.com` and a
   wildcard SSL certificate (cPanel → SSL/TLS Status covers subdomains you add
   as domains; AutoSSL issues wildcards only with DNS validation), set
   `platform.baseDomain` and `cookie.domain` in `.env`, then create the first
   workspace: `php spark tenants:create acme --name="Acme Ltd" --owner=jane@acme.co.ug`.

## Every update

Push to GitHub, then cPanel → Git Version Control → Manage → Pull or Deploy:
**Update from Remote**, then **Deploy HEAD Commit**. That runs
`deploy/cpanel-deploy.sh` through `.cpanel.yml`, which:

- installs exactly the packages in `composer.lock` (commit it!),
- backs up the database to `~/backups/<folder>/` (keeps 15),
- runs `php spark platform:migrate`,
- publishes the platform's CSS and JS (`php spark platform:publish`),
- syncs roles and permissions (`php spark auth:sync`),
- clears caches and ends with `php spark deploy:check`.

Every run is logged to `writable/logs/deploy-*.log`.

## Choosing modules per server

For one install per client, list what this client has in `.env` (read at boot):

```ini
platform.modulesOnly = Crm, Sales, Finance
```

Optional modules can then be switched on and off without a deploy under
Administration → Modules, or `php spark modules:on Payroll`.
