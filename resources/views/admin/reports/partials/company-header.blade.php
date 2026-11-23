@php
    $logoDataUri = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('Images/logo.png')));
@endphp
<div class="company-header">
    <img src="{{ $logoDataUri }}" alt="CCTV Express Solution" class="company-logo">
    <h1>CCTV Express</h1>
    <p>Your Trusted Security Partner</p>
</div>
