<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\Especialista;
use App\Models\Profesional;
use Illuminate\Database\QueryException;

it('impide dos perfiles activos del mismo usuario y área', function () {
    $user = Especialista::factory()->create();
    $area = Area::factory()->create();

    Profesional::factory()->create([
        'user_id' => $user->id,
        'area_id' => $area->id,
    ]);

    expect(fn () => Profesional::factory()->create([
        'user_id' => $user->id,
        'area_id' => $area->id,
    ]))->toThrow(QueryException::class);
});

it('permite un nuevo perfil activo si el anterior está borrado lógicamente', function () {
    $user = Especialista::factory()->create();
    $area = Area::factory()->create();

    $previo = Profesional::factory()->create([
        'user_id' => $user->id,
        'area_id' => $area->id,
    ]);
    $previo->delete();

    $nuevo = Profesional::factory()->create([
        'user_id' => $user->id,
        'area_id' => $area->id,
    ]);

    expect($nuevo->exists)->toBeTrue()
        ->and(Profesional::withTrashed()->where('user_id', $user->id)->where('area_id', $area->id)->count())
        ->toBe(2);
});
