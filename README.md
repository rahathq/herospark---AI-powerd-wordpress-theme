# HeroSpark

A simple classic WordPress theme for Assignment 2: AI-Assisted Custom Theme Builder. Administrators enter a project brief in Content Studio, generate a hero heading and paragraph with Gemini, and see the saved result on the homepage.

## Requirements

- WordPress 6.0 or newer and PHP 7.4 or newer.
- Administrator access to install the theme and manage its settings.
- Your own Gemini API key and outbound HTTPS access from your WordPress server.
- JavaScript enabled in the admin browser for generation. Manual editing works without JavaScript.

## Install or update

1. In WordPress, open **Appearance → Themes → Add New Theme → Upload Theme**.
2. Upload `herospark.zip`, click **Install Now**, then **Activate**. When updating, choose **Replace current with uploaded**. Back up any custom theme-file edits first.
3. Visit your homepage. The default hero appears immediately; no manual page creation is needed.
4. Open **Appearance → Content Studio** to configure the content.

Alternatively, copy the theme folder into `wp-content/themes/herospark/` and activate it. The ZIP contains one `herospark/` directory with the theme files directly inside it. Existing saved content and API credentials remain in WordPress when updating.

`front-page.php` supplies the homepage whether Settings → Reading uses latest posts or a static homepage. With a static page selected, the hero replaces that page's editor content. The site name comes from **Settings → General → Site Title**. Activation does not create pages. Old Elementor templates and global styles may still affect an existing development site; a clean WordPress install is recommended for the assignment demo.

## Generate homepage content

1. Create and copy your own key in [Google AI Studio](https://aistudio.google.com/apikey).
2. In Content Studio's **AI connection** card, paste the complete key and click **Save connection**.
3. Enter a brief describing your business, audience, services, and tone (up to 4000 characters).
4. Click **Generate with Gemini**. Your current brief is used without a separate save step. Wait for the success message.
5. The returned title and paragraph are saved automatically and populated in the editor. Click **View homepage** to see them.
6. To refine the text manually, edit the fields and click **Save homepage content**.

Example brief: “A neighbourhood coffee shop serving organic coffee and fresh pastries. Audience: local families and remote workers. Tone: warm and welcoming.”

Generation replaces the saved hero copy on success. A failed request preserves saved content and keeps the current editor fields available. Requests are limited to one attempt per 30 seconds. If an error appears after a slow or interrupted connection, reload Content Studio to check whether the server saved a result before trying again. Clear page/plugin/CDN caches if the homepage still displays old text.

## Content history and restore

Content Studio keeps the **20 most recent previous versions** in a separate `herospark_content_history` WordPress option with autoload disabled. Each version contains only the project brief, heading, paragraph, an ID, and the time it was archived. API credentials are never copied into history.

- A changed manual save or successful AI generation archives the previous content before replacing it. Saving unchanged content does not create a duplicate.
- Open **Content history** below the editor, expand a version to review its full text, then click **Restore this version**.
- Restore publishes that version immediately and reloads the editor. It also archives the current saved version, so you can undo the restore using history.
- Restoring replaces all three fields, including the brief, and replaces unsaved editor edits. It makes no Gemini request and does not require an API key.
- Archive times use the WordPress site's configured timezone and date/time formats. They describe when a version was replaced, not its original creation time.
- History begins with this update. Existing text is preserved when it is next changed, but previously overwritten text cannot be recovered retroactively.
- Older entries are removed once the 20-version limit is reached. History is stored in the database, not in the downloadable theme ZIP.
- Restore requires administrator permissions and a version-specific nonce. If another save occurred after you opened the page, the restore is rejected so you can review the current text first.
- If a previous version cannot be archived, the content change is blocked rather than overwriting it without history.

## API key storage

Keys are stored separately from homepage content in the `herospark_gemini_api_key` WordPress option, with autoload disabled. Leave the field blank to keep the existing key, enter a new value to replace it, or select **Remove saved key** and save to delete it. The key is never filled back into the input or exposed to frontend JavaScript, public HTML, or redirect URLs.

Only users with `manage_options` can change the key or generate content. Both operations check permissions and nonces. The database value is not encrypted; protect database access and backups. Never commit real keys or `wp-config.php` to GitHub. Saving a key alone does not establish whether Google accepts it; successful generation confirms that the request worked.

Developers can optionally define `HEROSPARK_GEMINI_API_KEY` in the site's existing `wp-config.php`. A nonempty constant takes precedence over the database value. `wp-config.example.php` is a reference only and is not executed. Do not replace your real WordPress configuration with it.

## AI integration notes

- Default model: `gemini-3.5-flash-lite`.
- Endpoint: `POST https://generativelanguage.googleapis.com/v1beta/interactions` through WordPress `wp_remote_post()`.
- Authentication: the server sends `x-goog-api-key` in the request header. The key is never put in a URL or sent to the browser.
- The theme adds instructions asking for a heading of at most 10 words and a 30–50-word paragraph. The brief supplies business context, language, and tone.
- Structured output requires a JSON object with exactly two strings: `hero_title` and `paragraph`. The theme checks the completed response, rejects missing/invalid/oversized fields, sanitizes the text, and saves both together in `herospark_content`.
- Maximum accepted output sizes are 160 characters for the title and 1000 for the paragraph. Word counts are prompt targets rather than a language-specific hard validator.
- No API requests run on public homepage visits. One click sends one request; there are no automatic retries. The request has a 40-second timeout and a bounded response size.
- Missing keys, invalid briefs, authentication failures, quota limits, unavailable models, connection errors, and invalid responses produce a message without exposing raw provider responses or credentials.
- Google receives your brief when you click Generate. The request uses `store: false` to avoid storing an interaction for conversation continuation; Google's service terms and data-use policies still apply.
- Google currently lists a free tier for this model, subject to account eligibility and quotas. Paid projects can incur charges according to their billing settings. The theme does not promise unlimited free usage.

The default model can be changed by a developer with the `herospark_gemini_model` filter to a compatible Gemini text model. Availability and pricing can change; confirm access in AI Studio.

Official references: [model information](https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite), [pricing](https://ai.google.dev/gemini-api/docs/pricing), [structured outputs](https://ai.google.dev/gemini-api/docs/structured-output), [Interactions API](https://ai.google.dev/api/interactions-api), and [API key setup](https://ai.google.dev/gemini-api/docs/api-key).

## Theme files

```text
herospark/
├── index.php
├── style.css
├── functions.php
├── front-page.php
├── header.php
├── footer.php
├── inc/
│   ├── content-studio.php
│   ├── gemini.php
│   └── history.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
├── screenshot.png
├── wp-config.example.php
├── preview-image-notes.md
├── README.md
└── .gitignore
```

`screenshot.png` is the bundled 1200 × 900 AI-generated theme preview. The homepage artwork is built with CSS. No external fonts, page builder, or build tools are required. Content Studio's CSS and JavaScript load only on its admin screen.

## Validation and assessment demo

PHP syntax and isolated tests cover generation success, response validation, failed requests preserving content, permission/nonce checks, key handling, history retention, and restoring previous versions. JavaScript checks cover the editor's request and result handling. These do not establish that a particular Google key/project has quota or model access.

Before submitting, test the complete workflow with your own key in WordPress:

1. Activate the theme and confirm the default homepage.
2. Save a key and a project brief, generate, then reload the homepage and settings to verify persistence.
3. Edit the generated text manually and save.
4. Confirm missing or invalid credentials and quota errors do not erase existing content.
5. Check mobile layout, keyboard navigation, and both Reading settings for the homepage.
6. Save two different versions, restore the first, and then undo the restore from history. Confirm the heading, paragraph, and brief all match.
7. Confirm a non-administrator cannot access generation, restore, or connection settings.

## GitHub submission

Publish the theme source and README to your GitHub repository. Keep the API key in your local WordPress database/configuration. Do not upload the full WordPress installation, database backups, credentials, or `wp-config.php`. Repository publishing is a separate deliverable.

## License

GPL-2.0-or-later. See https://www.gnu.org/licenses/gpl-2.0.html.
