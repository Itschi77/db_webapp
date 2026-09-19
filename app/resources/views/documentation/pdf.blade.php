<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>{{ $title }}</title>
<style>
@page { margin: 18mm 16mm 18mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; line-height: 1.42; color: #1f2937; }
h1 { font-size: 19pt; color: #0f172a; margin: 0 0 12pt; }
h2 { font-size: 14pt; color: #0f172a; margin: 16pt 0 7pt; page-break-after: avoid; }
h3 { font-size: 11.5pt; color: #1e293b; margin: 12pt 0 5pt; page-break-after: avoid; }
h4 { font-size: 10pt; margin: 10pt 0 4pt; page-break-after: avoid; }
p, ul, ol { margin: 0 0 7pt; }
li { margin-bottom: 2pt; }
table { width: 100%; border-collapse: collapse; margin: 8pt 0 12pt; font-size: 8.5pt; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: 0.5pt solid #94a3b8; padding: 4pt; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
th { background: #e2e8f0; font-weight: bold; }
code { font-family: DejaVu Sans Mono, monospace; font-size: 8.2pt; background: #f1f5f9; padding: 0 2pt; }
pre { font-family: DejaVu Sans Mono, monospace; font-size: 7.4pt; line-height: 1.3; background: #f8fafc; border: 0.5pt solid #cbd5e1; padding: 6pt; white-space: pre-wrap; word-break: break-word; }
pre code { background: transparent; padding: 0; }
blockquote { margin: 8pt 0; padding: 6pt 9pt; border-left: 2pt solid #94a3b8; background: #f8fafc; }
a { color: #1d4ed8; text-decoration: none; }
hr { border: 0; border-top: 0.5pt solid #cbd5e1; margin: 12pt 0; }
.meta { margin-bottom: 14pt; padding-bottom: 8pt; border-bottom: 0.7pt solid #94a3b8; color: #64748b; font-size: 8.5pt; }
.footer { position: fixed; bottom: -11mm; left: 0; right: 0; text-align: center; color: #64748b; font-size: 7.5pt; }
.footer .page:after { content: counter(page); }
</style>
</head>
<body>
<div class="footer">{{ $title }} · Seite <span class="page"></span></div>
<h1>{{ $title }}</h1>
<div class="meta">PDF-Export der DB-Webapp · erzeugt am {{ $generatedAt->format('d.m.Y H:i') }}</div>
{!! $content !!}
</body>
</html>
