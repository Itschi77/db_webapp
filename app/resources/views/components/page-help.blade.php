@props(['title'])
@once
<style>
.page-help{position:fixed;right:22px;top:18px;z-index:1000}.page-help-button{width:27px;height:27px;border-radius:50%;border:1px solid #b9c8d8;background:#eef4fb;color:#1f4f8a;font:800 14px/1 system-ui,Segoe UI,Arial;display:flex;align-items:center;justify-content:center;cursor:help;outline:none;box-shadow:0 2px 7px #0001}.page-help-button:hover,.page-help-button:focus{background:#dbe9f8}.page-help-popup{display:none;position:absolute;right:0;top:34px;width:min(390px,82vw);padding:12px 13px;background:#fff;color:#263548;border:1px solid #cbd5e1;border-radius:9px;box-shadow:0 10px 28px #0002;font:12px/1.48 system-ui,Segoe UI,Arial;text-align:left}.page-help-popup strong{display:block;margin-bottom:6px;color:#172033;font-size:13px}.page-help-button:hover+.page-help-popup,.page-help-button:focus+.page-help-popup{display:block}@media(max-width:700px){.page-help{right:10px;top:10px}}
</style>
@endonce
<div class="page-help">
  <span class="page-help-button" tabindex="0" role="button" aria-label="Hilfe: {{ $title }}">?</span>
  <div class="page-help-popup" role="tooltip"><strong>{{ $title }}</strong><div>{{ $slot }}</div></div>
</div>
