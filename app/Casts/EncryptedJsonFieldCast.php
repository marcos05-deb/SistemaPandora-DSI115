<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class EncryptedJsonFieldCast implements CastsAttributes
{
    protected EncryptedFieldCast $encryptedFieldCast;

    public function __construct()
    {
        $this->encryptedFieldCast = new EncryptedFieldCast();
    }

    public function get($model, string $key, $value, $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        $decrypted = $this->encryptedFieldCast->get($model, $key, $value, $attributes);
        
        if (is_null($decrypted)) {
            return null;
        }

        return json_decode($decrypted, true);
    }

    public function set($model, string $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $encoded = json_encode($value);

        return $this->encryptedFieldCast->set($model, $key, $encoded, $attributes);
    }
}
