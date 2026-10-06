<!DOCTYPE html>
{{--
    Reporte gerencial en PDF (DomPDF).

    DomPDF no soporta flexbox ni grid: toda la maquetacion va con tablas y
    anchos en porcentaje. La fuente DejaVu Sans es la unica del paquete base
    que trae acentos y el simbolo de moneda completos.
--}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 110px 32px 70px 32px; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 0;
        }

        header {
            position: fixed;
            top: -85px;
            left: 0;
            right: 0;
            height: 80px;
        }

        footer {
            position: fixed;
            bottom: -45px;
            left: 0;
            right: 0;
            height: 40px;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }

        .marca { width: 100%; border-collapse: collapse; }
        .marca td { vertical-align: middle; }
        .logo { width: 64px; }
        .logo img { max-height: 54px; max-width: 60px; }

        .tienda { font-size: 15px; font-weight: bold; color: #0f172a; }
        .contacto { font-size: 8px; color: #6b7280; }

        .meta { text-align: right; font-size: 8px; color: #6b7280; }
        .meta strong { display: block; font-size: 11px; color: #0f172a; }

        h2 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #334155;
            margin: 18px 0 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos th {
            background: #f1f5f9;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            padding: 5px 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.datos td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
        table.datos tr.total td {
            font-weight: bold;
            background: #f8fafc;
            border-top: 1px solid #cbd5e1;
            border-bottom: none;
        }
        .num { text-align: right; }

        .kpis { width: 100%; border-collapse: separate; border-spacing: 5px; }
        .kpis td {
            width: 25%;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 7px 8px;
        }
        .kpis .etiqueta {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }
        .kpis .valor { font-size: 13px; font-weight: bold; color: #0f172a; }
        .kpis .pista { font-size: 7px; color: #94a3b8; }

        .vacio { color: #94a3b8; font-style: italic; padding: 6px 0; }
    </style>
</head>
<body>
    <header>
        <table class="marca">
            <tr>
                @if ($logo)
                    <td class="logo"><img src="{{ $logo }}" alt="{{ $tienda['nombre'] }}"></td>
                @endif
                <td>
                    <div class="tienda">{{ $tienda['nombre'] }}</div>
                    <div class="contacto">
                        {{ $tienda['direccion'] }}@if ($tienda['direccion'] && ($tienda['telefono'] || $tienda['email'])) · @endif
                        @if ($tienda['telefono']) Tel. {{ $tienda['telefono'] }} @endif
                        @if ($tienda['email']) · {{ $tienda['email'] }} @endif
                    </div>
                </td>
                <td class="meta">
                    <strong>{{ $titulo }}</strong>
                    {{ $rango }}<br>
                    Emitido el {{ $emitidoEl }}@if ($emitidoPor) por {{ $emitidoPor }} @endif
                </td>
            </tr>
        </table>
    </header>

    <footer>
        {{ $tienda['nombre'] }} · {{ $titulo }} · {{ $rango }} · documento generado por el sistema
    </footer>

    <main>
        <h2>Indicadores del periodo</h2>
        <table class="kpis">
            <tr>
                <td>
                    <div class="etiqueta">Consultas totales</div>
                    <div class="valor">{{ $kpis['total_inquiries'] }}</div>
                    <div class="pista">recibidas en el rango</div>
                </td>
                <td>
                    <div class="etiqueta">Consultas vendidas</div>
                    <div class="valor">{{ $kpis['sold'] }}</div>
                    <div class="pista">conversion {{ $kpis['conversion_rate'] }}%</div>
                </td>
                <td>
                    <div class="etiqueta">Ventas confirmadas</div>
                    <div class="valor">{{ $kpis['sales_count'] }}</div>
                    <div class="pista">ticket promedio {{ $moneda($kpis['average_ticket']) }}</div>
                </td>
                <td>
                    <div class="etiqueta">Ingresos</div>
                    <div class="valor">{{ $moneda($kpis['revenue']) }}</div>
                    <div class="pista">margen {{ $moneda($kpis['margin']) }}</div>
                </td>
            </tr>
        </table>

        <h2>Detalle diario</h2>
        <table class="datos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th class="num">Consultas</th>
                    <th class="num">Consultas vendidas</th>
                    <th class="num">Ventas</th>
                    <th class="num">Ingresos</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($serie as $fila)
                    <tr>
                        <td>{{ \Carbon\CarbonImmutable::parse($fila['day'])->format('d/m/Y') }}</td>
                        <td class="num">{{ $fila['total'] }}</td>
                        <td class="num">{{ $fila['sold'] }}</td>
                        <td class="num">{{ $fila['sales'] }}</td>
                        <td class="num">{{ $moneda($fila['revenue']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Totales</td>
                    <td class="num">{{ $totales['inquiries'] }}</td>
                    <td class="num">{{ $totales['sold'] }}</td>
                    <td class="num">{{ $totales['sales'] }}</td>
                    <td class="num">{{ $moneda($totales['revenue']) }}</td>
                </tr>
            </tbody>
        </table>

        <h2>Productos mas vendidos</h2>
        @if (count($masVendidos))
            <table class="datos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Codigo</th>
                        <th class="num">Cantidad</th>
                        <th class="num">Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($masVendidos as $p)
                        <tr>
                            <td>{{ $p['name'] }}</td>
                            <td>{{ $p['code'] }}</td>
                            <td class="num">{{ $p['qty'] }}</td>
                            <td class="num">{{ $moneda($p['revenue']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="2">Totales</td>
                        <td class="num">{{ collect($masVendidos)->sum('qty') }}</td>
                        <td class="num">{{ $moneda(collect($masVendidos)->sum('revenue')) }}</td>
                    </tr>
                </tbody>
            </table>
        @else
            <p class="vacio">Sin ventas registradas en el rango.</p>
        @endif

        <h2>Productos mas solicitados en consultas</h2>
        @if (count($masSolicitados))
            <table class="datos">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Codigo</th>
                        <th class="num">Cantidad pedida</th>
                        <th class="num">Valor solicitado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($masSolicitados as $p)
                        <tr>
                            <td>{{ $p['name'] }}</td>
                            <td>{{ $p['code'] }}</td>
                            <td class="num">{{ $p['qty'] }}</td>
                            <td class="num">{{ $moneda($p['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="vacio">Sin consultas con productos en el rango.</p>
        @endif

        <h2>Ingresos por categoria</h2>
        @if (count($categorias))
            <table class="datos">
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th class="num">Unidades</th>
                        <th class="num">Ingresos</th>
                        <th class="num">Participacion</th>
                    </tr>
                </thead>
                <tbody>
                    @php($ingresoCategorias = collect($categorias)->sum('revenue'))
                    @foreach ($categorias as $c)
                        <tr>
                            <td>{{ $c['category'] }}</td>
                            <td class="num">{{ $c['units'] }}</td>
                            <td class="num">{{ $moneda($c['revenue']) }}</td>
                            <td class="num">
                                {{ $ingresoCategorias > 0 ? round($c['revenue'] / $ingresoCategorias * 100, 1) : 0 }}%
                            </td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Totales</td>
                        <td class="num">{{ collect($categorias)->sum('units') }}</td>
                        <td class="num">{{ $moneda($ingresoCategorias) }}</td>
                        <td class="num">100%</td>
                    </tr>
                </tbody>
            </table>
        @else
            <p class="vacio">Sin ventas con categoria en el rango.</p>
        @endif
    </main>
</body>
</html>
