<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly ImageProcessor $images) {}

    public function edit(): Response
    {
        $settings = Setting::query()
            ->orderBy('grupo')
            ->orderBy('etiqueta')
            ->get(['clave', 'valor', 'grupo', 'etiqueta', 'tipo'])
            ->map(function ($s) {
                $arr = $s->toArray();
                if ($s->type === 'image' && $s->value) {
                    $arr['url'] = str_starts_with($s->value, 'http')
                        ? $s->value
                        : Storage::url($s->value);
                }

                return $arr;
            });

        return Inertia::render('admin/settings/Edit', [
            'settings' => $settings,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        foreach ($request->input('settings', []) as $i => $row) {
            $existing = Setting::where('clave', $row['key'])->first();
            if (! $existing) {
                continue;
            }

            // Tipo image: si vino archivo nuevo, lo optimiza; si vino delete=1, lo borra
            if ($existing->type === 'image') {
                $fileKey = "settings.$i.file";
                if ($request->hasFile($fileKey)) {
                    // Borrar anterior si era local
                    $this->images->delete($existing->value ?? '');
                    // Procesar + convertir a WebP
                    $path = $this->images->setting($request->file($fileKey));
                    $existing->update(['value' => $path]);
                } elseif (! empty($row['delete'])) {
                    $this->images->delete($existing->value ?? '');
                    $existing->update(['value' => '']);
                }
                continue;
            }

            $value = isset($row['value']) && is_array($row['value'])
                ? json_encode($row['value'])
                : (string) ($row['value'] ?? '');
            $existing->update(['value' => $value]);
        }

        return back()->with('success', 'Configuración guardada.');
    }
}
