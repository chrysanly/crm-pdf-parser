{{-- Name, job title and contact line — shared by every logo placement. --}}
<h2 class="name">{{ $header['name'] }}</h2>

@if ($header['headline'] !== null)
    <div class="headline">{{ $header['headline'] }}</div>
@endif

@if ($header['contact_lines'] !== [])
    <div class="contact">{{ implode(' · ', $header['contact_lines']) }}</div>
@endif
