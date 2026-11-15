<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>@yield('title') — SangaTV Plus</title>
<link rel="stylesheet" href="{{ asset('css/sangatv-fonts.css') }}">
<style>
    * { box-sizing: border-box; } body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #0F172A; font-size: 14px;
        background: radial-gradient(rgba(79,70,229,.07) 1px, transparent 1px) 0 0/22px 22px, #F4F5FB; }
    .band { background: radial-gradient(rgba(255,255,255,.14) 1px, transparent 1px) 0 0/18px 18px, linear-gradient(120deg,#312E81,#4338CA 40%,#6D28D9 75%,#0E7490); color: #fff; padding: 22px 16px 70px; }
    .wrap { max-width: 1040px; margin: 0 auto; }
    .band small { opacity: .8; letter-spacing: .1em; text-transform: uppercase; font-size: 11px; } .band h1 { margin: 4px 0 2px; font-size: 24px; letter-spacing: -.02em; }
    .main { margin-top: -54px; padding: 0 16px 40px; }
    .grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
    .card { background: #fff; border-radius: 14px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05), 0 18px 40px -24px rgba(49,46,129,.35); border: 1px solid rgba(15,23,42,.06); }
    .label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .08em; color: #64748B; font-weight: 700; } .value { font-size: 19px; font-weight: 800; margin-top: 2px; } .muted { color: #64748B; font-size: 12.5px; }
    h2 { font-size: 15px; margin: 0 0 8px; } section { margin-top: 12px; }
    table { width: 100%; border-collapse: collapse; } th { text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; color: #64748B; padding: 7px 8px; border-bottom: 1px solid #E2E8F0; }
    td { padding: 8px; border-bottom: 1px solid #F1F5F9; font-size: 13px; } .scroll { overflow-x: auto; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
    .ok { background: #ECFDF5; color: #047857; } .warn { background: #FFFBEB; color: #B45309; } .bad { background: #FFF1F2; color: #BE123C; } .info { background: #EEF2FF; color: #4338CA; }
    .btn { display: inline-block; padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; margin: 1px; }
    .btn-primary { background: #4F46E5; color: #fff; } .btn-ghost { background: #F1F5F9; color: #334155; }
    .foot { text-align: center; color: #94A3B8; font-size: 12px; margin-top: 18px; }
</style></head>
<body>@yield('body')</body></html>
