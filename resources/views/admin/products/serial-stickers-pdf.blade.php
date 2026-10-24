{{-- Serial number stickers: A4 sticker paper, 4 columns x 10 rows, each
     52.5 x 29.7 mm, no page margin. Each sticker is that piece's barcode at
     the POS (Code 128 set B). --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Serial Stickers - {{ $product->ProductName }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #000; }
        .page { position: relative; width: 210mm; height: 296.9mm; page-break-after: always; overflow: hidden; }
        .page.last { page-break-after: auto; }
        .sticker { position: absolute; width: 52.5mm; height: 29.7mm; text-align: center; overflow: hidden; }
        .name { margin: 2.4mm 2mm 0; height: 3mm; font-size: 6.2pt; line-height: 3mm; white-space: nowrap; overflow: hidden; }
        .barcode { display: block; width: 46mm; height: 13mm; margin: 1mm auto 0; }
        .serial { margin-top: 0.8mm; font-size: 9pt; font-weight: bold; letter-spacing: 0.4pt; }
    </style>
</head>
<body>
@foreach($pages as $pageIndex => $pageSerials)
    <div class="page {{ $loop->last ? 'last' : '' }}">
        @foreach($pageSerials->values() as $index => $serial)
            <div class="sticker" style="left: {{ ($index % 4) * 52.5 }}mm; top: {{ intdiv($index, 4) * 29.7 }}mm;">
                <div class="name">{{ \Illuminate\Support\Str::limit($product->ProductName, 34) }}</div>
                <img class="barcode" src="{{ \App\Services\Code128Barcode::dataUri($serial->SerialNumber) }}" alt="{{ $serial->SerialNumber }}">
                <div class="serial">{{ $serial->SerialNumber }}</div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
