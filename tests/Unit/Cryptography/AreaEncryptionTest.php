<?php

declare(strict_types=1);

use App\Casts\EncryptedFieldCast;
use App\Services\AreaEncryptionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

test('el cifrado es determinista y descifrable para la misma area', function () {
    // Arrange
    Config::set('app.area_key_secret', 'secreto-maestro-super-seguro');
    $service = new AreaEncryptionService();
    $areaId = 'area-cardiologia-uuid';
    $plaintext = 'datos_protegidos_phi';

    // Act
    $encrypted = $service->encrypt($plaintext, $areaId);
    $decrypted = $service->decrypt($encrypted, $areaId);

    // Assert
    expect($encrypted)->not->toBe($plaintext)
        ->and($decrypted)->toBe($plaintext);
});

test('dos especialistas distintos pueden descifrar un modelo si pertenecen a la misma area', function () {
    // Arrange
    Config::set('app.area_key_secret', 'secreto-maestro-super-seguro');
    $service = new AreaEncryptionService();
    
    $areaId = 'area-cardiologia-uuid';
    $plaintext = 'diagnostico_paciente_confidencial';
    
    // Especialista A (Sistema) cifra el dato
    $encryptedBySpecialistA = $service->encrypt($plaintext, $areaId);

    // Act
    // Especialista B descifra el dato requiriendo únicamente el ID del área
    $decryptedBySpecialistB = $service->decrypt($encryptedBySpecialistA, $areaId);

    // Assert
    expect($decryptedBySpecialistB)->toBe($plaintext);
});

test('falla si se intenta usar la clave de un area distinta', function () {
    // Arrange
    Config::set('app.area_key_secret', 'secreto-maestro-super-seguro');
    $service = new AreaEncryptionService();
    
    $areaCardiologiaId = 'area-cardiologia-uuid';
    $areaPediatriaId = 'area-pediatria-uuid';
    $plaintext = 'datos_protegidos_phi';
    
    $encryptedInCardiologia = $service->encrypt($plaintext, $areaCardiologiaId);

    // Act & Assert
    expect(fn () => $service->decrypt($encryptedInCardiologia, $areaPediatriaId))
        ->toThrow(RuntimeException::class, 'Decryption failed');
});

test('EncryptedFieldCast resuelve area_id e inyecta el servicio correctamente', function () {
    // Arrange
    Config::set('app.area_key_secret', 'secreto-maestro-super-seguro');
    
    $model = new class extends Model {
        public int $area_id = 42;
    };
    
    $cast = app(EncryptedFieldCast::class);
    $plaintext = 'informacion_sensible';

    // Act
    $encryptedValue = $cast->set($model, 'campo_clinico', $plaintext, []);
    $decryptedValue = $cast->get($model, 'campo_clinico', $encryptedValue, []);

    // Assert
    expect($encryptedValue)->not->toBe($plaintext)
        ->and($decryptedValue)->toBe($plaintext);
});
