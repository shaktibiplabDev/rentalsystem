<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vehicle Rental Receipt</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: white;
            font-family: 'Times New Roman', Georgia, serif;
            color: #1e2a44;
            padding: 0.45in 0.55in;
            font-size: 13px;
            line-height: 1.5;
        }

        /* ── Header ── */
        .header-block {
            display: table;
            width: 100%;
            border-bottom: 3px solid #0f4c6f;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-left  { display: table-cell; vertical-align: bottom; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; }

        h1 {
            font-size: 21px;
            font-weight: 900;
            color: #0f4c6f;
            letter-spacing: -0.4px;
        }
        .subtitle { font-size: 11px; color: #546a8b; margin-top: 3px; }

        .receipt-id {
            font-family: monospace;
            font-size: 11px;
            background: #e6f0fa;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            color: #0f4c6f;
            display: inline-block;
        }
        .ref-date { font-size: 11px; color: #546a8b; margin-top: 5px; }

        /* ── Section label ── */
        .section-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #0f4c6f;
            border-left: 3px solid #1a9a8b;
            padding-left: 7px;
            margin-bottom: 7px;
        }

        /* ── Card ── */
        .card {
            border: 1px solid #dde7f4;
            border-radius: 6px;
            padding: 9px 11px;
            margin-bottom: 10px;
        }

        /* ── 2-col row ── */
        .row2 {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 10px;
        }
        .col2 { display: table-cell; width: 50%; vertical-align: top; }
        .col2 .card { margin-bottom: 0; }

        /* ── Info rows ── */
        .info-row {
            display: table;
            width: 100%;
            font-size: 12px;
            padding: 3px 0;
            border-bottom: 1px dotted #dde7f4;
        }
        .info-row:last-child { border-bottom: none; }
        .lbl { display: table-cell; width: 42%; font-weight: 700; color: #2c4a6e; }
        .val { display: table-cell; color: #1c2b44; }

        .paid-badge {
            display: inline-block;
            background: #d4f4e6;
            color: #1a6e45;
            font-size: 10px;
            font-weight: 800;
            padding: 1px 9px;
            border-radius: 20px;
            letter-spacing: 0.5px;
        }

        /* ── Charges table ── */
        .charges-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-top: 6px;
        }
        .charges-table th {
            background: #f4f9ff;
            font-weight: 700;
            color: #2c4a6e;
            padding: 6px 9px;
            border: 1px solid #dde7f4;
            text-align: left;
        }
        .charges-table th:last-child { text-align: right; }
        .charges-table td {
            padding: 5px 9px;
            border: 1px solid #dde7f4;
            color: #1c2b44;
        }
        .charges-table td:last-child { text-align: right; }
        .total-row td {
            background: #f4f9ff;
            font-weight: 800;
            font-size: 14px;
            color: #0f4c6f;
        }

        /* ── Thank you ── */
        .thankyou {
            text-align: center;
            margin: 12px 0 10px;
            font-size: 13px;
            color: #1a9a8b;
            font-weight: 700;
        }

        /* ── Footer ── */
        .footer {
            font-size: 10px;
            text-align: center;
            color: #546a8b;
            margin-top: 10px;
            padding-top: 9px;
            border-top: 1px solid #dde7f4;
        }

        @media print {
            body { padding: 0.35in 0.45in; }
        }
    </style>
</head>
<body>

    <!-- ── Header ── -->
    <div class="header-block">
        <div class="header-left">
            <h1>VEHICLE RENTAL RECEIPT</h1>
            <div class="subtitle">{{ $shop_owner['name'] ?? 'Vehicle Rental Shop' }} &nbsp;&bull;&nbsp; Phone: {{ $shop_owner['phone'] ?? 'N/A' }}</div>
        </div>
        <div class="header-right">
            <div class="receipt-id">{{ $receipt_number ?? 'RCP-001-20260323' }}</div>
            <div class="ref-date">{{ $date ?? '23 March 2026' }} | {{ $time ?? '22:23' }}</div>
        </div>
    </div>

    <!-- ── Row 1: Receipt Details + Customer Info ── -->
    <div class="row2">
        <div class="col2">
            <div class="card">
                <div class="section-label">Receipt Details</div>
                <div class="info-row"><span class="lbl">Rental ID:</span><span class="val">#{{ $rental_id ?? '16' }}</span></div>
                <div class="info-row"><span class="lbl">Payment Status:</span><span class="val"><span class="paid-badge">PAID</span></span></div>
                <div class="info-row"><span class="lbl">Payment Method:</span><span class="val">{{ $payment_method ?? 'Cash' }}</span></div>
            </div>
        </div>
        <div class="col2">
            <div class="card">
                <div class="section-label">Customer Information</div>
                <div class="info-row"><span class="lbl">Name:</span><span class="val">{{ $customer['name'] ?? 'JOSH DOE' }}</span></div>
                <div class="info-row"><span class="lbl">Phone:</span><span class="val">{{ $customer['phone'] ?? '7895264133' }}</span></div>
                @if(isset($customer['address']))
                <div class="info-row"><span class="lbl">Address:</span><span class="val">{{ $customer['address'] }}</span></div>
                @endif
            </div>
        </div>
    </div>

    <!-- ── Row 2: Vehicle Info + Rental Period ── -->
    <div class="row2">
        <div class="col2">
            <div class="card">
                <div class="section-label">Vehicle Information</div>
                <div class="info-row"><span class="lbl">Vehicle Name:</span><span class="val">{{ $vehicle['name'] ?? 'Toyota Camry' }}</span></div>
                <div class="info-row"><span class="lbl">Number Plate:</span><span class="val">{{ $vehicle['number_plate'] ?? 'KA01AB7455' }}</span></div>
                <div class="info-row"><span class="lbl">Hourly Rate:</span><span class="val">Rs {{ $vehicle['hourly_rate'] ?? '189' }}/hour</span></div>
                <div class="info-row"><span class="lbl">Daily Rate:</span><span class="val">Rs {{ $vehicle['daily_rate'] ?? '1,249' }}/day</span></div>
            </div>
        </div>
        <div class="col2">
            <div class="card">
                <div class="section-label">Rental Period</div>
                <div class="info-row"><span class="lbl">Start Time:</span><span class="val">{{ $rental_period['start_time'] ?? '23/03/2026 22:23' }}</span></div>
                <div class="info-row"><span class="lbl">End Time:</span><span class="val">{{ $rental_period['end_time'] ?? '24/03/2026 08:23' }}</span></div>
                <div class="info-row"><span class="lbl">Total Duration:</span><span class="val">{{ $rental_period['duration'] ?? '10 hours' }}</span></div>
                <div class="info-row"><span class="lbl">Hours Charged:</span><span class="val">{{ $rental_period['hours_charged'] ?? '10' }} hrs &nbsp;(Min: {{ $rental_period['lease_threshold'] ?? '540' }} min)</span></div>
            </div>
        </div>
    </div>

    <!-- ── Charges Breakdown ── -->
    <div class="card">
        <div class="section-label">Charges Breakdown</div>
        <table class="charges-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Document Verification Fee</td>
                    <td>Rs {{ $charges['verification_fee'] ?? '5.00' }}</td>
                </tr>
                <tr>
                    <td>Rental Charges ({{ $rental_period['hours_charged'] ?? '10' }} hours &times; Rs {{ $vehicle['hourly_rate'] ?? '189' }})</td>
                    <td>Rs {{ $charges['rental_amount'] ?? '1,890.00' }}</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL AMOUNT</td>
                    <td>Rs {{ $charges['total'] ?? '1,895.00' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ── Thank You ── -->
    <div class="thankyou">
        Thank you for choosing our service! &nbsp; We hope to serve you again.
    </div>

    <!-- ── Footer ── -->
    <div class="footer">
        This is a computer-generated receipt and does not require a physical signature.<br>
        Generated on {{ $generated_at ?? '23 March 2026, 22:23' }} &nbsp;|&nbsp; For queries, contact {{ $shop_owner['phone'] ?? 'the shop' }}
    </div>

</body>
</html>