# BT Spirit Wear

Boomer T's spirit wear store landing page and lead form, `[bt_spiritwear]`, live at
boomerts.com/spiritwear/. Leads email to orders@boomerts.com (`btsw_lead_email()`,
filterable). Success redirects to `?sw=thanks#get-started`, which is the Meta custom
conversion. The Meta Pixel itself is a separate snippet, not in this plugin.

- Current version: **1.0.0**. Constant `BTSW_VERSION`, function prefix `btsw_`.
- Repo: `strummer95/bt-spiritwear`

## History

Was a Code Snippets PHP snippet through v21. Ported to a plugin at 1.0.0 because Dillon
does not want to edit code by hand. Functions were renamed `bt_spiritwear_*` → `btsw_*`
so the two can never collide. While the old snippet is active, `btsw_snippet_active()`
keeps the plugin idle (otherwise every lead would be emailed twice).

The fundraiser landing page (`[bt_fundraiser]`, `fr_*` fields, `?fr=thanks`) is still a
separate Code Snippets snippet and is NOT in this plugin yet.

## Hard-won rules

- Form fields are namespaced `sw_*`. A field named `name` collided with WordPress's
  post-slug query var and 404'd every submit. `btsw_strip_post_query_vars()` guards the
  whole class of bug. Never add an un-namespaced field.
- Read every `$_POST` value through `wp_unslash()`. WordPress slashes all POST data.
- The redirect is sent and the connection closed before `wp_mail()` runs.

## Environment

Boomer T's is a separate company from Duck and Rabbit Co. (Dillon's dad's shop). AWS
Lightsail Bitnami WordPress + Elementor, NOT IONOS. Dillon works only through the
WordPress dashboard. No SSH, no SFTP. Everything ships as a plugin update.

## Release process

Four places must match or WordPress loops forever: `Version:` header, `BTSW_VERSION`,
`manifest.json` version, and the version inside the zip. Build `bt-spiritwear-X.Y.Z.zip`
plus plain `bt-spiritwear.zip` at the repo root, point `download_url` at the versioned raw
URL, commit and push to `main`. Dillon then does BT Spirit Wear → Check for updates, then
Plugins → Update Now.

`includes/bt-admin.php` is the shared BT Admin Kit and must stay byte-identical to the
copy in the other BT plugins. Do not fork it.
