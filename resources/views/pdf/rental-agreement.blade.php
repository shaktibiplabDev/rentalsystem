<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Rental Agreement</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: white;
            font-family: 'Times New Roman', Georgia, serif;
            color: #1e2a44;
            padding: 0.5in 0.6in;
            line-height: 1.5;
            font-size: 13px;
        }

        /* ── Header ── */
        .header-block {
            display: table;
            width: 100%;
            border-bottom: 3px solid #0f4c6f;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .header-left  { display: table-cell; vertical-align: bottom; }
        .header-right { display: table-cell; vertical-align: bottom; text-align: right; }

        h1 {
            font-size: 22px;
            font-weight: 900;
            color: #0f4c6f;
            letter-spacing: -0.5px;
        }
        .subtitle { font-size: 11px; color: #546a8b; margin-top: 3px; }

        .agreement-id {
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
            margin-bottom: 8px;
        }

        /* ── Cards ── */
        .card {
            border: 1px solid #dde7f4;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        /* ── 2-col row ── */
        .row2 {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 -5px 12px;
        }
        .col2 {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .col2 .card { margin-bottom: 0; }

        /* ── Info rows ── */
        .info-row {
            display: table;
            width: 100%;
            padding: 3px 0;
            border-bottom: 1px dotted #dde7f4;
            font-size: 12px;
        }
        .info-row:last-child { border-bottom: none; }
        .lbl { display: table-cell; width: 45%; font-weight: 700; color: #2c4a6e; }
        .val { display: table-cell; color: #1c2b44; }

        .badge {
            font-size: 9px;
            padding: 1px 6px;
            border-radius: 20px;
            margin-left: 4px;
            font-weight: 700;
        }
        .verified { background: #d4f4e6; color: #1a6e45; }
        .active   { background: #d0e9ff; color: #1e5a9e; }

        /* ── Pricing 4-col ── */
        .pricing-row {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 8px -4px 6px;
        }
        .price-cell {
            display: table-cell;
            background: #f4f9ff;
            border: 1px solid #dde7f4;
            border-radius: 5px;
            padding: 6px 8px;
            text-align: center;
        }
        .plabel { display: block; font-size: 9px; color: #546a8b; text-transform: uppercase; font-weight: 700; }
        .pvalue { display: block; font-size: 15px; font-weight: 800; color: #0f4c6f; }

        /* ── Page break ── */
        .page-break { page-break-before: always; padding-top: 0.3in; }

        /* ── Images ── */
        .img-row {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
        }
        .img-cell {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: center;
            background: #f4f9ff;
            border: 1px solid #dde7f4;
            border-radius: 6px;
            padding: 8px;
        }
        .img-cell h5 { font-size: 11px; margin-bottom: 6px; color: #0f4c6f; font-weight: 700; }
        .img-box {
            height: 70px;
            background: #eaf1fb;
            border-radius: 4px;
            display: table;
            width: 100%;
        }
        .img-box span {
            display: table-cell;
            vertical-align: middle;
            font-size: 10px;
            color: #6b8ab3;
            text-align: center;
        }
        .img-cell img { max-width: 100%; max-height: 70px; border-radius: 4px; object-fit: cover; }

        /* ── Terms ── */
        .terms-row {
            display: table;
            width: 100%;
            border-collapse: separate;
            border-spacing: 16px 0;
        }
        .terms-col { display: table-cell; vertical-align: top; width: 50%; }

        .terms-list { list-style: none; padding: 0; }
        .terms-list li {
            font-size: 12px;
            margin-bottom: 7px;
            padding-left: 14px;
            position: relative;
            line-height: 1.45;
        }
        .terms-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #1a9a8b;
            font-weight: bold;
            font-size: 14px;
        }

        /* ── Declaration ── */
        .declaration {
            background: #fffbf0;
            padding: 10px 13px;
            border-radius: 6px;
            font-size: 12px;
            border-left: 4px solid #f4c95d;
            margin: 12px 0 10px;
            line-height: 1.5;
        }

        /* ── Signatures ── */
        .sig-wrap { border-top: 1.5px solid #d0dcea; padding-top: 12px; margin-top: 10px; }
        .sig-table { display: table; width: 100%; }
        .sig-cell { display: table-cell; width: 50%; text-align: center; vertical-align: top; padding: 0 30px; }
        .sig-title { font-weight: 700; font-size: 13px; }
        .sig-line { border-top: 1.5px solid #1e2a44; margin: 14px auto 6px; width: 80%; }
        .sig-name { font-size: 12px; }
        .sig-sub  { font-size: 10px; color: #546a8b; margin-top: 2px; }

        /* ── Footer ── */
        .footer {
            font-size: 10px;
            text-align: center;
            color: #546a8b;
            margin-top: 14px;
            padding-top: 10px;
            border-top: 1px solid #dde7f4;
        }

        @media print {
            body { padding: 0.4in 0.45in; }
        }
    </style>
</head>
<body>

    <!-- ── Header ── -->
    <div class="header-block">
        <div class="header-left">
            <h1>VEHICLE RENTAL AGREEMENT</h1>
            <div class="subtitle">Digitally Verified &bull; Legally Binding Contract</div>
        </div>
        <div class="header-right">
            <div class="agreement-id">{{ $agreement_number ?? 'AGR-69C1BD531D1D1-20260323' }}</div>
            <div class="ref-date">{{ $date ?? '23 March 2026' }} | {{ $time ?? '22:23' }}</div>
        </div>
    </div>

    <!-- ── Row 1: Parties + License ── -->
    <div class="row2">
        <div class="col2">
            <div class="card">
                <div class="section-label">Parties to the Agreement</div>
                <div class="info-row"><span class="lbl">Rental Company:</span><span class="val">{{ $shop_owner['name'] ?? 'John Doe Rentals' }}</span></div>
                <div class="info-row"><span class="lbl">Contact No:</span><span class="val">{{ $shop_owner['phone'] ?? '9876543210' }}</span></div>
                <div class="info-row"><span class="lbl">Customer Name:</span><span class="val">{{ $customer['name'] ?? 'JOSH DOE' }}</span></div>
                <div class="info-row"><span class="lbl">Phone:</span><span class="val">{{ $customer['phone'] ?? '7895264133' }}</span></div>
            </div>
        </div>
        <div class="col2">
            <div class="card">
                <div class="section-label">Driving License Details</div>
                <div class="info-row"><span class="lbl">License Number:</span><span class="val">{{ $customer['license_number'] ?? 'AB1120040002378' }} <span class="badge verified">VERIFIED</span></span></div>
                <div class="info-row"><span class="lbl">Valid Until:</span><span class="val">{{ $customer['license_valid_to'] ?? '13 May 2027' }}</span></div>
                <div class="info-row"><span class="lbl">Date of Birth:</span><span class="val">{{ $customer['date_of_birth'] ?? '02/10/2001' }}</span></div>
            </div>
        </div>
    </div>

    <!-- ── Row 2: Vehicle + Rental ── -->
    <div class="row2">
        <div class="col2">
            <div class="card">
                <div class="section-label">Vehicle Details</div>
                <div class="info-row"><span class="lbl">Vehicle Name:</span><span class="val">{{ $vehicle['name'] ?? 'Toyota Camry' }}</span></div>
                <div class="info-row"><span class="lbl">Registration No:</span><span class="val">{{ $vehicle['number_plate'] ?? 'KA01AB7455' }}</span></div>
            </div>
        </div>
        <div class="col2">
            <div class="card">
                <div class="section-label">Rental Information</div>
                <div class="info-row"><span class="lbl">Rental ID:</span><span class="val">#{{ $rental_id ?? '16' }} <span class="badge active">ACTIVE</span></span></div>
                <div class="info-row"><span class="lbl">Start Date &amp; Time:</span><span class="val">{{ $start_time ?? '23/03/2026 22:23' }}</span></div>
                <div class="info-row"><span class="lbl">Status:</span><span class="val">On Rent &bull; Active</span></div>
            </div>
        </div>
    </div>

    <!-- ── Pricing ── -->
    <div class="card">
        <div class="section-label">Pricing &amp; Payment Terms</div>
        <div class="pricing-row">
            <div class="price-cell">
                <span class="plabel">Hourly Rate</span>
                <span class="pvalue">Rs {{ $terms['hourly_rate'] ?? '189' }}</span>
            </div>
            <div class="price-cell">
                <span class="plabel">Daily Rate</span>
                <span class="pvalue">Rs {{ $terms['daily_rate'] ?? '1,249' }}</span>
            </div>
            <div class="price-cell">
                <span class="plabel">Lease Threshold</span>
                <span class="pvalue">{{ $terms['lease_threshold'] ?? '540' }} min</span>
            </div>
            <div class="price-cell">
                <span class="plabel">Verification Fee</span>
                <span class="pvalue">Rs {{ $terms['verification_fee'] ?? '5.00' }}</span>
            </div>
        </div>
        <div style="font-size:11px; color:#546a8b; margin-top:4px;">
            Fuel, tolls, parking, and additional charges are payable by the customer. Final settlement at vehicle return.
        </div>
    </div>

    <!-- ══════════════ PAGE 2 ══════════════ -->
    <div class="page-break">

        <!-- ── Verification Documents (no vehicle condition) ── -->
        <div class="card">
            <div class="section-label">Verification Documents</div>
            <div class="img-row">
                <div class="img-cell">
                    <h5>Customer Photograph</h5>
                    @if(isset($customer['photo']) && $customer['photo'])
                        <img src="{{ $customer['photo'] }}" alt="Customer Photo">
                    @else
                        <div class="img-box"><span>Identity Verified</span></div>
                    @endif
                </div>
                <div class="img-cell">
                    <h5>Driving License</h5>
                    @if(isset($documents['license_image']) && $documents['license_image'])
                        <img src="{{ $documents['license_image'] }}" alt="Driving License">
                    @else
                        <div class="img-box"><span>RTO Verified Copy</span></div>
                    @endif
                </div>
            </div>
        </div>

        <!-- ── Terms & Conditions ── -->
        <div class="card">
            <div class="section-label">Terms and Conditions</div>
            <div class="terms-row">
                <div class="terms-col">
                    <ul class="terms-list">
                        <li>The vehicle must be returned in the same condition with the same fuel level as at the time of rental (normal wear and tear excepted).</li>
                        <li>All traffic fines, challans, tolls, and parking charges incurred during the rental period are the sole responsibility of the customer.</li>
                        <li>Late return beyond 30 minutes will attract pro-rata hourly charges. A full daily rate applies after 3 hours of delay.</li>
                        <li>Sub-leasing, racing, off-roading, transporting prohibited goods, or any illegal use of the vehicle is strictly prohibited.</li>
                    </ul>
                </div>
                <div class="terms-col">
                    <ul class="terms-list">
                        <li>The customer shall be liable for any damage to the vehicle up to Rs 5,000. Insurance shall cover major damages subject to applicable deductible.</li>
                        <li>The rental company reserves the right to terminate this agreement and repossess the vehicle immediately upon any breach of these terms.</li>
                        <li>The customer must carry the original driving license at all times while operating the vehicle. Photocopies are not acceptable.</li>
                        <li>Any disputes arising from this agreement shall be subject to the exclusive jurisdiction of courts at the rental office location.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ── Declaration ── -->
        <div class="declaration">
            <strong>Customer Declaration:</strong> I have personally inspected the vehicle and accept its current condition including any pre-existing scratches or dents as noted. I have read, understood, and agree to abide by all the terms and conditions mentioned in this agreement and confirm that all information provided by me is accurate and true.
        </div>

        <!-- ── Signatures ── -->
        <div class="sig-wrap">
            <div class="sig-table">
                <div class="sig-cell">
                    <div class="sig-title">Customer Signature</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $customer['name'] ?? 'JOSH DOE' }}</div>
                    <div class="sig-sub">Date: {{ $date ?? '23 March 2026' }}</div>
                </div>
                <div class="sig-cell">
                    <div class="sig-title">For Rental Company</div>
                    <div class="sig-line"></div>
                    <div class="sig-name">{{ $shop_owner['name'] ?? 'John Doe' }}</div>
                    <div class="sig-sub">Authorized Signatory &bull; Official Stamp</div>
                </div>
            </div>
        </div>

        <!-- ── Footer ── -->
        <div class="footer">
            This is a digitally generated rental agreement and is legally binding.<br>
            Agreement ID: {{ $agreement_number ?? 'AGR-69C1BD531D1D1-20260323' }} &nbsp;|&nbsp; Generated on: {{ $generated_at ?? '23 March 2026, 22:23' }}<br>
            <strong>* All details have been verified through government records. *</strong>
        </div>

    </div>

</body>
</html>