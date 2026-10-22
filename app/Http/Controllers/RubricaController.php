<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RubricaVersionStoreRequest;
use App\Http\Resources\RubricaResource;
use App\Http\Resources\RubricaVersionResource;
use App\Models\Rubrica;
use App\Models\RubricaVersion;
use App\Services\RubricaResolver;
use App\Services\RubricaVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RubricaController extends Controller
{
    public function __construct(
        private readonly RubricaVersionService $rubricaVersionService,
        private readonly RubricaResolver $rubricaResolver,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Rubrica::class);

        $rubrica = $this->rubricaVersionService->rubrica();
        $vigenteId = $this->rubricaResolver->vigente()?->id;
        $versions = $this->rubricaVersionService->versiones($rubrica);
        $versions->through(static function (RubricaVersion $version) use ($vigenteId): array {
            $version->setAttribute('es_vigente', $version->id === $vigenteId);

            return RubricaVersionResource::make($version)->resolve();
        });

        return Inertia::render('Rubricas/Index', [
            'versions' => $versions,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Rubrica::class);

        $rubrica = $this->rubricaVersionService->rubrica();
        $latestVersion = $this->rubricaVersionService->ultimaVersion($rubrica);
        $latestVersion?->setAttribute(
            'es_vigente',
            $latestVersion->id === $this->rubricaResolver->vigente()?->id
        );

        return Inertia::render('Rubricas/Create', [
            'rubrica' => RubricaResource::make($rubrica)->resolve(),
            'latestVersion' => $latestVersion ? RubricaVersionResource::make($latestVersion)->resolve() : null,
        ]);
    }

    public function store(RubricaVersionStoreRequest $request): RedirectResponse
    {
        $profesional = $request->user()->profesional;

        $version = $this->rubricaVersionService->crearVersion(
            $this->rubricaVersionService->rubrica(),
            $request->validated(),
            $profesional
        );

        return redirect()->route('rubricas.show', $version->id)
            ->with('message', 'La versión de la rúbrica se creó correctamente.')
            ->with('variant', 'success');
    }

    public function show(RubricaVersion $version): Response
    {
        Gate::authorize('view', $version);
        $version->loadMissing(['rubrica', 'variables', 'creadaPor.especialista']);

        $version->setAttribute(
            'es_vigente',
            $version->id === $this->rubricaResolver->vigente()?->id
        );

        return Inertia::render('Rubricas/Show', [
            'version' => RubricaVersionResource::make($version)->resolve(),
        ]);
    }
}
