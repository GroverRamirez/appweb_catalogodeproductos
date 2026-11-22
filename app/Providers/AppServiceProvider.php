<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\AdminGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
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
        $this->configureAuthorization();
    }

    /**
     * Configure authorization: el propietario pasa todas las verificaciones y se
     * registra la policy del modelo de roles de Spatie.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            return $user->hasRole(AdminGuard::OWNER_ROLE) ? true : null;
        });

        Gate::define('access-admin', fn (User $user): bool => AdminGuard::canAccessAdmin($user));

        Gate::policy(Role::class, RolePolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Forzar HTTPS en todas las URLs generadas (url(), route(), asset(), etc.)
        // Solo en producción para no romper el entorno local ni los tests. La
        // versión instalable para Windows corre en producción pero sobre
        // http://127.0.0.1, y la apaga con APP_FORCE_HTTPS=false.
        if (app()->isProduction() && config('app.force_https')) {
            URL::forceScheme('https');
        }

        // En desarrollo la app también se abre desde otros equipos de la WiFi
        // (http://IP-de-la-PC:8020): las imágenes subidas usan URLs relativas
        // en vez de APP_URL, que apunta a localhost.
        if (app()->environment('local') && ! app()->runningInConsole()) {
            config(['filesystems.disks.public.url' => '/storage']);
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
        Inertia::handleExceptionsUsing(function (ExceptionResponse $e): mixed {
            if ($redirect = $this->redirectBackWithError($e)) {
                return $redirect;
            }

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
                ->withSharedData();  // expone store{} y auth{} al componente
        });
    }

    /**
     * Cuando falla un envío de formulario (POST/PUT/PATCH/DELETE hecho por
     * Inertia), lo mejor es dejar al usuario donde estaba, con lo que escribió,
     * y avisarle con una notificación de error en vez de una página de error.
     *
     * Los errores inesperados (500) solo se ocultan con APP_DEBUG=false: en
     * desarrollo conviene seguir viendo el detalle de la excepción.
     */
    protected function redirectBackWithError(ExceptionResponse $e): ?RedirectResponse
    {
        $request = $e->request;

        if (! $request->header('X-Inertia') || $request->isMethod('GET')) {
            return null;
        }

        $exception = $e->exception;

        if (! ($exception instanceof HttpException)) {
            return config('app.debug')
                ? null
                : back()->with('error', [
                    'message' => 'No se pudo completar la operación.',
                    'description' => 'Ocurrió un error inesperado y no se guardaron los cambios. Intenta de nuevo.',
                ]);
        }

        $message = match ($exception->getStatusCode()) {
            419 => [
                'message' => 'Tu sesión expiró por inactividad.',
                'description' => 'Vuelve a intentarlo; si el problema sigue, recarga la página.',
            ],
            403 => 'No tienes permiso para realizar esta acción.',
            // abort(422, 'Esta venta ya está anulada.') y similares: el texto
            // del abort ya está pensado para el usuario.
            409, 422 => $exception->getMessage() ?: 'No se pudo completar la operación.',
            429 => 'Demasiados intentos seguidos. Espera un momento y vuelve a intentarlo.',
            default => null,
        };

        return $message === null ? null : back()->with('error', $message);
    }
}
