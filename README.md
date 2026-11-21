# Destiny Recruitment — website

A four-page website (Home, Services, About & guarantee, Contact) that opens in English and switches to Arabic (right to left) with the **عربي** button. Visitors book on WhatsApp. It's plain HTML, CSS and JavaScript, so there's nothing to install or build.

## Files

| File | What it is |
|---|---|
| `index.html`, `services.html`, `about.html`, `contact.html` | The four pages (English text lives here) |
| `assets/css/styles.css` | All styling: colours, fonts, layout, phone sizes |
| `assets/js/i18n-ar.js` | The Arabic text for every page |
| `assets/js/main.js` | Language switch, phone menu, WhatsApp links, service filters, call-back form |

## Preview on this computer

Open a terminal in this `site` folder and run:

```
python -m http.server 5173
```

Then open http://localhost:5173 in a browser.

## Editing text

- **English:** edit it directly in the `.html` file.
- **Arabic:** find the same key (the `data-i18n="…"` value next to the English) in `assets/js/i18n-ar.js` and change it there.
- **WhatsApp number:** `WA_NUMBER` at the top of `assets/js/main.js`. The phone numbers shown on the pages are in the HTML files.
- **WhatsApp messages:** the pre-filled messages are in each button's `data-wa` (English) and `data-wa-ar` (Arabic).
- After changing CSS or JS, raise the `?v=3` number in the four HTML files so browsers load the new version.

## Before launch: still needed from the client

Search the HTML files for `[` to find every placeholder:

- Photos: the homepage hero, the team photo and one per service are AI-generated (Higgsfield, Nano Banana Pro) with staff in green uniforms carrying the arch logo. They live in `assets/img/photos/` (WebP). To use real photos instead, save them under the same file names. The full-size originals are in `generated-images/raw/` in the project folder.
- Office address, working hours and a Google Maps embed (marked with a TODO in `contact.html`).
- The client must confirm the copy we wrote for them: "for years", the 3-day replacement guarantee and its conditions, the selection, training and follow-up steps, and the reply within 1 hour.

## Publishing

**Live now on GitHub Pages:** https://wilsontenk.github.io/destinyrecruitment/

GitHub Pages serves the `gh-pages` branch, which holds only the contents of this `site` folder. After committing changes on `main`, run this from the project folder (the one that contains `site`) to update the live site:

```
git push origin main
git subtree push --prefix site origin gh-pages
```

The live site updates a minute or two later.

Other hosts work too. Upload the contents of this `site` folder to Netlify, Vercel, Cloudflare Pages, GitHub Pages or a normal web host, then point the client's domain at it.

## Launching on your own domain (cPanel)

Everything is ready for cPanel hosting:

- `.cpanel.yml` (project folder) tells cPanel's **Git Version Control** to copy this `site` folder into `public_html` on every deploy.
- `site/.htaccess` forces HTTPS and adds security headers, caching and compression on the cPanel (Apache) server. GitHub Pages ignores it.
- `tools/set-domain.py` switches every address in the site from the GitHub Pages address to your domain.

**Steps**

1. **Buy the domain** and point it at your cPanel hosting (your host gives you the nameservers).
2. **Turn on HTTPS:** in cPanel, open **SSL/TLS Status** and run **AutoSSL** for the domain (and `www`).
3. **Switch the site's addresses** on your computer, from the project folder:
   ```
   python tools/set-domain.py https://www.yourdomain.com --dry-run
   python tools/set-domain.py https://www.yourdomain.com
   ```
   Then commit and push to GitHub as usual (`git push origin main`).
4. **Connect cPanel to GitHub:** cPanel → **Git Version Control** → **Create**.
   - Clone URL: `https://github.com/WilsonTenk/destinyrecruitment.git`
   - Repository path: for example `/home/YOUR-CPANEL-USER/repositories/destinyrecruitment` (not inside `public_html`).
5. **Deploy:** open the repository in Git Version Control → **Pull or Deploy** → **Update from Remote**, then **Deploy HEAD Commit**. The site appears at your domain.
6. **Check it:** open the domain, try the language switch, the request form and a blog article, and visit a made-up address to see the 404 page.
7. **Lock in HTTPS:** once `https://` works on the domain (and `www` if you use it), uncomment the `Strict-Transport-Security` line in `site/.htaccess`, push and deploy again. Optionally pick www or non-www in the same file.
8. **Tell Google:** add the domain in Google Search Console and submit `https://www.yourdomain.com/sitemap.xml`.
9. **Retire the GitHub Pages copy:** after the domain is live, turn off GitHub Pages (repo **Settings → Pages**) so there aren't two copies of the site.

**Future updates:** edit, commit and `git push origin main`, then in cPanel **Update from Remote** and **Deploy HEAD Commit**.

## Pricing and requests

No fixed prices are shown. Instead:

- Each Services card lists how it can be booked (for example *Hourly · Monthly · Live-in*) and has an **Add to request** button.
- **How pricing works** on the Services page explains what the price depends on (home type and size, outdoor areas, services combined, schedule, who needs care, area).
- **Build your request** (`services.html#request`) lets visitors pick one or more services and describe their home, then sends it all as one WhatsApp message.
- The Contact page's call-back form also accepts several services.

## Not built yet

The **admin panel** in the designs (availability, prices, services, guarantee text, contact details) needs a back end and a login. Until then, availability labels and prices are edited in the HTML files.

## Icons

All icons are from [Lucide](https://lucide.dev) (v0.460.0), free to use with no account. They're pasted into the pages as inline SVG, so there's nothing extra to load. To change one, copy its SVG from lucide.dev and replace the drawing inside the `<svg>` tag.

Lucide licence (ISC): Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as part of Feather (MIT). All other copyright (c) for Lucide are held by Lucide Contributors 2022. Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby granted, provided that the above copyright notice and this permission notice appear in all copies.
