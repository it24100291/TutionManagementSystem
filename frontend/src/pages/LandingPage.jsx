import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { API_BASE_URL } from '../api/axios';

/* ─────────────────────────────────────────────
   All styles injected into <head> — no CSS file needed
───────────────────────────────────────────── */
const STYLES = `
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Outfit', sans-serif; background: #fff; color: #0f172a; -webkit-font-smoothing: antialiased; }
img { max-width: 100%; display: block; }
a { text-decoration: none; }

:root {
  --b950: #020d1f;
  --b900: #051833;
  --b800: #0b2d5e;
  --b700: #103f82;
  --b600: #1755b0;
  --b500: #2270d8;
  --b400: #4a90e8;
  --b300: #93c4f5;
  --b100: #dbeafe;
  --b50:  #f0f7ff;
  --gold: #f59e0b;
  --white: #ffffff;
  --n50:  #f8fafc;
  --n100: #f1f5f9;
  --n200: #e2e8f0;
  --n300: #cbd5e1;
  --n500: #64748b;
  --n700: #334155;
  --n900: #0f172a;
  --success: #16a34a;
  --error: #dc2626;
  --r: 8px;
  --rl: 16px;
  --rxl: 24px;
  --sh: 0 4px 20px rgba(23,85,176,.18);
}

.lp-container { max-width: 1180px; margin: 0 auto; padding: 0 28px; }

/* NAVBAR */
.lp-nav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 200;
  padding: 18px 0;
  background: rgba(2, 13, 31, .62);
  border-bottom: 1px solid rgba(255,255,255,.12);
  box-shadow: 0 10px 28px rgba(0,0,0,.22);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  transition: background .25s, box-shadow .25s, padding .25s;
}
.lp-nav.scrolled {
  background: var(--b950);
  box-shadow: 0 2px 24px rgba(0,0,0,.35);
  padding: 11px 0;
}
.lp-nav-inner { display: flex; align-items: center; gap: 24px; }
.lp-logo { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.lp-logo img { width: 42px; height: 42px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,.25); }
.lp-logo-name { display: block; font-size: 14px; font-weight: 700; color: #fff; letter-spacing: .02em; }
.lp-logo-tag  { display: block; font-size: 11px; color: rgba(255,255,255,.55); margin-top: 2px; }
.lp-nav-links { display: flex; align-items: center; gap: 2px; margin-left: auto; }
.lp-nav-links a { padding: 8px 15px; font-size: 14px; font-weight: 500; color: rgba(255,255,255,.82); border-radius: 6px; transition: color .2s, background .2s; }
.lp-nav-links a:hover { color: #fff; background: rgba(255,255,255,.1); }
.lp-nav-btns { display: flex; gap: 10px; }
.lp-hamburger { display: none; flex-direction: column; gap: 5px; background: none; border: none; cursor: pointer; padding: 4px; margin-left: auto; }
.lp-hamburger span { display: block; width: 23px; height: 2px; background: #fff; border-radius: 2px; }
.lp-mobile-nav {
  position: fixed; inset: 0; background: var(--b950); z-index: 199;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 24px;
  transform: translateX(100%); transition: transform .3s ease;
}
.lp-mobile-nav.open { transform: translateX(0); }
.lp-mobile-nav a { font-size: 22px; font-weight: 600; color: rgba(255,255,255,.85); padding: 10px 24px; border-radius: 8px; }
.lp-mobile-nav-btns { display: flex; flex-direction: column; gap: 12px; width: 220px; margin-top: 8px; }
.lp-mobile-nav-btns a { width: 100%; text-align: center; }

/* BUTTONS */
.btn { display: inline-flex; align-items: center; justify-content: center; padding: 11px 24px; border-radius: var(--r); font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 600; border: 2px solid transparent; cursor: pointer; transition: all .2s; white-space: nowrap; }
.btn-lg { padding: 14px 30px; font-size: 15px; border-radius: 10px; }
.btn-full { width: 100%; }
.btn-blue { background: var(--b600); color: #fff; border-color: var(--b600); box-shadow: 0 4px 18px rgba(23,85,176,.28); }
.btn-blue:hover { background: var(--b700); border-color: var(--b700); transform: translateY(-1px); box-shadow: 0 6px 24px rgba(23,85,176,.38); }
.btn-ghost { background: rgba(255,255,255,.12); color: #fff; border-color: rgba(255,255,255,.3); backdrop-filter: blur(8px); }
.btn-ghost:hover { background: rgba(255,255,255,.2); border-color: rgba(255,255,255,.55); }
.btn-outline-white { background: rgba(255,255,255,.95); color: #000000; border-color: transparent; font-weight: 700; }
.btn-outline-white:hover { background: #ffffff; color: #000000; box-shadow: 0 4px 18px rgba(0,0,0,.2); }
.btn-white { background: #fff; color: #000000; border-color: #fff; font-weight: 700; }
.btn-white:hover { background: var(--b50); transform: translateY(-1px); }

/* EYEBROW */
.eyebrow { display: inline-block; font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; padding: 4px 14px; border-radius: 20px; margin-bottom: 14px; }
.eyebrow-blue  { background: var(--b100); color: var(--b700); }
.eyebrow-white { background: rgba(255,255,255,.9); color: var(--b900); }
.eyebrow-dim   { background: rgba(255,255,255,.3); color: #000000; }

/* SECTION */
.lp-section { padding: 96px 0; }
.lp-section-white { background: #fff; }
.lp-section-white.lp-features-section { background: #D5DEEF; }
.lp-section-tint  { background: #001D3F; }
.lp-section-match-two { background: #D5DEEF; }
.lp-section-about { background: #E9ECEE; }
.lp-contact-section .sec-title,
.lp-contact-section .sec-desc {
  color: #E9ECEE;
}
.lp-contact-section .eyebrow {
  color: #000000;
}
.lp-section-head { text-align: center; max-width: 680px; margin: 0 auto 60px; }
.lp-section-head-left { text-align: left; margin-left: 0; }
.sec-title { font-family: 'Playfair Display', Georgia, serif; font-size: clamp(1.7rem, 2.8vw, 2.5rem); font-weight: 700; color: var(--b900); line-height: 1.2; margin-bottom: 16px; letter-spacing: -.01em; }
.sec-desc { font-size: 16px; color: var(--n500); line-height: 1.8; }
.lp-divider { width: 48px; height: 3px; background: var(--b500); border-radius: 2px; margin: 14px 0 22px; }
.lp-divider-center { margin: 14px auto 22px; }

/* HERO */
.lp-hero { position: relative; min-height: 100vh; display: flex; align-items: center; overflow: hidden; }
.lp-hero-bg { position: absolute; inset: 0; z-index: 0; }
.lp-hero-slide { position: absolute; inset: 0; opacity: 0; background-size: cover; background-position: center; }
.lp-hero-slide.active { opacity: 1; }
.lp-hero-slide-1 { background-image: url('/Landing%20BG.jpeg'); }
.lp-hero-slide-2 { background-image: url('/Landing%20BG.jpeg'); background-position: right center; }
.lp-hero-slide-3 { background-image: url('/Landing%20BG.jpeg'); background-position: left center; }
.lp-hero-slide-4 { background-image: url('/Landing%20BG.jpeg'); background-position: center top; }
.lp-hero-slide-5 { background-image: url('/Landing%20BG.jpeg'); background-position: center bottom; }
.lp-hero-overlay { position: absolute; inset: 0; background: linear-gradient(110deg, rgba(2,10,30,.72) 0%, rgba(5,20,55,.62) 42%, rgba(10,40,90,.38) 72%, rgba(10,40,90,.18) 100%); }
.lp-hero-grid { position: relative; z-index: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; padding-top: 130px; padding-bottom: 90px; }
.lp-hero-title { font-family: 'Playfair Display', Georgia, serif; font-size: clamp(2.1rem, 4vw, 3.4rem); font-weight: 800; color: #ffffff; line-height: 1.16; margin: 14px 0 22px; letter-spacing: -.015em; text-shadow: 0 2px 16px rgba(0,0,0,.55), 0 1px 4px rgba(0,0,0,.4); }
.lp-hero-title span { color: #dbeafe; }
.lp-hero-desc { font-size: 17px; color: #f8fafc; line-height: 1.75; margin-bottom: 36px; font-weight: 600; text-shadow: 0 1px 8px rgba(0,0,0,.45); }
.lp-hero-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 40px; }
.lp-trust-row { display: flex; flex-wrap: wrap; gap: 22px; }
.lp-trust-item { display: flex; align-items: center; gap: 9px; }
.lp-trust-check { width: 22px; height: 22px; background: var(--b500); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.lp-trust-item strong { font-size: 14px; font-weight: 800; color: #ffffff; text-shadow: 0 1px 8px rgba(0,0,0,.4); }

/* MOCK WINDOW */
.lp-mock-window { background: linear-gradient(180deg, rgba(15,23,42,.92) 0%, rgba(30,41,59,.94) 100%); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,.1); border-radius: 24px; overflow: hidden; max-width: 430px; width: 100%; box-shadow: 0 30px 70px rgba(0,0,0,.42); }
.lp-mock-bar { display: flex; gap: 8px; padding: 16px 20px; background: rgba(255,255,255,.04); border-bottom: 1px solid rgba(255,255,255,.08); }
.lp-mock-bar span { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,.28); }
.lp-mock-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: rgba(255,255,255,.06); }
.lp-mock-card { background: linear-gradient(180deg, rgba(51,65,85,.92) 0%, rgba(30,41,59,.96) 100%); padding: 24px 20px; min-height: 160px; }
.lp-mock-card-tag { display: block; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #93c5fd; margin-bottom: 10px; }
.lp-mock-card-val { display: block; font-size: 19px; font-weight: 800; color: #ffffff; margin-bottom: 8px; }
.lp-mock-card-note { font-size: 12px; color: rgba(255,255,255,.78); line-height: 1.65; }
.lp-mock-card-primary { background: linear-gradient(180deg, rgba(37,99,235,.72) 0%, rgba(30,64,175,.76) 100%); border-bottom: 2px solid #93c5fd; }

/* FEATURES */
.lp-features-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; }
.lp-feat-card { background: #054E98; border: 1.5px solid rgba(255,255,255,.18); border-radius: var(--rl); padding: 32px 26px; transition: background-color .2s, border-color .2s, box-shadow .2s, transform .2s; }
.lp-feat-card:hover { background: #0A2472; border-color: rgba(255,255,255,.34); box-shadow: var(--sh); transform: translateY(-3px); }
.lp-feat-icon { width: 50px; height: 50px; background: rgba(255,255,255,.12); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #dbeafe; margin-bottom: 20px; }
.lp-feat-icon svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 1.8; fill: none; stroke-linecap: round; stroke-linejoin: round; }
.lp-feat-tag { display: block; font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #dbeafe; margin-bottom: 8px; }
.lp-feat-title { font-size: 17px; font-weight: 700; color: #ffffff; margin-bottom: 10px; line-height: 1.3; }
.lp-feat-desc { font-size: 14px; color: rgba(255,255,255,.88); line-height: 1.72; }

/* STATS */
.lp-stats-band { background: linear-gradient(135deg, var(--b900) 0%, var(--b700) 100%); }
.lp-stats-inner { display: grid; grid-template-columns: repeat(4,1fr); }
.lp-stat { padding: 52px 24px; text-align: center; border-right: 1px solid rgba(255,255,255,.1); }
.lp-stat:last-child { border-right: none; }
.lp-stat-val { font-family: 'Playfair Display', serif; font-size: 3.2rem; font-weight: 700; color: #fff; line-height: 1; margin-bottom: 10px; display: block; }
.lp-stat-lbl { font-size: 13px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--b300); display: block; }

/* ABOUT */
.lp-about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 72px; align-items: center; }
.lp-about-body { font-size: 16px; color: var(--n700); line-height: 1.82; }
.lp-benefits-box { background: #fff; border: 1.5px solid var(--n200); border-radius: var(--rxl); padding: 38px 34px; display: flex; flex-direction: column; gap: 22px; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
.lp-benefit { display: flex; align-items: flex-start; gap: 14px; }
.lp-benefit-check { width: 30px; height: 30px; background: var(--b600); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
.lp-benefit-check svg { width: 13px; height: 13px; stroke: #fff; stroke-width: 2.5; fill: none; stroke-linecap: round; stroke-linejoin: round; }
.lp-benefit p { font-size: 15px; color: var(--n700); line-height: 1.65; font-weight: 500; }

/* TESTIMONIALS */
.lp-testi-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; }
.lp-testi-card { background: #fff; border: 1.5px solid var(--n200); border-radius: var(--rl); padding: 32px 28px; display: flex; flex-direction: column; gap: 18px; box-shadow: 0 2px 12px rgba(0,0,0,.05); transition: box-shadow .2s, transform .2s; }
.lp-testi-card:hover { box-shadow: var(--sh); transform: translateY(-2px); }
.lp-stars { color: var(--gold); font-size: 15px; letter-spacing: 3px; }
.lp-testi-quote { font-size: 15px; color: var(--n700); line-height: 1.78; flex: 1; font-style: italic; }
.lp-testi-meta { padding-top: 18px; border-top: 1px solid var(--n200); }
.lp-testi-name { display: block; font-size: 14px; font-weight: 700; color: var(--b900); }
.lp-testi-role { display: block; font-size: 13px; color: var(--b500); font-weight: 500; margin-top: 2px; }

/* FAQ */
.lp-faq-shell { display: grid; grid-template-columns: 260px 1fr; gap: 64px; align-items: start; }
.lp-faq-list { display: flex; flex-direction: column; gap: 3px; }
.lp-faq-item { border: 1.5px solid var(--n200); border-radius: var(--r); overflow: hidden; background: #fff; transition: border-color .2s; }
.lp-faq-item.open { border-color: var(--b400); }
.lp-faq-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 22px; font-size: 15px; font-weight: 600; color: var(--b900); background: none; border: none; cursor: pointer; text-align: left; font-family: 'Outfit', sans-serif; transition: background .2s; }
.lp-faq-btn:hover { background: var(--b50); }
.lp-faq-icon { font-size: 20px; color: var(--b600); flex-shrink: 0; line-height: 1; }
.lp-faq-ans { padding: 0 22px 20px; font-size: 14px; color: var(--n700); line-height: 1.78; }

/* CTA */
.lp-cta { background: #848c9f; padding: 84px 0; position: relative; overflow: hidden; }
.lp-cta::after { content: ''; position: absolute; top: -100px; right: -60px; width: 340px; height: 340px; background: radial-gradient(circle, rgba(74,144,232,.22) 0%, transparent 68%); pointer-events: none; }
.lp-cta-inner { display: flex; align-items: center; justify-content: space-between; gap: 48px; position: relative; z-index: 1; }
.lp-cta-title { font-family: 'Playfair Display', serif; font-size: clamp(1.6rem, 2.8vw, 2.3rem); font-weight: 700; color: #000000; line-height: 1.22; margin-bottom: 10px; }
.lp-cta-desc { font-size: 16px; color: #000000; }
.lp-cta-btns { display: flex; gap: 14px; flex-shrink: 0; }

/* CONTACT */
.lp-contact-grid { display: flex; justify-content: center; gap: 40px; align-items: start; }
.lp-contact-info { background: #ffffff; border: 1px solid rgba(15,23,42,0.12); border-radius: var(--rxl); padding: 36px 32px; display: flex; flex-direction: column; gap: 26px; box-shadow: 0 20px 40px rgba(0,0,0,.12); }
.lp-contact-info { width: min(640px, 100%); }
.lp-contact-row { display: flex; flex-direction: column; gap: 4px; }
.lp-contact-lbl { font-size: 11px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #334155; }
.lp-contact-val { font-size: 15px; color: #000000; line-height: 1.5; font-weight: 600; }
.lp-contact-link { font-size: 15px; color: #000000; font-weight: 600; }
.lp-contact-link:hover { text-decoration: underline; }
.lp-contact-form { background: #fff; border: 1.5px solid var(--n200); border-radius: var(--rxl); padding: 36px 32px; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 2px 16px rgba(0,0,0,.05); }
.lp-field { display: flex; flex-direction: column; gap: 6px; }
.lp-field label { font-size: 13px; font-weight: 600; color: var(--n700); }
.lp-field input, .lp-field textarea { font-family: 'Outfit', sans-serif; font-size: 15px; color: var(--n900); background: var(--n50); border: 1.5px solid var(--n200); border-radius: var(--r); padding: 12px 15px; outline: none; transition: border-color .2s, box-shadow .2s; }
.lp-field input:focus, .lp-field textarea:focus { border-color: var(--b500); box-shadow: 0 0 0 3px rgba(34,112,216,.12); background: #fff; }
.lp-field textarea { resize: vertical; min-height: 126px; }
.lp-form-msg { padding: 12px 16px; border-radius: var(--r); font-size: 14px; font-weight: 500; }
.lp-form-msg.success { background: #dcfce7; color: var(--success); border: 1px solid #bbf7d0; }
.lp-form-msg.error   { background: #fee2e2; color: var(--error);   border: 1px solid #fecaca; }

/* FOOTER */
.lp-footer { background: #E9ECEE; padding: 64px 0 32px; border-top: 1px solid rgba(15,23,42,.08); }
.lp-footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 48px; padding-bottom: 40px; border-bottom: 1px solid rgba(255,255,255,.07); margin-bottom: 28px; }
.lp-footer-brand-name { display: block; font-size: 18px; font-weight: 700; color: var(--b900); margin-bottom: 10px; }
.lp-footer-tagline { font-size: 14px; color: var(--n700); line-height: 1.75; margin-bottom: 20px; }
.lp-footer-copy { font-size: 13px; color: var(--n500); display: block; }
.lp-footer-col-title { font-size: 12px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: var(--b700); margin-bottom: 16px; display: block; }
.lp-footer-col { display: flex; flex-direction: column; gap: 10px; }
.lp-footer-link { font-size: 14px; color: var(--n700); cursor: pointer; transition: color .2s; }
.lp-footer-link:hover { color: var(--b700); }

/* RESPONSIVE */
@media (max-width: 1024px) {
  .lp-features-grid { grid-template-columns: repeat(2,1fr); }
  .lp-stats-inner { grid-template-columns: repeat(2,1fr); }
  .lp-stat { border-right: none; border-bottom: 1px solid rgba(255,255,255,.08); }
  .lp-stat:nth-child(odd) { border-right: 1px solid rgba(255,255,255,.08); }
  .lp-stat:nth-last-child(-n+2) { border-bottom: none; }
}
@media (max-width: 900px) {
  .lp-hero-grid { grid-template-columns: 1fr; text-align: center; }
  .lp-hero-panel-wrap { display: none; }
  .lp-hero-actions, .lp-trust-row { justify-content: center; }
  .lp-about-grid { grid-template-columns: 1fr; gap: 40px; }
  .lp-faq-shell { grid-template-columns: 1fr; gap: 32px; }
  .lp-contact-grid { grid-template-columns: 1fr; }
  .lp-cta-inner { flex-direction: column; text-align: center; }
  .lp-cta-btns { justify-content: center; }
  .lp-footer-grid { grid-template-columns: 1fr 1fr; }
  .lp-testi-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .lp-hamburger { display: flex; }
  .lp-nav-links, .lp-nav-btns { display: none; }
  .lp-section { padding: 68px 0; }
  .lp-features-grid { grid-template-columns: 1fr; }
  .lp-footer-grid { grid-template-columns: 1fr; gap: 36px; }
}
@media (max-width: 480px) {
  .lp-container { padding: 0 18px; }
  .lp-hero-grid { padding-top: 108px; padding-bottom: 64px; }
  .lp-hero-title { font-size: 1.9rem; }
}
`;

/* ─────────────────────────────────────────────
   DATA
───────────────────────────────────────────── */
const INSTITUTE = 'NEW KRISHNA EDUCATION CENTER-KODIKAMAM';
const EMAIL     = 'info@newkrishnaedu.com';
const PHONE     = '+94 77 123 4567';
const ADDRESS   = 'Kodikamam, Jaffna, Sri Lanka';

const NAV = [
  { label: 'Home',     key: 'home'     },
  { label: 'Features', key: 'features' },
  { label: 'About',    key: 'about'    },
  { label: 'Contact',  key: 'contact'  },
];

const FEATURES = [
  { eyebrow: 'Students',   title: 'Student Management',   desc: 'Manage registrations, profile history, attendance, payments, and student progression from one place.', icon: 'M12 12a4 4 0 1 0 0-8a4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0' },
  { eyebrow: 'Tutors',     title: 'Tutor Coordination',   desc: 'Assign tutors, review workload, track performance, and organize communication without manual follow-up.', icon: 'M4 6h16v12H4z M8 3v3 M16 3v3 M4 10h16' },
  { eyebrow: 'Attendance', title: 'Attendance Tracking',  desc: 'Mark attendance quickly, review student history, and keep academic records updated in real time.', icon: 'M7 4h10v16H7z M9 8h6 M9 12h6 M9 16h3' },
  { eyebrow: 'Scheduling', title: 'Smart Timetable',      desc: 'Maintain structured class schedules, fixed periods, and cleaner planning across grades and subjects.', icon: 'M4 18h16 M7 15V9 M12 15V6 M17 15v-3' },
  { eyebrow: 'Finance',    title: 'Fee & Salary Control', desc: 'Track student fees, unpaid months, tutor salary summaries, and financial records with less admin work.', icon: 'M3 7h18v10H3z M3 11h18 M7 15h4' },
  { eyebrow: 'Results',    title: 'Exams & Reports',      desc: 'Store marks, evaluate class performance, and surface academic insights across students and tutors.', icon: 'M5 18V8l7-4l7 4v10 M9 22v-6h6v6' },
];

const STATS = [
  { val: '100+', lbl: 'Students Managed'    },
  { val: '15+',  lbl: 'Tutors Coordinated'  },
  { val: '100%', lbl: 'Secure Role Access'  },
  { val: '24/7', lbl: 'Real-Time Visibility' },
];

const BENEFITS = [
  'Easy to use interface for daily institute operations',
  'Role-based dashboards for admin, tutor, and student users',
  'Automated attendance, timetable, and fee tracking',
  'Clean, secure record management for academic data',
];

const TESTIMONIALS = [
  { quote: 'The system gives us one clean place to manage registration, class flow, and payments without confusion.',          name: 'Kumara Silva',    role: 'Institute Admin' },
  { quote: 'I can check schedules, attendance, and student updates much faster than before. Daily work is more structured.',  name: 'Roshan Fernando', role: 'Tutor'           },
  { quote: 'The interface is simple and clear. It feels like a real institute platform instead of scattered manual records.', name: 'Nilmini Perera',  role: 'Parent'          },
];

const FAQS = [
  { q: 'Who can use this system?',                           a: 'The system supports Admin, Tutor, Student, and Parent workflows with role-based access and separate dashboards.' },
  { q: 'Can the system handle attendance and fees together?', a: 'Yes. Attendance, fee tracking, timetable management, and results are designed to work from the same platform.' },
  { q: 'Is it suitable for mobile devices?',                 a: 'Yes. The landing page and dashboard layout are fully responsive across desktop, tablet, and mobile screens.' },
];

/* ─────────────────────────────────────────────
   MINI SVG HELPERS
───────────────────────────────────────────── */
const Icon = ({ d }) => (
  <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d={d} />
  </svg>
);

const CheckIcon = () => (
  <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
    <path d="M20 7L10 17l-6-6" />
  </svg>
);

/* ─────────────────────────────────────────────
   COMPONENT
───────────────────────────────────────────── */
export default function LandingPage() {
  const [menuOpen,   setMenuOpen]   = useState(false);
  const [openFaq,    setOpenFaq]    = useState(0);
  const [slide,      setSlide]      = useState(0);
  const [scrolled,   setScrolled]   = useState(false);
  const [form,       setForm]       = useState({ name: '', email: '', message: '' });
  const [formStatus, setFormStatus] = useState({ type: '', msg: '' });

  const refs = {
    home: useRef(null), features: useRef(null),
    about: useRef(null), contact: useRef(null),
  };

  /* Inject styles once */
  useEffect(() => {
    const id = 'lp-injected-styles';
    if (document.getElementById(id)) return;
    const el = document.createElement('style');
    el.id = id;
    el.textContent = STYLES;
    document.head.appendChild(el);
  }, []);

  /* Scroll detection */
  useEffect(() => {
    const fn = () => setScrolled(window.scrollY > 16);
    fn();
    window.addEventListener('scroll', fn, { passive: true });
    return () => window.removeEventListener('scroll', fn);
  }, []);

  const goto = key => e => {
    e.preventDefault();
    refs[key]?.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    setMenuOpen(false);
  };

  const onInput = e => {
    const { name, value } = e.target;
    setForm(f => ({ ...f, [name]: value }));
  };

  const onSubmit = async e => {
    e.preventDefault();
    if (!form.name.trim() || !form.email.trim() || !form.message.trim()) {
      setFormStatus({ type: 'error', msg: 'All fields are required.' }); return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      setFormStatus({ type: 'error', msg: 'Please enter a valid email address.' }); return;
    }
    try {
      const res  = await fetch(`${API_BASE_URL}/api/contact.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: form.name.trim(), email: form.email.trim(), message: form.message.trim() }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.error || 'Failed to send.');
      setForm({ name: '', email: '', message: '' });
      setFormStatus({ type: 'success', msg: 'Message sent successfully.' });
    } catch (err) {
      setFormStatus({ type: 'error', msg: err.message || 'Failed to send message.' });
    }
  };

  return (
    <div>

      {/* ═══════════════════ NAVBAR ═══════════════════ */}
      <header className={`lp-nav${scrolled ? ' scrolled' : ''}`}>
        <div className="lp-container lp-nav-inner">
          <a href="#home" className="lp-logo" onClick={goto('home')}>
            <img src="/Logo.jpeg" alt="Smart Tuition System" />
            <div>
              <span className="lp-logo-name">Smart Tuition System</span>
              <span className="lp-logo-tag">Institute operations platform</span>
            </div>
          </a>

          <nav className="lp-nav-links" aria-label="Main navigation">
            {NAV.map(n => (
              <a key={n.key} href={`#${n.key}`} onClick={goto(n.key)}>{n.label}</a>
            ))}
          </nav>

          <div className="lp-nav-btns">
            <Link to="/login"    className="btn btn-ghost">Sign In</Link>
            <Link to="/register" className="btn btn-blue">Sign Up</Link>
          </div>

          <button
            className="lp-hamburger"
            onClick={() => setMenuOpen(o => !o)}
            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={menuOpen}
          >
            <span /><span /><span />
          </button>
        </div>
      </header>

      {/* Mobile overlay */}
      <div className={`lp-mobile-nav${menuOpen ? ' open' : ''}`} aria-hidden={!menuOpen}>
        {NAV.map(n => (
          <a key={n.key} href={`#${n.key}`} onClick={goto(n.key)}>{n.label}</a>
        ))}
        <div className="lp-mobile-nav-btns">
          <Link to="/login"    className="btn btn-ghost">Sign In</Link>
          <Link to="/register" className="btn btn-blue">Sign Up</Link>
        </div>
      </div>

      <main>

        {/* ═══════════════════ HERO — BG IMAGE ONLY HERE ═══════════════════ */}
        <section id="home" ref={refs.home} className="lp-hero">
          {/* Slideshow confined strictly to this section */}
          <div className="lp-hero-bg" aria-hidden="true">
            {[1].map(i => (
              <div
                key={i}
                className={`lp-hero-slide lp-hero-slide-${i} active`}
              />
            ))}
            <div className="lp-hero-overlay" />
          </div>

          <div className="lp-container lp-hero-grid">
            {/* Left copy */}
            <div>
              <span className="eyebrow eyebrow-white">Smart Tuition Center Management System</span>
              <h1 className="lp-hero-title">
                Manage Your Institute <span>Smarter</span> and Faster.
              </h1>
              <p className="lp-hero-desc">
                A complete digital solution for managing students, tutors, attendance,
                schedules, payments, and academic reporting — all from one structured platform.
              </p>
              <div className="lp-hero-actions">
                <Link to="/register" className="btn btn-blue btn-lg">Get Started</Link>
                <a href="#features" className="btn btn-outline-white btn-lg" onClick={goto('features')}>
                  Learn More
                </a>
              </div>
              <div className="lp-trust-row">
                {['100+ students', '15+ tutors', 'Secure access'].map(t => (
                  <div key={t} className="lp-trust-item">
                    <span className="lp-trust-check"><CheckIcon /></span>
                    <strong>{t}</strong>
                  </div>
                ))}
              </div>
            </div>

          </div>
        </section>

        {/* ═══════════════════ FEATURES — white, no bg image ═══════════════════ */}
        <section id="features" ref={refs.features} className="lp-section lp-section-white lp-features-section">
          <div className="lp-container">
            <div className="lp-section-head">
              <span className="eyebrow eyebrow-blue">Features</span>
              <div className="lp-divider lp-divider-center" />
              <h2 className="sec-title">Core tools built for daily institute operations</h2>
              <p className="sec-desc">Designed for clarity, speed, and structured management across academic and financial work.</p>
            </div>
            <div className="lp-features-grid">
              {FEATURES.map(f => (
                <article key={f.title} className="lp-feat-card">
                  <div className="lp-feat-icon"><Icon d={f.icon} /></div>
                  <span className="lp-feat-tag">{f.eyebrow}</span>
                  <h3   className="lp-feat-title">{f.title}</h3>
                  <p    className="lp-feat-desc">{f.desc}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* ═══════════════════ STATS — deep blue band ═══════════════════ */}
        <div className="lp-stats-band">
          <div className="lp-container">
            <div className="lp-stats-inner">
              {STATS.map(s => (
                <div key={s.lbl} className="lp-stat">
                  <span className="lp-stat-val">{s.val}</span>
                  <span className="lp-stat-lbl">{s.lbl}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* ═══════════════════ ABOUT — white ═══════════════════ */}
        <section id="about" ref={refs.about} className="lp-section lp-section-white lp-section-match-two lp-section-about">
          <div className="lp-container lp-about-grid">
            <div>
              <span className="eyebrow eyebrow-blue">Why Choose Us</span>
              <div className="lp-divider" />
              <h2 className="sec-title">A cleaner way to run a growing tuition center</h2>
              <p className="lp-about-body">
                Smart Tuition System is designed to reduce scattered manual work and give the
                institute a more reliable digital workflow. From registration to timetable,
                attendance, payments, and academic records — the system keeps daily operations
                organized and easier to manage across all user roles.
              </p>
            </div>
            <div className="lp-benefits-box">
              {BENEFITS.map(b => (
                <div key={b} className="lp-benefit">
                  <span className="lp-benefit-check"><CheckIcon /></span>
                  <p>{b}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ═══════════════════ TESTIMONIALS — light tint ═══════════════════ */}
        <section className="lp-section lp-section-tint lp-section-match-two">
          <div className="lp-container">
            <div className="lp-section-head">
              <span className="eyebrow eyebrow-blue">Testimonials</span>
              <div className="lp-divider lp-divider-center" />
              <h2 className="sec-title">What users say about the platform</h2>
            </div>
            <div className="lp-testi-grid">
              {TESTIMONIALS.map(t => (
                <article key={t.name} className="lp-testi-card">
                  <div className="lp-stars">★★★★★</div>
                  <p className="lp-testi-quote">"{t.quote}"</p>
                  <div className="lp-testi-meta">
                    <span className="lp-testi-name">{t.name}</span>
                    <span className="lp-testi-role">{t.role}</span>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>

        {/* ═══════════════════ CTA — deep blue ═══════════════════ */}
        <section className="lp-cta">
          <div className="lp-container lp-cta-inner">
            <div>
              <span className="eyebrow eyebrow-dim">Start Now</span>
              <h2 className="lp-cta-title">Ready to digitize your tuition center?</h2>
              <p className="lp-cta-desc">Join institutes running smarter operations from a single platform.</p>
            </div>
            <div className="lp-cta-btns">
              <Link to="/register" className="btn btn-white btn-lg">Get Started Free</Link>
              <Link to="/login"    className="btn btn-outline-white btn-lg">Sign In</Link>
            </div>
          </div>
        </section>

        {/* ═══════════════════ CONTACT — light tint ═══════════════════ */}
        <section id="contact" ref={refs.contact} className="lp-section lp-section-tint lp-contact-section">
          <div className="lp-container">
            <div className="lp-section-head">
              <span className="eyebrow eyebrow-blue">Contact</span>
              <div className="lp-divider lp-divider-center" />
              <h2 className="sec-title">Get in touch</h2>
              <p className="sec-desc">Use the form for inquiries, setup discussions, or institute support requests.</p>
            </div>

            <div className="lp-contact-grid">
              <div className="lp-contact-info">
                {[
                  { lbl: 'Institute', val: INSTITUTE, isLink: false },
                  { lbl: 'Email',     val: EMAIL,     isLink: true  },
                  { lbl: 'Phone',     val: PHONE,     isLink: false },
                  { lbl: 'Address',   val: ADDRESS,   isLink: false },
                ].map(r => (
                  <div key={r.lbl} className="lp-contact-row">
                    <span className="lp-contact-lbl">{r.lbl}</span>
                    {r.isLink
                      ? <a href={`mailto:${r.val}`} className="lp-contact-link">{r.val}</a>
                      : <span className="lp-contact-val">{r.val}</span>
                    }
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

      </main>

      {/* ═══════════════════ FOOTER ═══════════════════ */}
      <footer className="lp-footer">
        <div className="lp-container lp-footer-grid">
          <div>
            <strong className="lp-footer-brand-name">Smart Tuition System</strong>
            <p className="lp-footer-tagline">
              Professional management software for tuition centers that need cleaner operations.
            </p>
            <span className="lp-footer-copy">© 2026 {INSTITUTE}. All rights reserved.</span>
          </div>
          <div className="lp-footer-col">
            <span className="lp-footer-col-title">Quick Links</span>
            {NAV.map(n => (
              <a key={n.key} href={`#${n.key}`} className="lp-footer-link" onClick={goto(n.key)}>
                {n.label}
              </a>
            ))}
          </div>
          <div className="lp-footer-col">
            <span className="lp-footer-col-title">Contact</span>
            <a href={`mailto:${EMAIL}`} className="lp-footer-link">{EMAIL}</a>
            <span className="lp-footer-link">{PHONE}</span>
          </div>
        </div>
      </footer>

    </div>
  );
}
