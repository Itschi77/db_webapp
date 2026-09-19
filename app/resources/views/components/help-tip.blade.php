@props(['title'])
<span class="help-tip" tabindex="0" role="button" aria-label="Hilfe: {{ $title }}">
    <span class="help-tip-icon" aria-hidden="true">?</span>
    <span class="help-tip-popup" role="tooltip">
        <strong>{{ $title }}</strong>
        <span>{{ $slot }}</span>
    </span>
</span>
