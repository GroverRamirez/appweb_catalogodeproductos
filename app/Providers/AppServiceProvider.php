<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureInertiaErrors();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Forzar HTTPS en todas las URLs generadas (url(), route(), asset(), etc.)
        // Solo en producción para no romper el entorno local ni los tests.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // El frontend (Inertia/Vue) consume colecciones planas (featured.length,
        // products.data/total/links) y paginadores con sus campos al nivel superior.
        // Sin esto, ProductResource::collection() envuelve todo en una clave "data"
        // extra que rompe el renderizado del catálogo y la paginación.
        JsonResource::withoutWrapping();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Redirigir errores HTTP al componente Error.vue de Inertia.
     *
     * Inertia v3 expone Inertia::handleExceptionsUsing() para interceptar
     * HttpExceptions antes de que Laravel las convierta en respuestas Blade.
     * El callback recibe un ExceptionResponse con los métodos:
     *
     *   ->render('Error', ['status' => $status])   — componente + props
     *   ->withSharedData()                         — incluir shared props (store, auth, etc.)
     *
     * Códigos excluidos:
     *   - 422 (Unprocessable Entity): Inertia ya maneja errores de validación.
     *   - 302/301: son redirecciones, no errores.
     *
     * En tests, QUEUE_CONNECTION=sync e Inertia intercepta normalmente.
     */
    protected function configureInertiaErrors(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $e): ?ExceptionResponse {
            if (! ($e->exception instanceof HttpException)) {
                return null;
            }

            $status = $e->statusCode();

            // 422 lo maneja el middleware de Inertia (flashea errores de validación).
            // Cualquier otro error HTTP 4xx/5xx va al componente Error.vue.
            if ($status < 400 || $status === 422) {
                return null;
            }

            return $e
                ->render('Error', ['status' => $status])
                ->withSharedData();  // expone store{}, auth{} y flash{} al componente
        });
    }
}
