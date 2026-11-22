<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Copias de seguridad de la base de datos desde Configuracion. Solo genera,
 * lista, descarga y elimina; restaurar se hace a mano (ver DEPLOY.md §6).
 */
class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackup $backups) {}

    public function index(): Response
    {
        return Inertia::render('admin/settings/Backups', [
            'backups' => $this->backups->list(),
        ]);
    }

    public function store(): RedirectResponse
    {
        // Una base grande puede tardar mas que el limite por defecto de PHP.
        @set_time_limit((int) config('backup.timeout', 300) + 30);

        try {
            $name = $this->backups->create();
        } catch (Throwable $e) {
            Log::error('Fallo la copia de seguridad', ['error' => $e->getMessage()]);

            return back()->with('error', 'No se pudo generar la copia: '.$e->getMessage());
        }

        Log::info('Copia de seguridad generada', ['archivo' => $name, 'usuario' => auth()->id()]);

        return back()->with('success', "Copia generada: {$name}");
    }

    public function download(string $backup): BinaryFileResponse
    {
        $path = $this->backups->path($backup);
        abort_if($path === null, 404);

        Log::info('Copia de seguridad descargada', ['archivo' => $backup, 'usuario' => auth()->id()]);

        return response()->download($path, $backup, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $backup): RedirectResponse
    {
        abort_unless($this->backups->delete($backup), 404);

        Log::info('Copia de seguridad eliminada', ['archivo' => $backup, 'usuario' => auth()->id()]);

        return back()->with('success', 'Copia eliminada.');
    }
}
