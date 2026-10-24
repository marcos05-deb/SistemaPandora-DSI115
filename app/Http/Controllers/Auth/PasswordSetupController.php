<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Especialista;
use App\Services\AuthAccessAuditor;
use App\Services\Crypto\KeyDerivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class PasswordSetupController extends Controller
{
    /**
     * Show the password setup view.
     */
    public function show(Request $request)
    {
        return Inertia::render('Auth/PasswordSetup');
    }

    /**
     * Update the user's password and regenerate the cryptographic session key.
     */
    public function update(Request $request, KeyDerivationService $kdfService, AuthAccessAuditor $authAuditor)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.mixed' => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
            'password.symbols' => 'La contraseña debe contener al menos un símbolo.',
        ]);

        $user = $request->user();

        // Regenerate KDF Salt and new Symmetric Key for encryption
        $newSaltBase64 = $kdfService->generateSalt(); // Already base64 encoded by service
        $newSaltRaw = base64_decode($newSaltBase64, strict: true);

        $derivedKey = $kdfService->derive($request->password, $newSaltRaw);

        // Update User
        $user->forceFill([
            'password' => Hash::make($request->password),
            'kdf_salt' => $newSaltBase64,
            'must_change_password' => false,
        ])->save();

        // Update Session with new cryptographic key
        session(['_sym_key' => base64_encode($derivedKey)]);

        if ($user instanceof Especialista) {
            $authAuditor->passwordChanged($user, $request);
        }

        // Redirect to intended dashboard
        if ($user->hasRole('sysadmin')) {
            return redirect()->route('admin.dashboard')
                ->with('message', 'Contraseña configurada exitosamente.')
                ->with('variant', 'success');
        }

        return redirect()->route('dashboard')
            ->with('message', 'Contraseña configurada exitosamente.')
            ->with('variant', 'success');
    }
}
