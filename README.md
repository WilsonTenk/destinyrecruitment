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

## Pricing and requests

No fixed prices are shown. Instead:

- Each Services card lists how it can be booked (for example *Hourly · Monthly · Live-in*) and has an **Add to request** button.
- **How pricing works** on the Services page explains what the price depends on (home type and size, outdoor areas, services combined, schedule, who needs care, area).
- **Build your request** (`services.html#request`) lets visitors pick one or more services and describe their home, then sends it all as one WhatsApp message.
- The Contact page's call-back form also accepts several services.

## Not built yet

The **admin panel** in the designs (availability, prices, services, guarantee text, contact details) needs a back end and a login. Until then, availability labels and prices are edited in the HTML files.
