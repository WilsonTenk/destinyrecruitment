"""Switch the website from its GitHub Pages address to your own domain.

Run once, from the project folder (the one that contains site/):

    python tools/set-domain.py https://www.yourdomain.com

It updates canonical links, share (Open Graph / Twitter) tags, structured data,
sitemap.xml, robots.txt and the 404 page. Run with --dry-run first to see what
would change without saving anything.
"""
import pathlib
import re
import sys

OLD_BASE = "https://wilsontenk.github.io/destinyrecruitment/"
OLD_404_BASE = '<base href="/destinyrecruitment/">'
SITE = pathlib.Path(__file__).resolve().parent.parent / "site"


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    dry = "--dry-run" in sys.argv
    if len(args) != 1 or not re.match(r"^https://[a-z0-9.-]+\.[a-z]{2,}/?$", args[0], re.I):
        print("Usage: python tools/set-domain.py https://www.yourdomain.com [--dry-run]")
        sys.exit(1)
    new_base = args[0].rstrip("/") + "/"

    total = 0
    for path in sorted(SITE.glob("*")):
        if path.suffix not in {".html", ".xml", ".txt"}:
            continue
        text = path.read_text(encoding="utf-8")
        new = text.replace(OLD_BASE, new_base)
        if path.name == "404.html":
            new = new.replace(OLD_404_BASE, '<base href="/">')
            new = new.replace("change /destinyrecruitment/ below to /", "already set to / for your own domain")
        if new != text:
            count = text.count(OLD_BASE) + (1 if path.name == "404.html" and OLD_404_BASE in text else 0)
            total += count
            print(f"{'would update' if dry else 'updated'} {path.name}: {count} change(s)")
            if not dry:
                path.write_text(new, encoding="utf-8", newline="")

    leftovers = [p.name for p in SITE.glob("*.*") if p.suffix in {".html", ".xml", ".txt"}
                 and "wilsontenk.github.io" in p.read_text(encoding="utf-8")]
    print(f"\n{total} change(s) {'found' if dry else 'made'}; site address is now {new_base}")
    if leftovers and not dry:
        print("Still mentions the old address:", ", ".join(leftovers))


if __name__ == "__main__":
    main()
