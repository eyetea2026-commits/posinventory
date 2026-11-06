{{-- Company branding at the top of every printed report (report-header,
     details-print). --}}
@php
    // A data URI (not a URL or filesystem path) is the one representation
    // that works unmodified in both consumers of this partial: the browser
    // Print Preview (which can't load a C:\... filesystem path) and dompdf
    // (which would otherwise need remote-fetch enabled to load a URL).
    $logoDataUri = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('Images/logo.png')));
@endphp
<div class="company-header">
    <img src="{{ $logoDataUri }}" alt="CCTV Express Solution" class="company-logo">
    <h1>CCTV Express</h1>
    <p>Your Trusted Security Partner</p>
</div>
