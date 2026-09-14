<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title }}</title>
<style>
body{font:14px Arial;background:#d9d9d9;padding:12px;color:#111}.window{max-width:1100px;margin:auto;background:#efefef;border:1px solid #888;padding:18px}.toolbar{display:flex;justify-content:space-between;gap:12px;margin-bottom:14px}.button{display:inline-block;padding:7px 12px;border:1px solid #777;background:#eee;color:#111;text-decoration:none}.doc{background:#fff;border:1px solid #aaa;padding:22px;line-height:1.5}.doc h1,.doc h2,.doc h3{color:#0000aa}.doc table{width:100%;border-collapse:collapse}.doc th,.doc td{border:1px solid #aaa;padding:6px;text-align:left;vertical-align:top}.doc code{background:#eee;padding:1px 4px}.doc pre{background:#f5f5f5;border:1px solid #ccc;padding:10px;overflow:auto}
</style></head><body><div class="window">
<div class="toolbar"><div><a class="button" href="{{ route('dashboard') }}">← Hauptmenü</a></div><div><a href="{{ route('frontend.switch','modern') }}">Zum neuen Frontend wechseln →</a></div></div>
<div class="doc">{!! $content !!}</div>
</div><script src="{{ asset('js/db-window-manager.js') }}"></script></body></html>
