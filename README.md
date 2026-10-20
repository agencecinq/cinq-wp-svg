# CINQ SVG

WordPress plugin that lets users who can post unfiltered HTML upload SVG files, and gives them dimensions. Files are not sanitized. No settings screen.

The [SVG Support](https://wordpress.org/plugins/svg-support/) plugin allows SVG uploads, sanitizes them, and can inline SVG images on the front end with JavaScript. A theme that renders its own templates (Timber, an icon sprite) only needs the upload, and an SVG from the media library to behave like an image. This plugin replaces SVG Support for that.

**Repository:** [`agencecinq/cinq-wp-svg`](https://github.com/agencecinq/cinq-wp-svg)

## Requirements

- WordPress 6.0+
- PHP 8.1+

## Lint (WordPress Coding Standards)

```bash
composer install
composer lint          # phpcs
composer lint:fix     # phpcbf (auto-fix)
```

Use `./vendor/bin/phpcs`, not the global `phpcs` binary — the global install does not register the WordPress standards.

## Install

Copy the plugin file into `mu-plugins`. WordPress loads it on every request, and it cannot be deactivated from the admin. It is listed under **Plugins → Must-Use**, not with the regular plugins.

```bash
cp cinq-wp-svg.php /path/to/wp-content/mu-plugins/
```

Replace that file when the plugin changes.

To install it as a regular plugin, copy the folder to `wp-content/plugins/cinq-wp-svg` and activate **CINQ SVG** in the WordPress admin.

### Replacing SVG Support

Delete SVG Support from **Plugins**, rather than only deactivating it: deleting runs its `uninstall.php`, which removes its `bodhi_svgs_settings` option. SVG files already in the media library keep working.

## Translations

French is included in [`languages/`](languages). The plugin does not load translations itself: WordPress loads them from its languages folder, which also works when the plugin is a must-use plugin.

Copy both French files to `wp-content/languages/plugins/`:

- `cinq-wp-svg-fr_FR.l10n.php`, read by WordPress 6.5 and later;
- `cinq-wp-svg-fr_FR.mo`, for earlier versions.

To update them after changing a string:

```bash
wp i18n make-pot . languages/cinq-wp-svg.pot --exclude=vendor
msgmerge --update --backup=none languages/cinq-wp-svg-fr_FR.po languages/cinq-wp-svg.pot
# Translate the new strings in the .po file, then:
msgfmt -o languages/cinq-wp-svg-fr_FR.mo languages/cinq-wp-svg-fr_FR.po
wp i18n make-php languages
```

`wp i18n make-php` needs WP-CLI 2.8 or later.

## Behaviour

### Who can upload SVG

Users with the `unfiltered_html` capability: administrators and editors on a single site, super admins only on a multisite. When `DISALLOW_UNFILTERED_HTML` is true, nobody has it, and nobody can upload SVG.

An SVG can hold scripts. A script runs when the file is opened in a browser, not when it is shown with `<img>`. The plugin does not sanitize the file: it leaves SVG to the users WordPress already trusts with raw HTML, and warns them under the upload area, on the **Media → Add New Media File** screen and in the media modal:

> SVG files are uploaded as they are, without sanitization: a script inside one runs when the file is opened in a browser. Only upload SVG files from a source you trust.

The warning is printed whenever the current user may upload SVG, so it also follows a theme or plugin that allows SVG to more users.

### Hooks

| Hook | What it does |
| --- | --- |
| `upload_mimes` | Adds `svg` → `image/svg+xml` for users who can post unfiltered HTML |
| `wp_generate_attachment_metadata` | Stores `width` and `height`, read from the `viewBox`, then from the `width` and `height` attributes, as in Timber |
| `image_downsize` | Returns the SVG URL and its dimensions at every image size |
| `post-upload-ui` | Prints the warning under the upload area |

WordPress does not count SVG as an image: without `image_downsize`, `wp_get_attachment_image_src()` returns false for an SVG, and so does Timber's `image.src( size )`. With it, `wp_get_attachment_image()`, `image.src( size )` and the media modal get the file and its dimensions, at every size.

WordPress checks the file content against its extension with `fileinfo`. The plugin relies on `fileinfo` reporting `image/svg+xml`, which it does for an SVG with or without an XML declaration.

## Undo one part

Every hook is a named function, so the theme can remove one of them:

```php
// Hide the warning.
remove_action( 'post-upload-ui', 'cinq_wp_svg_upload_notice' );

// Allow SVG to authors too.
add_filter( 'upload_mimes', fn ( $mimes ) => current_user_can( 'upload_files' ) ? $mimes + array( 'svg' => 'image/svg+xml' ) : $mimes, 11 );
```

## Scope

- No sanitization, no options, no admin UI, no front-end assets
- No inline SVG: a theme that needs the markup reads the file itself
- The warning is not shown when a file is dropped into the block editor

## License

MIT © [CINQ](https://agencecinq.com)
