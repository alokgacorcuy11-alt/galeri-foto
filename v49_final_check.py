# -*- coding: utf-8 -*-
# Verifikasi R-35 final: toast sukses (like) + toast danger (hapus foto MILIK SENDIRI).
# Tidak menyentuh foto user lain.
import re, json, time
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = "http://localhost/galeri-foto"
OUT  = Path(r"C:\Users\alokg\AppData\Local\Temp\opencode")
VIEW = {"width": 844, "height": 390}

def pv(col):
    m = re.search(r"rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)", col or "")
    if not m: return (0,0,0)
    return (float(m.group(1)), float(m.group(2)), float(m.group(3)))

def lum(col):
    r,g,b = pv(col)
    def lin(c):
        c = c/255
        return c/12.92 if c <= 0.03928 else ((c+0.055)/1.055)**2.4
    return 0.2126*lin(r) + 0.7152*lin(g) + 0.0722*lin(b)

def ratio(c1, c2):
    l1,l2 = lum(c1), lum(c2)
    a,b = max(l1,l2)+0.05, min(l1,l2)+0.05
    return a/b

def css(page, sel, props):
    return page.evaluate("""([sel, props]) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        const s = getComputedStyle(el);
        const o = {};
        for (const k of props) o[k] = s[k];
        return o;
    }""", [sel, props])

def grab(page, sel, props):
    c = css(page, sel, props)
    if not c: return None
    out = dict(c)
    if "color" in out and "backgroundColor" in out:
        out["contrast"] = round(ratio(out["color"], out["backgroundColor"]), 2)
    return out

with sync_playwright() as p:
    b = p.chromium.launch(headless=True)
    page = b.new_page(viewport=VIEW, device_scale_factor=2)
    errs = []
    page.on("console", lambda m: errs.append(f"[{m.type}] {m.text}") if m.type=="error" else None)
    page.on("pageerror", lambda e: errs.append(str(e)))

    # login
    page.goto(f"{BASE}/login.php", wait_until="networkidle")
    page.fill('input[name="username"]', "admin")
    page.fill('input[name="password"]', "admin123")
    page.evaluate("document.querySelector('form').requestSubmit()")
    page.wait_for_timeout(1000)

    # index
    page.goto(f"{BASE}/index.php", wait_until="networkidle")
    page.wait_for_timeout(400)

    # ===== LIKE -> TOAST SUKSES =====
    like = page.locator(".like-btn").first
    if like.count():
        like.click()
        page.wait_for_timeout(600)
        t = page.locator(".toast")
        print("toast sukses count:", t.count())
        if t.count():
            tj = grab(page, ".toast", ["backgroundColor","color","borderRadius","boxShadow","borderColor"])
            tb = grab(page, ".toast .toast-iconbox", ["backgroundColor","color","borderRadius"])
            print("toast css:", json.dumps(tj, ensure_ascii=False))
            print("iconbox css:", json.dumps(tb, ensure_ascii=False))
            page.screenshot(path=f"{OUT}\\v49-toast-sukses.png")
            page.locator(".toast-close").first.click()
            page.wait_for_timeout(300)
            print("toast after close:", page.locator(".toast").count())

    # ===== HAPUS foto sendiri -> TOAST DANGER =====
    # Cari kartu foto yang cuplikan teksnya mengandung 'uji' (foto milik admin hasil upload tadi)
    cards = page.locator(".photo-card, .photo-item")
    target = None
    for i in range(cards.count()):
        txt = cards.nth(i).inner_text()
        if "uji" in txt.lower():
            target = cards.nth(i)
            break
    if target:
        page.once("dialog", lambda d: d.accept())
        target.locator(".hapus-btn, .delete-btn, .hapus").first.click()
        page.wait_for_timeout(900)
        t = page.locator(".toast")
        print("\ntoast danger count:", t.count())
        if t.count():
            tj = grab(page, ".toast.danger, .toast", ["backgroundColor","color","borderRadius","boxShadow","borderColor"])
            tb = grab(page, ".toast.danger .toast-iconbox, .toast .toast-iconbox", ["backgroundColor","color","borderRadius"])
            print("danger toast css:", json.dumps(tj, ensure_ascii=False))
            print("danger iconbox css:", json.dumps(tb, ensure_ascii=False))
            page.screenshot(path=f"{OUT}\\v49-toast-danger.png")
        print("foto uji dihapus:", page.locator(".photo-card", has_text="uji").count())
    else:
        print("\nFoto uji tidak ditemukan — tidak hapus apa pun (aman).")

    print("\n=== CONSOLE/PAGE ERRORS ===")
    print("\n".join(errs) if errs else "TIDAK ADA")
    b.close()
