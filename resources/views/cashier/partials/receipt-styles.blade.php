    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            max-width: 300px;
            margin: 0 auto;
            padding: 20px 12px;
            font-size: 12px;
            color: #000;
            background: #f1f5f9;
        }
        .receipt { background: #fff; border: 1px solid #ccc; padding: 16px 14px; }

        .center { text-align: center; }
        .bold { font-weight: bold; }
        .rule { border: none; border-top: 1px dashed #000; margin: 8px 0; }

        .store-name { margin: 0; font-size: 16px; font-weight: bold; letter-spacing: 0.5px; }
        .store-line { margin: 2px 0 0; font-size: 11px; }

        .doc-title { margin: 10px 0 8px; font-size: 13px; font-weight: bold; letter-spacing: 2px; }

        .meta-row { display: flex; justify-content: space-between; margin: 2px 0; }

        .items-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items-table th, .items-table td { padding: 3px 2px; vertical-align: top; }
        .items-table thead th { font-weight: bold; border-bottom: 1px dashed #000; padding-bottom: 4px; }
        .items-table .col-desc { text-align: left; width: 52%; word-break: break-word; }
        .items-table .col-qty { text-align: right; width: 18%; white-space: nowrap; }
        .items-table .col-amt { text-align: right; width: 30%; white-space: nowrap; }

        .total-row { display: flex; justify-content: space-between; margin: 2px 0; }
        .grand-total { font-weight: bold; font-size: 14px; }

        .footer p { margin: 3px 0; }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .print-text { display: none; }

        .receipt-qr { display: flex; justify-content: center; margin-top: 10px; }
        .receipt-qr img { width: 110px; height: 110px; image-rendering: pixelated; }

        @page { margin: 0; }

        @media print {
            body { margin: 0; padding: 0; max-width: 100%; background: #fff; }
            .no-print { display: none; }
            .receipt { display: none; }
            .print-text {
                display: block;
                width: 48mm;
                margin: 0;
                font-family: Consolas, 'Courier New', monospace;
                font-size: 3mm;
                letter-spacing: -0.15mm;
                line-height: 3.75mm;
                color: #000;
            }
            .print-text .pl, .print-text .pl-tail { white-space: pre; margin: 0; font: inherit; }
            .print-text .pl-bold { font-weight: bold; }
            .print-text .pl-tall { transform: scaleY(2); transform-origin: top left; margin-bottom: 3.75mm; }
            .print-text .pl-qr { margin: 1.5mm 0; justify-content: center; }
            .print-text .pl-qr img { width: 22mm; height: 22mm; }
        }
    </style>
