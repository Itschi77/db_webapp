<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box}body{margin:0;font:14px Segoe UI,Arial;background:#f4f6f8;color:#1f2937}.page{max-width:1150px;margin:auto;padding:32px}.toolbar{display:flex;justify-content:space-between;gap:12px;margin-bottom:18px}.button{display:inline-block;padding:9px 13px;border-radius:7px;background:#0069c2;color:#fff;text-decoration:none}.doc{background:#fff;border-radius:12px;box-shadow:0 2px 8px #0001;padding:28px;line-height:1.6}.doc h1,.doc h2,.doc h3{color:#0f172a}.doc table{width:100%;border-collapse:collapse}.doc th,.doc td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}.doc code{background:#f1f5f9;padding:1px 4px;border-radius:4px}.doc pre{background:#f8fafc;padding:12px;border-radius:8px;overflow:auto}
</style></head><body><div class="page">
<div class="toolbar"><a class="button" href="{{ route('dashboard') }}">← Hauptmenü</a><a href="{{ route('frontend.switch','classic') }}">← Zur klassischen Ansicht</a></div>
<div class="doc">{!! $content !!}</div>
</div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
