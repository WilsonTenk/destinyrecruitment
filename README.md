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

- Real photos: the homepage hero, the About photo and one per service. Put them in `assets/img/` and replace the placeholder text inside each `.arch` or `.svc-photo` with an `<img src="…" alt="…">`.
- Prices (hourly and monthly) for each service.
- Office address, working hours and a Google Maps embed (marked with a TODO in `contact.html`).
- Commercial registration / Musaned licence number (in every footer).
- Real customer reviews (homepage, marked with a TODO).
- The client must confirm the copy we wrote for them: "for years", the 3-month replacement guarantee and its conditions, the selection, training and follow-up steps, and the same-day reply.

## Publishing

Any static host works. Upload the contents of this `site` folder to Netlify, Vercel, Cloudflare Pages, GitHub Pages or a normal web host, then point the client's domain at it.

## Not built yet

The **admin panel** in the designs (availability, prices, services, guarantee text, contact details) needs a back end and a login. Until then, availability labels and prices are edited in the HTML files.
