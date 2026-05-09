@php
    $fmt = static fn($value, $currency = 'USD') => ($currency ?: 'USD') . ' ' . number_format((float) $value, 2, '.', ',');
    $date = static fn($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : 'Sin vigencia';
    $currency = (string) ($proposal['currency'] ?? 'USD');
    $brand = is_array($brand ?? null) ? $brand : [];
    $companyName = (string) ($brand['name'] ?? 'Consulmed');
    $companyLegalName = (string) ($brand['legal_name'] ?? '');
    $logoPath = (string) ($brand['logo_path'] ?? '');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; color: #172033; font-size: 11px; }
        .header { width: 100%; border-bottom: 3px solid #0f766e; padding-bottom: 14px; margin-bottom: 20px; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .logo-cell { width: 118px; padding-right: 14px; }
        .logo { max-width: 105px; max-height: 58px; }
        .brand { font-size: 22px; font-weight: 800; color: #0f766e; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #ecfeff; color: #0e7490; font-weight: 700; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .grid td { vertical-align: top; width: 50%; padding: 0 10px 0 0; }
        .box { border: 1px solid #dbe4ee; border-radius: 8px; padding: 12px; background: #f8fafc; }
        .items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .items th { background: #0f766e; color: #fff; padding: 8px; text-align: left; }
        .items td { border-bottom: 1px solid #e2e8f0; padding: 8px; }
        .right { text-align: right; }
        .totals { width: 42%; margin-left: auto; border-collapse: collapse; margin-top: 16px; }
        .totals td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
        .total-row td { font-size: 14px; font-weight: 800; color: #0f766e; }
        .notes { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; color: #64748b; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            @if($logoPath !== '')
                <td class="logo-cell"><img class="logo" src="{{ $logoPath }}" alt="{{ $companyName }}"></td>
            @endif
            <td>
                <div class="brand">{{ $companyName }}</div>
                @if($companyLegalName !== '')
                    <div class="muted">{{ $companyLegalName }}</div>
                @endif
                <div class="muted">Propuesta clínica comercial</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td>
                <div class="box">
                    <div class="badge">{{ strtoupper((string) ($proposal['status'] ?? 'draft')) }}</div>
                    <h2 style="margin: 10px 0 4px;">{{ $proposal['proposal_number'] ?? ('Propuesta #' . $proposal['id']) }}</h2>
                    <div><strong>Título:</strong> {{ $proposal['title'] ?? 'Propuesta' }}</div>
                    <div><strong>Vigencia:</strong> {{ $date($proposal['valid_until'] ?? null) }}</div>
                    <div><strong>Fecha:</strong> {{ $date($proposal['created_at'] ?? now()) }}</div>
                </div>
            </td>
            <td>
                <div class="box">
                    <h3 style="margin-top: 0;">Paciente / Lead</h3>
                    <div><strong>Nombre:</strong> {{ $proposal['lead_name'] ?? $proposal['customer_name'] ?? 'Paciente' }}</div>
                    <div><strong>HC:</strong> {{ $proposal['lead_hc_number'] ?? '—' }}</div>
                    <div><strong>Correo:</strong> {{ $proposal['lead_email'] ?? '—' }}</div>
                    <div><strong>Teléfono:</strong> {{ $proposal['lead_phone'] ?? '—' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="right">Cant.</th>
                <th class="right">Precio</th>
                <th class="right">Desc.</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                @php
                    $line = ((float) ($item['quantity'] ?? 0)) * ((float) ($item['unit_price'] ?? 0));
                    $discount = $line * (((float) ($item['discount_percent'] ?? 0)) / 100);
                @endphp
                <tr>
                    <td>{{ $item['description'] ?? 'Ítem' }}</td>
                    <td class="right">{{ number_format((float) ($item['quantity'] ?? 0), 2) }}</td>
                    <td class="right">{{ $fmt($item['unit_price'] ?? 0, $currency) }}</td>
                    <td class="right">{{ number_format((float) ($item['discount_percent'] ?? 0), 2) }}%</td>
                    <td class="right">{{ $fmt($line - $discount, $currency) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Sin ítems registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $fmt($proposal['subtotal'] ?? 0, $currency) }}</td></tr>
        <tr><td>Descuento</td><td class="right">{{ $fmt($proposal['discount_total'] ?? 0, $currency) }}</td></tr>
        <tr><td>Impuesto {{ number_format((float) ($proposal['tax_rate'] ?? 0), 2) }}%</td><td class="right">{{ $fmt($proposal['tax_total'] ?? 0, $currency) }}</td></tr>
        <tr class="total-row"><td>Total</td><td class="right">{{ $fmt($proposal['total'] ?? 0, $currency) }}</td></tr>
    </table>

    @if(!empty($proposal['notes']) || !empty($proposal['terms']))
        <div class="notes">
            @if(!empty($proposal['notes']))
                <h3>Notas</h3>
                <p>{{ $proposal['notes'] }}</p>
            @endif
            @if(!empty($proposal['terms']))
                <h3>Condiciones</h3>
                <p>{{ $proposal['terms'] }}</p>
            @endif
        </div>
    @endif

    <div class="notes">
        <strong>Enlace de revisión:</strong> {{ $publicUrl }}
    </div>

    <div class="footer">Documento generado por MedForge / {{ $companyName }}.</div>
</body>
</html>
