<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $clients = Client::query()
            ->withCount(['sales as purchases_count' => fn ($q) => $q->where('estado', 'confirmada')])
            ->withSum(['sales as total_spent' => fn ($q) => $q->where('estado', 'confirmada')], 'total')
            ->when($request->string('q')->toString(), function ($q, $term) {
                // El OR va agrupado: sin el where() envolvente, SQL lo lee como
                // "nombre LIKE ? OR (telefono AND activo = ?)" y el filtro de
                // estado deja de aplicarse a lo que matchea por nombre.
                $q->where(function ($group) use ($term) {
                    $group->where('nombre', 'like', "%$term%")
                        ->orWhere('carnet_identidad', 'like', "%$term%")
                        ->orWhere('email', 'like', "%$term%");

                    // Solo si el término trae dígitos: normalizar "ana" da null
                    // y la condición quedaría LIKE '%%', que matchea todo.
                    $phone = Client::normalizePhone($term);

                    if ($phone !== null) {
                        $group->orWhere('telefono', 'like', "%$phone%");
                    }
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('activo', $request->boolean('status')))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    /**
     * Busca clientes por teléfono o nombre para el formulario de venta.
     * Devuelve JSON porque se consulta mientras se escribe, sin recargar.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->toString();

        if (strlen($term) < 2) {
            return response()->json(['clients' => []]);
        }

        $phone = Client::normalizePhone($term);

        $clients = Client::query()
            ->active()
            ->where(function ($group) use ($term, $phone) {
                $group->where('nombre', 'like', "%$term%");

                if ($phone !== null) {
                    $group->orWhere('telefono', 'like', "%$phone%");
                }
            })
            ->orderBy('nombre')
            ->take(10)
            ->get(['id', 'nombre', 'telefono', 'carnet_identidad']);

        return response()->json(['clients' => $clients]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/clients/Form', ['client' => null]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        return to_route('admin.clients.show', $client)->with('success', 'Cliente registrado.');
    }

    /**
     * Ficha del cliente: sus datos más el historial que le da sentido a tener
     * la tabla — qué compró, qué consultó y cuánto lleva gastado.
     */
    public function show(Client $client): Response
    {
        $client->load([
            'user:id,name,email',
            'sales:id,cliente_id,numero_recibo,estado,total,metodo_pago,created_at',
            'inquiries:id,cliente_id,estado,total_estimado,created_at',
        ]);

        $summary = DB::table('ventas')
            ->where('cliente_id', $client->id)
            ->where('estado', 'confirmada')
            ->selectRaw('COUNT(*) as compras, COALESCE(SUM(total), 0) as gastado, MAX(created_at) as ultima')
            ->first();

        return Inertia::render('admin/clients/Show', [
            'client' => $client,
            'summary' => [
                'purchases' => (int) ($summary->compras ?? 0),
                'total_spent' => (float) ($summary->gastado ?? 0),
                'last_purchase' => $summary->ultima ?? null,
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('admin/clients/Form', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return to_route('admin.clients.show', $client)->with('success', 'Cliente actualizado.');
    }

    /**
     * Baja lógica: el cliente tiene ventas y consultas colgando, y borrarlo de
     * verdad dejaría el historial sin dueño.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return to_route('admin.clients.index')->with('success', 'Cliente dado de baja.');
    }
}
