<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportProductsController extends Controller
{
    /**
     * Expected CSV columns (header row, case-insensitive, trimmed):
     *
     *   codigo | nombre | precio | precio_oferta | stock | categoria | marca | activo
     *
     * Estrategia todo-o-nada (all-or-nothing):
     *
     *   1. Fase de validación — se parsea y valida el CSV completo en memoria.
     *      Si CUALQUIER fila tiene error se devuelve 422 sin escribir nada en la DB.
     *
     *   2. Fase de escritura — si todas las filas son válidas, se ejecutan todos
     *      los upserts dentro de una única DB::transaction(). Si algún INSERT/UPDATE
     *      falla de forma inesperada, la transacción hace rollback automático y se
     *      devuelve 500.
     *
     * Returns JSON with { imported, updated, errors[] }.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Endpoint XHR: devolvemos siempre JSON 422 ante errores de validación del
        // archivo (no un redirect 302), aunque el cliente no envíe Accept: application/json.
        $fileValidator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        if ($fileValidator->fails()) {
            return response()->json([
                'message' => $fileValidator->errors()->first('file'),
                'errors' => $fileValidator->errors()->toArray(),
            ], 422);
        }

        $path = $request->file('file')->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['message' => 'No se pudo leer el archivo.'], 422);
        }

        // ── Leer y normalizar headers ──────────────────────────────────────────

        $rawHeaders = fgetcsv($handle, 0, ',');
        if ($rawHeaders === false || $rawHeaders === null) {
            fclose($handle);

            return response()->json(['message' => 'El archivo está vacío.'], 422);
        }

        $headers = array_map(fn ($h) => mb_strtolower(trim($h)), $rawHeaders);

        $requiredCols = ['codigo', 'nombre', 'precio'];
        $missing = array_diff($requiredCols, $headers);
        if (! empty($missing)) {
            fclose($handle);

            return response()->json([
                'message' => 'Faltan columnas requeridas: '.implode(', ', $missing),
            ], 422);
        }

        // ── Mapas nombre→id para categoría y marca (case-insensitive) ─────────

        $categoryMap = Category::query()
            ->get(['id', 'nombre'])
            ->keyBy(fn ($c) => mb_strtolower(trim($c->nombre)));

        $brandMap = Brand::query()
            ->get(['id', 'nombre'])
            ->keyBy(fn ($b) => mb_strtolower(trim($b->nombre)));

        // ── Fase 1: parsear y validar TODAS las filas ──────────────────────────
        // No se escribe nada en la DB durante esta fase.

        /** @var array<int, array{codigo: string, nombre: string, payload: array<string, mixed>}> $validRows */
        $validRows = [];
        $errors = [];
        $row = 1; // la fila 0 fue el header

        while (($cols = fgetcsv($handle, 0, ',')) !== false) {
            $row++;

            // Ignorar filas completamente en blanco
            if (empty(array_filter($cols, fn ($v) => trim($v) !== ''))) {
                continue;
            }

            // Mapear columnas por nombre de header
            $data = [];
            foreach ($headers as $i => $col) {
                $data[$col] = isset($cols[$i]) ? trim($cols[$i]) : '';
            }

            // Validación de tipos y rangos
            $v = Validator::make($data, [
                'codigo' => ['required', 'string', 'max:100'],
                'nombre' => ['required', 'string', 'max:255'],
                'precio' => ['required', 'numeric', 'min:0'],
                'precio_oferta' => ['nullable', 'numeric', 'min:0'],
                'stock' => ['nullable', 'integer', 'min:0'],
                'activo' => ['nullable'],
            ]);

            if ($v->fails()) {
                $errors[] = [
                    'row' => $row,
                    'code' => $data['codigo'] ?? '',
                    'errors' => $v->errors()->all(),
                ];

                continue;
            }

            // Resolución de categoría
            $categoriaId = null;
            if (! empty($data['categoria'])) {
                $key = mb_strtolower(trim($data['categoria']));
                $categoriaId = $categoryMap->get($key)?->id;
                if (! $categoriaId) {
                    $errors[] = [
                        'row' => $row,
                        'code' => $data['codigo'],
                        'errors' => ["Categoría «{$data['categoria']}» no encontrada."],
                    ];

                    continue;
                }
            }

            // Resolución de marca
            $marcaId = null;
            if (! empty($data['marca'])) {
                $key = mb_strtolower(trim($data['marca']));
                $marcaId = $brandMap->get($key)?->id;
                if (! $marcaId) {
                    $errors[] = [
                        'row' => $row,
                        'code' => $data['codigo'],
                        'errors' => ["Marca «{$data['marca']}» no encontrada."],
                    ];

                    continue;
                }
            }

            // Parse de activo
            $activo = true;
            if (isset($data['activo']) && $data['activo'] !== '') {
                $activo = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $activo = $activo ?? ! in_array(
                    mb_strtolower($data['activo']),
                    ['0', 'no', 'false', 'inactivo', 'inactive'],
                    true
                );
            }

            $validRows[] = [
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                // Claves en inglés porque son las que figuran en $fillable del modelo
                // Product; los alias en español sólo aplican a lectura/escritura de
                // atributos individuales, no a la asignación masiva (mass assignment).
                'payload' => [
                    'name' => $data['nombre'],
                    'price' => (float) $data['precio'],
                    'sale_price' => ($data['precio_oferta'] ?? '') !== '' ? (float) $data['precio_oferta'] : null,
                    'stock' => ($data['stock'] ?? '') !== '' ? (int) $data['stock'] : 0,
                    'category_id' => $categoriaId,
                    'brand_id' => $marcaId,
                    'is_active' => $activo,
                ],
            ];
        }

        fclose($handle);

        // ── Si hay errores de validación: fallo temprano, sin escribir nada ──

        if (! empty($errors)) {
            return response()->json([
                'imported' => 0,
                'updated' => 0,
                'errors' => $errors,
            ], 422);
        }

        // ── Fase 2: upserts en una única transacción ───────────────────────────
        // Si cualquier escritura lanza una excepción, DB::transaction() hace
        // rollback automático antes de re-lanzar el Throwable.

        $imported = 0;
        $updated = 0;

        try {
            DB::transaction(function () use ($validRows, &$imported, &$updated): void {
                foreach ($validRows as $item) {
                    $existing = Product::where('codigo', $item['codigo'])->first();

                    if ($existing) {
                        $existing->update($item['payload']);
                        $updated++;
                    } else {
                        $payload = $item['payload'];
                        $payload['code'] = $item['codigo'];
                        $payload['slug'] = Product::uniqueSlug($item['nombre']);
                        Product::create($payload);
                        $imported++;
                    }
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Error inesperado al guardar los productos. Se revirtieron todos los cambios.',
                'imported' => 0,
                'updated' => 0,
                'errors' => [],
            ], 500);
        }

        return response()->json([
            'imported' => $imported,
            'updated' => $updated,
            'errors' => [],
        ]);
    }
}
