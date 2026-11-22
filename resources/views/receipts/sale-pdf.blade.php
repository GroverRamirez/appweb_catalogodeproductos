<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de Venta N° {{ str_pad((string) $sale->numero_recibo, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { margin: 40px 32px 40px 32px; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .tienda-nombre {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }

        .tienda-info {
            font-size: 8px;
            color: #64748b;
            line-height: 1.4;
        }

        .recibo-box {
            text-align: right;
            vertical-align: top;
        }

        .recibo-badge {
            display: inline-block;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 4px;
            text-align: center;
        }

        .recibo-titulo {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            color: #475569;
            letter-spacing: 0.5px;
        }

        .recibo-numero {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }

        .datos-table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }

        .datos-table td {
            vertical-align: top;
            width: 50%;
            padding: 4px 0;
        }

        .card-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
            font-size: 9px;
        }

        .card-info h4 {
            margin: 0 0 6px 0;
            font-size: 10px;
            text-transform: uppercase;
            color: #334155;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        .card-info p {
            margin: 2px 0;
            color: #475569;
        }

        .card-info strong {
            color: #1e293b;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .items-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            text-align: left;
        }

        .items-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }

        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .totales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .totales-table td {
            padding: 4px 8px;
            font-size: 9px;
        }

        .total-destacado {
            background-color: #f1f5f9;
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
        }

        .footer-note {
            margin-top: 30px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            text-align: center;
            font-size: 8px;
            color: #64748b;
            line-height: 1.5;
        }

        .estado-anulado {
            color: #dc2626;
            font-weight: bold;
            text-transform: uppercase;
        }

        .estado-confirmado {
            color: #16a34a;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="vertical-align: middle; width: 60%;">
                @if($logo)
                    <img src="{{ $logo }}" style="max-height: 48px; max-width: 160px; margin-bottom: 4px;" alt="Logo"><br>
                @endif
                <span class="tienda-nombre">{{ $tienda['nombre'] }}</span>
                <div class="tienda-info">
                    @if(!empty($tienda['direccion'])) {{ $tienda['direccion'] }}<br> @endif
                    @if(!empty($tienda['telefono'])) WhatsApp / Tel: {{ $tienda['telefono'] }}<br> @endif
                    @if(!empty($tienda['email'])) {{ $tienda['email'] }} @endif
                </div>
            </td>
            <td class="recibo-box">
                <div class="recibo-badge">
                    <div class="recibo-titulo">Comprobante de Venta</div>
                    <div class="recibo-numero">N° {{ str_pad((string) $sale->numero_recibo, 6, '0', STR_PAD_LEFT) }}</div>
                    <div style="font-size: 8px; color: #64748b; margin-top: 2px;">
                        Fecha: {{ $sale->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="datos-table">
        <tr>
            <td style="padding-right: 8px;">
                <div class="card-info">
                    <h4>Datos del Cliente</h4>
                    <p><strong>Cliente:</strong> {{ $sale->cliente_nombre ?: ($sale->client?->name ?: 'Consumidor Final') }}</p>
                    @if($sale->client?->id_card || $sale->customer_phone)
                        <p><strong>C.I. / NIT:</strong> {{ $sale->client?->id_card ?: 'S/N' }}</p>
                        <p><strong>Teléfono:</strong> {{ $sale->cliente_telefono ?: ($sale->client?->phone ?: 'S/N') }}</p>
                    @endif
                    @if($sale->client?->email)
                        <p><strong>Email:</strong> {{ $sale->client->email }}</p>
                    @endif
                </div>
            </td>
            <td style="padding-left: 8px;">
                <div class="card-info">
                    <h4>Detalles de la Transacción</h4>
                    <p><strong>Estado:</strong> 
                        @if($sale->estado === 'confirmada')
                            <span class="estado-confirmado">Confirmada</span>
                        @else
                            <span class="estado-anulado">Anulada</span>
                        @endif
                    </p>
                    <p><strong>Método de Pago:</strong> {{ ucfirst($sale->metodo_pago ?? 'Efectivo') }}</p>
                    <p><strong>Origen:</strong> {{ ucfirst($sale->origen ?? 'Mostrador') }}</p>
                    @if($sale->tipo_cambio)
                        <p><strong>T/C Aplicado:</strong> {{ number_format((float) $sale->tipo_cambio, 2) }} Bs/USD</p>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 12%;">Código</th>
                <th style="width: 48%;">Descripción del Producto</th>
                <th class="text-center" style="width: 10%;">Cant.</th>
                <th class="text-right" style="width: 15%;">P. Unit (Bs)</th>
                <th class="text-right" style="width: 15%;">Subtotal (Bs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->producto_codigo_copia ?: '-' }}</td>
                    <td><strong>{{ $item->producto_nombre_copia }}</strong></td>
                    <td class="text-center">{{ $item->cantidad }}</td>
                    <td class="text-right">{{ number_format((float) $item->precio_unitario, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($item->cantidad * $item->precio_unitario), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 15px;">
                @if(!empty($sale->notas))
                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 8px; border-radius: 4px; font-size: 8px; color: #475569;">
                        <strong>Notas adicionales:</strong><br>
                        {{ $sale->notas }}
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totales-table">
                    <tr>
                        <td class="text-right"><strong>Subtotal:</strong></td>
                        <td class="text-right" style="width: 110px;">{{ $moneda($sale->subtotal) }}</td>
                    </tr>
                    @if((float) $sale->descuento_monto > 0)
                        <tr>
                            <td class="text-right" style="color: #16a34a;"><strong>Descuento:</strong></td>
                            <td class="text-right" style="color: #16a34a;">- {{ $moneda($sale->descuento_monto) }}</td>
                        </tr>
                    @endif
                    <tr class="total-destacado">
                        <td class="text-right">TOTAL A PAGAR:</td>
                        <td class="text-right">{{ $moneda($sale->total) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        <p>¡Gracias por tu compra en <strong>{{ $tienda['nombre'] }}</strong>!</p>
        <p>Este documento es un comprobante de control interno emitido por nuestro sistema web.<br>
        Para consultas técnicas, garantías o soporte, contáctanos a nuestro canal oficial de WhatsApp.</p>
    </div>

</body>
</html>
