{{--
    The ATS document as a PDF — the preview's content and nothing else.

    Renders exactly what AtsResumeFormatter returned, the same array the React
    preview consumes (DESIGN §1.4), so the two cannot disagree about order or
    content. Kept to dompdf-safe CSS: no flexbox, no grid, no custom fonts —
    tables and floats only. Text stays real text, which is the whole point of an
    ATS resume.

    @var array<string, mixed> $ats
    @var string $brandColor
    @var string|null $logoData  data: URI, or null
    @var string|null $companyName
--}}
@php
    $header = $ats['header'];
    $layout = $ats['template'];
    $ruled = $layout === 'professional';
    $compact = $layout === 'compact';
    $logo = $header['logo'];
    $placement = $logo['placement'] ?? 'right';
    $logoPixels = $logo['pixels'] ?? 0;
    $showLogo = $logoData !== null && $logo !== null;
    $baseSize = $compact ? '10.5pt' : ($ruled ? '10.5pt' : '11pt');
@endphp
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $header['name'] }}</title>
    <style>
        @page {
            margin: 14mm 14mm 16mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ $baseSize }};
            line-height: {{ $compact ? 1.32 : 1.45 }};
            color: #171717;
            margin: 0;
        }

        h1, h2, h3 {
            margin: 0;
            font-weight: bold;
        }

        .name {
            font-size: {{ $compact ? '17pt' : '19pt' }};
            letter-spacing: -0.2pt;
            @if ($layout === 'modern') color: {{ $brandColor }}; @endif
        }

        .headline {
            font-size: {{ $compact ? '11pt' : '12pt' }};
            color: #404040;
            margin-top: 2pt;
        }

        .contact {
            font-size: 9.5pt;
            color: #525252;
            margin-top: 3pt;
        }

        .doc-header {
            padding-bottom: 8pt;
            @if (! $ruled)
                border-bottom: {{ $layout === 'modern' ? '2pt solid '.$brandColor : '1pt solid #d4d4d4' }};
            @endif
        }

        .centred {
            text-align: center;
        }

        .section {
            padding-top: {{ $ruled ? '10pt' : '9pt' }};
            @if (! $ruled) border-top: 1pt solid #e5e5e5; @endif
        }

        .doc-header + .section {
            border-top: 0;
        }

        .section-label {
            font-size: 8.5pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            margin-bottom: 4pt;
            @if ($ruled)
                border-bottom: 1pt solid #a3a3a3;
                padding-bottom: 2pt;
            @elseif ($layout !== 'classic')
                color: {{ $brandColor }};
            @endif
        }

        .entry {
            margin-bottom: {{ $compact ? '5pt' : '7pt' }};
        }

        .entry-primary {
            font-weight: bold;
        }

        .entry-meta {
            color: #525252;
        }

        .period {
            color: #525252;
            font-size: 9.5pt;
        }

        ul {
            margin: 2pt 0 0;
            padding-left: 13pt;
        }

        li {
            margin-bottom: 1pt;
            color: #262626;
        }

        .tag-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tag-list li {
            display: inline-block;
            border: 0.6pt solid #d4d4d4;
            background: #fafafa;
            padding: 1pt 4pt;
            margin: 0 2pt 2pt 0;
            font-size: 9pt;
        }

        table.layout {
            width: 100%;
            border-collapse: collapse;
        }

        table.layout td {
            vertical-align: top;
            padding: 0;
        }

        .detail-label {
            font-weight: bold;
            padding-right: 8pt;
            white-space: nowrap;
        }
    </style>
</head>
<body>

<div class="doc-header">
    @if ($showLogo && $placement === 'centre')
        <div class="centred">
            <img src="{{ $logoData }}" alt="" height="{{ $logoPixels }}">
        </div>
        <div class="centred">
            @include('pdf.partials.ats-name', ['header' => $header])
        </div>
    @elseif ($showLogo)
        <table class="layout">
            <tr>
                @if ($placement === 'left')
                    <td width="{{ $logoPixels + 16 }}"><img src="{{ $logoData }}" alt=""
                                                            height="{{ $logoPixels }}"></td>
                    <td class="{{ $header['centred'] ? 'centred' : '' }}">
                        @include('pdf.partials.ats-name', ['header' => $header])
                    </td>
                @else
                    <td class="{{ $header['centred'] ? 'centred' : '' }}">
                        @include('pdf.partials.ats-name', ['header' => $header])
                    </td>
                    <td width="{{ $logoPixels + 16 }}" align="right"><img src="{{ $logoData }}" alt=""
                                                                         height="{{ $logoPixels }}"></td>
                @endif
            </tr>
        </table>
    @else
        <div class="{{ $header['centred'] ? 'centred' : '' }}">
            @include('pdf.partials.ats-name', ['header' => $header])
        </div>
    @endif
</div>

@foreach ($ats['sections'] as $section)
    <div class="section">
        <h3 class="section-label">{{ $section['label'] }}</h3>

        @if ($section['type'] === 'text')
            <p style="margin: 0; color: #262626;">{{ $section['text'] }}</p>

        @elseif ($section['type'] === 'details')
            <table class="layout">
                @foreach ($section['rows'] as $row)
                    <tr>
                        <td class="detail-label">{{ $row['label'] }}:</td>
                        <td>{{ $row['value'] }}</td>
                    </tr>
                @endforeach
            </table>

        @elseif ($section['type'] === 'skill_groups')
            @foreach ($section['groups'] as $group)
                <div>
                    @if ($group['label'] !== null)
                        <span class="entry-primary">{{ $group['label'] }}:</span>
                    @endif
                    <span>{{ implode(', ', $group['items']) }}</span>
                </div>
            @endforeach

        @elseif ($section['type'] === 'tags')
            <ul class="tag-list">
                @foreach ($section['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

        @elseif ($section['type'] === 'list')
            <ul>
                @foreach ($section['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>

        @elseif ($section['type'] === 'timeline')
            @foreach ($section['entries'] as $entry)
                <div class="entry">
                    @if ($ruled)
                        <div class="entry-primary">
                            {{ $entry['primary'] }}@if ($entry['secondary'] !== null) - {{ $entry['secondary'] }}@endif
                            @if ($entry['location'] !== null) - {{ $entry['location'] }}@endif
                        </div>
                        @if ($entry['period'] !== '')
                            <div class="period">{{ $entry['period'] }}</div>
                        @endif
                    @else
                        <table class="layout">
                            <tr>
                                <td class="entry-primary">{{ $entry['primary'] }}</td>
                                @if ($entry['period'] !== '')
                                    <td class="period" align="right" width="30%">{{ $entry['period'] }}</td>
                                @endif
                            </tr>
                        </table>
                        @if ($entry['secondary'] !== null || $entry['location'] !== null)
                            <div class="entry-meta">
                                {{ implode(' — ', array_filter([$entry['secondary'], $entry['location']])) }}
                            </div>
                        @endif
                    @endif

                    @if ($entry['highlights'] !== [])
                        <ul>
                            @foreach ($entry['highlights'] as $highlight)
                                <li>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
@endforeach

</body>
</html>
