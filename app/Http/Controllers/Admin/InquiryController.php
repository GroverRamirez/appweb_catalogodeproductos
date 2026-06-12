<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInquiryNoteRequest;
use App\Http\Requests\Admin\UpdateInquiryRequest;
use App\Models\Inquiry;
use App\Support\CsvDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public function index(Request $request): Response
    {
        $inquiries = Inquiry::query()
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('estado', $request->string('status')))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('cliente_nombre', 'like', "%$term%")
                        ->orWhere('cliente_telefono', 'like', "%$term%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/inquiries/Index', [
            'inquiries' => $inquiries,
            'filters' => $request->only(['q', 'status']),
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function show(Inquiry $inquiry): Response
    {
        $inquiry->load(['items.product:id,nombre,codigo', 'handler:id,name', 'notes.author:id,name']);

        return Inertia::render('admin/inquiries/Show', [
            'inquiry' => $inquiry,
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validated();

        $previousStatus = $inquiry->status;

        $inquiry->fill($data);
        if ($inquiry->status !== 'pendiente' && ! $inquiry->contacted_at) {
            $inquiry->contacted_at = now();
            $inquiry->handled_by = $request->user()->id;
        }
        $inquiry->save();

        // Dejar rastro del cambio de estado en el historial de seguimiento.
        if ($inquiry->status !== $previousStatus) {
            $inquiry->notes()->create([
                'user_id' => $request->user()->id,
                'body' => "Cambió el estado de \"{$previousStatus}\" a \"{$inquiry->status}\".",
            ]);
        }

        return back()->with('success', 'Consulta actualizada.');
    }

    public function storeNote(StoreInquiryNoteRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $inquiry->notes()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Nota agregada.');
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return to_route('admin.inquiries.index')->with('success', 'Consulta eliminada.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Inquiry::query()
            ->with('items:id,consulta_id,producto_nombre_copia,producto_codigo_copia,cantidad,precio_unitario')
            ->when($request->filled('status'), fn ($q) => $q->where('estado', $request->string('status')))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('cliente_nombre', 'like', "%$term%")
                        ->orWhere('cliente_telefono', 'like', "%$term%");
                });
            })
            ->latest();

        $headers = [
            'ID', 'Fecha', 'Cliente', 'Teléfono', 'Email',
            'Origen', 'Estado', 'Total estimado', 'Items', 'Notas',
        ];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $inq) {
                $items = $inq->items
                    ->map(fn ($i) => "{$i->quantity}x {$i->product_name_snapshot} ({$i->product_code_snapshot})")
                    ->implode(' | ');
                yield [
                    $inq->id,
                    optional($inq->created_at)->format('Y-m-d H:i'),
                    $inq->customer_name,
                    $inq->customer_phone,
                    $inq->customer_email,
                    $inq->source,
                    $inq->status,
                    $inq->total_estimated,
                    $items,
                    $inq->admin_notes,
                ];
            }
        };

        return CsvDownload::stream(
            'consultas-'.now()->format('Y-m-d').'.csv',
            $headers,
            $rows(),
        );
    }
}
