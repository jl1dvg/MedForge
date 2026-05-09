@php
    $fmt = static fn($value, $currency = 'USD') => ($currency ?: 'USD') . ' ' . number_format((float) $value, 2, '.', ',');
    $currency = (string) ($proposal['currency'] ?? 'USD');
    $brand = is_array($brand ?? null) ? $brand : [];
    $companyName = (string) ($brand['name'] ?? 'Consulmed');
    $logoUrl = (string) ($brand['logo_url'] ?? '');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $proposal['proposal_number'] ?? 'Propuesta' }} | {{ $companyName }}</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <style>
        body { background: linear-gradient(135deg, #ecfeff, #f8fafc 45%, #fff7ed); color: #172033; }
        .proposal-shell { max-width: 980px; margin: 32px auto; padding: 0 16px; }
        .proposal-card { background: #fff; border: 1px solid #dbe4ee; border-radius: 22px; box-shadow: 0 24px 70px rgba(15, 23, 42, .12); overflow: hidden; }
        .proposal-hero { background: #0f766e; color: #fff; padding: 28px; }
        .proposal-logo { max-height: 52px; max-width: 180px; object-fit: contain; background: rgba(255,255,255,.94); border-radius: 12px; padding: 8px; }
        .proposal-body { padding: 28px; }
        .total-box { background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 16px; padding: 18px; }
        .table th { color: #64748b; font-size: 12px; text-transform: uppercase; }
    </style>
</head>
<body>
    <main class="proposal-shell">
        <section class="proposal-card">
            <div class="proposal-hero d-flex flex-wrap justify-content-between gap-3">
                <div>
                    @if($logoUrl !== '')
                        <img class="proposal-logo mb-3" src="{{ $logoUrl }}" alt="{{ $companyName }}">
                    @endif
                    <h1 class="h3 mb-1">{{ $companyName }}</h1>
                    <p class="mb-0 opacity-75">Propuesta clínica comercial</p>
                </div>
                <div class="text-end">
                    <div class="badge text-bg-light text-dark">{{ strtoupper((string) ($proposal['status'] ?? 'draft')) }}</div>
                    <div class="mt-2 fw-bold">{{ $proposal['proposal_number'] ?? ('Propuesta #' . $proposal['id']) }}</div>
                </div>
            </div>
            <div class="proposal-body">
                <div class="row g-4">
                    <div class="col-md-7">
                        <h2 class="h4">{{ $proposal['title'] ?? 'Propuesta' }}</h2>
                        <p class="text-muted mb-1">Paciente: {{ $proposal['lead_name'] ?? $proposal['customer_name'] ?? 'Paciente' }}</p>
                        <p class="text-muted">HC: {{ $proposal['lead_hc_number'] ?? '—' }}</p>
                    </div>
                    <div class="col-md-5">
                        <div class="total-box">
                            <div class="text-muted small">Total propuesta</div>
                            <div class="display-6 fw-bold text-success">{{ $fmt($proposal['total'] ?? 0, $currency) }}</div>
                            <div class="small text-muted">Vigencia: {{ !empty($proposal['valid_until']) ? \Carbon\Carbon::parse($proposal['valid_until'])->format('d/m/Y') : 'Sin vigencia' }}</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="text-end">Cant.</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php
                                    $line = ((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_price'] ?? 0));
                                    $discount = $line * (((float) ($item['discount_percent'] ?? 0)) / 100);
                                @endphp
                                <tr>
                                    <td>{{ $item['description'] ?? 'Ítem' }}</td>
                                    <td class="text-end">{{ number_format((float) ($item['quantity'] ?? 0), 2) }}</td>
                                    <td class="text-end">{{ $fmt($item['unit_price'] ?? 0, $currency) }}</td>
                                    <td class="text-end">{{ $fmt($line - $discount, $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(!empty($proposal['notes']))
                    <div class="alert alert-light border mt-3">
                        <strong>Notas:</strong><br>
                        {{ $proposal['notes'] }}
                    </div>
                @endif

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                    <a class="btn btn-outline-secondary" href="{{ $pdfUrl }}" target="_blank" rel="noopener">Descargar PDF</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
