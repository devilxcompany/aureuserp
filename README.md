# AureusERP

ERP Management for Devil X Company

[![Deploy to Netlify](https://www.netlify.com/img/deploy/button.svg)](https://app.netlify.com/start/deploy?repository=https://github.com/devilxcompany/AUREUSERP)
[![Netlify Status](https://api.netlify.com/api/v1/badges/NETLIFY_SITE_ID/deploy-status)](https://app.netlify.com)

> **Note:** Replace `NETLIFY_SITE_ID` in the badge URL above with your site's API ID (found in Site Settings → General) after you've connected the repo to Netlify.

## 🚀 Deploy to Netlify

### One-Click Deploy

Click the **Deploy to Netlify** button above to instantly clone this repo to your own GitHub account and deploy it to Netlify — no configuration needed.

### Manual Setup

1. **Fork / clone** this repository.

2. **Connect to Netlify:**
   - Log in at <https://app.netlify.com>.
   - Click **Add new site → Import an existing project**.
   - Choose **GitHub** and select this repository.
   - Netlify auto-detects `netlify.toml`:
     - **Publish directory:** `public`
     - **Build command:** *(none – static site)*

3. **Update backend proxy URLs** in `netlify.toml`:

   Search for `your-laravel-backend.example.com` and replace it with your actual Laravel backend URL in all three redirect rules.

4. **Add GitHub secrets** for the CI/CD pipeline (Repository Settings → Secrets and variables → Actions):

   | Secret | Description |
   |---|---|
   | `NETLIFY_AUTH_TOKEN` | Your Netlify personal access token (User Settings → OAuth → Personal access tokens) |
   | `NETLIFY_SITE_ID` | API ID from Site Settings → General (e.g. `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`) |

5. Push to `main` — GitHub Actions will deploy automatically. Pull requests get a preview URL posted as a comment.

## 🗂 Project Structure

```
public/          # Static assets served by Netlify
  index.html     # Login page (entry point)
  _redirects     # Netlify redirect rules (fallback)
netlify.toml     # Netlify build & redirect configuration
.github/
  workflows/
    netlify-deploy.yml  # CI/CD pipeline
resources/
  views/         # Laravel Blade templates
app/
  Http/
    Controllers/ # Laravel controllers
```

## 🔒 Security Headers

The following headers are added automatically by `netlify.toml`:

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
